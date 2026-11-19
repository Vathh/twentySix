# Twenty Six Training — dokument roboczy sektora

**Status:** ustalenia do realizacji  
**Zakres:** sekcja treningowa aplikacji mobilnej oraz narzędzia Training w istniejącym panelu administratora  
**Nie zastępuje** plików źródłowych `TWENTY_SIX_TRAINING_PRODUCT_SPEC.md` i `CWICZENIA.md`. Tam, gdzie te pliki mówią co innego niż późniejsze ustalenia wpisane tutaj, przy realizacji obowiązuje ten dokument.

Cursor nie projektuje samodzielnie ekranów pojedynczego ćwiczenia ani szczegółowego przebiegu całego planu. Ogólne zasady z tego dokumentu są zatwierdzone. Wygląd konkretnego ćwiczenia i przejścia wewnątrz planu uzgadniamy z właścicielem, zanim powstanie dany ekran.

---

## 1. Gdzie to żyje

Sekcja dla zawodnika jest tylko w aplikacji mobilnej.

Panel administratora Training jest w istniejącej aplikacji webowej, w panelu właściciela (`users.role = admin`). Nie zmieniamy blokowania kont, ról ani dotychczasowych statystyk kont. Dokładamy narzędzia Training obok nich.

Nie ruszamy silnika meczowego (`useGameScoring`, turniej, liga, szybka gra online). Wolna gra zostaje przy swoim obecnym silniku. Nowe treningi, plany, wyzwania i eventy mają własny silnik w aplikacji mobilnej.

Gość nie widzi kafelka Trening. Na ekranie Graj ma od razu przycisk Wolnej gry. Reszta Training wymaga zalogowania.

---

## 2. Ekran główny Trening

Kolejność:

1. Rozpocznij trening
2. Plany
3. Wyzwania
4. Biblioteka ćwiczeń
5. Wolna gra
6. Analiza zawodnika
7. Moje rekordy
8. Moje cele
9. Ulubione
10. Moje treningi

Ekran jest minimalistyczny. Nie ma na nim ostatnich treningów ani statystyk.

Jeśli na tym telefonie jest niezakończony plan, widać subtelny komunikat „Wznów trening” i przycisk „Anuluj trening”. Anulowanie nie pyta drugi raz. Kasuje lokalny stan tej sesji.

Na jednym telefonie jest jedna wisząca sesja. Wisząca sesja to plan. Pojedyncze ćwiczenie, wyzwanie i event po przerwaniu nie zostają do wznowienia.

**Rozpocznij trening** prowadzi do: planu, pojedynczego ćwiczenia, wyzwania, ulubionych, ostatnio używanych.

Ostatnio używane:

- najwyżej trzy różne pozycje,
- tylko na tym telefonie, nie na koncie,
- plany, samodzielne ćwiczenia i wyzwania,
- lista pojawia się po rozpoczęciu przynajmniej jednej sesji,
- najnowsza pozycja jest pierwsza, ponowienie tej samej przesuwa ją na początek,
- wybranie otwiera szczegóły albo przygotowanie, nie startuje sesji.

Użytkownik nie układa własnych planów ani zestawów ćwiczeń.

---

## 3. Wolna gra

Osobny tryb, 1–8 graczy, dotychczasowy przebieg i dotychczasowy sposób wpisywania wyników.

Wchodzi do historii „Moje treningi” jako rodzaj Wolna gra. Szczegóły biorą się z danych, które Wolna gra już zapisuje: uczestnicy, wynik, zwycięzca, format. Bez dziennika lotek i bez zmiany jej silnika.

Nie wlicza się do:

- statystyk treningowych,
- rekordów,
- celów,
- odznak,
- licznika ukończonych ćwiczeń.

Gość gra w Wolną grę lokalnie, bez historii na koncie.

---

## 4. Ulubione

Jedna sekcja z ekranu głównego. Trzy zakładki, w tej kolejności, domyślnie Wyzwania:

1. Wyzwania
2. Plany
3. Ćwiczenia

Aplikacja nie pamięta ostatniej zakładki.

Użytkownik dodaje, usuwa i zmienia kolejność przeciąganiem, osobno w każdej zakładce. Kolejność i skład synchronizują się z kontem. Gwiazdka jest na kafelkach planów oraz na listach i ekranach szczegółów.

Komunikaty: „Dodano do Ulubionych”, „Usunięto z Ulubionych”.

Nie ma wyszukiwarki, zmiany widoku ani „Wyczyść wszystkie”.

Nieaktywny plan lub wyzwanie zostaje w ulubionych z oznaczeniem „Nieaktywne”. Nie da się go uruchomić ani dodać do ulubionych, dopóki jest nieaktywne. Anulowany plan i anulowane wyzwanie znikają z ulubionych wszystkich użytkowników. Ćwiczenie zostaje, bo lista ćwiczeń jest stała.

Eventy nie wchodzą do ulubionych.

Pozycję można dodać bez wcześniejszego rozegrania.

---

## 5. Biblioteka ćwiczeń

Biblioteka i szybki start korzystają z tej samej, stałej listy ćwiczeń. Ta lista jest całym katalogiem sposobów rozgrywki. Administrator jej nie zakłada, nie nazywa po swojemu i nie wyłącza.

Każde ćwiczenie ma jedną zmienną. Albo czas, albo liczbę lotek. Druga zostaje pusta. Wartości w punktach 5.1–5.15 są domyślne: tak ćwiczenie startuje z biblioteki i z szybkiego startu.

W planie administrator bierze ćwiczenie z tej listy i może zmienić tylko tę zmienną, w tym jednym miejscu planu. To samo ćwiczenie może wejść drugi raz, z inną wartością. Zasady rzutów zostają przy ćwiczeniu. Po publikacji planu tej wartości się nie zmienia.

Użytkownik nie zmienia parametrów.

Jest wyszukiwarka po nazwie ćwiczenia. Filtr poziomu trudności zostaje przy planach.

Grafika planu, wyzwania i eventu jest opcjonalna. Plik jak awatar: JPEG, PNG albo WebP, do 2 MB. Brak pliku daje inicjały z nazwy. Ćwiczenie nie ma własnej grafiki: na kafelku są inicjały nazwy.

Ekran i przebieg każdego ćwiczenia ustalamy osobno. Do tego czasu ćwiczenie jest pustym szablonem z nazwą, zasadami i domyślną zmienną.

### Zasada podsumowania

Podczas ćwiczenia nie pokazujemy punktacji ani statystyk. Wynik jest po zakończeniu.

Nie wysyłamy i nie trzymamy na serwerze listy kolejnych lotek. Zostają statystyki.

Dopóki ćwiczenie w planie jest otwarte, telefon trzyma jego rzuty lokalnie, bo cofanie w tym ćwiczeniu jest nieograniczone, a plan ma wrócić po zamknięciu aplikacji. Przejście do następnego elementu zatwierdza poprzedni: zostają statystyki, lista rzutów tego elementu znika.

