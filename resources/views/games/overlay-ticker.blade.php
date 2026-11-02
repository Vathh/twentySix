@extends('layouts.broadcast')

@section('title', 'Pasek live')

@section('content')
    <div
        class="ticker-stage"
        id="overlay-ticker"
        data-state-url="{{ $stateUrl }}"
        data-preview="{{ $overlayPreview ? '1' : '0' }}"
    >
        <div class="ticker-bar">
            <div class="ticker-live" @if(count($items) === 0) hidden @endif>
                <span class="ticker-live-dot" aria-hidden="true"></span>
                <span>Live</span>
            </div>
            <div class="ticker-mark" @if(count($items) > 0) hidden @endif>
                <img src="{{ asset('images/logo.svg') }}" alt="twentySix" class="ticker-mark-img">
            </div>
            <div class="ticker-viewport">
                <p class="ticker-empty" @if(count($items) > 0) hidden @endif>
                    Brak meczów na żywo
                </p>
                <div class="ticker-track" @if(count($items) === 0) hidden @endif>
                    <div class="ticker-group">
                        @foreach($items as $item)
                            @include('games.partials.overlay-ticker-item', ['item' => $item])
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
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
                'finished' => false,
            ]])
        </template>
    </div>
@endsection
