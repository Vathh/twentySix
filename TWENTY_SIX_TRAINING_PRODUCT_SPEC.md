# Twenty Six Training --- główny dokument produktowy

**Wersja:** 1.0\
**Status:** Dokument bazowy --- zatwierdzone ustalenia produktowe\
**Przeznaczenie:** Stały dokument referencyjny dla Cursora i zespołu
rozwijającego Twenty Six Training.

------------------------------------------------------------------------

## 1. Cel i sposób korzystania z dokumentu

Ten dokument opisuje docelową koncepcję produktową sekcji **Twenty Six
Training**: jej zakres, strukturę, zachowania, przepływy użytkownika,
reguły biznesowe, dane, analitykę, zasady administracyjne i
ograniczenia.

Dokument należy traktować jako nadrzędne źródło wymagań produktowych
podczas prac nad Twenty Six Training. Jeżeli szczegóły implementacyjne w
repozytorium są niejasne, należy najpierw odnieść się do tego dokumentu,
a następnie przeanalizować istniejący kod i rozwiązania projektu.

### 1.1. Zasady nadrzędne dla implementacji

1.  **Zakres zmian jest ograniczony wyłącznie do Twenty Six Training.**
2.  Nie wolno zmieniać działania innych sekcji aplikacji ani
    istniejących przepływów niezwiązanych z Training.
3.  Istniejące komponenty, wzorce UI lub mechanizmy mogą być ponownie
    wykorzystane wewnątrz Training, jeśli jest to bezpieczne. Ich
    dotychczasowe działanie poza Training musi pozostać bez zmian.
4.  Nie należy przebudowywać istniejących obszarów aplikacji „przy
    okazji" realizacji Training.
5.  Przed implementacją należy przeanalizować repozytorium i dopasować
    rozwiązanie do istniejącej architektury. Dokument określa wymagania
    produktowe, a nie narzuca technologii, bibliotek, schematów API ani
    konkretnego modelu implementacji.
6.  Nie należy samodzielnie dopowiadać nieustalonych zasad ćwiczeń,
    punktacji, progów ani zachowań. Niejasności wymienione w rozdziale
    22 wymagają decyzji przed wdrożeniem danego elementu.
7.  Gdy wymaganie wydaje się sprzeczne z istniejącym kodem, należy
    wskazać konflikt i zaproponować rozwiązanie ograniczone do Training,
    zamiast samodzielnie rozszerzać zakres zmian.
8.  Priorytetem MVP jest niezawodne ręczne wprowadzanie każdej lotki w
    treningach i wyzwaniach. Integracja z elektroniczną tarczą i
    automatyczne rozpoznawanie lotek są kierunkiem przyszłego rozwoju,
    nie warunkiem MVP.

------------------------------------------------------------------------

## 2. Wizja produktu

Twenty Six Training ma być pełnoprawnym centrum treningowym dla
zawodników darta. Ma prowadzić użytkownika przez treningi, rejestrować
ich przebieg, analizować skuteczność i błędy oraz pokazywać historię,
rekordy i postępy.

Główne cele produktu:

-   ułatwienie rozpoczęcia dobrze zorganizowanego treningu;
-   prowadzenie zawodnika krok po kroku przez ćwiczenia i plany;
-   dokładne rejestrowanie lotek w ćwiczeniach, planach i wyzwaniach;
-   rozróżnianie zamierzonego celu od faktycznego trafienia;
-   wiarygodne obliczanie wyników i skuteczności zgodnie z zasadami
    danego ćwiczenia;
-   prezentowanie historii, rekordów, celów i analityki;
-   wspieranie rozwoju umiejętności na podstawie rzeczywistych danych.

### 2.1. Zakres MVP i kierunek rozwoju

**MVP:** - ręczne wprowadzanie każdej pojedynczej lotki w treningach i
wyzwaniach; - plany treningowe, samodzielne ćwiczenia i wyzwania; -
sesje jednoosobowe dla treningów i wyzwań; - analiza, rekordy, cele,
odznaki i historia; - obsługa offline dla wcześniej pobranych
definicji; - zarządzanie treścią Training z panelu administratora.

**Przyszły kierunek:** - własne plany użytkownika, jeśli zostanie to
zaprojektowane jako osobny zakres; - integracja z elektroniczną
tarczą; - automatyczne rozpoznawanie lotek; - dalsze rozszerzanie
katalogu ćwiczeń i mechanizmów analitycznych.

------------------------------------------------------------------------

## 3. Struktura Twenty Six Training

Główne sekcje Training:

1.  Wolna gra.
2.  Plany treningowe.
3.  Wyzwania.
4.  Biblioteka ćwiczeń.
5.  Analiza zawodnika.
6.  Moje rekordy.
7.  Moje cele.

„Moje rekordy" i „Moje cele" są osobnymi sekcjami/kafelkami na ekranie
głównym Training.

### 3.1. Ekran główny

Ekran główny ma być minimalistyczny i skoncentrowany na rozpoczęciu
aktywności. Nie należy umieszczać na nim rozbudowanych wykresów,
statystyk, ostatnich treningów ani dużych podsumowań.

Elementy i kolejność:

1.  Dyskretny komunikat o przerwanym treningu --- tylko jeśli istnieje.
2.  Główne CTA **„Rozpocznij trening"**.
3.  Plany treningowe.
4.  Wyzwania.
5.  Biblioteka ćwiczeń.
6.  Wolna gra.
7.  Sekcja **„Twoje postępy"** zawierająca:
    -   Analizę zawodnika,
    -   Moje rekordy,
    -   Moje cele.

Nagłówek: **Twenty Six Training** oraz krótki tekst powitalny.

Nie umieszczać karty „Ostatni trening".

Jeśli istnieje przerwana sesja, komunikat powinien zawierać nazwę
planu/wariantu/ćwiczenia lub wyzwania, status i przycisk **„Wznów
trening"**. Element ma być dyskretny, nie może dominować nad ekranem.
Powinien umożliwiać przejście do sesji i jej wznowienie lub zakończenie
zgodnie z zasadami danego rodzaju aktywności.

### 3.2. Nawigacja i onboarding

-   Training korzysta z obecnej nawigacji aplikacji. Nie tworzyć osobnej
    dolnej belki nawigacyjnej.
-   Przy pierwszym wejściu można pokazać krótki onboarding wyjaśniający
    Training.
-   Onboarding można pominąć.
-   Po zamknięciu nie wyświetla się ponownie automatycznie.
-   Użytkownik może uruchomić go ponownie z pozycji **„Poznaj Twenty Six
    Training"**.

------------------------------------------------------------------------

## 4. Wolna gra a właściwe treningi

Wolna gra i treningi szczegółowo rejestrowane są rozdzielone pod
względem logiki.

### 4.1. Wolna gra

-   Zachowuje obecny wygląd i sposób działania.
-   Obsługuje dotychczasowe tryby i 1--8 graczy.
-   Zachowuje obecny sposób wprowadzania wyników.
-   Nie wymaga rejestrowania każdej pojedynczej lotki w modelu
    treningowym.
-   Nie należy zmieniać jej istniejącego silnika ani przepływów poza
    zakresem niezbędnym do udostępnienia jej historii w Training.
-   Może wykorzystywać istniejące sprawdzone komponenty i wzorce UI.

### 4.2. Plany, samodzielne ćwiczenia i wyzwania

-   Są jednoosobowe.
-   Wymagają rejestrowania każdej pojedynczej lotki.
-   Zapisują zamierzony cel, faktyczne trafienie i punkty zgodnie z
    zasadami aktywności.
-   Powinny korzystać z odrębnej logiki/silnika treningowego, ale mogą
    ponownie wykorzystywać sprawdzone komponenty UI i wzorce z Wolnej
    gry.
-   Oddzielna logika nie oznacza konieczności pisania wszystkich
    elementów od zera.

### 4.3. Cofanie lotek

Podczas aktywnej sesji użytkownik może cofnąć jedną lub kilka ostatnich
lotek, maksymalnie pięć.

-   Cofnięte lotki zostają usunięte z bieżącego przebiegu i muszą zostać
    ponownie wprowadzone.
-   Wynik i statystyki są przeliczane.
-   Nie tworzyć odrębnego mechanizmu edycji pojedynczej lotki, historii
    korekt ani analityki operacji Undo.
-   Po zamknięciu sesji nie można edytować ani cofać lotek.

