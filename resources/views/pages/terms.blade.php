@extends('layouts.app')

@section('title', 'Regulamin')

@section('content')
    <article class="legal-doc">
        <h1 class="page-title">Regulamin</h1>
        <p>
            Regulamin dotyczy serwisu <strong>twentySix</strong> na twentysix.pl i aplikacji mobilnej twentySix.
            Zakładając konto, akceptujesz ten regulamin i
            <a href="{{ route('pages.privacy') }}">politykę prywatności</a>.
        </p>

        <h2>Konto</h2>
        <p>
            Konto zakładasz na swój adres e-mail. Nazwa gracza jest widoczna dla innych osób w rozgrywkach i na tablicy.
            Hasło zna tylko właściciel konta. Potwierdzenie adresu e-mail jest potrzebne, żeby się zalogować.
        </p>

        <h2>Rozgrywki</h2>
        <p>
            Wyniki szybkiej gry, turnieju i ligi zapisujemy przy koncie gracza.
            Osoby biorące udział w tej samej rozgrywce widzą te wyniki.
        </p>

        <h2>Tablica</h2>
        <p>
            Komentarze i reakcje piszesz we własnym imieniu. Nie wolno publikować:
        </p>
        <ul>
            <li>obelg, gróźb i nękania,</li>
            <li>spamu i reklam,</li>
            <li>danych osobowych innych osób,</li>
            <li>treści podszywających się pod innego gracza.</li>
        </ul>

        <h2>Moderacja</h2>
        <p>
            Komentarz niezgodny z regulaminem możemy usunąć.
            Konto, które łamie regulamin, możemy zablokować.
        </p>

        <h2>Akceptacja</h2>
        <p>
            Bez akceptacji regulaminu i polityki prywatności nie zakładamy konta i nie publikujemy komentarzy.
        </p>
    </article>
@endsection
