@extends('layouts.app')

@section('title', 'Logowanie')

@section('content')
    <div class="flex items-center justify-center w-full min-h-[70vh]">
        <div class="container flex justify-center items-center flex-grow flex-1">
            <div class="inline-flex flex-col justify-center items-center bg-lighter-bg rounded-2xl p-20 ">
                <form class="" action="{{ route('login') }}" method="POST">
                    @csrf
                    <div class="flex flex-col items-center">
                        <h1 class="text-center text-light-green mb-10 text-2xl">Zaloguj się</h1>

                        <label class="mb-3 text-xl text-light-orange" for="login"><b>Email</b></label>
                        <input class="mb-5 input-orange"
                               type="email"
                               placeholder="Wprowadź email"
                               name="email"
                               value="{{ old('email') }}"
                               required>

                        <label class="mb-3 text-xl text-light-orange" for="password"><b>Hasło</b></label>
                        <input class="mb-5 input-orange"
                               type="password"
                               placeholder="Wprowadź hasło"
                               name="password" id="password"
                               required>

                        <button class="btn btn-primary mt-3" type="submit" name="loginBtn">Zaloguj</button>

                        @if($errors->any())
                            <ul class="px-4 py-2 border-2 rounded border-light-red text-light-red mt-8">
                                @foreach($errors->all() as $error)
                                    <li class="my-2 text-light-red">{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </form>
                <p class="text-light-orange mt-7">Nie masz jeszcze konta? <a href="{{ route('pages.registerPanel') }}" class="font-bold">Zarejestruj się</a></p>
            </div>
        </div>
    </div>
@endsection

