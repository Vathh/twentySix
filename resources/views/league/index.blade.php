@extends('layouts.app')

@section('title', 'Ligi')

@section('content')
    @php
        $alerts = [];
        if(session('success')) {
            $alerts[] = ['type' => 'success', 'text' => session('success')];
        }
        if(session('error')) {
            $alerts[] = ['type' => 'error', 'text' => session('error')];
        }
    @endphp

    <x-alert :messages="$alerts" duration="4000" />

    <div class="flex flex-wrap gap-1 items-center justify-center pt-10">
        @if($leagues->isEmpty())
            <p>Brak.</p>
        @else
            @foreach($leagues as $league)
                <a href="{{ route('leagues.show', ['league' => $league->id]) }}">
                    <div class="bg-lighter-bg shadow rounded-lg p-6 hover:shadow-xl hover:cursor-pointer hover:bg-[#333333] transition">
                        <h3 class="text-xl font-semibold mb-2 text-light-orange">{{ $league->name }}</h3>
                        <p class="mb-2 text-light-orange">Ostatnia aktywność : {{ $league->updatedAt }}</p>
                    </div>
                </a>
            @endforeach
        @endif
    </div>

    @admin
    <a href="{{ route('leagues.create') }}"
       class="fixed bottom-30 right-20 btn-primary py-5 px-8 rounded-xl font-bold">
        Stwórz nową ligę
    </a>
    @endadmin

@endsection

