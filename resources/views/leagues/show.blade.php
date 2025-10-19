@extends('layouts.app')

@section('title', $league ? 'Szczegóły' : $league->name)

@section('content')

    <div class="flex min-h-screen bg-dark-bg text-light-white">

        @leagueAdmin($league)
            <aside class="w-72 backdrop-blur bg-white/5 border-r border-white/10 p-6 flex flex-col">
                <h2 class="text-light-green font-bold text-lg mb-6 tracking-wide">⚙️ Zarządzanie ligą</h2>

                <nav class="flex flex-col space-y-3">
                    <a href="{{ route('seasons.create') }}?leagueId={{ $league->id }}"
                       class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                        ➕ Dodaj sezon
                    </a>
                    <a href="{{ route('leagues.admins', $league->id) }}"
                       class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                        ‍💼 Administratorzy
                    </a>
                    <a href="{{ route('leagues.edit', ['league' => $league->id]) }}"
                       class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                        ✏️ Edytuj ligę
                    </a>
                    <a href="{{ route('leagues.relatedUsers', $league->id) }}"
                       class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                        👨‍👨‍👦 Edytuj powiązanych użytkowników
                    </a>
                    {{--                    <a href="#" class="flex items-center gap-3 bg-light-red/20 hover:bg-light-red/30 px-4 py-3 rounded-lg transition">--}}
                    {{--                        🗑️ Usuń ligę--}}
                    {{--                    </a>--}}
                </nav>
            </aside>
        @endleagueAdmin

        <div class="flex-1 p-10 flex justify-center">
            <div class="max-w-3xl w-full">

                <h1 class="text-4xl font-bold text-light-orange mb-6 tracking-wide">{{ $league->name }}</h1>

                <div class="bg-white/5 border border-white/10 p-6 rounded-xl shadow-lg backdrop-blur">
                    <p class="mb-2"><span
                            class="text-light-green font-semibold">Opis:</span> {{ $league->description }}</p>
                    <p class="mb-2"><span
                            class="text-light-green font-semibold">Data utworzenia:</span> {{ $league->createdAtDate() }}
                    </p>
                    <p class="mb-2"><span
                            class="text-light-green font-semibold">Liczba sezonów:</span> {{ count($league->seasons) }}
                    </p>
                    <p><span
                            class="text-light-green font-semibold">Ostatnia aktywność:</span> {{ $league->updatedAtDate() }}
                    </p>
                </div>

                <h2 class="text-2xl font-bold text-light-green mt-10 mb-4">Sezony</h2>
                <div class="space-y-3">
                    @foreach($league->seasons as $season)
                        <div
                            class="bg-white/5 p-4 rounded-lg border border-white/10 hover:bg-white/10 transition">{{ $season->name }}</div>
                    @endforeach
                </div>

            </div>
        </div>

    </div>

@endsection

