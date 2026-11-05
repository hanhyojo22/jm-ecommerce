<aside data-request-feedback hidden class="fixed inset-x-4 bottom-4 z-[100] mx-auto max-w-xl rounded-xl border border-red-200 bg-white p-4 text-sm text-ink shadow-lg sm:left-auto sm:right-6 sm:mx-0 sm:w-[min(32rem,calc(100vw-3rem))]" aria-label="Request feedback">
    <p data-request-feedback-message role="alert" aria-atomic="true" class="break-words leading-6"></p>
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" data-request-feedback-reload class="min-h-12 rounded-lg bg-brand px-4 font-semibold text-white">Refresh page</button>
        <button type="button" data-request-feedback-dismiss class="min-h-12 rounded-lg border border-line px-4 font-semibold">Dismiss</button>
    </div>
</aside>
@if ($errors->any())
    <section data-validation-summary role="alert" class="mx-auto my-4 max-w-5xl rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
        <h2 class="font-semibold">Please check the following before continuing:</h2>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </section>
@endif
