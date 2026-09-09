@props(['currency' => null])

{{-- A bare number box next to another bare number box says nothing about what either holds.
     The code comes from CurrencyConverter so it follows the shop currency set in settings,
     rather than a constant that quietly disagrees with it. Pass `currency` to show a
     supplier's own instead. --}}
@php($code = $currency ?: app(\App\Commerce\CurrencyConverter::class)->baseCurrency())

<span class="relative block">
    <input {{ $attributes->merge(['type' => 'number', 'step' => '0.01', 'min' => '0', 'class' => 'pr-14']) }}>
    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-stone-500">
        {{ $code }}
    </span>
</span>
