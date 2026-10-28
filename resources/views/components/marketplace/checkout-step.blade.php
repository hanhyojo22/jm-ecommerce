@props(['number', 'title', 'sub', 'value', 'change' => 'Change'])

{{--
 * One section of the checkout sheet. Its header opens the section; once the buyer has moved
 * past it, the header shows what they chose instead of the prompt. `value` is an Alpine expression.
 --}}
<section aria-labelledby="step-{{ $number }}-title">
    <h2>
        <button type="button" id="step-{{ $number }}-title" @click="open({{ $number }})" :aria-expanded="(step === {{ $number }}).toString()" aria-controls="step-{{ $number }}-body"
            class="grid w-full scroll-mt-24 grid-cols-[28px_minmax(0,1fr)_auto] items-start gap-3.5 px-6 py-5 text-left transition-colors duration-150 focus-visible:-outline-offset-2 max-sm:grid-cols-[26px_minmax(0,1fr)_auto] max-sm:gap-3 max-sm:p-4"
            :class="step === {{ $number }} ? 'cursor-default border-b border-[#EEF1F5]' : 'hover:bg-[#FAFBFE]'">
            <span class="mt-px grid size-7 place-items-center rounded-full border-[1.5px] text-[12.5px] font-semibold tabular-nums leading-none transition-colors duration-200 max-sm:size-[26px]" aria-hidden="true"
                :class="step === {{ $number }} ? 'border-brand bg-brand text-white' : (done.includes({{ $number }}) ? 'border-[#D6E2FB] bg-[#EEF3FF] text-brand' : 'border-[#CBD4E0] text-muted')">
                <span x-show="! collapsed({{ $number }})">{{ $number }}</span>
                <svg x-cloak x-show="collapsed({{ $number }})" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
            </span>
            <span class="min-w-0">
                <span class="block text-[15.5px] font-semibold leading-[1.35] tracking-[-0.012em] text-ink max-sm:text-[15px]">{{ $title }}</span>
                <span x-show="! collapsed({{ $number }})" class="mt-[3px] block text-[13.5px] leading-normal text-muted max-sm:text-[13px]">{{ $sub }}</span>
                <span x-cloak x-show="collapsed({{ $number }})" x-text="{{ $value }}" class="mt-[3px] block max-w-[62ch] whitespace-pre-line text-[13.5px] leading-normal text-ink max-sm:text-[13px]"></span>
            </span>
            <span x-cloak x-show="collapsed({{ $number }})" class="inline-flex items-center gap-0.5 whitespace-nowrap pt-[3px] text-[13.5px] font-semibold text-brand max-sm:text-[13px]">{{ $change }}<svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg></span>
            <svg x-show="step !== {{ $number }} && ! done.includes({{ $number }})" class="size-[18px] self-center justify-self-end text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
        </button>
    </h2>
    <div id="step-{{ $number }}-body" x-cloak x-show="step === {{ $number }}" x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0" class="px-6 pb-6 pt-5 max-sm:px-4 max-sm:pb-[18px] max-sm:pt-4">
        {{ $slot }}
    </div>
</section>
