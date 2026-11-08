@extends('layouts.app')

@section('title', 'Zgłoszenia — panel')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-6 pb-12">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-text-secondary text-sm hover:text-accent">← Panel</a>
            <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1">Zgłoszenia</h1>
        </div>
    </div>

    @if($reports === [])
        <p class="text-text-secondary text-sm">Brak otwartych zgłoszeń.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm text-text-secondary">
                <thead class="bg-bg text-accent">
                    <tr>
                        <th class="text-left py-3 px-3 font-semibold">Kto</th>
                        <th class="text-left py-3 px-3 font-semibold">Kogo</th>
                        <th class="text-left py-3 px-3 font-semibold">Treść</th>
                        <th class="text-left py-3 px-3 font-semibold">Powód</th>
                        <th class="text-left py-3 px-3 font-semibold">Komentarz</th>
                        <th class="text-left py-3 px-3 font-semibold">Kiedy</th>
                        <th class="text-left py-3 px-3 font-semibold">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                        <tr class="border-t border-border/60">
                            <td class="py-3 px-3 text-text">{{ $report['reporterName'] }}</td>
                            <td class="py-3 px-3 text-text">{{ $report['subjectName'] }}</td>
                            <td class="py-3 px-3">{{ $report['subjectLabel'] }}</td>
                            <td class="py-3 px-3">{{ $report['reason'] }}</td>
                            <td class="py-3 px-3 max-w-xs break-words">{{ $report['details'] ?: '—' }}</td>
                            <td class="py-3 px-3 text-text-muted whitespace-nowrap">{{ $report['createdAt'] }}</td>
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap gap-2">
                                    @if($report['canDeletePost'])
                                        <form method="POST" action="{{ route('admin.reports.delete-post', $report['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn-mini">Usuń wpis</button>
                                        </form>
                                    @endif
                                    @if($report['canDeleteComment'])
                                        <form method="POST" action="{{ route('admin.reports.delete-comment', $report['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn-mini">Usuń komentarz</button>
                                        </form>
                                    @endif
                                    @if($report['canBan'])
                                        <form method="POST" action="{{ route('admin.reports.ban', $report['id']) }}">
                                            @csrf
                                            <button type="submit" class="btn-mini">Zbanuj konto</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.reports.dismiss', $report['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn-mini">Odrzuć</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