------------------------------------------------------------------------

## 5. Szybki start i katalogi

### 5.1. Szybki start

Kliknięcie **„Rozpocznij trening"** otwiera ekran z opcjami w
następującej kolejności:

1.  Plan treningowy.
2.  Samodzielne ćwiczenie.
3.  Wyzwanie.
4.  Ulubione.
5.  Ostatnio używane.

Wolna gra pozostaje osobnym kafelkiem na ekranie głównym Training.

Szybki start jest ścieżką szybkiego rozpoczęcia aktywności. Osobne
katalogi służą do przeglądania treści i poznawania szczegółów. Nie są to
dwa różne mechanizmy treningowe --- korzystają z tych samych definicji,
zasad i konfiguracji.

### 5.2. Ostatnio używane

-   Maksymalnie trzy różne pozycje.
-   Obejmują ostatnio uruchamiane plany (z konkretnym wariantem),
    samodzielne ćwiczenia i wyzwania.
-   Najnowsza pozycja jest pierwsza.
-   Ponowne uruchomienie tej samej pozycji przesuwa ją na początek, bez
    tworzenia duplikatu.
-   Kliknięcie otwiera ekran przygotowania, nie uruchamia automatycznie
    aktywności.
-   Jeśli brak historii, sekcja jest ukryta.

### 5.3. Ulubione

Ulubione obejmują wyłącznie: - plany treningowe; - konkretne warianty
planów; - samodzielne ćwiczenia.

Wyzwania i eventy nie mogą być oznaczane jako ulubione.

-   Liczba ulubionych nie jest ograniczona.
-   Ulubione są zapisywane na koncie użytkownika.
-   Dodawanie/usuwanie odbywa się przez ikonę gwiazdki lub serca.
-   Oznaczanie jest dostępne w katalogach i na ekranach szczegółowych.
-   Pusta lista pokazuje dyskretną zachętę.
-   Sekcja służy szybkiemu dostępowi, nie prezentuje rankingów ani
    wyników.

------------------------------------------------------------------------

## 6. Plany treningowe

### 6.1. Struktura planu

Struktura: **Plan → Warianty planu → Przebieg treningu**.

-   Plan może mieć wiele wariantów.
-   Każdy wariant może mieć własny zestaw ćwiczeń, kolejność, czasy i
    zasady.
-   Użytkownik wybiera wariant przed rozpoczęciem.
-   Historia zapisuje konkretny wariant.
-   Administrator może dodawać i edytować plany oraz warianty w panelu
    webowym.

### 6.2. Katalog planów

-   Prosta lista wszystkich opublikowanych planów.
-   Na początek bez kategorii, wyszukiwarki i filtrów.
-   Karta planu zawiera:
    -   nazwę;
    -   krótki opis;
    -   orientacyjny czas;
    -   liczbę wariantów;
    -   możliwość oznaczenia jako ulubiony.

Po otwarciu planu prezentowane są warianty jako osobne karty. Każda
karta wariantu zawiera: - nazwę; - krótki opis; - orientacyjny czas; -
rozwijaną sekcję **„Zobacz ćwiczenia"**; - CTA rozpoczęcia; - możliwość
oznaczenia wariantu jako ulubionego.

Lista ćwiczeń jest domyślnie zwinięta.

### 6.3. Przygotowanie planu

Ekran przygotowania jest uproszczony. Zawiera: - nazwę planu i
wariantu; - krótki opis; - orientacyjny czas; - najważniejsze
informacje; - przycisk **„Rozpocznij trening"**; - link do szczegółów
planu.

Nie pokazuje ponownie pełnej listy ćwiczeń, ponieważ jest ona dostępna w
„Zobacz ćwiczenia".

### 6.4. Powtórzenie planu

Po ukończeniu planu przycisk **„Powtórz trening"** uruchamia ten sam
plan i wariant z tymi samymi ustawieniami. Powstaje nowa, niezależna
sesja. Poprzednia historia pozostaje zachowana.

------------------------------------------------------------------------

## 7. Samodzielne ćwiczenia i Biblioteka ćwiczeń

Oba widoki korzystają z tych samych definicji ćwiczeń, zasad i
konfiguracji, ale pełnią różne funkcje.

### 7.1. Szybka ścieżka --- Samodzielne ćwiczenie

Dostęp: **Rozpocznij trening → Samodzielne ćwiczenie**.

-   Minimalistyczna lista ćwiczeń.
-   Każda pozycja zawiera krótką informację i przycisk **„Rozpocznij"**.
-   Możliwość oznaczenia ćwiczenia jako ulubionego.
-   Wybranie ćwiczenia prowadzi do uproszczonego ekranu przygotowania.
-   Nie wymaga rozbudowanych opisów ani pełnych przykładów.
-   Na początek nie wymuszać wyszukiwarki w tym widoku.

### 7.2. Biblioteka ćwiczeń

Biblioteka jest katalogiem edukacyjnym i kompendium, również dla
początkujących.

Dwa działy: 1. Tryby gry, np. X01, Cricket, Bob's 27, Around the Clock.
2. Ćwiczenia treningowe.

Karta/strona wpisu może zawierać: - nazwę; - opis; - cel ćwiczenia; -
pełne zasady; - punktację; - warianty; - poziom trudności; -
przykłady; - informację, jakie dane są rejestrowane; - CTA rozpoczęcia,
jeśli wpis jest uruchamialny.

Biblioteka od początku ma: - wyszukiwarkę nazw; - filtry według
umiejętności, np. Scoring, Doubles, Checkouty, Cricket; - filtry
trudności: początkujący, średniozaawansowany, zaawansowany.

Nie każdy wpis biblioteki musi być uruchamialnym ćwiczeniem.

### 7.3. Przygotowanie samodzielnego ćwiczenia

Ekran zawiera: - nazwę; - krótki opis celu; - najważniejsze zasady; -
domyślny czas lub liczbę lotek; - możliwość zmiany parametrów, jeśli
dane ćwiczenie to obsługuje; - CTA **„Rozpocznij ćwiczenie"**; - link
**„Poznaj pełne zasady"** do biblioteki.

Wartości domyślne powinny być rozsądne. Użytkownik może rozpocząć
ćwiczenie bez zmian. Parametry zależą od danego ćwiczenia i nie mogą
uniemożliwiać jego prawidłowego wykonania.

Nie przyjmować przykładowych wartości, np. 60 lotek lub 10 minut dla
Doubles, jako zatwierdzonych ustawień --- to były wyłącznie przykłady.

### 7.4. Zasady w trakcie ćwiczenia

Pełna instrukcja jest dostępna przed rozpoczęciem i w trakcie przez
przycisk **„Zasady"**. Po zamknięciu instrukcji użytkownik wraca
dokładnie do miejsca przerwania, bez utraty postępu.

### 7.5. Podsumowanie samodzielnego ćwiczenia

Pokazywać wyłącznie statystyki dotyczące konkretnego ćwiczenia: -
wynik; - liczbę lotek; - skuteczność, jeśli ma zastosowanie; -
najważniejsze statystyki; - informację o nowym rekordzie.

Szczegóły mogą być zwinięte.

Przyciski: - **„Spróbuj ponownie"** --- ta sama konfiguracja, nowa
niezależna sesja; - **„Wróć do Training"**; - ewentualny dostęp do
szczegółów sesji.

Historia poprzednich prób pozostaje zachowana.

------------------------------------------------------------------------

## 8. Wyzwania

### 8.1. Rodzaje wyzwań

1.  **Standardowe** --- stale dostępne, np. JDC Challenge.
2.  **Specjalne** --- dodatkowe wyzwania dostępne okresowo.
3.  **Eventy i konkursy** --- wydarzenia z terminem rozpoczęcia i
    zakończenia, wieloma uczestnikami i publiczną klasyfikacją.

### 8.2. Wyzwania standardowe i specjalne

-   Możliwa jest nieograniczona liczba podejść.
-   Wyniki domyślnie są prywatne.
-   Po ukończeniu użytkownik może dobrowolnie opublikować konkretny
    wynik, jeśli dana funkcja publikacji jest dostępna.
-   Zachowywana jest historia ukończonych wyników i rekord osobisty.
-   Próby można powtarzać.
-   Możliwa jest pauza, świadome zakończenie przed czasem i wznowienie.
-   Zapisywane są wykonane lotki; niewykonane etapy pozostają
    nieukończone.