Ćwiczenie z wyznaczonym celem zapisuje w podsumowaniu liczbę lotek, które trafiły w cel, i procent na tym celu. Ćwiczenie bez celu (luźne rzuty) nie zbiera trafień. Kończy się statusem ukończone albo pominięte.

Pominięty element planu nie wnosi punktów, trafień ani licznika. Ma status „Pominięty”.

Cele i odznaki na tym etapie nie są podpięte pod ćwiczenia. Najpierw istnieją osobno. Połączenie, które ćwiczenie zasila który cel i którą odznakę, zapada później.

Trafienie w sektor, jeśli ćwiczenie nie mówi inaczej, to single, double albo triple tej liczby. Środek tarczy to outer albo inner, jeśli ćwiczenie tak mówi.

### 5.1. Luźne rzuty

3 minuty. Dowolne rzuty, bez celu, bez punktacji, bez skuteczności, bez listy lotek. Zapisujemy czas. Status ukończone albo pominięte.

Na środku ekranu jest zegar „Zostało” i pasek, który wypełnia się wraz z upływem. Rusza wraz ze startem. Nie ma celu ani przycisków lotek. Przez ostatnie 5 sekund leci tykanie i kończy się razem z czasem. Po upływie czasu gra dzwonek i ćwiczenie przechodzi do podsumowania z zapisanym czasem.

### 5.2. 9 lotek

36 lotek, cztery etapy po 9:

1. Środek tarczy, outer albo inner.
2. Sektor 20.
3. Sektor 19.
4. Na zmianę 20, 19, 18, aż do 9 lotek.

Bez punktacji. W podsumowaniu trafienia i procent osobno dla każdego etapu oraz łącznie.

### 5.3. Zegar szybki

21 lotek, po jednej w sektory 1–20, potem Bull. Bull to outer albo inner. Nietrafienie nie zatrzymuje przejścia: kolejna lotka celuje w następny sektor. Bez punktacji. W podsumowaniu trafienia i procent.

Ekran pokazuje napis „Celuj kolejno po jednej lotce w te sektory” i aktualną trójkę celów (1, 2, 3, potem 4, 5, 6, aż do 19, 20 i Bull). Podświetlony jest cel lotki, którą gracz właśnie oznacza. Między celami a przyciskami jest licznik lotki w podejściu: „Lotka nr 1”, „Lotka nr 2”, „Lotka nr 3”. Po trzeciej lotce wraca do „Lotka nr 1”. Pod spodem dwa duże przyciski obok siebie: Pudło po lewej, Trafiona po prawej. Licznik pozycji w całym ćwiczeniu nie jest pokazywany.

### 5.4. Zegar sektory

5 minut. Sektory 1–20, potem Bull. Bull to outer albo inner. Sektor zalicza dowolny pierścień tej liczby: single 1 punkt, double 2, triple 3. Pudło zostawia aktualny cel. Trafienie przechodzi do następnego, także w tej samej trójce lotek. Koniec czasu ucina ćwiczenie w trakcie celu. Ukończenie bulla przed czasem kończy ćwiczenie.

Na górze ekranu, na środku, jest zegar „Zostało” i pasek, który wypełnia się wraz z upływem. Rusza wraz ze startem. Pod nim napis „Celuj w podświetlony sektor aż trafisz. Jeżeli Ci się uda to celuj w następny z wyświetlonych.” i okno kolejnych celów, najwyżej trzech, od aktualnego (1, 2, 3, po trafieniu jedynki 2, 3, 4, na końcu 20 i Bull). Podświetlony jest pierwszy z tego okna. Przez ostatnie 5 sekund leci tykanie i kończy się razem z czasem. Po upływie czasu gra dzwonek i ćwiczenie przechodzi do podsumowania. Między celami a przyciskami jest licznik lotki w podejściu: „Lotka nr 1”, „Lotka nr 2”, „Lotka nr 3”. Po trzeciej lotce wraca do „Lotka nr 1”. Pod spodem dwa duże przyciski obok siebie: Pudło po lewej, Trafiona po prawej. Licznik pozycji w całym ćwiczeniu nie jest pokazywany.

### 5.5. Bob's 27 easy

Osobne ćwiczenie. Start 27. Cele D1–D20, bez bulla. Trzy lotki na cel. Wartość D1 to 2, D2 to 4, aż do D20 równego 40. Każde trafienie dodaje tę wartość. Trzy pudła odejmują ją raz. Wynik może zejść poniżej zera i ćwiczenie jedzie dalej. Czas 5 minut. Niedokończona trójka na końcu czasu nic nie dodaje i nic nie odejmuje. Wynik to stan licznika, także ujemny.

### 5.6. Bob's 27 hard

Osobne ćwiczenie. Jak easy, plus bull o wartości 50 po D20. Outer bull nie jest trafieniem. Jeśli rozliczenie celu zjechałoby wynik poniżej 1, ćwiczenie się kończy. Nie zapisujemy liczby ujemnej. Wynik takiego zakończenia to 0. Inaczej koniec po 5 minutach albo po ostatnim celu, ze stanem licznika.

### 5.7. Catch20

Checkouty od 41 do 60 po kolei. Na liczbę najwyżej 6 lotek. Zamknięcie albo 6 lotek bez zamknięcia przenosi dalej. Bez powrotu. Koniec po 9 minutach albo po przejściu wszystkich wartości.

Zamknięcie jest jak w X01: dokładnie zero, ostatnia lotka w double. Przestrzelenie i inna ostatnia lotka nie zamykają.

Wynik: liczba zamkniętych checkoutów.

### 5.8. Scoring 20/19/18

9 minut. Kolejki po 3 lotki. Cele w kółko: 20, 19, 18. Po kolejce sektor się zmienia, także przy pudle. Trafienie w aktualny sektor: single 1, double 2, triple 3. Inne pole, także bull, daje 0. Wynik to suma.

### 5.9. 18 lotek

Sześć kolejek po 3 lotki:

1. Środek, outer albo inner.
2. Sektor 20.
3. Sektor 19.
4. Sektor 20.
5. Sektor 19.
6. Sektor 18.

Bez punktacji. W podsumowaniu trafienia i procent.

### 5.10. Zegar doubli

Po jednej lotce w D1–D20, potem Bull. Bull to outer albo inner. Nietrafienie nie zatrzymuje. Bez punktacji. Trafienie doubli to double tego numeru. W podsumowaniu trafienia i procent.

### 5.11. Scoring z presją

Trzy elementy po 5 minut.

**Sektor 20.** Single 1, double 2, triple 3. Trafienie poza 20 zeruje aktualną serię. Nie ma dodatkowej kary. Wynik elementu to najwyższa seria, nie suma serii.

**Sektory 20/19/18.** Zawodnik przy każdej lotce sam wybiera, który z tych trzech sektorów jest celem. Nie ma narzuconej kolejności. Ta sama skala 1, 2, 3. Trafienie poza tą trójką zeruje serię. Wynik elementu to najwyższa seria.

