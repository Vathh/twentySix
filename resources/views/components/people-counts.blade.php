@props([
    'relatedCount' => null,
    'guestCount' => null,
    'relatedUrl' => null,
    'guestsUrl' => null,
])

@if($relatedCount !== null)
    <x-place-stat icon="people" label="Powiązani" :href="$relatedUrl"><span class="score-num">{{ $relatedCount }}</span></x-place-stat>
@endif
@if($guestCount !== null)
    <x-place-stat icon="guest" label="Goście" :href="$guestsUrl"><span class="score-num">{{ $guestCount }}</span></x-place-stat>
@endif
