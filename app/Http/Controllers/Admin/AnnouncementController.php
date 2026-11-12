<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StorePublicImage;
use App\Http\Controllers\Controller;
use App\Jobs\SendAdminAnnouncement;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Queries\CatalogQuery;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function create(Request $request): View
    {
        $tab = $request->query('tab') === 'history' ? 'history' : 'compose';

        return view('admin.announcements.create', [
            'tab' => $tab,
            'announcements' => Announcement::query()->with('sender', 'user')->latest()->limit(10)->get(),
            // Only the composer shows audience sizes.
            'audienceCounts' => $tab === 'compose' ? [
                'customers' => SendAdminAnnouncement::recipients('customers')->count(),
                'stores' => SendAdminAnnouncement::recipients('stores')->count(),
            ] : [],
            'initialLinkTarget' => self::linkTargetFor((string) old('url', '')),
        ]);
    }

    /**
     * Stores and products a campaign can deep-link to, searched by name so the
     * picker stays fast however large the catalog grows.
     */
    public function linkTargets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['stores', 'products'])],
            'q' => ['nullable', 'string', 'max:100'],
            'store' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $matching = fn (Builder $query): Builder => $query->when($search !== '', fn (Builder $query) => $query->whereLike('name', "%{$search}%"));

        $results = $validated['type'] === 'stores'
            ? $matching(CatalogQuery::publicStores())
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'slug', 'city', 'logo_path'])
                ->map(fn (Store $store): array => self::storeTarget($store))
            : $matching(CatalogQuery::publishedProducts())
                ->when(filled($validated['store'] ?? null), fn (Builder $query) => $query->whereHas('store', fn (Builder $store) => $store->where('slug', $validated['store'])))
                ->with('thumbnail')
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'store_id', 'name', 'slug', 'price'])
                ->map(fn (Product $product): array => self::productTarget($product));

        return response()->json(['results' => $results->all()]);
    }

    /**
     * Restores the picked store or product after a failed submit.
     *
     * @return array<string, string|null>|null
     */
    private static function linkTargetFor(string $url): ?array
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        if (count($segments) === 4 && $segments[0] === 'stores' && $segments[2] === 'products') {
            $product = CatalogQuery::publishedProducts()->with('thumbnail')
                ->where('slug', $segments[3])
                ->whereHas('store', fn (Builder $query) => $query->where('slug', $segments[1]))
                ->first();

            return $product ? self::productTarget($product) : null;
        }

        if (count($segments) === 2 && $segments[0] === 'stores') {
            $store = CatalogQuery::publicStores()->where('slug', $segments[1])->first();

            return $store ? self::storeTarget($store) : null;
        }

        return null;
    }

    /**
     * @return array{kind: string, name: string, slug: string, city: string|null, url: string, image: string|null}
     */
    private static function storeTarget(Store $store): array
    {
        return [
            'kind' => 'store',
            'name' => $store->name,
            'slug' => $store->slug,
            'city' => $store->city,
            'url' => route('catalog.store', $store->slug),
            'image' => $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null,
        ];
    }

    /**
     * @return array{kind: string, name: string, url: string, image: string|null, store: string, price: string}
     */
    private static function productTarget(Product $product): array
    {
        return [
            'kind' => 'product',
            'name' => $product->name,
            'url' => route('catalog.show', ['store' => $product->store->slug, 'product' => $product->slug]),
            'image' => $product->thumbnail ? Storage::disk('product_images')->url($product->thumbnail->path) : null,
            'store' => $product->store->name,
            'price' => '₱'.number_format((float) $product->price, 2),
        ];
    }

    public function store(Request $request, StorePublicImage $storePublicImage): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['customers', 'stores', 'customer'])],
            'email' => ['nullable', 'required_if:audience,customer', 'email', 'max:255'],
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:200'],
            'campaign_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image_url' => ['nullable', 'url:https,http', 'max:1000', function (string $attribute, string $value, Closure $fail): void {
                // Plain HTTP only for JMMart's own files (local product photos and uploads).
                if (parse_url($value, PHP_URL_SCHEME) !== 'https' && parse_url($value, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)) {
                    $fail('The image link must use HTTPS.');
                }
            }],
            'url' => ['nullable', 'url:https,http', 'max:500', function (string $attribute, string $value, Closure $fail): void {
                // A push from JMMart that opens another site would read as phishing.
                if (parse_url($value, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)) {
                    $fail('The link must be a JMMart page.');
                }
            }],
        ]);

        $imageUrl = $validated['image_url'] ?? null;

        if ($request->hasFile('campaign_image')) {
            $path = $storePublicImage->handle($request->file('campaign_image'), 'announcements', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        $customer = null;

        if ($validated['audience'] === 'customer') {
            $customer = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($validated['email'])])->where('account_type', 'buyer')->where('is_active', true)->first();

            if ($customer === null) {
                throw ValidationException::withMessages(['email' => 'No active customer has this email.']);
            }
        }

        $announcement = DB::transaction(function () use ($request, $validated, $customer, $imageUrl): Announcement {
            $announcement = Announcement::query()->create([
                'sender_id' => $request->user()->id,
                'audience' => $validated['audience'],
                'user_id' => $customer?->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'url' => $validated['url'] ?? route('home'),
                'image_url' => $imageUrl,
                'recipient_count' => SendAdminAnnouncement::recipients($validated['audience'], $customer?->id)->count(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'announcement.sent',
                'entity_type' => Announcement::class,
                'entity_id' => $announcement->id,
                'new_values' => $announcement->only('audience', 'user_id', 'title', 'body', 'url', 'image_url', 'recipient_count'),
                'ip_address' => $request->ip(),
            ]);

            return $announcement;
        });

        SendAdminAnnouncement::dispatch($announcement->id);

        return to_route('admin.announcements.create', ['tab' => 'history'])->with('status', "Notification queued for {$announcement->recipient_count} recipient(s).");
    }

    public function cancel(Request $request, Announcement $announcement): RedirectResponse
    {
        if ($announcement->isFinished()) {
            return to_route('admin.announcements.create', ['tab' => 'history'])->with('status', 'That notification has already finished sending.');
        }

        $announcement->update(['cancelled_at' => now()]);

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'announcement.cancelled',
            'entity_type' => Announcement::class,
            'entity_id' => $announcement->id,
            'new_values' => ['sent_count' => $announcement->sent_count, 'recipient_count' => $announcement->recipient_count],
            'ip_address' => $request->ip(),
        ]);

        return to_route('admin.announcements.create', ['tab' => 'history'])->with('status', 'Notification cancelled. Batches not yet sent were stopped.');
    }
}