**65.** Podejście to 3 lotki. Liczą się punkty z tarczy, na przykład 20, 20 i 25. Suma co najmniej 65 daje 1 punkt ćwiczenia. Słabsze podejście daje 0. Powtarzane do końca czasu. Urwane podejście na końcu czasu nie wchodzi do wyniku. Wynik elementu to suma tych punktów.

### 5.12. Singielki z presją

10 minut. Liczą się tylko single. Double i triple nie zaliczają sektora.

Ścieżka: 1 → 2 → … → 20 → Bull, potem powrót 20 → 19 → … → 1. Bulla zalicza outer i inner.

Trafienie single aktualnego sektora przesuwa na następną lotkę, także w tej samej trójce. W jednej trójce można więc przejść kilka sektorów. Jeśli żadna z trzech lotek nie trafi sektora, który był aktualny przy tej lotce, cofamy o jeden sektor. Nie niżej niż 1. Na powrocie, przy S1, trzy pudła zostawiają S1.

Po bullu kierunek się odwraca. Następna lotka celuje w 20, także w tej samej trójce.

Punkty zależą od pozycji:

- w drodze do bulla: numer sektora, bull to 25,
- w drodze powrotnej: 25 plus krok od sektora 20 (20 dodaje 1, 19 dodaje 2, …, 1 dodaje 20, czyli 45).

Single 1 na powrocie kończy ścieżkę za 45 punktów. Jeśli czas jeszcze idzie, nowa ścieżka zaczyna się od sektora 1. Nie wracamy do ukończonej ścieżki. Wynik końcowy to suma ukończonych ścieżek plus punkty pozycji ścieżki niedokończonej w chwili końca czasu.

### 5.13. Checkout 41+

10 minut. Start od 41. Na liczbę najwyżej 3 lotki. Zamknięcie jak w X01. Sukces wchodzi o jeden w górę. Porażka schodzi o jeden, nie niżej niż 41. Niedokończona trójka na końcu czasu nie zamyka.

Zapisujemy najwyższy zamknięty checkout, nawet jeśli później zawodnik zejdzie niżej. Od niego liczy się wynik:

- 41 to 1 punkt,
- każdy kolejny aż do 50 dokłada 5,
- każdy powyżej 50 dokłada 7.

Stąd 49 to 41, 50 to 46, 51 to 53, 52 to 60. Górnego limitu nie ma.

### 5.14. Checkout Bull 81+

8 minut. Podejście ma 3 lotki. Dwie pierwsze ustawiają checkout i liczą się tylko jako single: odejmują swoją wartość. Inne trafienie na tych lotkach nic nie odejmuje. Trzecia lotka zawsze idzie w czerwony, inner bull, także wtedy, gdy single nie zostawiły 50. To jest wskazówka dla gracza i zarazem przebieg podejścia.

Zamknięcie jest tylko wtedy, gdy po dwóch single zostało dokładnie 50 i trzecia lotka trafiła w inner. Outer nie zamyka. Nieudane podejście zostawia tę samą liczbę.

Start od 81 w górę do 90, potem powrót do 81. Po ponownym zamknięciu 81, jeśli został czas, kolejna runda od 81. Każde zamknięcie daje 10 punktów. Wynik to suma.

### 5.15. Walka o licznik

3 minuty. Jedna lotka w ręce, po rzucie ta sama wraca do ręki. Inner 4 punkty, outer 2, reszta 0. Wynik to suma.

### 5.16. Lista jest stała

Ćwiczenia nie mają statusu. Nie da się ich dezaktywować ani usunąć z biblioteki.

Z planu wyjmuje się ćwiczenie edycją planu, zanim plan zostanie opublikowany. Po publikacji układu się nie zmienia. Nowa treść to nowy plan, nowe wyzwanie albo nowy event.

Wyzwanie i event, które używają ćwiczenia, biorą jego domyślny czas albo domyślną liczbę lotek. Tej zmiennej tam się nie nadpisuje.

---

## 6. Plany

Wspólna lista, bez kategorii tematycznych.

Szukanie po nazwie. Filtr poziomu trudności: Łatwy, Średni, Trudny. Filtr deklarowanego czasu: 30, 60, 90, 120 minut. Sortowanie po nazwie, trudności i czasie. Domyślnie A–Z. Przy każdym wejściu filtry i sort wracają do domyślnych.

Kafelek: nazwa, trudność, deklarowany czas, gwiazdka ulubionych. Bez liczby ćwiczeń, daty ostatniego rozegrania i liczby ukończeń.

Szczegóły: pełny opis, deklarowany czas, liczba ukończeń tego użytkownika, ulubione, „Podgląd planu”, „Rozpocznij plan”. Podgląd przy każdym wejściu jest zwinięty. Po rozwinięciu: ćwiczenia po kolei, czas każdego, przerwy i ich czas. Można przeczytać opis ćwiczenia. Nie da się wystartować jednego elementu z podglądu. „Rozpocznij plan” jest zawsze widoczny i nie pyta drugi raz.

Administrator układa plan z ćwiczeń biblioteki. Może powtórzyć to samo ćwiczenie, wstawić dowolnie wiele przerw, także na początku i na końcu, i ustalić czas każdej przerwy. Przy ćwiczeniu może zmienić tylko jego zmienną: czas albo liczbę lotek. Pominięta wartość zostawia domyślną. Deklarowany czas wybiera z 30, 60, 90, 120. System go nie liczy z sumy elementów i nie ostrzega, gdy suma się różni.

Nowy plan jest nieaktywny, aż administrator go włączy. Po pierwszej publikacji nie edytuje się nazwy, opisu, grafiki, trudności, czasu ani układu. Zmiana to nowy plan.

Dezaktywacja: znika z aktywnego katalogu, zostaje w ulubionych jako nieaktywny, nie da się zacząć nowej sesji, sesje w toku są unieważnione i nie da się ich wznowić ani dokończyć. Na liście „Nieaktywne plany” można go obejrzeć i włączyć z powrotem, bez edycji.

Anulowanie jest nieodwracalne. Plan znika z aplikacji i z panelu, wypada z ulubionych, sesje w toku są unieważnione. Ślad zostaje tylko dla programisty. Wymaga hasła administratora i końcowego potwierdzenia.

### Przebieg planu

Widać aktualny element, nazwę następnego i pasek w postaci „3 z 8”. Licznik obejmuje ćwiczenia i przerwy. Pominięty element też przesuwa licznik. Nie ma procentów.

Ćwiczenie po zakończeniu samo przechodzi dalej. Przerwa zaczyna się sama i ma timer. W przerwie można: pominąć ją po potwierdzeniu, przedłużyć o minutę przy każdym użyciu, zatrzymać timer i puścić go od tego samego miejsca. Po przerwie następne ćwiczenie startuje samo. Pominięcie przerwy nie pomija ćwiczenia.

