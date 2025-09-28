@extends('layouts.app')

@section('title', 'Logowanie')

@section('content')
    <div class="container flex justify-center items-center flex-grow flex-1">
        <div class="inline-flex flex-col justify-center items-center bg-lighter-bg rounded-2xl p-20 ">
            <form name="loginForm" class="" action="/login" method="POST">
                <div class="flex flex-col items-center">
                    <h1 class="text-center text-light-green mb-10 text-2xl">Zaloguj się</h1>

                    <label class="mb-3 text-xl text-light-orange" for="login"><b>Login</b></label>
                    <input class="mb-5 bg-dark-bg text-light-orange p-4 rounded border-[0.2px] border-light-orange" type="text" placeholder="Wprowadź login" name="login" id="login" required>

                    <label class="mb-3 text-xl text-light-orange" for="password"><b>Password</b></label>
                    <input class="mb-5 bg-dark-bg text-light-orange p-4 rounded border-[0.2px] border-light-orange" type="password" placeholder="Wprowadź hasło" name="password" id="password" required>

                    <button class="btn btn-primary" type="submit" name="loginBtn">Zaloguj</button>
                </div>
            </form>
            <p class="text-light-orange mt-7">Nie masz jeszcze konta? <a href="/register" class="">Zarejestruj się</a></p>
        </div>
    </div>
@endsection

