<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminAnnouncement;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function create(): View
    {
        return view('admin.announcements.create', [
            'announcements' => Announcement::query()->with('sender', 'user')->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['customers', 'stores', 'customer'])],
            'email' => ['nullable', 'required_if:audience,customer', 'email', 'max:255'],
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:200'],
            'url' => ['nullable', 'url:https,http', 'max:500', function (string $attribute, string $value, Closure $fail): void {
                // A push from JMMart that opens another site would read as phishing.
                if (parse_url($value, PHP_URL_HOST) !== parse_url(config('app.url'), PHP_URL_HOST)) {
                    $fail('The link must be a JMMart page.');
                }
            }],
        ]);

        $customer = null;

        if ($validated['audience'] === 'customer') {
            $customer = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($validated['email'])])->where('account_type', 'buyer')->where('is_active', true)->first();

            if ($customer === null) {
                throw ValidationException::withMessages(['email' => 'No active customer has this email.']);
            }
        }

        $announcement = DB::transaction(function () use ($request, $validated, $customer): Announcement {
            $announcement = Announcement::query()->create([
                'sender_id' => $request->user()->id,
                'audience' => $validated['audience'],
                'user_id' => $customer?->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'url' => $validated['url'] ?? route('home'),
                'recipient_count' => SendAdminAnnouncement::recipients($validated['audience'], $customer?->id)->count(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'announcement.sent',
                'entity_type' => Announcement::class,
                'entity_id' => $announcement->id,
                'new_values' => $announcement->only('audience', 'user_id', 'title', 'body', 'url', 'recipient_count'),
                'ip_address' => $request->ip(),
            ]);

            return $announcement;
        });

        SendAdminAnnouncement::dispatch($announcement->id);

        return to_route('admin.announcements.create')->with('status', "Notification queued for {$announcement->recipient_count} recipient(s).");
    }

    public function cancel(Request $request, Announcement $announcement): RedirectResponse
    {
        if ($announcement->isFinished()) {
            return to_route('admin.announcements.create')->with('status', 'That notification has already finished sending.');
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

        return to_route('admin.announcements.create')->with('status', 'Notification cancelled. Batches not yet sent were stopped.');
    }
}