Ćwiczenie można pominąć po potwierdzeniu. Status: „Pominięty”.

Cofanie jest nieograniczone wewnątrz aktualnego ćwiczenia. Nie sięga do elementu już zamkniętego.

Wcześniejsze zakończenie planu wymaga potwierdzenia. Potem użytkownik wraca na ekran Trening. Sesja jest zamknięta, nie da się jej wznowić, nie wchodzi do historii i nie rusza statystyk, rekordów ani celów.

Na telefonie, na którym plan wystartował, da się go pauzować i wrócić dokładnie w to miejsce. Zamknięcie aplikacji też. Postęp planu w trakcie nie wymaga API. Po wylogowaniu niezakończony plan na tym telefonie jest kasowany.

### Podsumowanie planu

Jedno podsumowanie sesji: nazwa, data ukończenia, łączna punktacja. Suma bierze się z ukończonych ćwiczeń. Pominięte dają zero.

Niżej ćwiczenia po kolei, każde zwinięte. Można rozwinąć kilka naraz. Przy następnym otwarciu znów są zwinięte. W środku: wynik i statystyki ćwiczenia, bez listy rzutów. Pominięte mają oznaczenie i nie mają statystyk.

Przyciski: „Powrót do ekranu treningu” i „Powtórz plan”. Powtórzenie pyta: „Czy na pewno chcesz rozpocząć ten plan ponownie?” Przyciski „Anuluj” i „Rozpocznij”. Nowa sesja, bez przenoszenia stanu. Jeśli plan jest nieaktywny, powtórzenie jest wyłączone.

Z historii: „Wróć do historii” i „Powtórz plan”. Powrót zachowuje filtry, przewinięcie i stan listy.

Szczegółowy wygląd ekranów wewnątrz planu uzgadniamy osobno. Zasady z tej sekcji są wiążące.

---

## 7. Pojedyncze ćwiczenie

Po zakończeniu od razu podsumowanie: wynik i statystyki, bez listy rzutów.

Przyciski: „Powtórz ćwiczenie” i „Powrót do ekranu treningu”. Powtórzenie od razu zaczyna nową sesję, bez dodatkowego pytania. Poprzedni wynik zostaje w historii. Z historii zamiast powrotu do Treningu jest „Wróć do historii”.

Nie da się wznowić. Przerwanie pyta. Po potwierdzeniu użytkownik wraca na ekran Trening, a sesja przepada: bez historii, statystyk, rekordów i celów.

Nie ma pomijania. Cofanie jest nieograniczone w tym ćwiczeniu. W trakcie nie ma punktacji.

Ekran każdego ćwiczenia uzgadniamy osobno, zanim powstanie.

---

## 8. Wyzwania

Są dwie kategorie treści rywalizacyjnej: wyzwania i eventy. Nie ma osobnej kategorii „wyzwania specjalne”.

Wyzwanie jest indywidualne, bezterminowe i powtarzalne. Event jest czasowy, z limitem prób, klasyfikacją i medalami.

Nowe wyzwanie powstaje od zera jako nieaktywne. Nie ma kopiowania ani wersjonowania. Po pierwszej publikacji nie edytuje się nazwy, opisu, grafiki, mechaniki, punktacji ani zasad. Zmiana to dezaktywacja albo anulowanie i nowy byt.

Dezaktywacja jest odwracalna i wymaga zwykłego potwierdzenia, bez hasła. Wyzwanie znika z katalogu. Próby w toku są unieważnione. Użytkownik widzi: „Wyzwanie zostało dezaktywowane przez administratora”. Unieważniona próba nie wchodzi do historii i nie rusza statystyk, rekordów, celów ani odznak. Ranking znika z widoku. W ulubionych zostaje oznaczenie „Nieaktywne”. Po ponownym włączeniu ranking wraca razem z wcześniejszymi opublikowanymi wynikami. Włączyć można, edytować nie.

Anulowanie jest nieodwracalne i wymaga hasła oraz potwierdzenia. Wyzwanie znika z aplikacji, panelu i ulubionych. Próby w toku są unieważnione. Ranking jest usunięty. Z widoku użytkownika znikają historia, wyniki, statystyki, rekordy i opublikowane wyniki tego wyzwania. Zdobyta odznaka zostaje, ale tylko z nazwą wyzwania, bez wyniku i historii. Ślad techniczny zostaje dla programisty. Administrator go nie przywraca z panelu.

### Próba

Bez pauzy i bez wznowienia. Przerwanie pyta i wraca na ekran Trening. Niedokończona próba przepada. Nie ma pomijania etapów. W trakcie nie ma punktacji.

### Historia wyzwania

Są wszystkie ukończone próby, opublikowane i nie. Nie da się skasować jednej próby. Da się skasować całą historię treningową, jak w rozdziale 12.

Filtr daty. Sort od najnowszych albo od najlepszego wyniku. Szczegóły: wynik końcowy, etapy, statystyki. Bez listy rzutów.

Po ukończeniu: opublikuj wynik, jeśli spełnia warunek, albo spróbuj ponownie. Nowa próba jest niezależna.

### Ranking

Wynik jest prywatny, dopóki użytkownik go nie opublikuje. W rankingu widać nazwę zawodnika i opublikowany wynik. Bez statystyk i bez lotek. Reszta danych Training pozostaje prywatna.

Każdy ma jedną pozycję: swój najlepszy opublikowany wynik. Nowy wynik da się opublikować tylko wtedy, gdy jest lepszy od już opublikowanego. Równy albo gorszy nie wchodzi. Publikację można wycofać. Wynik znika z rankingu i zostaje w prywatnej historii.

Remis: miejsca konkursowe, na przykład 1, 1, 3. Przy równym wyniku kolejność na liście jest alfabetyczna po nazwie gracza. Data remisu nie rozstrzyga.

Ranking jest zakładką szczegółów aktywnego wyzwania. Pusto: „Brak opublikowanych wyników. Podejmij wyzwanie jako pierwszy”. Domyślnie 10 najlepszych, „Pokaż więcej” ładuje kolejne 10. Gracz poza dziesiątką jest pokazany osobno. Wiersz: miejsce, nazwa, awatar, wynik, data. Po publikacji komunikat z miejscem i wynikiem. Nie ma powiadomienia, gdy ktoś wyprzedzi.

### 8.1. 54-Dart Challenge

Zwykłe wyzwanie, dostępne stale, dopóki administrator go nie wyłączy albo nie anuluje. Trzy etapy, 54 rzuty.

**Shanghai 10–15.** Sześć sektorów, 3 rzuty na sektor. Single 15, double 30, triple 45. Bonus 100 za komplet S+D+T w jednej wizycie. Maksimum 1140.

**Shanghai 16–20.** Pięć sektorów, ta sama punktacja i ten sam bonus. Maksimum 950.

**Doubles 1–20 i Bull.** Po jednej lotce w każdy double, potem Bull. Double 50. Inner bull 100. Outer bull 0 i nie jest trafieniem. Maksimum 1100.