-   Rekord osobisty jest aktualizowany wyłącznie na podstawie
    ukończonych prób, niezależnie od publiczności wyniku.
-   Nieukończone i anulowane próby zwykłych wyzwań nie pojawiają się w
    widocznej historii Training.
-   Dane nieukończonej próby mogą być tymczasowo zachowane, jeśli są
    potrzebne do wznowienia.

Wyzwania i eventy nie mogą być oznaczane jako ulubione.

### 8.3. Katalog wyzwań

Dwa widoki korzystają z tych samych definicji i zasad:

**Szybki start:** - Rozpocznij trening → Wyzwanie. - Prosta lista
dostępnych wyzwań z szybkim startem.

**Pełny katalog:** - Kafelek „Wyzwania" na ekranie głównym. - Pełniejsze
opisy, zasady, punktacja i szczegóły.

Na początek bez wyszukiwarki.

### 8.4. Przygotowanie wyzwania standardowego/specjalnego

Ekran uproszczony: - nazwa; - krótki opis; - podstawowa informacja o
wyniku/punktacji, jeśli potrzebna; - przycisk **„Rozpocznij
wyzwanie"**; - link **„Poznaj szczegóły"** do pełnej sekcji Wyzwania.

Nie pokazywać liczby dostępnych prób, ponieważ podejścia są
nieograniczone.

### 8.5. Podsumowanie i powtórzenie

Po ukończeniu zwykłego wyzwania: - dostępny jest przycisk **„Spróbuj
ponownie"**; - uruchamia nową, niezależną próbę tego samego wyzwania; -
poprzednie wyniki pozostają zachowane.

Eventy i konkursy nie mają przycisku ponowienia oficjalnej próby.

### 8.6. JDC Challenge

JDC Challenge jest osobnym wyzwaniem, a nie zwykłym planem treningowym.

Etapy: 1. Shanghai 10--15 --- 18 lotek. 2. Double 1--20 + Bull --- 21
lotek. 3. Shanghai 15--20 --- 18 lotek.

Łącznie: 57 lotek.

Zasady punktacji znane na etapie ustaleń: - Shanghai: wartości
trafionego single/double/triple; bonus 100 za komplet S+D+T tego samego
numeru w jednej kolejce. - Doubles: jedna lotka na D1--D20 i Bull; 50
punktów za trafione double. - Sposób punktowania Bull (czy 50 punktów
podstawowych i dodatkowe 50 bonusu) oraz to, czy outer bull jest
zaliczany, wymagają potwierdzenia na podstawie aktualnej oficjalnej
karty zasad. - Progi kolorystyczne z wtórnych źródeł nie są
zatwierdzone. Nie przedstawiać ich jako oficjalnych. - Wynik punktowy
może być prezentowany, ale klasyfikację kolorystyczną należy pominąć do
czasu potwierdzenia zasad.

------------------------------------------------------------------------

## 9. Eventy i konkursy

Eventy to specjalne wydarzenia z określonym terminem, wieloma
uczestnikami i publiczną klasyfikacją. W klasyfikacji widoczni są
wyłącznie użytkownicy, którzy dobrowolnie dołączyli do eventu.

### 9.1. Dostępność eventów

-   Eventy specjalne nie są widoczne przed rozpoczęciem.
-   Nie pokazujemy zapowiedzi ani katalogu nadchodzących eventów.
-   W momencie rozpoczęcia event pojawia się w katalogu jako
    **Aktywny**.
-   Po zakończeniu trafia do grupy **Zakończone**.
-   Nie wyświetlać grupy „Nadchodzące".

Katalog eventów może więc prezentować grupy Aktywne i Zakończone.

### 9.2. Dołączenie i oficjalna próba

-   Każdy uczestnik ma jedną oficjalną próbę.
-   Użytkownik może dołączyć w dowolnym momencie trwania eventu.
-   Dołączenie oznacza natychmiastowe rozpoczęcie oficjalnej próby ---
    nie ma osobnego zapisu na później.
-   CTA: **„Dołącz i rozpocznij próbę"**.
-   Przed startem należy pokazać pełny ekran z zasadami, punktacją,
    warunkami, terminem oraz informacją, że próba jest jedna i
    niepowtarzalna.
-   Użytkownik musi świadomie potwierdzić rozpoczęcie.

### 9.3. Zasady aktywnej próby

-   Brak pauzy.
-   Brak możliwości dobrowolnego opuszczenia aktywnej próby bez
    konsekwencji.
-   Próba wyjścia z ekranu pokazuje ostrzeżenie z opcjami **„Wróć do
    próby"** i **„Przerwij próbę"**.
-   Potwierdzone „Przerwij próbę" zużywa jedyną próbę.
-   Przy przerwaniu zachowuje się dotychczasowe lotki/wynik prywatnie,
    ale niepełny wynik nie trafia do publicznej klasyfikacji.
-   W prywatnym profilu widoczny jest status **„Próba niezaliczona"** i
    informacja, że próba została wykorzystana. Nie pokazuje się wyniku i
    nie ma możliwości ponownego udziału w tej edycji.
-   W trakcie próby nie pokazujemy aktualnej klasyfikacji ani odliczania
    czasu do końca eventu.
-   Użytkownik może otworzyć modalne okno **„Zasady"**. Próba trwa
    dalej, a timer nie zatrzymuje się.

### 9.4. Awaria, zamknięcie aplikacji i brak internetu

Rozróżniać świadome przerwanie od zdarzeń przypadkowych:

-   Przypadkowe zamknięcie aplikacji, awaria lub restart telefonu nie
    zużywają próby.
-   Po ponownym uruchomieniu ta sama próba jest odzyskiwana z ostatniego
    lokalnie zapisanego postępu.
-   Utrata internetu nie przerywa próby; użytkownik kontynuuje lokalnie.
-   Wymagane jest lokalne, trwałe zapisywanie postępu.
-   Nie wolno tworzyć nowej próby przy odzyskiwaniu tej samej aktywnej
    próby.

### 9.5. Koniec eventu podczas aktywnej próby

-   Jeśli event zakończy się, gdy użytkownik jest w trakcie oficjalnej
    próby, może ją dokończyć.
-   Ukończony wynik zostaje uwzględniony w klasyfikacji.
-   Po terminie nie można dołączać ani rozpoczynać nowych prób.

### 9.6. Klasyfikacja i szczegóły

-   Aktualna klasyfikacja jest widoczna w szczegółach eventu poza
    ekranem aktywnej próby.
-   Klasyfikacja aktualizuje się na bieżąco.
-   Nie pokazujemy szczegółowych statystyk ani lotek innych zawodników.
-   Publiczna klasyfikacja pokazuje końcowe wyniki uczestników i miejsce
    użytkownika.
-   Szczegóły własnej ukończonej próby są dostępne bezterminowo w
    historii Training.
-   Po ukończeniu użytkownik widzi własne statystyki: wynik, etapy,
    trafione/nietrafione, skuteczność, lotka po lotce, miejsce w
    klasyfikacji i ewentualne odznaczenia/medale.
-   Zakończone eventy i ich publiczna klasyfikacja pozostają dostępne
    dla wszystkich, również nieuczestników.
-   Nieuczestnicy nie mają dostępu do prywatnych danych uczestników.

### 9.7. Karty eventów

-   Użytkownik, który nie brał udziału: bez dodatkowego statusu
    uczestnictwa.
-   Użytkownik, który ukończył: status uczestnictwa i własny wynik.
-   Użytkownik, który przerwał: status **„Próba niezaliczona"** i
    informacja o wykorzystaniu próby.
-   Karta nie pokazuje szczegółowych statystyk.

### 9.8. Powiadomienie o rozpoczęciu eventu

W momencie rozpoczęcia eventu wysyłany jest jeden push: **„Wystartował
nowy event: \[nazwa\]"**.

-   Tylko użytkownikom, którzy wyrazili zgodę na powiadomienia push.
-   Wykorzystać istniejący mechanizm powiadomień aplikacji.
-   Nie tworzyć nowego systemu zgód.
-   Kliknięcie prowadzi do szczegółów eventu w Training.
-   Poza opisanymi wyjątkami Training nie wysyła innych pushy
    dotyczących zwykłego przebiegu treningu.

### 9.9. Klasyfikacja końcowa

-   Po zakończeniu eventu system automatycznie ustala i zamyka końcową
    klasyfikację.
