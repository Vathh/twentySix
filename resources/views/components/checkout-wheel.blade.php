@props([
    'hits' => [],
    'items' => [],
    'demo' => false,
])

@php
    $svg = \App\Support\Checkout\CheckoutWheelSvg::inline();
@endphp

<div
    {{ $attributes->class('checkout-wheel') }}
    data-checkout-wheel
    @if($demo) data-checkout-wheel-demo="1" @endif
    data-checkout-hits='@json($hits)'
    data-checkout-items='@json($items)'
>
    <div class="checkout-wheel__stage overflow-visible px-2 py-4 sm:px-4">
        {!! $svg !!}
    </div>
</div>
