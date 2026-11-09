<x-app-layout>
    <x-slot name="header"><div><h1 class="mt-1 marketplace-page-title">Send a notification</h1></div></x-slot>
    <div class="page-shell"><div class="page-container space-y-6">
        @if (session('status'))<div class="rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success" role="status">{{ session('status') }}</div>@endif
        <section class="section-card">
            <h2 class="text-lg font-semibold text-ink">Push notification</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Sent to the mobile app and to browsers with notifications turned on, and kept in each recipient's notifications. It cannot be recalled once sent.</p>
            <form method="POST" action="{{ route('admin.announcements.store') }}" x-data="{ audience: @js(old('audience', 'customers')), submitting: false }" @submit="submitting = true" class="mt-5 grid gap-5 md:grid-cols-2">@csrf
                <fieldset class="md:col-span-2">
                    <legend class="text-sm font-medium text-ink">Send to</legend>
                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                        @foreach (['customers' => 'All customers', 'stores' => 'All stores', 'customer' => 'One customer'] as $value => $label)
                            <label class="inline-flex min-h-11 items-center gap-2 text-sm text-ink"><input type="radio" name="audience" value="{{ $value }}" x-model="audience" class="text-brand focus:ring-brand"> {{ $label }}</label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                </fieldset>
                <div class="md:col-span-2" x-show="audience === 'customer'" x-cloak><x-input-label for="email" :value="__('Customer email')" /><x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email')" x-bind:required="audience === 'customer'" /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                <div class="md:col-span-2"><x-input-label for="title" :value="__('Title')" /><x-text-input id="title" class="mt-1.5" name="title" :value="old('title')" maxlength="60" required /><x-input-error :messages="$errors->get('title')" class="mt-2" /></div>
                <div class="md:col-span-2"><x-input-label for="body" :value="__('Message')" /><textarea id="body" name="body" rows="3" maxlength="200" required class="field-control mt-1.5">{{ old('body') }}</textarea><x-input-error :messages="$errors->get('body')" class="mt-2" /></div>
                <div class="md:col-span-2"><x-input-label for="url" :value="__('Link (optional)')" /><x-text-input id="url" class="mt-1.5" type="url" name="url" :value="old('url')" placeholder="{{ route('home') }}" /><p class="mt-1.5 text-xs text-muted">A JMMart page the notification opens on the web. Leave empty for the home page.</p><x-input-error :messages="$errors->get('url')" class="mt-2" /></div>
                <div class="md:col-span-2"><x-primary-button x-bind:disabled="submitting"><span x-show="!submitting">Send notification</span><span x-cloak x-show="submitting">Sending…</span></x-primary-button></div>
            </form>
        </section>
        <section class="content-card" aria-labelledby="recent-notifications">
            <div class="border-b border-line px-5 py-4 sm:px-6"><h2 id="recent-notifications" class="text-lg font-semibold text-ink">Recent notifications</h2></div>
            <ul class="divide-y divide-line">
                @forelse ($announcements as $announcement)
                    @php($status = $announcement->cancelled_at ? ['Cancelled', 'bg-canvas text-muted ring-line'] : ($announcement->isFinished() ? ['Sent', 'bg-success/10 text-success ring-success/20'] : ['Sending', 'bg-blue-50 text-brand ring-brand/20']))
                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $announcement->title }}</p>
                            <p class="mt-0.5 text-sm text-muted">{{ $announcement->body }}</p>
                            <p class="mt-1 text-xs text-muted">
                                {{ ['customers' => 'All customers', 'stores' => 'All stores', 'customer' => $announcement->user?->email ?? 'One customer'][$announcement->audience] }}
                                · {{ min($announcement->sent_count, $announcement->recipient_count) }}/{{ $announcement->recipient_count }} sent
                                · {{ $announcement->sender?->name ?? 'Deleted admin' }}, {{ $announcement->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="status-badge {{ $status[1] }}">{{ $status[0] }}</span>
                            @unless ($announcement->isFinished())
                                <form method="POST" action="{{ route('admin.announcements.cancel', $announcement) }}" onsubmit="return confirm('Stop sending this notification? People already reached keep it.')">@csrf @method('PATCH') <x-danger-button>{{ __('Cancel') }}</x-danger-button></form>
                            @endunless
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-muted sm:px-6">No notifications sent yet.</li>
                @endforelse
            </ul>
        </section>
    </div></div>
</x-app-layout>