-   Medale są przyznawane automatycznie.
-   Późniejsze zmiany klasyfikacji są możliwe wyłącznie wskutek
    dyskwalifikacji lub cofnięcia dyskwalifikacji przez administratora.
-   Każda taka operacja powoduje ponowne przeliczenie klasyfikacji i
    medali.

------------------------------------------------------------------------

## 10. Medale eventowe i Gablota

Medale eventowe są odrębne od odznak treningowych.

### 10.1. Przyznawanie medali

-   1.  miejsce --- złoty medal.
-   2.  miejsce --- srebrny medal.
-   3.  miejsce --- brązowy medal.
-   Medale otrzymują zawodnicy zajmujący miejsca medalowe.
-   Przy remisie zawodnicy z identycznym wynikiem i miejscem otrzymują
    ten sam medal.
-   W przypadku remisu liczba medali może przekroczyć trzy.
-   Kolejne miejsce jest odpowiednio przesuwane. Przykład: dwóch
    zawodników na 1. miejscu otrzymuje złote medale, kolejny zawodnik
    zajmuje 3. miejsce i otrzymuje brązowy medal; srebrny medal nie jest
    przyznawany.
-   Medale pojawiają się automatycznie po zakończeniu eventu i ustaleniu
    ostatecznej klasyfikacji.

### 10.2. Gdzie prezentować osiągnięcia

-   Istniejąca zakładka profilu **„Odznaczenia"** zachowuje swoje obecne
    przeznaczenie. Nie zmieniać jej działania.
-   Odznaki za aktywności Training są dostępne w sekcji Training.
-   Medale zdobyte w eventach Training trafiają do profilu zawodnika, do
    zakładki **„Gablota"**.
-   Gablota jest miejscem na trofea. W przyszłości może prezentować
    również puchary i inne wyjątkowe nagrody.
-   Każdy medal ma informację, za który event i kiedy został zdobyty.

### 10.3. Push o zdobyciu medalu

Po ustaleniu klasyfikacji i przyznaniu medalu zawodnik otrzymuje push,
np.: **„Otrzymałeś złoty medal w evencie \[nazwa eventu\]!"**

Analogicznie dla srebrnego i brązowego medalu.

-   Powiadomienie otrzymują tylko zdobywcy medali.
-   Wysyłka następuje po ustaleniu ostatecznej klasyfikacji.
-   Korzystać z istniejącego mechanizmu push i obowiązujących zgód.
-   Jeśli zawodnikowi odebrano medal wskutek dyskwalifikacji, nie
    wysyłać powiadomienia o odebraniu.
-   Jeśli dyskwalifikacja zostanie cofnięta i zawodnik odzyska medal,
    wysłać powiadomienie o ponownym przyznaniu.
-   Jeśli zmiana klasyfikacji po zakończeniu eventu daje zawodnikowi
    nowy lub wyższy medal, wysłać mu push o przyznanym medalu.
-   Zawodnik, któremu medal odebrano, nie otrzymuje powiadomienia o tej
    zmianie.

------------------------------------------------------------------------

## 11. Dyskwalifikacje --- panel administratora

W istniejącym panelu administratora aplikacji webowej należy dodać
narzędzia do zarządzania wynikami eventów Training.

### 11.1. Widok uczestników eventu

Administrator może wyświetlić tabelę uczestników konkretnego eventu, w
tym: - zawodnika; - wynik; - miejsce w klasyfikacji; - status
dyskwalifikacji; - odpowiednią akcję administracyjną.

### 11.2. Dyskwalifikacja i cofnięcie

-   Przy zawodniku niedyskwalifikowanym widoczny jest status uczestnika
    i przycisk **„Dyskwalifikuj"**.
-   Przy zawodniku zdyskwalifikowanym widoczny jest status
    **„Zdyskwalifikowany"** i przycisk **„Cofnij dyskwalifikację"**.
-   Obie operacje wymagają dodatkowego okna potwierdzenia.
-   Administrator może dyskwalifikować również po zakończeniu eventu.
-   Administrator może cofnąć dyskwalifikację.
-   Dyskwalifikacja dotyczy wyłącznie konkretnego eventu. Nie wpływa na
    wyniki, medale ani udział zawodnika w innych eventach.
-   Zdyskwalifikowany zawodnik jest wykluczany z oficjalnej klasyfikacji
    danego eventu.
-   Jego wynik nie jest uwzględniany przy przydziale miejsc medalowych.
-   Jeśli otrzymał medal, medal zostaje odebrany.
-   Klasyfikacja i medale zostają przeliczone.
-   Inni zawodnicy mogą awansować i uzyskać medale.
-   Cofnięcie dyskwalifikacji przywraca zawodnika do klasyfikacji tego
    eventu, po czym klasyfikacja i medale są przeliczane ponownie.
-   Nie tworzyć osobnej rozbudowanej historii zmian klasyfikacji ani
    widoku historii zmian miejsc/medali. Wystarczają mechanizmy
    potrzebne do obsługi operacji administracyjnych.

------------------------------------------------------------------------

## 12. Ogólne zasady sesji treningowych

Dotyczą planów, samodzielnych ćwiczeń i zwykłych wyzwań, o ile zasady
konkretnego wyzwania nie stanowią inaczej. Eventy mają odrębne reguły z
rozdziału 9.

### 12.1. Prowadzenie krok po kroku

-   Użytkownik jest prowadzony przez kolejne etapy.
-   Nie ma pełnej swobody nawigacji pomiędzy ćwiczeniami planu.
-   Użytkownik może pominąć etap/ćwiczenie po potwierdzeniu.
-   Status pominiętego etapu to **„Pominięty"**, a nie zero lub
    ukończenie.
-   Podsumowanie pokazuje pominięcia.

### 12.2. Zapisywanie i wznowienie

-   Postęp jest zapisywany na bieżąco.
-   Po ponownym wejściu użytkownik może wznowić sesję dokładnie w
    miejscu przerwania.
-   Zapis obejmuje lotki, wyniki, etap, ustawienia i stan timera.
-   Aktywne/przerwane sesje są chronione przed czyszczeniem lokalnego
    cache.

### 12.3. Pauza

Pauza jest dostępna przez cały czas w normalnych treningach: - podczas
ćwiczenia; - podczas wprowadzania lotek; - podczas przerw; - między
etapami planu; - w samodzielnych ćwiczeniach; - w zwykłych wyzwaniach
standardowych i specjalnych.

Pauza: - zatrzymuje zegar ćwiczenia lub przerwy; - zachowuje wyniki i
postęp; - pokazuje ekran pauzy; - pozwala wznowić dokładnie w tym samym
miejscu; - nie ma limitu długości; - może być używana wielokrotnie; -
nie jest pominięciem.

Na ekranie pauzy: - **„Wznów trening"**; - **„Zakończ trening"**.

Zakończenie wymaga potwierdzenia. Status sesji zmienia się na
**„Zakończony przed czasem"**. Wykonane dane zostają zachowane, reszta
jest oznaczona jako **„Nieukończone"**.

### 12.4. Wyjście z aktywnego treningu

-   Przy próbie opuszczenia ekranu aktywnego treningu pokazywane jest
    ostrzeżenie.
-   Użytkownik może pozostać w sesji albo wyjść.
-   Wyjście z ekranu nie kończy sesji. Sesja zostaje zapisana jako
    przerwana i może być wznowiona.
-   Eventy są wyjątkiem --- świadome wyjście z potwierdzeniem „Przerwij
    próbę" zużywa jedyną próbę.

### 12.5. Blokowanie równoległych sesji

-   Użytkownik może rozpocząć inny trening, jeśli ma inny rodzaj lub
    konfigurację.
-   Nie może rozpocząć drugiej sesji tego samego rodzaju i wariantu,
    jeśli poprzednia pozostaje aktywna, przerwana lub na pauzie.
-   Sesja ukończona lub świadomie zakończona przed czasem nie blokuje
    nowej sesji tej samej konfiguracji.
-   Sesja ukończona, oczekująca na synchronizację lub z błędem
    synchronizacji nie blokuje nowej sesji.
-   Blokują wyłącznie sesje, które nadal można wznowić/kontynuować.

### 12.6. Statusy

-   **Ukończony** --- wszystkie wymagane etapy ukończone.
-   **Zakończony przed czasem** --- użytkownik świadomie zamknął trening
    przed ukończeniem.
