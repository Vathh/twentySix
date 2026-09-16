{{-- Zakładka Odznaczenia: Checkout Wheel 100+ --}}
@php
    $checkoutHits = $checkoutHits ?? [];
    $checkoutItems = $checkoutItems ?? [];
    $unlocked = collect($checkoutItems)->where('timesEarned', '>', 0)->count();
    $total = count($checkoutItems);
@endphp
<section>
    <div class="career-section-head">
        <h2 class="text-xl font-bold text-accent">Checkouty 100+</h2>
        <p class="text-sm text-text-muted">{{ $unlocked }} / {{ $total }} odblokowanych</p>
    </div>
    <p class="text-sm text-text-secondary mb-4">
        Finish 100–170 w meczach 501 (liga i turniej). Im więcej trafień, tym jaśniejszy klin.
    </p>
    <x-checkout-wheel :hits="$checkoutHits" :items="$checkoutItems" />
</section>