Maksimum całości: 3190.

Podsumowanie pokazuje wynik łączny. Etapy są w historii. Progi kolorystyczne wyniku nie istnieją.

Cele tego wyzwania: od 100 do 3100 co 100, według najlepszego ukończonego wyniku. Progi odznaki tego wyzwania ustala administrator przy definicji. Nie ma jednej drabinki dla wszystkich wyzwań.

Ekran próby uzgadniamy osobno. Zasady punktacji powyżej są wiążące.

---

## 9. Eventy

Event wymaga sieci od startu do końca. Bez sieci nie da się zacząć. Utrata sieci w trakcie przerywa próbę i zużywa ją. Crash i świadoma rezygnacja też zużywają próbę. Zużyta, niedokończona próba nie wchodzi do historii ani statystyk.

To jedyny byt Training, w którym udział jest limitowany. Administrator ustawia, ile prób przypada na okno. Niewykorzystana próba nie przechodzi na następne okno.

Okno jest stałe. Nie liczy się od chwili, w której gracz skończył próbę. Administrator wybiera jedno z trzech:

- tydzień kalendarzowy, od poniedziałku do niedzieli,
- kolejne bloki 7 dni od dnia startu eventu, ten dzień włącznie,
- kolejne bloki 7 dni od daty, którą wskaże.

Przykład drugiego i trzeciego trybu: 25–31 sierpnia jedna próba, potem 1–7 września kolejna.

Próby nie da się pauzować ani wznowić. Przerwanie pyta, unieważnia próbę i wraca na ekran Trening. Etapów nie pomijamy. W trakcie nie ma punktacji.

### Prowadzenie eventu

Przed startem administrator może zmienić nazwę, opis, grafikę, datę startu i datę końca. Po starcie może zmienić nazwę, opis, grafikę i datę końca. Daty startu po rozpoczęciu nie zmienia. Mechanika i punktacja stoją.

Może wystartować wcześniej. Faktyczna chwila startu staje się datą rozpoczęcia. Data końca zostaje, chyba że zmieni ją osobno.

Może wstrzymać aktywny event i wznowić go. W pauzie nikt nowy nie dołącza i nie startuje próby. Rozpoczęte próby można dokończyć. Pauza i wznowienie nie wysyłają pusha.

Wcześniejsze zakończenie: nikt nowy nie dołącza, nie ma nowych prób, event znika z widoku użytkowników, rozpoczęte próby można dokończyć. Status „Zakończony” pojawia się dopiero wtedy, gdy każda rozpoczęta próba jest ukończona albo unieważniona. Wtedy powstaje klasyfikacja i medale, a event trafia do zakończonych i zostaje tam dostępny, także dla osób, które nie grały.

Administrator nie unieważnia ręcznie niedokończonej próby. Samo przerwanie przez gracza ją unieważnia i wyrzuca z klasyfikacji.

### Klasyfikacja i medale

Klasyfikacja bierze się z ukończonych prób, automatycznie. Administrator nie poprawia wyników, miejsc ani medali.

Miejsca konkursowe: ten sam wynik to to samo miejsce i ten sam medal, następne miejsce jest przesunięte. Przykład: dwa razy miejsce 1 i złoto, następny ma miejsce 3 i brąz. Srebra wtedy nie ma. Medal za miejsce 1, 2 i 3.

Dyskwalifikacja wyklucza z medali tego eventu. Po dyskwalifikacji i po jej cofnięciu klasyfikacja i medale liczą się od nowa. Innych eventów to nie rusza.

Ukończony event widać także bez udziału. Cudze próby zostają prywatne. Swoją próbę użytkownik widzi.

Medale są w Analizie, w tej samej konwencji co odznaki: ikona, po kliknięciu event, kolor i data.

### Usunięcie i anulowanie

Anulowanie wymaga hasła i potwierdzenia. Event znika z katalogu, próby w toku są unieważnione, nie ma klasyfikacji ani medali.

Usunięcie na dowolnym etapie też wymaga hasła i potwierdzenia. Event znika użytkownikom, próby w toku są unieważnione, ślad zostaje dla programisty, bez przywracania w panelu.

Po zakończeniu administrator może event usunąć, ale nie edytuje już nazwy, opisu, grafiki ani dat.

W panelu lista uczestników to osoby, które ukończyły próbę. Widać ich statystyki. Nie ma wglądu w próby nieukończone, przerwane i unieważnione. Nie ma eksportu CSV ani Excel.

Operacje z hasłem: dyskwalifikacja, zmiana statusu, pauza, wznowienie, usunięcie, anulowanie.

---

## 10. Moje treningi

Jedna historia ukończonych sesji: plany, pojedyncze ćwiczenia, wyzwania, eventy i Wolna gra.

Nie wchodzą sesje przerwane, niedokończone, anulowane i unieważnione. Wcześniej zakończony plan też nie.

Sort od najnowszych. Wiersz: nazwa, rodzaj, data, wynik końcowy. Bez godziny.

Filtry łączone: rodzaj (wiele naraz) i zakres dat. Rodzaje: Plany, Ćwiczenia, Wyzwania, Eventy, Wolna gra. Filtry zwinięte pod „Filtruj”, z liczbą aktywnych, na przykład „Filtruj (2)”. Jest „Wyczyść filtry”. Zmiana działa od razu.

Domyślnie pięć najnowszych. „Załaduj więcej” bierze kolejne pięć, także przy filtrach. Wejście z innego miejsca zaczyna listę od nowa, bez starych filtrów. Wejście z powrotem z podsumowania zachowuje filtry, przewinięcie i stan listy.

Nie da się usunąć jednej sesji, edytować wyniku, zmienić nazwy, dodać notatki ani udostępnić obrazka podsumowania.

Licznik ukończonych ćwiczeń nie stoi na tej liście. Jest w Analizie.

---

## 11. Offline, synchronizacja, pamięć

Plan, ćwiczenie i wyzwanie da się rozegrać bez sieci, jeśli definicja jest już na telefonie. Eventu nie.

Aktywny plan żyje na telefonie, na którym wystartował. Drugi telefon może mieć własny trening. Nie kasuje sesji na pierwszym. W trakcie planu API nie jest wymagane.

Po ukończeniu telefon trzyma podsumowanie i statystyki do czasu potwierdzenia serwera. Taka sesja nie blokuje następnego treningu. Jest przypisana do konta, na którym powstała. Po wylogowaniu czeka na to samo konto i nie może wejść na inne. Po ponownym zalogowaniu wysyłka rusza sama.

Na serwer idzie: podsumowanie, statystyki, wyniki ćwiczeń planu, status pominięć. Bez listy lotek. Zapis ukończonej sesji na serwerze jest niezmienny. Powtórna wysyłka tej samej sesji nie tworzy drugiego wpisu historii.