-   **Pominięty** --- etap pominięty przez użytkownika.
-   **Nieukończony** --- etap, którego nie wykonano przed zamknięciem
    sesji.

Nie traktować niewykonanych ćwiczeń jako wyniku zero.

### 12.7. Przerwy w planach

-   Podczas przerwy pokazywany jest czas do końca, nazwa i krótki opis
    następnego ćwiczenia.
-   Użytkownik może pominąć pozostały czas przerwy przez **„Rozpocznij
    następne ćwiczenie"**.
-   Po zakończeniu przerwy następne ćwiczenie może uruchomić się
    automatycznie.
-   Użytkownik może zatrzymać odliczanie/przedłużyć przerwę bez
    ograniczeń i sam rozpocząć następne ćwiczenie.
-   Nie zmieniać planowych czasów ćwiczeń.
-   Pominięcie przerwy nie oznacza pominięcia ćwiczenia.
-   Pominięcie ćwiczenia wymaga osobnej akcji i potwierdzenia.

### 12.8. Ekran przygotowania ćwiczenia w planie

Przed ćwiczeniem pokazać: - nazwę; - opis i cel; - zasady; -
punktację; - liczbę lotek lub czas; - parametry, jeśli są obsługiwane; -
CTA **„Rozpocznij ćwiczenie"**; - dostęp do pełnej instrukcji.

Po kliknięciu ćwiczenie rozpoczyna się natychmiast, bez odliczania.

------------------------------------------------------------------------

## 13. Rejestrowanie lotek i dane treningowe

### 13.1. Dane każdej lotki

W treningach i wyzwaniach rejestrować: - zawodnika; - sesję; - ćwiczenie
lub etap; - kolejność lotki; - zamierzony cel; - faktyczne trafienie; -
przyznane punkty.

Nie zbierać czasu każdej pojedynczej lotki.

### 13.2. Cel a faktyczne trafienie

-   Rejestrować zamierzony cel oraz faktyczne trafienie.
-   Jeśli lotka nie trafi w zamierzony cel, traktować ją jako
    nietrafioną w cel.
-   Nie analizować dokładnego miejsca pomyłki, np. sąsiedniego sektora,
    jako osobnej kategorii błędu w MVP.
-   Rozkład faktycznych trafień może prezentować udział np. S20, D20,
    T20 i pozostałych trafień.
-   Sukces zależy od zasad ćwiczenia. Trafienie w double może być błędem
    w ćwiczeniu na single, a wartościowym wynikiem w ćwiczeniu
    punktowym.
-   Nie używać ogólnych słownych ocen zawodnika typu „początkujący",
    „średni" czy „ekspert" jako oceny formy. Prezentować liczby i
    statystyki.

Nie rozszerzać szczegółowego rejestrowania lotek na Wolną grę. Jej
obecny sposób działania pozostaje bez zmian.

------------------------------------------------------------------------

## 14. Analiza zawodnika

Analiza ma pięć zakładek:

1.  Podsumowanie.
2.  Scoring.
3.  Doubles.
4.  Checkouty.
5.  Wyzwania.

### 14.1. Okres analizy

Dostępne są dwa gotowe okresy: - ostatnie 7 dni --- porównanie z
poprzednimi 7 dniami; - ostatnie 30 dni --- domyślnie, porównanie z
poprzednimi 30 dniami.

Nie dodawać wyboru dwóch dowolnych okresów ani własnego zakresu w
analizie zawodnika. Filtry zakresu dat w Historii są odrębną funkcją.

### 14.2. Podsumowanie

Pokazuje: - liczbę sesji; - czas treningu; - liczbę lotek; - ogólną
skuteczność, ale tylko na podstawie ćwiczeń jednoznacznie definiujących
trafienie w cel; - ostatnie rekordy; - zmiany liczbowe względem
poprzedniego analogicznego okresu.

Zmiany skuteczności prezentować w punktach procentowych (pp).

Brak aktywności lub danych przedstawiać odpowiednim komunikatem, nie
sztucznymi zerami. Nie tworzyć sztucznej oceny „forma 82/100".

### 14.3. Scoring

-   średnia punktowa na lotkę i kolejkę (3 lotki);
-   skuteczność S1--S20, D1--D20, T1--T20 oraz Bull z rozróżnieniem
    25/50;
-   mapa tarczy z polami i procentami;
-   skuteczność pola liczona tylko wtedy, gdy było zamierzonym celem;
-   najlepszy wynik punktowy w jednej kolejce;
-   rozkład wyników kolejek w przedziałach co 10: 1--10, 11--20, 21--30
    itd. do 171--179, a 180 osobno;
-   porównanie z poprzednim analogicznym okresem.

### 14.4. Doubles

-   skuteczność każdego D1--D20 i Bull;
-   łączna skuteczność doubles;
-   liczba prób i trafień dla każdego pola;
-   najlepsze wyniki ćwiczeń doubles;
-   porównanie z poprzednim okresem;
-   najlepszy i najsłabszy double z uwzględnieniem liczby prób i
    trafień.

Do zestawienia najlepszego/najsłabszego pola wymagane jest minimum **20
prób na danym polu**. Pola poniżej progu nadal mogą być widoczne w
szczegółowych statystykach, ale nie biorą udziału w tym porównaniu.

### 14.5. Checkouty

-   ogólna skuteczność;
-   liczba udanych i nieudanych prób;
-   skuteczność w przedziałach: 41--60, 61--80, 81--100, 101--120, 121+;
-   najwyższy zamknięty checkout;
-   najlepszy i najsłabszy przedział skuteczności;
-   porównanie z poprzednim okresem.

### 14.6. Wyzwania

-   liczba wyzwań w okresie;
-   najlepszy wynik w każdym rodzaju;
-   ostatnie wyniki;
-   rekordy osobiste;
-   porównanie z poprzednim okresem;
-   historia JDC z trzema etapami: Shanghai 10--15, Double 1--20 + Bull,
    Shanghai 15--20.
-   wyłącznie własne osiągnięcia, bez rankingów innych graczy.

### 14.7. Brak wystarczających danych

Jeśli zawodnik nie ma wystarczającej liczby treningów lub prób: -
pokazać prosty komunikat, że potrzeba więcej danych; - nie pokazywać
pustych wykresów ani sztucznych zer; - automatycznie uzupełnić analizę,
gdy danych będzie wystarczająco dużo.

------------------------------------------------------------------------

## 15. Rekordy osobiste

Osobna sekcja **„Moje rekordy"** na ekranie głównym Training. Pokazuje
aktualne rekordy z pięciu kategorii, daty, historię poprawiania i
różnicę względem poprzedniego rekordu. Rekordy są również dostępne w
analizie.

### 15.1. Kategorie

**Scoring** - maksymalna liczba punktów w ćwiczeniu; - najwyższa średnia
na kolejkę. - Nie tworzyć osobnego rekordu średniej na lotkę.

**Doubles** - najwyższa skuteczność doubles; - najwięcej trafionych
doubles w określonym ćwiczeniu; - najlepszy Bob's 27; - najwyższy wynik
punktowy, jeśli ćwiczenie jest punktowane.

**Checkouty** - najwyższy zamknięty checkout; - najwięcej udanych
checkoutów w sesji; - najwyższa skuteczność w przedziale; - najwyższy
wynik punktowy, jeśli ćwiczenie jest punktowane.

**Wyzwania** - najlepszy wynik w każdym rodzaju wyzwania; - bez osobnych
rekordów etapów JDC.

**Ćwiczenia** - najlepszy rezultat w ćwiczeniu; - najwyższa
skuteczność; - najlepszy wynik punktowy.

### 15.2. Wspólne zasady rekordów

-   Porównywać tylko to samo ćwiczenie, wariant i zasady punktacji.
-   Rekordy skuteczności wymagają minimum **20 prób**.
-   Wyniki poniżej progu mogą być prezentowane w statystykach, ale nie
    są uznawane za rekord skuteczności.
-   Jeśli ćwiczenie jest punktowane, zapisywać najlepszą punktację.
-   Rekordy przechowują datę oraz wskazanie treningu/ćwiczenia/wyzwania.
-   Historia poprawiania rekordu jest przechowywana bezterminowo.

------------------------------------------------------------------------

## 16. Cele treningowe

Osobna sekcja **„Moje cele"**.

