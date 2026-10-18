{{--
 * Clerk renders the account profile; the page supplies the surface. Clerk's own card chrome,
 * fixed 44rem height and navbar treatment are stripped in resources/js/clerk-appearance.js,
 * and the navbar heading — which has no appearance key — is tuned in app.css.
 --}}
<div class="content-card mt-4">
    <div data-clerk-component="user-profile" class="min-w-0" aria-busy="true" aria-live="polite">
        <p class="sr-only">Loading account settings...</p>
        <div class="grid animate-pulse motion-reduce:animate-none md:grid-cols-[14rem_minmax(0,1fr)]" aria-hidden="true">
            <div class="space-y-3 border-b border-line p-4 md:border-b-0 md:border-r md:px-3 md:py-4">
                <div class="h-3 w-16 rounded-full bg-slate-200"></div>
                <div class="h-11 rounded-[10px] bg-slate-100"></div>
                <div class="h-11 rounded-[10px] bg-slate-100"></div>
            </div>
            <div class="space-y-4 p-4 md:px-6">
                <div class="h-5 w-36 rounded-full bg-slate-200"></div>
                <div class="space-y-4">
                    @for ($row = 0; $row < 3; $row++)
                        <div class="grid gap-3 border-t border-line pt-4 md:grid-cols-[11.5rem_minmax(0,1fr)]">
                            <div class="h-4 w-24 rounded-full bg-slate-200"></div>
                            <div class="h-4 w-full max-w-[14rem] rounded-full bg-slate-100"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>
