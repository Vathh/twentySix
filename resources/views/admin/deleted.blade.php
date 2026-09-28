@extends('layouts.app')

@section('title', 'Usunięte byty — panel')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-6 pb-12">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-text-secondary text-sm hover:text-accent">← Panel</a>
            <h1 class="text-xl sm:text-2xl font-semibold text-accent mt-1">Usunięte byty</h1>
            <p class="text-text-secondary text-sm mt-1">Przywrócenie jest możliwe przez 90 dni od ukrycia.</p>
        </div>
    </div>

    @if($entities === [])
        <p class="text-text-muted">Brak usuniętych bytów.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full text-sm text-text-secondary">
                <thead class="bg-bg text-accent">
                    <tr>
                        <th class="text-left py-3 px-3 font-semibold">Rodzaj</th>
                        <th class="text-left py-3 px-3 font-semibold">Nazwa</th>
                        <th class="text-left py-3 px-3 font-semibold">Usunął</th>
                        <th class="text-left py-3 px-3 font-semibold">Ukryto</th>
                        <th class="text-left py-3 px-3 font-semibold">Kasacja</th>
                        <th class="text-left py-3 px-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entities as $entity)
                        <tr class="border-t border-border/60">
                            <td class="py-3 px-3">{{ $entity['kind']->label() }}</td>
                            <td class="py-3 px-3 text-text">{{ $entity['name'] }}</td>
                            <td class="py-3 px-3">{{ $entity['deletedBy'] }}</td>
                            <td class="py-3 px-3">{{ $entity['deletedAt']->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="py-3 px-3">{{ $entity['purgeAt']->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                            <td class="py-3 px-3 text-right">
                                <form method="POST" action="{{ route('admin.deleted.restore', ['kind' => $entity['kind']->value, 'id' => $entity['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-mini">Przywróć</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