-   Cele są jednakowe dla wszystkich użytkowników.
-   Są progresywne, z rosnącymi progami.
-   Osiągnięte progi pozostają zapisane.
-   Progi nigdy nie spadają.
-   Nie przyznawać odznak za cele.
-   Cele są długoterminowe --- bez tygodniowych i okresowych celów.
-   Zestaw ma być niewielki i prosty, a nie składać się z
    kilkudziesięciu celów lub osobnego celu dla każdego pola.
-   Cele powinny dotyczyć rozwoju umiejętności, nie tylko liczby sesji.

Obszary: 1. **Scoring:** średnia na kolejkę, wyniki 100+, 140+, 180. 2.
**Doubles:** skuteczność poszczególnych pól i łączna skuteczność. 3.
**Checkouty:** skuteczność w przedziałach. 4.
**Regularność/powtarzalność:** utrzymanie poziomu w kolejnych
treningach. 5. **Wyzwania:** wyniki.

Dokładne progi i lista konkretnych celów wymagają osobnego
zaprojektowania.

------------------------------------------------------------------------

## 17. Odznaki treningowe

Ogólny system odznaczeń aplikacji już istnieje. Training nie może
kopiować ogólnych osiągnięć, np. 180 czy checkout 100+.

Odznaki Training są wyłącznie za dokonania specyficzne dla Training.

### 17.1. Odznaki sekcyjne

-   Jedna odznaka dla Scoring.
-   Jedna odznaka dla Doubles.
-   Jedna odznaka dla Checkoutów.
-   Każda ma poziomy: **Żelazo, Brąz, Złoto, Platyna, Diament**.
-   Awans wymaga jednoczesnego spełnienia kilku warunków.
-   Poziom jest trwały i nie spada.
-   Konkretne progi i warunki wymagają zaprojektowania.

### 17.2. Odznaki za wyzwania standardowe

-   Każde wyzwanie może mieć własną odznakę z pięcioma poziomami:
    Żelazo, Brąz, Złoto, Platyna, Diament.
-   Poziom zależy od średniego wyniku z ostatnich pięciu ukończonych
    prób.
-   Nieukończone, przerwane i anulowane próby nie liczą się.
-   Przed pięcioma ukończonymi próbami użytkownik nie ma poziomu.
-   Po piątej ukończonej próbie obliczana jest średnia i przyznawany
    poziom.
-   Każda kolejna ukończona próba aktualizuje średnią z ostatnich
    pięciu.
-   Poziom może wzrosnąć lub spaść.

### 17.3. Odznaki za wyzwania specjalne

-   Każde wyzwanie specjalne może mieć odznakę z pięcioma poziomami:
    Żelazo, Brąz, Złoto, Platyna, Diament.
-   Poziom może być przyznany na podstawie pojedynczego ukończonego
    wyniku.
-   Nie wymaga się pięciu podejść.
-   Ponowne ukończenie z lepszym wynikiem może dać wyższy poziom.
-   Zdobyty poziom jest trwały i nigdy nie spada.
-   System zachowuje informację o zdobytych poziomach i dacie awansu.

### 17.4. Prezentacja i komunikaty

-   Pokazywać aktualny poziom, warunki kolejnego poziomu i postęp.
-   W odznakach wyzwań standardowych pokazywać średnią z ostatnich
    pięciu ukończonych prób.
-   Komunikaty o zdobyciu/awansie/spadku odznaki, nowym rekordzie i
    osiągniętym celu pojawiają się na jednym ekranie podsumowania sesji.
-   Sekcja osiągnięć w podsumowaniu występuje tylko, jeśli pojawiły się
    nowe osiągnięcia.
-   Nie tworzyć osobnej historii powiadomień o osiągnięciach.
-   Nie dodawać opcji wyłączania tych komunikatów.
-   Odznaki Training są widoczne w sekcji Training, nie w istniejącej
    zakładce profilu „Odznaczenia".

### 17.5. Medale eventowe nie są odznakami

Medale eventowe są opisane w rozdziale 10. Nie stosować dla nich systemu
pięciu poziomów odznak.

------------------------------------------------------------------------

## 18. Historia treningów

Jedna historia aktywności w Training, obejmująca: - plany; - samodzielne
ćwiczenia; - wyzwania; - eventy i konkursy; - Wolną grę.

### 18.1. Lista i filtry

-   Jedna lista aktywności.
-   Filtry rodzaju: Plany, Samodzielne ćwiczenia, Wyzwania, Wolna gra.
    Eventy mogą być uwzględnione w grupie Wyzwania albo jako
    wyodrębniony typ, jeśli wymaga tego czytelność --- nie zmienia to
    ich odrębnych reguł historii.
-   Sortowanie domyślnie od najnowszych, opcjonalnie od najstarszych.
-   Filtry dat: 7 dni, 30 dni, 90 dni i własny zakres.
-   Wyszukiwanie po nazwach planów, wariantów, ćwiczeń i wyzwań.
-   Nie dodawać wyszukiwania sesji Wolnej gry po nazwie trybu.
    Użytkownik przegląda je przez wybór filtra Wolna gra.
-   Nie dodawać filtrowania po statusie.

### 18.2. Wpis historii

Wpis może zawierać: - datę; - nazwę aktywności; - wariant, jeśli
dotyczy; - czas trwania; - status; - najważniejszy wynik.

### 18.3. Szczegóły sesji

-   Szczegóły treningów i wyzwań pokazują statystyki oraz przebieg lotka
    po lotce.
-   Zakończone sesje są tylko do odczytu.
-   Eventy: uczestnik ma dostęp do szczegółów własnej próby
    bezterminowo; nieuczestnicy widzą tylko publiczne szczegóły eventu i
    klasyfikację, nie prywatne dane uczestników.
-   Wolna gra: szczegóły pokazują dostępne dane zapisywane obecnie przez
    ten tryb. Dla sesji wieloosobowej pokazać uczestników, wynik końcowy
    każdego gracza, zwycięzcę jeśli wynika z trybu i inne dostępne
    obecnie statystyki. Nie wprowadzać szczegółowego zapisu każdej
    lotki.
-   Historia Wolnej gry korzysta z tych samych zasad retencji co
    pozostałe dane Training.

### 18.4. Szczególne zasady widoczności prób

-   Historia zwykłych wyzwań standardowych i specjalnych pokazuje
    wyłącznie ukończone próby.
-   Anulowane i nieukończone próby zwykłych wyzwań nie pojawiają się na
    liście historii.
-   Dane przerwanej próby mogą pozostać tymczasowo zapisane, aby
    umożliwić wznowienie.
-   Eventy mają odrębny status wykorzystanej/niezaliczonej próby,
    opisany w rozdziale 9.
-   Plany i samodzielne ćwiczenia mogą mieć wpisy zakończone przed
    czasem zgodnie z ogólnymi statusami sesji.

### 18.5. Brak usuwania i notatek

-   Użytkownik nie może usuwać pojedynczych sesji z historii.
-   Nie dodawać notatek użytkownika do zakończonych sesji.
-   Historia jest obiektywnym zapisem aktywności i wyników.
-   Rekordy, cele i analizy korzystają ze spójnych, zachowanych danych.

------------------------------------------------------------------------

## 19. Offline-first, synchronizacja i retencja

### 19.1. Model koncepcyjny

Trzy warstwy odpowiedzialności:

1.  **Core aplikacji:** wykonywanie treningów, obliczanie
    wyników/punktacji, obsługa lotek, pauz, wznowień i lokalnego zapisu.
2.  **Backend:** centralne źródło prawdy dla definicji ćwiczeń, planów,
    wariantów, wyzwań, konfiguracji, historii i analityki.
3.  **Pamięć lokalna:** kopia danych niezbędnych do przeprowadzenia
    treningu bez internetu.

To opis odpowiedzialności produktowych, nie nakaz konkretnej
architektury technicznej.

### 19.2. Definicje i cache

-   Definicje, zasady, punktacja, plany, warianty i wyzwania są
    cache'owane lokalnie.
-   Nie pobierać definicji przed każdym treningiem.
-   Użytkownik może rozpocząć offline dowolne ćwiczenie/plan/wariant,
    którego definicja jest już zapisana lokalnie.
-   Nie musi wcześniej ręcznie otwierać danego ćwiczenia.
-   Każda definicja ma wersję.
-   Rozpoczęta sesja zachowuje wersję zasad, na której wystartowała.
-   Aktualizacje w tle nie zmieniają aktywnych sesji.

### 19.3. Działanie offline

