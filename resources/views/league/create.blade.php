@extends('layouts.app')

@section('title', 'Tworzenie nowej ligi')

@section('content')

    <form class="" action="{{ route('createLeague') }}" method="POST">
        @csrf
        <div class="flex flex-col items-center">
            <h1 class="text-center text-light-green mb-10 text-2xl">Tworzenie nowej ligi</h1>

            <label class="mb-3 text-xl text-light-orange" for="login"><b>Nazwa ligi</b></label>
            <input class="mb-5 input-orange"
                   type="text"
                   placeholder="Wprowadź nazwę ligi"
                   name="name"
                   value="{{ old('name') }}"
                   required>

            <button class="btn btn-primary mt-3" type="submit" name="loginBtn">Stwórz ligę</button>

            <a href="{{ route('league.leagues') }}" class="btn btn-primary mt-5" type="submit" name="loginBtn">Powrót</a>

            @if($errors->any())
                <ul class="px-4 py-2 border-2 rounded border-light-red text-light-red mt-8">
                    @foreach($errors->all() as $error)
                        <li class="my-2 text-light-red">{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </form>

@endsection

