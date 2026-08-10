@extends('layouts.app')

@section('title', 'Użytkownicy — panel')

@section('content')
<div class="max-w-6xl mx-auto px-4 pt-10 pb-16">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-text-secondary text-sm hover:text-accent">← Panel</a>
            <h1 class="text-2xl sm:text-3xl font-semibold text-accent mt-1">Użytkownicy</h1>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users') }}" class="mb-6 flex flex-wrap gap-2">
        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Email lub nick…"
            class="select-field min-w-[16rem] flex-1"
        >
        <button type="submit" class="btn btn-primary">Szukaj</button>
        @if($search !== '')
            <a href="{{ route('admin.users') }}" class="btn btn-mini">Wyczyść</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-lg border border-border">
        <table class="w-full text-sm text-text-secondary">
            <thead class="bg-bg text-accent">
                <tr>
                    <th class="text-left py-3 px-3 font-semibold">ID</th>
                    <th class="text-left py-3 px-3 font-semibold">Gracz</th>
                    <th class="text-left py-3 px-3 font-semibold">Email</th>
                    <th class="text-left py-3 px-3 font-semibold">Rola</th>
                    <th class="text-left py-3 px-3 font-semibold">Weryfikacja</th>
                    <th class="text-left py-3 px-3 font-semibold">Tworzenie lig</th>
                    <th class="text-left py-3 px-3 font-semibold">Status</th>
                    <th class="text-left py-3 px-3 font-semibold">Rejestracja</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-t border-border/60 {{ $user->isBanned() ? 'opacity-60' : '' }}">
                        <td class="py-3 px-3 text-text-muted">{{ $user->id }}</td>
                        <td class="py-3 px-3 text-text">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="hover:text-accent">
                                {{ $user->player?->name ?? '—' }}
                            </a>
                        </td>
                        <td class="py-3 px-3 break-all">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="hover:text-accent">
                                {{ $user->email }}
                            </a>
                        </td>
                        <td class="py-3 px-3">
                            @if($user->isPlatformAdmin())
                                <span class="text-accent font-semibold">admin</span>
                            @else
                                user
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            @if($user->email_verified_at)
                                <span class="text-success">tak</span>
                            @else
                                <span class="text-danger">nie</span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <form method="POST" action="{{ route('admin.users.can-create-leagues', $user->id) }}" class="inline">
                                @csrf
                                <input type="hidden" name="can_create_leagues" value="{{ $user->can_create_leagues ? 0 : 1 }}">
                                <button type="submit" class="btn-mini" @if($user->isBanned()) disabled @endif>
                                    {{ $user->can_create_leagues ? 'Wyłącz' : 'Włącz' }}
                                </button>
                            </form>
                        </td>
                        <td class="py-3 px-3">
                            @if($user->isPlatformAdmin())
                                <span class="text-text-muted">—</span>
                            @elseif($user->isBanned())
                                <div class="flex flex-col gap-1">
                                    <span class="text-danger font-semibold">zablokowany</span>
                                    <form method="POST" action="{{ route('admin.users.ban', $user->id) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="banned" value="0">
                                        <button type="submit" class="btn-mini">Odblokuj</button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('admin.users.ban', $user->id) }}" class="inline"
                                      onsubmit="return confirm('Zablokować konto tego użytkownika?');">
                                    @csrf
                                    <input type="hidden" name="banned" value="1">
                                    <button type="submit" class="btn-mini">Zablokuj</button>
                                </form>
                            @endif
                        </td>
                        <td class="py-3 px-3 whitespace-nowrap">
                            {{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 px-3 text-center text-text-muted">Brak użytkowników.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
@endsection