-   Pierwsze logowanie wymaga internetu.
-   Po zalogowaniu i pobraniu danych Training działa offline.
-   Nie jest wymagany internet do rozpoczęcia, przeprowadzenia i
    zakończenia wcześniej pobranego treningu.
-   Silnik działa lokalnie.
-   Lotki, punkty, warunki ćwiczenia, pauza i postęp są zapisywane
    lokalnie.
-   Sesje są zapisywane najpierw lokalnie, a po odzyskaniu internetu
    synchronizowane automatycznie.
-   Utrata internetu podczas aktywnego treningu nie przerywa go, nie
    wymaga ponownego logowania i nie pokazuje blokującego komunikatu.
-   Zamknięcie aplikacji bez internetu nie może uniemożliwić wznowienia
    sesji offline.
-   Restart telefonu również nie może utracić postępu --- wymagany
    trwały zapis, nie tylko pamięć RAM.
-   Eventy z jedną oficjalną próbą i publiczną klasyfikacją mogą wymagać
    wcześniejszej autoryzacji online. Nie obiecywać pełnej obsługi
    offline, jeśli nie można zagwarantować uczciwości wyniku.

### 19.4. Synchronizacja

-   Automatyczna po odzyskaniu internetu.
-   Sesja pozostaje lokalnie do momentu potwierdzenia zapisu po stronie
    serwera.
-   Błędy synchronizacji nie blokują kolejnych treningów.
-   System automatycznie ponawia synchronizację.
-   Użytkownik może zobaczyć dyskretną informację o oczekującej
    synchronizacji lub problemie.
-   Dane lokalne można usunąć dopiero po potwierdzeniu serwera.
-   Niezsynchronizowane sesje nigdy nie są automatycznie usuwane.
-   Ukończona sesja nie blokuje nowego treningu tej samej konfiguracji,
    nawet jeśli oczekuje na synchronizację.

### 19.5. Wiele urządzeń i przejęcie sesji

-   Każda sesja jest niezależnym rekordem przypisanym zawodnikowi i
    urządzeniu.
-   Sesje z różnych urządzeń nie nadpisują się.
-   Poprawnie zsynchronizowane sesje tworzą wspólną historię Training.
-   Należy chronić przed wielokrotnym zapisaniem tej samej sesji.
-   Niezsynchronizowane dane pozostają bezpieczne na urządzeniu
    źródłowym.
-   Obowiązuje jedna aktywna instancja danej sesji na jednym urządzeniu.
-   Użytkownik może wznowić przerwany trening na innym urządzeniu, ale
    przejęcie wymaga połączenia z backendem, aby uzyskać prawo
    prowadzenia sesji i uniemożliwić dalsze zapisywanie z poprzedniego
    urządzenia.
-   Lokalna kopia na pierwszym urządzeniu nie jest usuwana.
-   Nie scalać dwóch równoległych przebiegów.
-   Drugie urządzenie offline nie może przejąć sesji z innego
    urządzenia, ponieważ nie może sprawdzić, czy pierwsze urządzenie
    nadal jej nie kontynuuje.

### 19.6. Limity pamięci i retencja

-   Maksymalny lokalny cache Training: **150 MB**.
-   Ostatnie 30 dni treningów: szczegółowe dane wszystkich sesji
    lokalnie.
-   Starsze szczegóły mogą być stopniowo usuwane z telefonu; podstawowa
    historia i podsumowania pozostają lokalnie w zakresie ustalonego
    limitu.
-   Starsze szczegóły można pobrać z backendu, gdy internet jest
    dostępny.
-   Szczegółowe dane każdej lotki są przechowywane bezterminowo na
    backendzie.
-   Wyniki sesji, rekordy i agregaty są przechowywane bezterminowo.
-   Nie kasować historii po roku lub dwóch.
-   Archiwizacja może przenosić dane do tańszego magazynu bez utraty
    możliwości analizy.
-   Niezsynchronizowane sesje i aktywne/przerwane treningi są chronione
    przed czyszczeniem.
-   Gdy limit lokalny zostanie osiągnięty, usuwać najstarsze
    zsynchronizowane szczegółowe dane w partiach, aby odzyskać zapas.
-   Nie usuwać definicji ćwiczeń i planów jako pierwszych danych.
-   Wolna gra jest objęta tymi samymi zasadami retencji Training.

### 19.7. Powiadomienia

-   Zwykłe komunikaty o przebiegu treningu, ukończeniu etapów,
    osiągnięciach, synchronizacji, ostrzeżeniach i potwierdzeniach są
    wewnątrz Training.
-   Training nie wysyła pushy z przypomnieniami ani zwykłymi
    komunikatami o treningach.
-   Wyjątki push:
    1.  rozpoczęcie eventu;
    2.  zdobycie medalu lub uzyskanie nowego/wyższego medalu wskutek
        zmiany klasyfikacji.
-   Wszystkie push korzystają z istniejącego mechanizmu i zgód
    użytkownika.

------------------------------------------------------------------------

## 20. Podsumowania sesji

Po ukończeniu treningu użytkownik widzi krótkie podsumowanie.

### 20.1. Zawartość

-   status sesji;
-   czas treningu;
-   liczba wykonanych lotek;
-   najważniejsze wyniki/statystyki;
-   nowe rekordy;
-   osiągnięte cele;
-   zdobycie, awans lub spadek poziomu odznaki, jeśli wystąpił;
-   lista ćwiczeń ze statusami: ukończone, pominięte, nieukończone.

Sekcja osiągnięć pojawia się tylko wtedy, gdy są nowe osiągnięcia.

### 20.2. Szczegóły

-   Główne podsumowanie ma być zwięzłe.
-   Szczegółowe statystyki, wyniki lotka po lotce i pełny przebieg
    dostępne są w rozwijanej sekcji **„Szczegóły treningu"**.
-   Bez subiektywnej oceny, ankiet i notatek.

### 20.3. Nawigacja po zakończeniu

-   Główny przycisk: **„Wróć do Training"**.
-   Dodatkowy przycisk: **„Powtórz trening"**, jeśli dany rodzaj
    aktywności pozwala na ponowne podejście.
-   Powtórzenie tworzy nową, niezależną sesję/próbę.
-   Poprzednia historia pozostaje zachowana.
-   Zakończoną sesję można otworzyć z historii wyłącznie do odczytu.

------------------------------------------------------------------------

## 21. Panel administratora --- zarządzanie Training

W istniejącym panelu administratora aplikacji webowej należy dodać
funkcje dotyczące wyłącznie Twenty Six Training. Nie przebudowywać
pozostałych narzędzi panelu.

### 21.1. Zarządzanie treścią

Administrator może bez aktualizacji aplikacji: - dodawać i edytować
plany treningowe; - dodawać i edytować warianty planów; - dodawać i
edytować ćwiczenia; - definiować i aktualizować zasady, punktację i
parametry ćwiczeń; - dodawać i edytować wyzwania standardowe i
specjalne; - zarządzać opisami, instrukcjami, konfiguracją i pozostałymi
danymi treści Training.

### 21.2. Zarządzanie eventami

Administrator może: - tworzyć eventy i konkursy Training; - ustalać datę
i godzinę rozpoczęcia oraz zakończenia; - definiować zasady, etapy,
punktację i warunki uczestnictwa; - edytować konfigurację eventu; -
uruchamiać i kończyć eventy; - przeglądać klasyfikację; -
dyskwalifikować zawodników i cofać dyskwalifikacje.

### 21.3. Wersje robocze i publikacja

-   Nowe plany, warianty, ćwiczenia, wyzwania i eventy mogą być
    przygotowane jako wersje robocze.
-   Administrator może je edytować i sprawdzać przed publikacją.
-   Tylko opublikowane elementy są widoczne dla użytkowników Training.
-   Administrator może wycofać opublikowaną treść z dostępności.
-   Po edycji i ponownej publikacji nowe sesje korzystają z najnowszej
    wersji.
-   Aktywne sesje zachowują wersję, na której zostały rozpoczęte.
-   Ukończone sesje zawsze odwołują się do pierwotnej wersji zasad.
-   Aktualizacje nie zmieniają wstecznie wyników ani rekordów.

### 21.4. Czasowe wyłączanie treści

Administrator może czasowo wyłączyć: - ćwiczenie; - plan; - wariant
planu; - wyzwanie.

