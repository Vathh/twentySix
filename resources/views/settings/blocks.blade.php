@extends('layouts.app')

@section('title', 'Zablokowani')

@section('content')
    <div class="detail-layout">
        @include('settings.partials.sidebar')

        <div class="detail-main">
            <div class="detail-content">
                <header class="entity-header">
                    <p class="entity-eyebrow">Ustawienia</p>
                    <h1 class="entity-title">Zablokowani</h1>
                    <span class="entity-rule" aria-hidden="true"></span>
                </header>

                @if($blocked === [])
                    <p class="text-text-secondary text-sm">Nikogo nie blokujesz.</p>
                @else
                    <ul class="form-card !mx-0 divide-y divide-border">
                        @foreach($blocked as $person)
                            <li class="flex items-center justify-between gap-3 py-3 text-sm">
                                <span class="text-text">{{ $person['name'] }}</span>
                                <form method="POST" action="{{ route('blocks.destroy', $person['userId']) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-mini">Odblokuj</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
