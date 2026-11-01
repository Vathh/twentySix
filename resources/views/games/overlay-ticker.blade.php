@extends('layouts.broadcast')

@section('title', 'Pasek live')

@section('content')
    <div
        class="ticker-stage"
        id="overlay-ticker"
        data-state-url="{{ $stateUrl }}"
        data-preview="{{ $overlayPreview ? '1' : '0' }}"
    >
        <div class="ticker-bar" @if(count($items) === 0) hidden @endif>
            <div class="ticker-live">
                <span class="ticker-live-dot" aria-hidden="true"></span>
                <span>Live</span>
            </div>
            <div class="ticker-viewport">
                <div class="ticker-track">
                    <div class="ticker-group">
                        @foreach($items as $item)
                            @include('games.partials.overlay-ticker-item', ['item' => $item])
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <p class="ticker-empty" @if(!($overlayPreview && count($items) === 0)) hidden @endif>
            Brak innych meczów na żywo
        </p>
        <template id="overlay-ticker-item">
            @include('games.partials.overlay-ticker-item', ['item' => [
                'key' => '',
                'label' => '',
                'player1' => '',
                'player2' => '',
                'score1' => '',
                'score2' => '',
                'setLegs1' => null,
                'setLegs2' => null,
            ]])
        </template>
    </div>
@endsection
