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
                            Historie graczy
                        </label>
                        <label class="flex items-center gap-2 mb-2 text-sm text-text">
                            <input type="checkbox" name="channel_organization" value="1" @checked(old('channel_organization', $preferences['channels']['organization']))>
                            Historie organizacji
                        </label>
                    </fieldset>

                    <button type="submit" class="btn btn-primary">Zapisz</button>
                </form>
            </div>
        </div>
    </div>
@endsection
