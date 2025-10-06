@extends('layouts.app')

@section('content')
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold text-light-green mb-6">Zarządzanie administratorami ligi: {{ $league->name }}</h1>

        <form action="{{ route('leagues.admins.update', $league) }}" method="POST" class="bg-lighter-bg p-10 rounded-2xl shadow-md">
            @csrf
            @method('PUT')

            <div class="mb-6">
                <label class="block mb-2 text-light-orange font-semibold">Wybierz administratorów:</label>
                <div class="grid grid-cols-2 gap-4">
                    @foreach($users as $user)
                        <label class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                name="admins[]"
                                value="{{ $user->id }}"
                                class="rounded border-light-orange text-light-green focus:ring-0"
                                {{ in_array($user->id, $league->admins->pluck('id')->toArray()) ? 'checked' : '' }}
                            >
                            <span class="text-light-white">{{ $user->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                <a href="{{ route('leagues.show', $league->id) }}" class="btn btn-secondary">Powrót</a>
            </div>

            @if($errors->any())
                <ul class="px-4 py-2 border-2 rounded border-light-red text-light-red mt-6">
                    @foreach($errors->all() as $error)
                        <li class="my-2">{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </form>
    </div>
@endsection