Do czasu udanej synchronizacji sesja nie rusza statystyk, rekordów, celów, odznak ani liczników. Po udanej aktualizują się same.

Jeśli treść została w międzyczasie dezaktywowana albo anulowana, serwer odrzuca domknięcie. Sesja nie wchodzi do historii.

Oznaczenie „Do zsynchronizowania” stoi na liście historii i znika po sukcesie. Przy błędzie niezgodności zostaje lokalny rekord i oznaczenie „Błąd synchronizacji”. Nie ma ręcznego ponowienia. System próbuje sam, w dłuższych odstępach. Danych nie kasuje. Statusu błędu nie ma na ekranie szczegółów.

Lokalnie zostaje pięć najnowszych poprawnie zsynchronizowanych sesji ze wszystkich rodzajów. Starsze przychodzą z serwera przy „Załaduj więcej”. Szczegóły starszej sesji dociągają się przy otwarciu. Offline, bez kopii: „Szczegóły niedostępne offline”.

Jest stały limit pamięci w konfiguracji aplikacji. Liczy się do niego to, co przyszło z serwera, także grafiki. Po przekroczeniu wypadają najstarsze zsynchronizowane kopie. Sesje oczekujące i z błędem są chronione. Nie ma ostrzeżenia ani ręcznego zarządzania pamięcią.

Historia bierze z serwera po pięć pozycji, z uwzględnieniem filtrów. W trakcie ładowania „Załaduj więcej” jest wyłączone. Przy błędzie załadowana lista zostaje i widać komunikat. Offline widać kopię lokalną, a próba dociągnięcia mówi o braku sieci. Nie ma przeciągania w dół. Otwarta lista nie dopisuje sama nowej sesji. Trzeba wyjść i wejść ponownie.

---

## 12. Usunięcie całej historii

Jedno działanie, po potwierdzeniu. Pojedynczej sesji skasować nie można.

Historia znika od razu z widoku. Lokalne kopie znikają na urządzeniach. Na serwerze dane czekają 30 dni. W tym czasie przywraca je wyłącznie programista, w całości: historia, statystyki, rekordy, cele, odznaki i opublikowane wyniki. Nie ma do tego ekranu w panelu i nie ma pusha. Po 30 dniach dane giną na stałe.

Usunięcie zeruje licznik ćwiczeń, statystyki, rekordy, cele, odznaki i opublikowane wyniki we wszystkich rankingach. Użytkownik wypada z rankingów i może zagrać oraz opublikować wynik od nowa. Te ćwiczenia wypadają też z globalnego licznika administratora. Przywrócenie w ciągu 30 dni oddaje je do licznika.

Usunięcie konta, już istniejące w aplikacji, kasuje dane treningowe od razu, bez 30 dni i bez powrotu.

Nie ma eksportu danych treningowych.

---

## 13. Rekordy, cele, odznaki, analiza

Liczą się ukończone sesje po udanej synchronizacji. Ukończone ćwiczenie z planu, wyzwania i eventu liczy się tak samo jak zagrane osobno, gdy przyjdzie czas podpięcia. Pominięte ćwiczenie nie wnosi danych. Przerwany plan, przerwane ćwiczenie, przerwane wyzwanie i unieważniona próba nie wnoszą nic.

Rekord jest przy ćwiczeniu. Bob's 27 easy i hard to dwa ćwiczenia, więc dwa rekordy. W „Moje rekordy” widać nazwę, najlepszy wynik i datę. Bez nazwy planu. Kolejność alfabetyczna. Historia poprawiania rekordu nie jest osobnym ekranem.

Na tym etapie cele i odznaki istnieją na sucho. Nie decydujemy, które ćwiczenie je zasila. Progi poniżej są zatwierdzone i czekają na to połączenie.

### Cele

Pięć ścieżek, te same dla wszystkich, bez cykli tygodniowych. W ścieżce widać jeden bieżący cel. Ukończone zostają zapisane i nie pokazują się na liście bieżących. Cel nie daje odznaki. Postęp się nie zeruje. Zebrana wartość zostaje i od niej liczy się następny próg. Jedno podejście, które przeskoczy kilka progów, zamyka wszystkie progi poniżej wyniku.

**Scoring.** Średnia punktów na trzy rzuty. Progi: 30, 40, 50, 60, 65, 70, 73, 76, 79, 82, 85, 88, 91, 94, 97, 100.

**Doubles.** Skuteczność D1–D20. Progi: 5%, 8%, 11%, 14%, 17%, 20%, 23%, 26%, 29%, 32%, 35%.

**Checkouty.** Skuteczność checkoutów. Progi: 2%, 4%, 6%, 8%, 10%, 12%, 14%, 16%, 18%, 20%.

**Regularność.** Ukończone sesje w kroczących 30 dniach. Cały plan to jedna sesja, nie liczba ćwiczeń w środku. Progi: 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35, 38, 41, 44, 47, 50.

**Wyzwania.** Najlepszy ukończony wynik 54-Dart Challenge. Progi od 100 do 3100 co 100.

Skuteczność jako ocena, gdy już ją podepniemy, wymaga co najmniej 20 odpowiednich prób tego samego ćwiczenia i tego samego celu. Poniżej progu procent nie jest oceną. Surowe liczby prób mogą być w szczegółach.

### Odznaki

Osobno od odznak profilu aplikacji. Są w Treningu. Poziomy: Żelazo, Brąz, Złoto, Platyna, Diament. Poziom odznak Scoring, Doubles i Checkouty jest trwały i nie spada. Widać aktualny poziom i postęp do następnego. Oba warunki poziomu muszą być spełnione naraz.

**Scoring.** Minimum 20 kolejek. Średnia na kolejkę i udział kolejek za co najmniej 60 punktów.

| Poziom | Średnia | Kolejki za co najmniej 60 |
|---|---:|---:|
| Żelazo | 40 | 20% |
| Brąz | 50 | 25% |
| Złoto | 60 | 30% |
| Platyna | 65 | 35% |
| Diament | 70 | 40% |

**Doubles.** Skuteczność dopiero po 20 próbach. Drugi warunek to liczba trafionych double.

| Poziom | Skuteczność | Trafione double |
|---|---:|---:|
| Żelazo | 10% | 50 |
| Brąz | 14% | 100 |
| Złoto | 18% | 200 |
| Platyna | 22% | 400 |
| Diament | 25% | 1000 |

**Checkouty.** Skuteczność dopiero po 20 próbach. Drugi warunek to liczba udanych checkoutów.

| Poziom | Skuteczność | Udane checkouty |
|---|---:|---:|
| Żelazo | 2% | 20 |
| Brąz | 4% | 40 |
| Złoto | 7% | 70 |
| Platyna | 10% | 110 |
| Diament | 15% | 150 |

