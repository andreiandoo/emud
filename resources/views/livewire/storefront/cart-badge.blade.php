<a href="{{ route('storefront.cart') }}" class="st-icon-btn"
   aria-label="Coșul meu{{ $count > 0 ? ' ('.$count.')' : '' }}">
    <x-storefront.icon name="cart" class="h-5 w-5" />

    @if($count > 0)
        {{-- Keyed by the count, so a change inserts a new element and the bump plays again. --}}
        <span wire:key="cart-count-{{ $count }}" class="st-badge is-bumped">{{ $count }}</span>
    @endif
</a>
