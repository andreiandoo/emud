@props(['min' => null, 'max' => null, 'disabled' => false, 'placeholder' => 'zz/ll/aaaa'])

{{-- A date typed and shown day first — 24/03/2027 — whatever language the browser speaks. The
     browser's own date field follows the operating system's locale, which on many machines here
     means month first. Its calendar is still one click away behind the button, and Livewire
     receives the ISO date it validates, so nothing downstream changes. --}}
<div x-data="{
        value: @entangle($attributes->wire('model')),
        text: '',
        init() {
            this.text = this.show(this.value);
            this.$watch('value', (value) => {
                if (this.parse(this.text) !== value) {
                    this.text = this.show(value);
                }
            });
        },
        show(iso) {
            const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');

            return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
        },
        parse(text) {
            const match = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec((text || '').trim());

            if (! match) {
                return null;
            }

            const [day, month, year] = [Number(match[1]), Number(match[2]), Number(match[3])];
            const date = new Date(year, month - 1, day);

            if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
                return null;
            }

            return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        },
        type(event) {
            const digits = event.target.value.replace(/\D/g, '').slice(0, 8);
            this.text = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter(Boolean).join('/');
            this.value = this.parse(this.text);
        },
     }"
     {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative']) }}>
    <input type="text" :value="text" @input="type($event)" inputmode="numeric" autocomplete="off" maxlength="10"
           placeholder="{{ $placeholder }}" @disabled($disabled)
           :aria-invalid="text !== '' && parse(text) === null ? 'true' : 'false'"
           :class="text !== '' && parse(text) === null ? 'border-red-400' : ''"
           class="w-full pr-11 tabular-nums">

    <input type="date" x-ref="picker" :value="value || ''" tabindex="-1" aria-hidden="true"
           @change="value = $event.target.value || null; text = show(value)"
           @if($min) min="{{ $min }}" @endif @if($max) max="{{ $max }}" @endif
           class="pointer-events-none absolute bottom-0 left-0 h-px w-px opacity-0">

    <button type="button" @disabled($disabled) aria-label="Alege din calendar"
            @click="$refs.picker.showPicker ? $refs.picker.showPicker() : $refs.picker.focus()"
            class="absolute inset-y-0 right-0 grid w-11 place-items-center opacity-60 transition hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-30">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 6h16v14H4zM4 10h16M8 3v4M16 3v4" />
        </svg>
    </button>
</div>
