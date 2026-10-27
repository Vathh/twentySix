@extends('layouts.app')

@section('title', 'Tablica')

@section('content')
    <div class="detail-layout">
        @include('settings.partials.sidebar')

        <div class="detail-main">
            <div class="detail-content">
                <header class="entity-header">
                    <p class="entity-eyebrow">Ustawienia</p>
                    <h1 class="entity-title">Tablica</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                <form class="form-card !mx-0" action="{{ route('settings.feed.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <fieldset class="mb-4">
                        <legend class="form-label text-accent">Kto widzi moją tablicę profilu</legend>
                        @foreach($visibilityOptions as $option)
                            <label class="flex items-center gap-2 mb-2 text-sm text-text">
                                <input
                                    type="radio"
                                    name="profile_feed_visibility"
                                    value="{{ $option->value }}"
                                    @checked(old('profile_feed_visibility', $preferences['profileFeedVisibility']) === $option->value)
                                >
                                {{ $option->label() }}
                            </label>
                        @endforeach
                    </fieldset>

                    <fieldset class="mb-4">
                        <legend class="form-label text-accent">Strona główna</legend>
                        <label class="flex items-center gap-2 mb-2 text-sm text-text">
                            <input type="checkbox" name="channel_friends" value="1" @checked(old('channel_friends', $preferences['channels']['friends']))>
                            Historie znajomych
                        </label>
                        <label class="flex items-center gap-2 mb-2 text-sm text-text">
                            <input type="checkbox" name="channel_organization" value="1" @checked(old('channel_organization', $preferences['channels']['organization']))>
                            Historie organizacji
                        </label>
                    </fieldset>

                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </form>

                <h2 class="text-sm font-semibold text-text mt-8 mb-3">Wyciszone</h2>
                @if($preferences['mutes'] === [])
                    <p class="text-sm text-text-secondary">Nic nie jest wyciszone.</p>
                @else
                    <ul class="flex flex-col gap-2">
                        @foreach($preferences['mutes'] as $mute)
                            <li class="flex items-center justify-between gap-3 text-sm text-text">
                                <span>{{ $mute['label'] }}</span>
                                @php
                                    $unmuteUrl = null;
                                    if ($mute['subjectType'] === 'organization') {
                                        $unmuteUrl = route('organizations.feed-unmute', $mute['subjectKey']);
                                    } elseif ($mute['playerId']) {
                                        $unmuteUrl = route('players.feed-unmute', $mute['playerId']);
                                    }
                                @endphp
                                @if($unmuteUrl)
                                    <form method="POST" action="{{ $unmuteUrl }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-mini">Przywróć</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
