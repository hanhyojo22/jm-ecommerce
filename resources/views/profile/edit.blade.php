<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-balance text-2xl font-semibold text-slate-950">My account</h2>
            <p class="mt-1 text-sm text-slate-500">Manage your details, sign-in security, and account access.</p>
        </div>
    </x-slot>

    <div class="min-h-screen bg-[#f7f7f2] py-8 sm:py-12">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_15rem] lg:px-8">
            <div class="space-y-6">
                <section class="rounded-2xl bg-white p-5 shadow-[0_16px_32px_-28px_rgba(15,23,42,0.42)] sm:p-8">
                    @include('profile.partials.update-profile-information-form')
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-[0_16px_32px_-28px_rgba(15,23,42,0.42)] sm:p-8">
                    @include('profile.partials.update-password-form')
                </section>

                <section class="rounded-2xl border border-red-100 bg-red-50/40 p-5 sm:p-8">
                    @include('profile.partials.delete-user-form')
                </section>
            </div>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="rounded-2xl bg-[#173a32] p-5 text-emerald-50 shadow-[0_16px_32px_-28px_rgba(15,23,42,0.55)]">
                    <p class="text-sm font-semibold">Account details</p>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div><dt class="text-emerald-100/70">Signed in as</dt><dd class="mt-1 break-words font-medium text-white">{{ $user->email }}</dd></div>
                        <div><dt class="text-emerald-100/70">Account type</dt><dd class="mt-1 font-medium text-white">{{ Str::headline($user->account_type) }}</dd></div>
                    </dl>
                    <a href="{{ route('catalog.index') }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition duration-200 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-emerald-950">Back to marketplace</a>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