Wyłączenie: - blokuje rozpoczynanie nowych sesji z wykorzystaniem tej
treści; - nie usuwa definicji ani historii wyników; - może zostać
cofnięte przez ponowną aktywację; - nie zmienia zasad wcześniej
rozpoczętych sesji.

### 21.5. Ograniczenie zakresu panelu

Wszystkie powyższe funkcje mają być dodatkiem w istniejącym panelu
administratora, ograniczonym do Training. Nie zmieniać istniejących
funkcji administracyjnych niezwiązanych z Training.

------------------------------------------------------------------------

## 22. Skalowalność, wydajność i trwałość danych

Projekt danych Training musi uwzględniać dużą skalę: wielu użytkowników,
dużą liczbę sesji i potencjalnie miliony lub miliardy rekordów
pojedynczych lotek.

Wymagania produktowo-danych: - trwałe przechowywanie szczegółowych
danych lotek; - wydajne odczyty historii użytkownika; - wydajne
obliczanie analityki i rekordów; - izolacja danych użytkowników; -
odporność na duplikaty synchronizacji; - możliwość archiwizacji bez
utraty historii i analityki; - obsługa wersji definicji ćwiczeń i
planów; - indeksowanie danych pod typowe zapytania po użytkowniku,
sesji, dacie, ćwiczeniu, etapie i celu; - unikanie pełnych skanów danych
przy standardowych widokach historii i analityki; - stosowanie
agregatów/podsumowań tam, gdzie poprawia to wydajność, przy zachowaniu
możliwości odtworzenia szczegółów; - oddzielenie danych Training od
danych innych sekcji tam, gdzie wymaga tego istniejąca architektura.

Nie narzucać konkretnej bazy, technologii ani dostawcy. Cursor ma
przeanalizować istniejący backend i zaproponować rozwiązanie zgodne z
projektem, spełniające powyższe wymagania.

------------------------------------------------------------------------

## 23. Treści i decyzje wymagające doprecyzowania

Poniższe punkty nie są zatwierdzonymi regułami. Nie wolno zgadywać ich
podczas implementacji.

### 23.1. JDC Challenge --- zasady Bull

Do potwierdzenia na podstawie aktualnej oficjalnej karty zasad: - czy
Bull daje 50 punktów podstawowych i dodatkowe 50 bonusu; - czy outer
bull zalicza się do celu.

Do czasu potwierdzenia nie wdrażać niezweryfikowanej interpretacji jako
oficjalnej.

### 23.2. Progi odznak

Do zaprojektowania: - konkretne warunki i progi pięciu poziomów odznak
Scoring, Doubles i Checkouty; - konkretne progi wyników dla poziomów
odznak wyzwań standardowych i specjalnych.

Mechanizmy przyznawania są zatwierdzone, ale wartości progowe nie.

### 23.3. Cele treningowe

Do zaprojektowania: - niewielka lista konkretnych celów; - progi
progresji dla poszczególnych celów; - zasady mierzenia
regularności/powtarzalności.

Nie tworzyć dużego zestawu celów bez wcześniejszego zatwierdzenia.

### 23.4. Progi i zasady rekordów

-   Minimum 20 prób dla rekordów skuteczności i porównań
    najlepszego/najsłabszego double jest zatwierdzone.
-   Szczegółowe definicje „próby" dla poszczególnych typów ćwiczeń
    należy wyprowadzić z ich zasad i potwierdzić, jeśli nie są
    jednoznaczne.

### 23.5. Przykładowe plany treningowe

Istnieją robocze koncepcje planu 30-minutowego i godzinnego, ale
zawierają niejasności dotyczące ćwiczeń, czasów i zasad. Nie traktować
ich jako gotowych, zatwierdzonych definicji do wdrożenia. W
szczególności: - plan określany roboczo jako „30 Minutowa rozgrzewka
Twenty Six" ma niespójny czas całkowity (bloki dają 33 minuty) i
niejasne elementy „9 lotek" oraz wariantów treningu głównego; - plan
godzinny ma sumę około 63 minut i niejasności dotyczące m.in. „Walka o
licznik" oraz szczegółów kilku ćwiczeń. Nie zmieniać samowolnie czasów
ani zasad. Przed dodaniem tych planów do katalogu trzeba potwierdzić ich
finalną zawartość.

### 23.6. Publikacja wyników zwykłych wyzwań

Wyniki zwykłych wyzwań są domyślnie prywatne, a użytkownik może
dobrowolnie opublikować konkretny ukończony wynik. Szczegółowy zakres
widoczności, sposób publikacji i docelowe miejsce prezentacji należy
doprecyzować, jeśli funkcja ma być realizowana w MVP.

### 23.7. Cele i własne plany użytkownika

Własne plany użytkownika są kierunkiem przyszłego rozwoju, nie
zatwierdzonym wymaganiem MVP. Nie dodawać ich bez osobnej decyzji.

------------------------------------------------------------------------

## 24. Kryteria ogólnej zgodności produktu

Implementacja Twenty Six Training powinna spełniać poniższe warunki:

1.  Użytkownik może szybko rozpocząć plan, samodzielne ćwiczenie lub
    wyzwanie.
2.  Katalogi szybkiego startu i pełne katalogi korzystają ze wspólnych
    definicji.
3.  Plany, ćwiczenia i zwykłe wyzwania prowadzą użytkownika krok po
    kroku.
4.  Każda lotka w treningach i wyzwaniach jest rejestrowana z
    zamierzonym celem i faktycznym trafieniem.
5.  Wyniki są obliczane zgodnie z wersją zasad przypisaną do sesji.
6.  Pauza, wznowienie, cofnięcie do pięciu ostatnich lotek i świadome
    zakończenie działają zgodnie z opisanymi regułami.
7.  Aktywna sesja może być wznowiona po zamknięciu aplikacji lub
    restarcie urządzenia.
8.  Offline nie blokuje wcześniej przygotowanych treningów; dane
    synchronizują się automatycznie.
9.  Sesje i historia z różnych urządzeń nie nadpisują się ani nie
    dublują.
10. Historia obejmuje aktywności Training, w tym Wolną grę, bez zmiany
    jej istniejącego sposobu rozgrywania.
11. Analityka, rekordy i cele opierają się na wiarygodnych danych i
    właściwych progach.
12. Zwykłe wyzwania pokazują w historii tylko ukończone próby.
13. Eventy respektują jedną oficjalną próbę, zasady przerwania i
    odzyskiwania po awarii.
14. Medale są przyznawane po ustaleniu klasyfikacji, a remisy
    obsługiwane zgodnie z zasadą wspólnego miejsca.
15. Administrator może zarządzać treścią Training i eventami, publikować
    wersje oraz czasowo wyłączać treści.
16. Dyskwalifikacja dotyczy tylko jednego eventu, może być cofnięta i
    powoduje ponowne przeliczenie klasyfikacji/medali.
17. Szczegółowe dane pozostają trwale zachowane na backendzie, a lokalny
    cache nie przekracza 150 MB.
18. Żadna implementacja nie zmienia funkcjonowania sekcji aplikacji poza
    Twenty Six Training.

------------------------------------------------------------------------

## 25. Instrukcja dla Cursora

Przy każdej pracy nad Twenty Six Training:

1.  Najpierw przeczytaj ten dokument w całości lub odpowiednie
    rozdziały.
2.  Przeanalizuj istniejące repozytorium i zidentyfikuj obecne
    komponenty, przepływy, modele i mechanizmy, które można bezpiecznie
    wykorzystać w Training.
3.  Przed zmianami wskaż, które elementy repozytorium będą dotknięte i
    jak zagwarantujesz, że zakres pozostanie ograniczony do Training.
4.  Nie zmieniaj istniejącego działania innych sekcji aplikacji.
5.  Nie implementuj niezatwierdzonych zasad z rozdziału 23.
6.  Jeśli wymaganie jest niejasne lub istnieje konflikt, zatrzymaj się i
    poproś o decyzję zamiast zgadywać.
7.  Zachowuj zgodność z istniejącym stylem UI i architekturą projektu,
    ale nie przenoś zmian na inne obszary.
8.  Po zakończeniu zadania podsumuj wykonane zmiany, wskazując
    pliki/obszary repozytorium i potwierdzając, że nie rozszerzono
    zakresu poza Training.

**Najważniejsza zasada:** ten dokument jest specyfikacją produktową
Twenty Six Training. Nie jest zgodą na przebudowę pozostałych części
aplikacji.
