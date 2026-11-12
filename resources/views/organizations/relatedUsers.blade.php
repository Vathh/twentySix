@extends('layouts.app')

@section('title', 'Powiązani użytkownicy')

@section('content')
    <x-people-page
        title="Powiązani użytkownicy"
        kind="Powiązani"
        tone="organization"
        :organization-name="$organization->name"
        :organization-url="route('organizations.show', $organization->id)"
        lead="Konta, które można zapraszać do rozgrywek tej organizacji."
    >
        <x-related-user-search
            :manage="$canManage"
            :search-url="route('organizations.relatedUsers', $organization->id)"
            :add-url="route('organizations.relatedUsers.add', $organization->id)"
            :remove-url="route('organizations.relatedUsers.remove', $organization->id)"
            :cancel-url-template="preg_replace('#/invitations/\d+/cancel$#', '/invitations/__ID__/cancel', route('organizations.relatedUsers.invitations.cancel', [$organization->id, 0]))"
            :related="$relatedUsers"
            :pending="$canManage ? $pendingInvitations->map(fn ($invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->userPlayer?->name ?? 'Brak nazwy',
            ])->values()->all() : []"
            add-label="Zaproś"
            empty-related="Nikt jeszcze nie jest powiązany z tą organizacją."
        />
        @if($canManage && count($pendingJoinRequests ?? []) > 0)
            <div class="card mt-6">
                <h2 class="text-lg font-semibold text-text mb-3">Zgłoszenia</h2>
                <ul class="space-y-3">
                    @foreach($pendingJoinRequests as $request)
                        <li class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-text">{{ $request['name'] }}</span>
                            <span class="flex gap-2">
                                <form method="POST" action="{{ route('organizations.join-requests.approve', [$organization->id, $request['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">Dołącz</button>
                                </form>
                                <form method="POST" action="{{ route('organizations.join-requests.reject', [$organization->id, $request['id']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary">Odrzuć</button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-people-page>
@endsection
