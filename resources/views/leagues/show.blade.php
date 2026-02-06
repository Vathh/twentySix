@extends('layouts.app')

@section('title', $league ? $league->name : 'Szczegóły')

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
                        👨‍👨‍👦 Powiązani użytkownicy
                    </a>
                    <a href="{{ route('leagues.guests', $league->id) }}"
                       class="flex items-center gap-3 bg-white/10 hover:bg-white/15 px-4 py-3 rounded-lg transition">
                        👨‍👨‍👦 Goście
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
                            class="text-light-green font-semibold">Ilość sezonów:</span> {{ count($league->seasons) }}
                    </p>
                    <p><span
                            class="text-light-green font-semibold">Ostatnia aktywność:</span> {{ $league->updatedAtDate() }}
                    </p>
                </div>

                <h2 class="text-2xl font-bold text-light-green mt-10 mb-4">Tabela wyników ligi (top 40)</h2>
                <div class="overflow-x-auto rounded-lg p-4 bg-darker-bg border-border mb-10">
                    <table class="border-collapse text-sm text-text-primary min-w-full">
                        <thead>
                        <tr class="bg-dark-bg text-text-muted hover:bg-thead-hover transition">
                            <th class="px-3 py-2 text-center">Miejsce</th>
                            <th class="px-3 py-2 text-left">Zawodnik</th>
                            <th class="px-2 py-2 text-center">Punkty</th>
                            <th class="px-2 py-2 text-center">180</th>
                            <th class="px-2 py-2 text-center">170+</th>
                            <th class="px-2 py-2 text-center">QF</th>
                            <th class="px-2 py-2 text-center">HF</th>
                            <th class="px-2 py-2 text-center">Najniższa lotka</th>
                            <th class="px-2 py-2 text-center">Najwyższy finish</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                        @foreach($standings as $row)
                            <tr class="hover:bg-row-hover transition">
                                <td class="px-3 py-2 text-center font-semibold text-light-green">{{ $row->place }}</td>
                                <td class="px-3 py-2 font-medium text-text-primary whitespace-nowrap">
                                    <a href="{{ route('players.show', $row->player_id) }}" class="hover:text-light-green transition">{{ $row->player_name }}</a>
                                </td>
                                <td class="px-2 py-2 text-center">{{ $row->points }}</td>
                                <td class="px-2 py-2 text-center">{{ $row->count_max }}</td>
                                <td class="px-2 py-2 text-center">{{ $row->count_170_plus }}</td>
                                <td class="px-2 py-2 text-center">{{ $row->count_qf }}</td>
                                <td class="px-2 py-2 text-center">{{ $row->count_hf }}</td>
                                <td class="px-2 py-2 text-center">{{ $row->best_qf !== null ? $row->best_qf . ' lotek' : '–' }}</td>
                                <td class="px-2 py-2 text-center">{{ $row->best_hf ?? '–' }}</td>
                            </tr>
                        @endforeach
                        @if($standings->isEmpty())
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-text-muted">Brak danych. Rozegraj turnieje w sezonach tej ligi.</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <h2 class="text-2xl font-bold text-light-green mt-10 mb-4">Sezony</h2>
                <div class="space-y-3">
                    @foreach($seasons as $season)
                        <a href="{{ route('seasons.show', ['season' => $season->id]) }}">
                            <div class="mb-5 bg-white/5 p-4 rounded-lg border border-white/10 hover:bg-white/10 transition">{{ $season->name }}</div>
                        </a>
                    @endforeach
                </div>

            </div>
        </div>

    </div>

@endsection