**Wyzwania.** Każde wyzwanie ma własną odznakę i własne progi pięciu poziomów, wpisywane przy tworzeniu. Nie ma wspólnej drabinki. Poziom to średnia z ostatnich pięciu ukończonych prób. Przed piątą próbą poziomu nie ma. Poziom może wzrosnąć albo spaść. Próby nieukończone, przerwane i unieważnione nie wchodzą do średniej. Zmiana zasad to nowe wyzwanie i nowa seria odznaki.

Na podsumowaniu sesji widać nowy rekord, nowy cel i zmianę odznaki tylko wtedy, gdy coś takiego zaszło. Nie ma osobnej skrzynki tych komunikatów i nie da się ich wyłączyć. Nie ma o nich pusha.

### Analiza zawodnika

Jedna przewijana strona, bez zakładek. Po wejściu zawsze od góry.

1. Licznik ukończonych ćwiczeń.
2. Wykres aktywności.
3. Rekordy osobiste.
4. Odznaki i medale.
5. Cele.

Licznik: jedno ukończone ćwiczenie to +1, plan z ośmioma ukończonymi ćwiczeniami to +8, przerwy i pominięcia to zero, ćwiczenia z wyzwań i eventów też wchodzą. Tylko dane po synchronizacji. To jedyne miejsce tego licznika. Nie ma go w profilu zawodnika ani nad historią. Regularność to inny miernik: liczy sesje, nie ćwiczenia.

Wykres: liczba ukończonych ćwiczeń na dzień, linia z punktami. Zakresy 7, 30 i 60 dni. Bez własnego zakresu i bez podziału na rodzaj. Dzień bez gry ma zero. Klik w punkt pokazuje wartość, nie listę treningów.

Odznaki to same ikony, na czas prac prostokąt z napisem „Ikona”. Widać najwyższy zdobyty poziom. Kolor to poziom. Grupy: Scoring, Doubles, Checkouty, Wyzwania. Medale są obok, tą samą konwencją. Klik: opis warunków albo nazwa eventu, kolor medalu, data.

Cele: pięć bieżących naraz, postęp, pasek, wartość do ukończenia. Bez osobnego komunikatu o ukończeniu i bez archiwum na tym ekranie. Klik pokazuje zasadę naliczania.

Wejście pobiera z API cały zestaw. Do czasu sukcesu jest ekran ładowania. Błąd jednej części chowa wszystko, pokazuje komunikat i „Spróbuj ponownie”. Ponowienie bierze całość od zera. Otwarty widok nie odświeża się po synchronizacji. Żeby zobaczyć nowe dane, trzeba wyjść i wejść.

Administrator nie widzi postępu bieżących celów użytkownika.

---

## 14. Panel administratora

Dokładamy Training do istniejącego panelu. Bez dziennika operacji. Bez zmian w banach i rolach.

Aktywacja i dezaktywacja planu albo wyzwania: zwykłe potwierdzenie. Ćwiczenia nie mają statusu.

Nieodwracalne operacje: hasło administratora i końcowe potwierdzenie, bez dodatkowej listy skutków. Dotyczy anulowania i usuwania oraz operacji eventu wymienionych w rozdziale 9.

### Dane użytkownika

Tylko odczyt, tylko ukończone sesje. Historia, statystyki, licznik ćwiczeń, rekordy, odznaki z poziomem i datą. Bez postępu celów. Bez sesji przerwanych i unieważnionych. Bez listy lotek. Filtr rodzaju i dat, sort od najnowszych. Bez eksportu. Administrator nie zmienia wyników.

### Statystyki planów

Liczba rozpoczętych sesji, ukończonych sesji i unikalnych użytkowników. Lista użytkowników z liczbą ukończeń: filtr po liczbie ukończeń, sort po ukończeniach i po nazwie. Nie ma listy wszystkich sesji planu ze szczegółami każdej. Wykres rozpoczętych i ukończonych na dzień, z zakresem dat.

Rozpoczęcie planu, ćwiczenia i wyzwania serwer poznaje przy synchronizacji. Czysto lokalna, anulowana sesja, która nigdy nie doszła do serwera, nie wchodzi do licznika rozpoczętych. Event jest inny: start próby jest zapisany na serwerze, bo bez sieci próba się nie zaczyna.

### Statystyki wyzwań

Rozpoczęte próby, ukończone, unikalni uczestnicy, lista osób i liczba ukończeń każdej. Z tej listy nie ma przejścia do pełnej historii użytkownika. Zakładka ukończonych prób: wynik, etapy, statystyki, uczestnik, data i godzina. Bez listy lotek i bez edycji. Filtr użytkownika i daty. Sort po dacie i po wyniku. Wykres rozpoczętych i ukończonych na dzień.

### Statystyki eventów

Uczestnicy z ukończoną próbą i statystyki tych prób. Bez edycji i bez eksportu.

### Statystyka globalna

W istniejącej sekcji statystyk dokładamy „Statystyki treningowe”: jedna liczba, łączna liczba ukończonych indywidualnych ćwiczeń w całej aplikacji, od początku, bez filtra dat. Ta sama definicja co licznik w Analizie. Skasowanie historii przez użytkownika odejmuje jego wkład. Przywrócenie w 30 dniach oddaje wkład. Dotychczasowych statystyk kont nie zmieniamy.

---

## 15. Powiadomienia

Żaden push Training nie wychodzi sam. Ani start eventu, ani medal, ani aktywacja, ani dezaktywacja.

Jest jedno narzędzie w panelu. Administrator wpisuje tytuł i treść, wybiera odbiorców, wysyła od razu albo wysyła próbę tylko do siebie. Wysłanie wymaga potwierdzenia. Nie ma planowania na później.

Na tym etapie jest jedna grupa odbiorców: wszyscy zarejestrowani użytkownicy, którzy mają już token push. Bez tokenu nie ma jak dostarczyć komunikatu. Nowej zgody nie zakładamy. Kolejne grupy dojdą później, gdy będą potrzebne.

Historia wysyłek jest tylko do odczytu: tytuł, treść, data i godzina, grupa, liczba odbiorców. Nie da się wysłać ponownie z historii ani skasować wpisu.

---

## 16. Prywatność

Dane treningowe widzi właściciel konta i administrator, w zakresie z rozdziału 14. Inni użytkownicy ich nie widzą.

Publiczne są tylko dobrowolnie opublikowane wyniki w rankingu wyzwania oraz oficjalna klasyfikacja eventu. Klasyfikacja nie pokazuje cudzych lotek ani cudzych statystyk próby.

---

## 17. Plan ogólny realizacji

Każdy numer jest osobnym podejściem. Agent najpierw czyta ten dokument, układa szczegółowy plan tego numeru i dopiero potem go realizuje. Nie bierze następnego numeru w tym samym podejściu.

Nie ruszamy silnika meczowego, turnieju, ligi, szybkiej gry online ani odznak profilu. Wolna gra zostaje przy swoim silniku. Ekranu ćwiczenia ani szczegółowego przebiegu planu nie wymyślamy. Jeśli numer go potrzebuje, praca staje do uzgodnienia wyglądu z właścicielem.

