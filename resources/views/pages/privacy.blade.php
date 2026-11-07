@extends('layouts.app')

@section('title', 'Polityka prywatności')

@section('content')
    <article class="legal-doc">
        <h1 class="page-title">Polityka prywatności</h1>
        <p>
            Ta polityka opisuje, jakie dane zbiera serwis <strong>twentySix</strong> pod adresem twentysix.pl
            i w aplikacji mobilnej twentySix oraz po co to robi.
        </p>

        <h2>Jakie dane zbieramy</h2>
        <ul>
            <li>adres e-mail i hasło (hasło trzymamy w postaci skrótu, nie w jawnej),</li>
            <li>nazwę gracza, opis profilu i awatar,</li>
            <li>wyniki gier, turniejów i lig,</li>
            <li>komentarze i reakcje na tablicy oraz listę znajomych,</li>
            <li>ustawienia widoczności tablicy,</li>
            <li>token powiadomień push, gdy włączysz powiadomienia na telefonie.</li>
        </ul>

        <h2>Po co</h2>
        <p>
            Dane służą do prowadzenia konta, rozgrywek, tablicy, znajomych i zaproszeń.
            Powiadomienia o zaproszeniach idą na telefon przez token push.
            Ten token przekazujemy do usługi Expo, która dostarcza powiadomienie na urządzenie.
        </p>
        <p>
            Nie sprzedajemy danych i nie wyświetlamy reklam. Połączenie z twentysix.pl jest szyfrowane (HTTPS).
        </p>

        <h2>Kto widzi profil i wyniki</h2>
        <p>
            Nazwa gracza i wyniki w turnieju oraz lidze widzą osoby, które mają dostęp do danej rozgrywki.
            Widoczność tablicy profilu ustawiasz sam: publiczna, dla znajomych albo węższa.
        </p>

        <h2>Jak długo trzymamy dane</h2>
        <p>
            Dane konta przechowujemy przez czas jego istnienia.
            Komentarze zostają na tablicy, dopóki ich nie usuniesz albo dopóki nie usuniemy ich, gdy łamią regulamin.
        </p>

        <h2>Usunięcie konta</h2>
        <p>
            Prośbę o usunięcie konta wyślij z adresu e-mail przypisanego do konta
            @if($contactEmail)
                na <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
            @else
                w odpowiedzi na wiadomość aktywacyjną, którą twentySix wysyła przy rejestracji.
            @endif
            Po przyjęciu prośby usuwamy e-mail, hasło, awatar, token powiadomień, komentarze i znajomości.
            Wyniki meczów w turniejach i ligach zostają, a profil w tych wynikach nazywa się „Gracz usunięty”.
        </p>

        <h2>Regulamin</h2>
        <p>
            Zasady korzystania z tablicy i konta są w <a href="{{ route('pages.terms') }}">regulaminie</a>.
        </p>
    </article>
@endsection