Playlist 30 i 60 minut nie jest warunkiem żadnego numeru. Plan składa administrator z listy ćwiczeń. Przy ćwiczeniu w planie może zmienić tylko czas albo liczbę lotek.

### 1. Fundament danych

Cel: tabele i kontrakt API, na których stoją kolejne numery. Katalog definicji, sesja ukończona, statystyki zamiast listy lotek, idempotencja zapisu, statusy treści.

Zależy od: nic.

Gotowe, gdy: da się zapisać nieaktywną definicję i odrzucić drugi zapis tej samej ukończonej sesji. Nie ma jeszcze ekranów gry ani panelu.

### 2. Wejście w aplikacji

Cel: gość na ekranie Graj widzi Wolną grę i gra jak dotąd. Zalogowany wchodzi w ekran główny Trening z kafelkami z rozdziału 2. Katalogi mogą być puste.

Zależy od: 1.

Gotowe, gdy: oba wejścia działają, a wiszący plan jeszcze nie, bo nie ma silnika.

### 3. Treść w panelu administratora

Cel: plany, wyzwania i eventy. Ćwiczenia są stałą listą z domyślnym czasem albo liczbą lotek. Tworzenie planu, wyzwania i eventu, grafika albo inicjały, poziom trudności planu, w planie zmienna ćwiczenia, status nieaktywny i aktywny. Z nieopublikowanego planu da się wyjąć ćwiczenie. Po publikacji brak edycji. Anulowanie i usunięcie z hasłem.

Zależy od: 1.

Gotowe, gdy: administrator układa plan z ćwiczenia i innej wartości czasu albo lotek, a aplikacja jeszcze nie rozgrywa planu.

### 4. Ulubione i ostatnio używane

Cel: trzy zakładki ulubionych na koncie, kolejność, gwiazdka, stany nieaktywne. Ostatnio używane, najwyżej trzy, tylko na telefonie.

Zależy od: 2 i 3.

Gotowe, gdy: ulubione schodzą się między telefonami, a ostatnio używane nie.

### 5. Silnik sesji

Cel: lokalne rozegranie jednego uzgodnionego ćwiczenia. Cofanie wewnątrz ćwiczenia, brak punktów w trakcie, podsumowanie ze statystykami, utrata sesji po przerwaniu, wysyłka ukończenia bez drugiej historii. Wzorzec dla kolejnych ćwiczeń.

Zależy od: 1 i 3. Startuje dopiero po uzgodnionym ekranie tego jednego ćwiczenia.

Gotowe, gdy: to ćwiczenie da się ukończyć offline i zobaczyć po synchronizacji jako jeden wpis.

### 6. Katalog ćwiczeń

Cel: pozostałe ćwiczenia z rozdziału 5, reguły już opisane. Każde ćwiczenie jest osobnym podejściem wewnątrz tego numeru i czeka na swój ekran.

Zależy od: 5.

Kolejność wewnątrz: luźne rzuty, 9 lotek, 18 lotek, zegary, Scoring 20/19/18, Walka o licznik, Catch20, Bob's 27 easy, Bob's 27 hard, Scoring z presją, Singielki z presją, Checkout 41+, Checkout Bull 81+.

Gotowe, gdy: każde ćwiczenie z listy da się zagrać samodzielnie i zsynchronizować.

### 7. Plany

Cel: plan jako kontener. Kolejność, przerwy, pasek „3 z 8”, pominięcie, pauza, jedna wisząca sesja na telefonie, anulowanie bez drugiego pytania, wcześniejsze zakończenie poza historią, podsumowanie, powtórzenie.

Zależy od: 6, co najmniej od ćwiczeń, które administrator wkłada do planu.

Gotowe, gdy: opublikowany plan da się dokończyć, pominąć w środku i wznowić po zabiciu aplikacji. Wygląd przejść, którego nie ma w rozdziale 6, czeka na właściciela.

### 8. Moje treningi

Cel: jedna historia. Plany, ćwiczenia, wyzwania, eventy i Wolna gra. Filtry, po pięć pozycji, offline, błędy synchronizacji, ochrona niesynchronizowanych sesji.

Zależy od: 5. Wolna gra dochodzi z istniejącego zapisu, bez zmiany jej rozgrywki. Wyzwania i eventy pokazują się tu dopiero, gdy istnieją ich ukończone sesje. Lista ma przyjąć ich rodzaj od razu.

Gotowe, gdy: zalogowany widzi Wolną grę i ukończone treningi, a przerwana sesja na liście nie występuje.

### 9. Analiza, rekordy, cele i odznaki

Cel: ekran Analizy, rekordy, pięć ścieżek celów i odznaki Scoring, Doubles, Checkouty oraz miejsce na odznaki wyzwań. Medale jako puste miejsce obok odznak. Na sucho: progi są w danych, ćwiczenia jeszcze ich nie zasilają.

Zależy od: 8.

Gotowe, gdy: Analiza pobiera się w całości albo wcale, a licznik ćwiczeń stoi tylko tutaj.

### 10. Wyzwania i ranking

Cel: próba bez pauzy i bez wznowienia, historia ukończonych, publikacja lepszego wyniku, wycofanie publikacji, ranking z miejscami konkursowymi. 54-Dart po uzgodnionym ekranie. Progi odznaki wyzwania wpisuje administrator przy definicji. Odznaka jeszcze nie musi reagować na próby. To jest numer 13.

Zależy od: 5 i 3.

Gotowe, gdy: ukończona próba jest prywatna, lepsza daje się opublikować, gorsza nie, a przerwana nie wchodzi do historii.

### 11. Eventy

Cel: start tylko online, stała sieć, zużycie próby przy crashu, rezygnacji i utracie sieci, okna prób, pauza i wcześniejsze zakończenie eventu, klasyfikacja, medale w Analizie, dyskwalifikacja z przeliczeniem.

Zależy od: 9 i 10.

Gotowe, gdy: bez sieci próba się nie zaczyna, drugie podejście w tym samym oknie jest odrzucone, a medal widać w Analizie po zamknięciu eventu.

### 12. Operacje administratora i dane użytkownika

Cel: statystyki planów, wyzwań, eventów i licznik globalny. Odczyt historii użytkownika. Ręczny push do wszystkich z tokenem. Usunięcie całej historii z 30 dniami dla programisty. Podpięcie pod istniejące usunięcie konta, od razu i bez powrotu.

Zależy od: 11.

Gotowe, gdy: skasowanie historii zeruje wkład w liczniku globalnym, a żaden push nie wychodzi sam.

### 13. Połączenie ćwiczeń z celami i odznakami

Cel: decyzja, które ukończone ćwiczenie zasila który cel i którą odznakę, potem samo naliczanie. Bez tej decyzji numer się nie zaczyna.

Zależy od: 6, 7, 9 i 10.

Gotowe, gdy: uzgodnione ćwiczenia po synchronizacji przesuwają właściwy cel albo odznakę, a reszta nadal nie.
