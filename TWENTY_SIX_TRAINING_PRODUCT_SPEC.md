# TWENTY SIX TRAINING

## Specyfikacja funkcjonalna projektu

**Status:** uporządkowana specyfikacja zatwierdzonych wymagań  
**Przeznaczenie:** dokumentacja projektu i instrukcja dla Cursor  
**Zakres:** aplikacja użytkownika, treningi, Plany, Wyzwania, Eventy, synchronizacja, statystyki i panel administratora.

---

# 1. Instrukcje dla Cursor – sposób współpracy i zakres samodzielności

Cursor powinien traktować tę specyfikację jako źródło zatwierdzonych wymagań funkcjonalnych projektu Twenty Six Training.

## 1.1. Nie projektuj samodzielnie przebiegu pojedynczych ćwiczeń

Widoki prezentowane użytkownikowi podczas wykonywania poszczególnych pojedynczych ćwiczeń będą projektowane i uzgadniane z właścicielem projektu na bieżąco.

Nie należy samodzielnie wymyślać ani wdrażać:

- układu ekranów poszczególnych ćwiczeń,
- sposobu prezentowania instrukcji,
- rozmieszczenia przycisków i elementów sterujących,
- sposobu wprowadzania wyników,
- szczegółowego przebiegu kolejnych etapów ćwiczenia,
- przejść pomiędzy ekranami,
- dodatkowych komunikatów i interakcji specyficznych dla danego ćwiczenia.

Jeżeli podczas prac pojawi się potrzeba zaprojektowania lub zmodyfikowania widoku pojedynczego ćwiczenia, Cursor powinien najpierw poinformować o tym właściciela projektu.

Właściciel projektu będzie uzgadniał i projektował te elementy z Cursorem na bieżąco.

## 1.2. Nie projektuj samodzielnie przebiegu całych Planów

Jeżeli pojawi się potrzeba szczegółowego zaprojektowania przebiegu realizacji całego Planu, Cursor również powinien poinformować o tym właściciela projektu i poczekać na wspólne ustalenia.

Dotyczy to między innymi:

- ekranów przejściowych,
- szczegółowego przebiegu ćwiczeń w Planie,
- sposobu prezentowania kolejnych elementów,
- dodatkowych interakcji podczas wykonywania Planu,
- sposobu wizualnego przedstawienia całego przebiegu sesji.

Ogólne zasady realizacji Planów zawarte w tej specyfikacji są zatwierdzone i należy je respektować. Nie oznacza to jednak zgody na samodzielne projektowanie wszystkich szczegółowych ekranów.

## 1.3. Zasada konsultowania nieustalonych funkcji

Cursor nie powinien samodzielnie podejmować istotnych decyzji funkcjonalnych, których nie opisano w specyfikacji.

Jeżeli podczas implementacji pojawi się niejasność lub potrzeba nowego rozwiązania, powinien:

1. Opisać, czego dotyczy problem.
2. Przedstawić go właścicielowi projektu.
3. Poczekać na decyzję.
4. Dopiero po uzgodnieniu rozwiązania przystąpić do implementacji.

Nie należy dodawać funkcji, ekranów ani zachowań wyłącznie na podstawie własnych przypuszczeń.

---

# 2. Główne założenia aplikacji

Twenty Six Training jest aplikacją treningową, w której użytkownik może wykonywać pojedyncze ćwiczenia, gotowe Plany treningowe, Wyzwania oraz Eventy.

Aplikacja rozróżnia:

- **Ćwiczenia** – pojedyncze jednostki treningowe.
- **Plany** – uporządkowane zestawy ćwiczeń i przerw.
- **Wyzwania** – indywidualne, powtarzalne próby z możliwością poprawiania wyników.
- **Eventy** – czasowe rywalizacje z oficjalnymi próbami, klasyfikacją i medalami.
- **Wolną grę** – tryb gry, który nie wpływa na statystyki treningowe.

Dane treningowe użytkownika są prywatne. Widzi je właściciel konta oraz administrator. Inni użytkownicy nie mają dostępu do indywidualnych wyników i historii treningowej. Wyjątkiem są wyniki dobrowolnie opublikowane w rankingach Wyzwań oraz oficjalne klasyfikacje Eventów.

# 3. Główny ekran Trening

Na ekranie głównym znajdują się:

- Rozpocznij trening
- Plany
- Wyzwania
- Biblioteka ćwiczeń
- Wolna gra
- Analiza zawodnika
- Moje rekordy
- Moje cele
- Ulubione
- Moje treningi

Ekran ma minimalistyczny charakter. Nie pokazujemy na nim ostatnich treningów ani dodatkowych statystyk.

Jeśli użytkownik ma niezakończony Plan zapisany lokalnie, może zobaczyć subtelny komunikat „Wznów trening” oraz przycisk „Anuluj trening”.

Anulowanie niezakończonego Planu z tego miejsca nie wymaga dodatkowego potwierdzenia. Usuwa lokalny stan niezakończonej sesji.

## 3.1. Rozpocznij trening

Użytkownik może przejść do:

- Planu treningowego,
- pojedynczego ćwiczenia,
- Wyzwania,
- Ulubionych,
- ostatnio używanych pozycji.

Lista ostatnio używanych obejmuje maksymalnie trzy różne pozycje. Jest przechowywana lokalnie na urządzeniu. Pojawia się dopiero po rozpoczęciu przynajmniej jednej sesji. Wybranie pozycji otwiera jej ekran przygotowania, a nie uruchamia trening automatycznie.

Użytkownik nie może tworzyć własnych Planów ani zestawów ćwiczeń.

## 3.2. Wolna gra

Wolna gra pozostaje osobnym trybem. Obsługuje od 1 do 8 graczy.

Nie wpływa na:

- statystyki treningowe,
- rekordy,
- cele,
- odznaki,
- licznik ukończonych ćwiczeń.

# 4. Ulubione

Ulubione to jedna wspólna sekcja dostępna bezpośrednio z głównego ekranu Trening.

Zawiera trzy zakładki:

1. Wyzwania
2. Plany
3. Ćwiczenia

Domyślnie otwiera się zakładka Wyzwania. Aplikacja nie zapamiętuje ostatnio wybranej zakładki.

Użytkownik może:

- dodawać i usuwać pozycje z Ulubionych,
- zmieniać kolejność pozycji metodą przeciągnij i upuść,
- zarządzać kolejnością niezależnie w każdej zakładce.

Ulubione i ich kolejność synchronizują się między urządzeniami.

Dodawanie i usuwanie jest dostępne zarówno na listach, jak i ekranach szczegółowych. Na kafelkach Planów dostępna jest również bezpośrednia kontrolka gwiazdki.

Komunikaty:

- „Dodano do Ulubionych”
- „Usunięto z Ulubionych”

Nie ma wyszukiwarki, opcji zmiany widoku ani funkcji „Wyczyść wszystkie”.

Jeżeli administrator usunie Plan lub ćwiczenie z Biblioteki, pozycja znika z Ulubionych wszystkich użytkowników.

Nieaktywne Wyzwania pozostają w Ulubionych z oznaczeniem „Nieaktywne”, ale nie można ich uruchomić ani dodawać do Ulubionych, gdy są nieaktywne. Anulowane Wyzwania są usuwane z Ulubionych wszystkich użytkowników.

Plany i ćwiczenia można dodawać do Ulubionych niezależnie od tego, czy użytkownik wcześniej z nich korzystał.

# 5. Biblioteka ćwiczeń

Biblioteka zawiera gotowe ćwiczenia przygotowane przez administratora.

Dostępne funkcje:

- wyszukiwarka,
- filtrowanie według poziomu umiejętności,
- szczegóły ćwiczeń.

Biblioteka oraz szybkie uruchamianie pojedynczych ćwiczeń korzystają z tych samych definicji ćwiczeń.

Użytkownik nie może samodzielnie modyfikować parametrów ćwiczenia.

## 5.1. Dezaktywowanie ćwiczenia

Administrator może dezaktywować ćwiczenie bez jego trwałego usuwania.

Po dezaktywacji:

- ćwiczenie znika z Biblioteki widocznej dla użytkowników,
- aktywne Plany zawierające ćwiczenie zostają automatycznie dezaktywowane,
- aktywne Wyzwania zawierające ćwiczenie zostają automatycznie dezaktywowane,
- aktywne Eventy zawierające ćwiczenie zostają automatycznie dezaktywowane,
- rozpoczęte treningi i próby korzystające z ćwiczenia zostają unieważnione.

Unieważnione próby nie trafiają do historii i nie wpływają na statystyki, rekordy, cele ani klasyfikacje.

Jeżeli dezaktywacja ćwiczenia spowoduje dezaktywację Eventu, Event pozostaje nieaktywny. Nie jest automatycznie anulowany ani usuwany.

Dezaktywowane ćwiczenia są dostępne w osobnej liście panelu administratora. Administrator może je ponownie aktywować.

Ponowna aktywacja ćwiczenia nie aktywuje automatycznie Planów, Wyzwań ani Eventów, które wcześniej zostały dezaktywowane. Administrator decyduje o ich ponownej aktywacji samodzielnie.

W szczegółach ćwiczenia panel administratora pokazuje listę Planów, Wyzwań i Eventów zawierających dane ćwiczenie.

## 5.2. Usuwanie ćwiczenia

Usunięcie ćwiczenia jest odrębną operacją od jego dezaktywacji.

Jeżeli administrator usuwa ćwiczenie, które jest wykorzystywane w istniejącym Planie lub Wyzwaniu, ćwiczenie zostaje również usunięte ze struktury tego Planu lub Wyzwania.

Nie pozostawiamy w Planie ani Wyzwaniu odwołania do usuniętego ćwiczenia.

Usunięcie ćwiczenia nie jest tym samym co jego dezaktywacja:

- dezaktywacja zachowuje ćwiczenie i jego zależności, ale blokuje korzystanie z niego,
- usunięcie usuwa ćwiczenie z Biblioteki oraz z Planów i Wyzwań, w których było wykorzystywane.

Nie należy automatycznie utożsamiać usunięcia ćwiczenia z anulowaniem całego Planu lub Wyzwania. Usunięcie ćwiczenia ze struktury nie jest równoznaczne z anulowaniem tych elementów.

Sposób obsługi zależności Eventów przy trwałym usuwaniu ćwiczenia wymaga osobnego uzgodnienia, jeżeli taka sytuacja pojawi się podczas implementacji. Nie należy samodzielnie wymyślać zachowania.

# 6. Plany treningowe

## 6.1. Katalog Planów

Plany znajdują się na wspólnej liście bez podziału na kategorie tematyczne.

Dostępne funkcje:

- wyszukiwanie po nazwie,
- filtrowanie według poziomu trudności,
- filtrowanie według deklarowanego czasu trwania,
- sortowanie według nazwy i poziomu trudności,
- sortowanie według czasu trwania.

Poziomy trudności:

- Łatwy
- Średni
- Trudny

Poziom trudności przypisuje administrator podczas tworzenia Planu.

Dostępne czasy trwania:

- 30 minut,
- 60 minut,
- 90 minut,
- 120 minut.

Domyślnie lista jest sortowana alfabetycznie A–Z. Przy każdym ponownym wejściu filtry i sortowanie wracają do ustawień domyślnych.

Kafelek Planu pokazuje:

- nazwę,
- poziom trudności,
- deklarowany czas trwania,
- możliwość dodania do Ulubionych.

Nie pokazuje liczby ćwiczeń, daty ostatniego wykonania ani liczby ukończonych sesji użytkownika.

## 6.2. Szczegóły Planu

Ekran szczegółów zawiera:

- pełny opis Planu,
- deklarowany czas trwania,
- liczbę ukończeń Planu przez aktualnego użytkownika,
- możliwość dodania do Ulubionych,
- przycisk „Podgląd planu”,
- przycisk „Rozpocznij plan”.

Podgląd Planu jest domyślnie zwinięty przy każdym wejściu na ekran szczegółów.

Po rozwinięciu pokazuje pełną strukturę Planu:

- ćwiczenia w kolejności wykonania,
- czas każdego ćwiczenia,
- przerwy,
- czas każdej przerwy.

Użytkownik może przed rozpoczęciem zapoznać się z opisem poszczególnych ćwiczeń.

Plan rozpoczyna się jako całość. Nie można uruchomić pojedynczego elementu Planu z poziomu jego szczegółów.

Użytkownik wykonuje Plan zgodnie z kolejnością ustaloną przez administratora. Nie może zmieniać kolejności ani pomijać elementów przed rozpoczęciem.

Przycisk „Rozpocznij plan” jest zawsze widoczny, niezależnie od stanu podglądu. Uruchamia Plan bez dodatkowego ekranu potwierdzenia.

## 6.3. Tworzenie Planów przez administratora

Administrator tworzy Plan od podstaw, korzystając wyłącznie z istniejących ćwiczeń Biblioteki.

Może:

- ustalać kolejność ćwiczeń,
- dodawać to samo ćwiczenie wielokrotnie,
- dodawać dowolną liczbę przerw,
- umieszczać przerwy w dowolnym miejscu, również na początku lub końcu,
- ustalać czas każdej przerwy.

Nie może modyfikować parametrów ćwiczeń ani ich czasu w kontekście Planu.

Administrator wybiera deklarowany czas Planu spośród wartości 30, 60, 90 i 120 minut. Czas nie jest automatycznie wyliczany z sumy elementów. System nie ostrzega, jeśli deklarowany czas różni się od sumy czasów ćwiczeń i przerw.

Nowy Plan otrzymuje status „Nieaktywny”. Administrator musi ręcznie go aktywować.

Po pierwszym opublikowaniu Planu nie można edytować żadnej jego właściwości. Dotyczy to nazwy, opisu, grafiki, trudności, czasu, kolejności ćwiczeń i przerw. Zmiana wymaga stworzenia nowego Planu od podstaw.

## 6.4. Dezaktywacja i anulowanie Planu

Dezaktywacja:

- ukrywa Plan w aktywnym katalogu,
- pozostawia go w Ulubionych z oznaczeniem „Nieaktywny”,
- uniemożliwia rozpoczęcie nowej sesji,
- unieważnia sesje będące w trakcie realizacji,
- nie pozwala wznowić ani ukończyć unieważnionej sesji.

Administrator ma osobną listę „Nieaktywne plany”. Może przeglądać szczegóły i ponownie aktywować Plan, ale nie może go edytować.

Anulowanie Planu jest nieodwracalne:

- Plan znika z aplikacji i panelu administratora,
- zostaje usunięty z Ulubionych wszystkich użytkowników,
- rozpoczęte sesje zostają unieważnione,
- nie ma możliwości przywrócenia Planu,
- pełny ślad techniczny pozostaje wyłącznie dla programisty.

Anulowanie wymaga ponownego wpisania hasła administratora i końcowego potwierdzenia.

## 6.5. Realizacja Planu

Podczas realizacji widoczny jest:

- aktualny element,
- nazwa następnego elementu,
- pasek postępu w formacie np. „3 z 8”.

Licznik obejmuje zarówno ćwiczenia, jak i przerwy. Pominięte elementy również zwiększają licznik postępu. Nie pokazujemy procentowego postępu.

Ćwiczenia przechodzą automatycznie do kolejnego elementu. Przerwy rozpoczynają się automatycznie i mają timer.

Podczas przerwy użytkownik może:

- pominąć przerwę – po potwierdzeniu,
- przedłużyć ją – każde użycie dodaje jedną minutę,
- zatrzymać timer i wznowić go od tego samego momentu.

Po zakończeniu przerwy kolejne ćwiczenie rozpoczyna się automatycznie.

Użytkownik może pominąć aktualne ćwiczenie po potwierdzeniu w oknie modalnym. Pominięte ćwiczenie otrzymuje status „Pominięty”.

Funkcja „Cofnij” jest nieograniczona w obrębie aktualnego ćwiczenia. Nie można cofnąć poprzedniego, zakończonego elementu. Przejście do kolejnego elementu zatwierdza poprzedni wynik.

Podczas treningu nie pokazujemy punktacji ani statystyk. Widoczne są wyłącznie informacje potrzebne do wykonania ćwiczenia lub Planu.

Użytkownik może zakończyć Plan przed czasem. Wymaga to potwierdzenia modalnego. Po potwierdzeniu wraca bezpośrednio na ekran Trening. Sesja zostaje definitywnie zamknięta, nie można jej wznowić ani przywrócić. Nie trafia do historii i nie wpływa na statystyki, rekordy ani cele.

Szczegółowe widoki i interakcje poszczególnych ćwiczeń oraz szczegółowy przebieg ekranów całego Planu będą uzgadniane z właścicielem projektu na bieżąco. Cursor nie powinien samodzielnie ich projektować.

## 6.6. Podsumowanie Planu

Po ukończeniu Planu użytkownik widzi jedno wspólne podsumowanie całej sesji.

Na górze:

- nazwa Planu,
- data ukończenia,
- łączna punktacja.

Łączna punktacja jest sumą punktów z ukończonych ćwiczeń. Pominięte ćwiczenia nie wnoszą punktów.

Poniżej znajduje się lista ćwiczeń w kolejności wykonania. Każde ćwiczenie jest osobną sekcją rozwijaną.

Domyślnie wszystkie sekcje są zwinięte. Można rozwinąć kilka jednocześnie. Przy każdym nowym otwarciu podsumowania wszystkie sekcje ponownie są zwinięte.

Rozwinięta sekcja pokazuje wynik punktowy i szczegółowe statystyki ćwiczenia. Nie pokazuje listy każdego rzutu ani każdego trafienia.

Pominięte ćwiczenia mają oznaczenie „Pominięty” i nie pokazują statystyk.

Przyciski po ukończeniu:

- „Powrót do ekranu treningu”
- „Powtórz plan”

Powtórzenie Planu wymaga potwierdzenia modalnego:
„Czy na pewno chcesz rozpocząć ten plan ponownie?”

Przyciski: „Anuluj” i „Rozpocznij”.

Powtórzenie tworzy nową, niezależną sesję. Nie przenosi wyników, postępu ani statusów z poprzedniej sesji.

Jeśli Plan jest nieaktywny, przycisk powtórzenia jest nieaktywny.

Podsumowanie otwarte z historii ma przyciski:

- „Wróć do historii”
- „Powtórz plan”

Powrót do historii zachowuje poprzedni stan listy, filtry i pozycję przewijania.

# 7. Pojedyncze ćwiczenia

Po zakończeniu pojedynczego ćwiczenia użytkownik od razu widzi podsumowanie.

Podsumowanie zawiera wynik i szczegółowe statystyki. Nie pokazuje pełnego dziennika każdego rzutu.

Przyciski:

- „Powtórz ćwiczenie”
- „Powrót do ekranu treningu”

Powtórzenie wymaga potwierdzenia modalnego i rozpoczyna nową sesję od początku. Poprzedni wynik pozostaje w historii.

Jeśli podsumowanie otwarto z historii, zamiast „Powrót do ekranu treningu” widoczny jest przycisk „Wróć do historii”.

Pojedynczego ćwiczenia nie można wznowić po przerwaniu. Przerwanie wymaga potwierdzenia modalnego. Po potwierdzeniu użytkownik wraca do głównego ekranu Trening. Niedokończona sesja jest bezpowrotnie tracona i nie wpływa na historię, statystyki, rekordy ani cele.

Nie ma funkcji pomijania pojedynczego ćwiczenia.

Funkcja „Cofnij” jest nieograniczona w obrębie aktualnego ćwiczenia.

Podczas ćwiczenia nie pokazujemy punktacji ani statystyk. Wyniki są dostępne dopiero po zakończeniu.

**Ważne dla Cursor:** szczegółowy wygląd i przebieg ekranów każdego pojedynczego ćwiczenia będzie projektowany wspólnie z właścicielem projektu. Nie należy samodzielnie tworzyć takich widoków ani zakładać ich ostatecznego układu.

# 8. Wyzwania

## 8.1. Kategorie

W aplikacji istnieją dwie kategorie:

**Wyzwania**

- indywidualne,
- bezterminowe,
- powtarzalne,
- pozwalają poprawiać wyniki,
- mogą mieć cele i odznaki.

**Eventy**

- czasowe,
- konkurencyjne,
- mają oficjalne próby,
- mają wspólną klasyfikację i medale.

Nie istnieje osobna kategoria „Wyzwania specjalne”.

54-Dart Challenge jest zwykłym Wyzwaniem, z własną mechaniką, punktacją, celami i odznakami.

## 8.2. Tworzenie i edycja Wyzwań

Nowe Wyzwanie tworzone jest od podstaw i domyślnie otrzymuje status „Nieaktywne”.

Nie ma funkcji kopiowania istniejącego Wyzwania ani wersjonowania.

Po pierwszym opublikowaniu Wyzwania administrator nie może edytować żadnej jego właściwości, w tym nazwy, opisu, grafiki, mechaniki, punktacji i zasad.

Jeśli potrzebne są zmiany, administrator dezaktywuje lub anuluje poprzednie Wyzwanie i tworzy nowe.

## 8.3. Dezaktywacja Wyzwania

Dezaktywacja jest odwracalna.

Po dezaktywacji:

- Wyzwanie znika z aktywnego katalogu,
- rozpoczęte próby zostają unieważnione,
- użytkownik widzi komunikat „Wyzwanie zostało dezaktywowane przez administratora”,
- unieważnione próby nie trafiają do historii i nie wpływają na statystyki, rekordy, cele ani odznaki,
- ranking znika z widoku użytkowników,
- Wyzwanie pozostaje w Ulubionych z oznaczeniem „Nieaktywne”.

Administrator ma osobną listę nieaktywnych Wyzwań. Może przeglądać ich dane i ponownie aktywować, ale nie może ich edytować.

Po ponownej aktywacji ranking wraca automatycznie wraz z wcześniejszymi opublikowanymi wynikami.

Dezaktywacja wymaga zwykłego potwierdzenia, bez ponownego wpisywania hasła.

## 8.4. Anulowanie Wyzwania

Anulowanie jest nieodwracalne.

Po anulowaniu:

- Wyzwanie znika z aplikacji i panelu administratora,
- rozpoczęte próby zostają unieważnione,
- ranking zostaje usunięty,
- Wyzwanie znika z Ulubionych wszystkich użytkowników,
- dane użytkownika dotyczące Wyzwania zostają usunięte z jego widoku: historia, wyniki, statystyki, rekordy i opublikowane wyniki.

Zdobyte odznaki pozostają na profilu użytkownika, ale zachowują wyłącznie nazwę Wyzwania. Nie pozostają przy nich wyniki, miejsca, statystyki ani historia.

Pełny ślad techniczny pozostaje w bazie wyłącznie dla programisty. Administrator nie ma do niego dostępu.

Anulowanie wymaga ponownego wpisania hasła administratora i końcowego potwierdzenia.

## 8.5. Próby Wyzwań

Wyzwania nie można pauzować.

Próba nie może zostać wznowiona po przerwaniu. Przerwanie wymaga potwierdzenia modalnego. Po potwierdzeniu użytkownik wraca do głównego ekranu Trening.

Nieukończona próba zostaje utracona i nie trafia do historii ani statystyk.

Nie ma możliwości pomijania ćwiczeń lub etapów. Wszystkie elementy Wyzwania są obowiązkowe.

Podczas próby nie pokazujemy punktacji ani statystyk. Są dostępne dopiero po ukończeniu.

## 8.6. Historia Wyzwań

Historia zawiera wszystkie ukończone próby, niezależnie od tego, czy wynik został opublikowany.

Użytkownik nie może usuwać pojedynczych ukończonych prób. Może usunąć całą historię treningową.

Historia pozwala:

- filtrować po dacie ukończenia,
- sortować od najnowszych do najstarszych,
- sortować według wyniku od najlepszego do najgorszego.

Szczegóły próby pokazują wynik końcowy, wyniki etapów i szczegółowe statystyki. Nie przechowujemy pełnego dziennika każdego rzutu.

Po ukończeniu użytkownik może:

- opublikować wynik, jeśli spełnia warunki,
- spróbować ponownie.

Ponowna próba tworzy niezależną sesję. Poprzednia pozostaje w historii.

Użytkownik może wycofać publikację wyniku w dowolnym momencie.

## 8.7. Rankingi Wyzwań

Wyniki są domyślnie prywatne. Użytkownik dobrowolnie publikuje ukończoną próbę.

Ranking zawiera wyłącznie opublikowane wyniki.

Każdy użytkownik ma w rankingu tylko jedną pozycję – swój najlepszy opublikowany wynik.

Nowy wynik może zostać opublikowany tylko wtedy, gdy jest lepszy od aktualnie opublikowanego. Wynik równy lub gorszy nie może zastąpić lepszego wyniku.

Użytkownik może wycofać publikację. Wynik znika z rankingu, ale pozostaje w prywatnej historii.

Remisy są klasyfikowane systemem miejsc konkursowych, np. 1, 1, 3. Użytkownicy z takim samym wynikiem są porządkowani alfabetycznie według nazwy gracza. Data nie rozstrzyga remisu.

Ranking znajduje się w osobnej zakładce szczegółów aktywnego Wyzwania.

Jeśli nie ma opublikowanych wyników, widoczny jest komunikat:
„Brak opublikowanych wyników. Podejmij wyzwanie jako pierwszy”.

Domyślnie ranking pokazuje 10 najlepszych wyników. Przycisk „Pokaż więcej” ładuje kolejne 10.

Jeżeli użytkownik znajduje się poza pierwszą dziesiątką, jego pozycja jest pokazana osobno. Jeśli znajduje się w pierwszej dziesiątce, jest widoczny normalnie na liście.

Każdy wiersz rankingu zawiera:

- miejsce,
- nazwę gracza,
- avatar,
- najlepszy opublikowany wynik,
- datę uzyskania wyniku.

Po opublikowaniu użytkownik otrzymuje komunikat o sukcesie wraz z osiągniętym miejscem i wynikiem.

Nie wysyłamy powiadomień, gdy inny użytkownik wyprzedzi gracza w rankingu.

# 9. 54-Dart Challenge

Wyzwanie składa się z 54 rzutów i trzech etapów.

## Etap 1: Shanghai 10–15

- 6 sektorów,
- 3 rzuty na sektor,
- Single: 15 punktów,
- Double: 30 punktów,
- Triple: 45 punktów,
- bonus 100 punktów za S+D+T w tej samej wizycie.

Maksymalny wynik: 1140 punktów.

## Etap 2: Shanghai 16–20

- 5 sektorów,
- 3 rzuty na sektor,
- ta sama punktacja,
- bonus 100 punktów za S+D+T w tej samej wizycie.

Maksymalny wynik: 950 punktów.

## Etap 3: Doubles 1–20 i Bull

- jeden rzut do każdego D1–D20,
- następnie jeden rzut do Bull,
- trafiony double: 50 punktów,
- Inner Bull: 100 punktów,
- Outer Bull: 0 punktów.

Maksymalny wynik: 1100 punktów.

Maksymalny wynik całego Wyzwania: 3190 punktów.

Podsumowanie pokazuje wynik łączny. Szczegóły etapów są dostępne w historii.

Cele dla tego Wyzwania obejmują wyniki od 100 do 3100 punktów, co 100 punktów. Liczy się najlepsza ukończona próba. Po odblokowaniu kolejnego celu jego postęp zaczyna się od zera.

Wyzwanie jest dostępne stale, chyba że administrator je dezaktywuje lub anuluje.

# 10. Eventy

Eventy są czasowymi rywalizacjami z oficjalnymi próbami, klasyfikacją i medalami.

## 10.1. Zarządzanie Eventem

Przed rozpoczęciem Eventu administrator może edytować:

- nazwę,
- opis,
- grafikę,
- datę rozpoczęcia,
- datę zakończenia.

Po rozpoczęciu Eventu można edytować:

- nazwę,
- opis,
- grafikę,
- datę zakończenia.

Nie można edytować daty rozpoczęcia po starcie Eventu. Mechanika i punktacja pozostają niezmienne.

Administrator może:

- rozpocząć Event wcześniej,
- wstrzymać aktywny Event,
- wznowić wstrzymany Event,
- ręcznie zmienić status Eventu,
- zdyskwalifikować uczestnika,
- usunąć lub anulować Event zgodnie z jego zasadami.

Wcześniejsze rozpoczęcie powoduje, że faktyczny czas startu staje się datą rozpoczęcia. Data zakończenia pozostaje bez zmian, chyba że administrator osobno ją edytuje.

Operacje wymagające szczególnego zabezpieczenia – takie jak dyskwalifikacja, zmiana statusu, pauzowanie, wznawianie i usuwanie – wymagają ponownego wpisania hasła administratora i potwierdzenia zgodnie z ustalonymi zasadami.

## 10.2. Pauzowanie Eventu

Podczas pauzy:

- nowi uczestnicy nie mogą dołączyć,
- nie można rozpoczynać nowych prób,
- rozpoczęte próby mogą zostać dokończone,
- postęp i wyniki rozpoczętych prób są zachowane.

Po wznowieniu ponownie można dołączać do Eventu i rozpoczynać próby.

Pauzowanie i wznowienie nie wysyła automatycznego powiadomienia push. Administrator może wysłać powiadomienie ręcznie za pomocą uniwersalnego narzędzia.

## 10.3. Wcześniejsze zakończenie Eventu

Jeżeli administrator zakończy Event przed planowanym terminem:

- nowi uczestnicy nie mogą dołączyć,
- nie można rozpoczynać nowych prób,
- Event znika z widoku użytkowników,
- rozpoczęte próby mogą zostać dokończone.

Event nie otrzymuje od razu statusu „Zakończony”.

Status „Zakończony” zostaje nadany dopiero wtedy, gdy każda rozpoczęta próba ma status ukończonej albo unieważnionej.

Po zakończeniu ostatniej rozpoczętej próby ustalana jest ostateczna klasyfikacja i przyznawane są medale.

Następnie Event trafia do sekcji zakończonych Eventów i pozostaje tam dostępny.

Jeżeli uczestnik sam przerwie próbę, próba zostaje unieważniona i nie wpływa na klasyfikację. Administrator nie ma możliwości ręcznego unieważniania rozpoczętych, niedokończonych prób.

## 10.4. Klasyfikacja i medale

Klasyfikacja jest ustalana automatycznie na podstawie ukończonych oficjalnych prób.

Administrator nie może ręcznie zmieniać wyników, miejsc ani przyznawać lub odbierać medali.

Medale:

-   1. miejsce,
-   2. miejsce,
-   3. miejsce.

Remisy stosują system miejsc konkursowych, np. 1, 1, 3.

Dyskwalifikowany uczestnik jest wykluczony z przyznawania medali. Po dyskwalifikacji lub jej cofnięciu klasyfikacja i medale są przeliczane.

Ukończone Eventy są widoczne również dla osób, które nie brały w nich udziału. Dane prywatnych prób innych uczestników pozostają ukryte. Użytkownik widzi szczegóły własnej próby.

## 10.5. Próby Eventów

Eventów nie można pauzować z poziomu użytkownika ani wznowić po przerwaniu.

Przerwanie wymaga potwierdzenia modalnego. Po potwierdzeniu próba zostaje unieważniona i nie trafia do historii ani statystyk.

Nie można pomijać ćwiczeń ani etapów.

Podczas próby nie pokazujemy punktacji ani statystyk.

## 10.6. Usunięcie i anulowanie Eventu

Anulowanie Eventu wymaga ponownego wpisania hasła administratora i potwierdzenia.

Po anulowaniu:

- Event znika z katalogu,
- rozpoczęte próby zostają unieważnione,
- nie ma klasyfikacji ani medali.

Usunięcie Eventu na dowolnym etapie wymaga ponownego wpisania hasła i potwierdzenia.

Po usunięciu:

- Event znika z aplikacji użytkowników,
- rozpoczęte próby zostają unieważnione,
- pełny ślad techniczny pozostaje wyłącznie dla programisty,
- administrator nie ma możliwości przywrócenia Eventu.

Po zakończeniu Eventu administrator może go usunąć, ale nie może już edytować jego nazwy, opisu, grafiki ani dat.

## 10.7. Panel administratora – dane Eventów

Lista uczestników w panelu pokazuje wyłącznie osoby, które ukończyły oficjalną próbę.

Dla ukończonych prób dostępne są szczegółowe statystyki. Nie ma dostępu do szczegółów prób nieukończonych, przerwanych lub unieważnionych.

Administrator nie może edytować wyników ani zapisanych statystyk.

Nie ma eksportu CSV ani Excel.

# 11. Moje treningi – wspólna historia

Wszystkie ukończone sesje są zapisywane w jednej wspólnej historii „Moje treningi”.

Historia obejmuje:

- Plany,
- pojedyncze ćwiczenia,
- Wyzwania,
- Eventy.

Historia zawiera wyłącznie ukończone sesje. Sesje przerwane, niedokończone, anulowane lub unieważnione nie trafiają do historii.

Lista jest sortowana chronologicznie od najnowszych do najstarszych.

Każdy element listy pokazuje:

- nazwę,
- rodzaj treningu,
- datę,
- końcowy wynik.

Nie pokazujemy godziny wykonania.

Historia umożliwia łączenie filtrów:

- rodzaj treningu,
- zakres dat.

Rodzaj treningu pozwala zaznaczyć wiele typów jednocześnie:

- Plany,
- Ćwiczenia,
- Wyzwania,
- Eventy.

Filtry są domyślnie zwinięte pod etykietą „Filtruj”. Etykieta pokazuje liczbę aktywnych filtrów, np. „Filtruj (2)”.

Dostępny jest przycisk „Wyczyść filtry”. Zmiany filtrów są stosowane automatycznie.

Domyślnie ładowanych jest pięć najnowszych pozycji. Przycisk „Załaduj więcej” pobiera kolejne pięć. To samo zachowanie obowiązuje przy filtrowaniu.

Po otwarciu elementu historii użytkownik trafia bezpośrednio do szczegółowego podsumowania.

Powrót z podsumowania zachowuje poprzednie filtry, pozycję przewijania i stan listy.

Wejście do historii z innej części aplikacji rozpoczyna od nowa ładowanie listy i nie zachowuje poprzednich filtrów.

Użytkownik nie może:

- usuwać pojedynczych sesji,
- edytować wyników,
- zmieniać nazw sesji,
- dodawać notatek,
- udostępniać podsumowań jako obrazów.

## 11.1. Licznik ukończonych ćwiczeń

Na górze „Moje treningi” znajduje się jeden pełnowymiarowy kafelek z łącznym licznikiem ukończonych indywidualnych ćwiczeń.

To nie jest licznik sesji ani Planów.

Zasady:

- pojedyncze ukończone ćwiczenie = +1,
- Plan zawierający 8 ukończonych ćwiczeń = +8,
- przerwy nie są liczone,
- pominięte ćwiczenia nie są liczone,
- ukończone ćwiczenia z Wyzwań i Eventów również są liczone.

Do licznika wchodzą wyłącznie dane poprawnie zsynchronizowane z API.

# 12. Historia, pamięć lokalna i synchronizacja

Aplikacja działa również offline.

## 12.1. Aktywne sesje

Tylko jeden aktywny trening może istnieć na danym urządzeniu. Nie jest to ograniczenie całego konta.

Jeśli użytkownik rozpoczął Plan na urządzeniu A, może rozpocząć inny trening na urządzeniu B. Nie unieważnia to sesji na urządzeniu A.

Postęp aktywnej sesji jest przechowywany lokalnie na urządzeniu, na którym sesja została rozpoczęta. Podczas trwania sesji nie jest wymagany kontakt z API.

Plan można pauzować i wznowić z dokładnie tego samego miejsca.

Po zamknięciu aplikacji lub przerwaniu jej działania Plan wraca do ostatniego zapisanego punktu.

Po ponownym otwarciu aplikacji użytkownik widzi subtelny komunikat „Wznów trening” oraz przycisk „Anuluj trening”.

Po wylogowaniu niezakończony Plan zostaje usunięty lokalnie. Nie można go wznowić po ponownym zalogowaniu.

Pojedyncze ćwiczenia, Wyzwania i Eventy nie są wznawiane po przerwaniu.

## 12.2. Zakończone sesje oczekujące na synchronizację

Po ukończeniu treningu aplikacja zapisuje lokalnie podsumowanie i szczegółowe statystyki.

Sesja oczekująca na synchronizację:

- jest dostępna lokalnie,
- nie blokuje rozpoczęcia kolejnego treningu,
- jest powiązana z kontem, na którym została wykonana.

Po wylogowaniu dane oczekujące na synchronizację pozostają przypisane do pierwotnego konta. Nie mogą zostać przesłane na inne konto.

Po ponownym zalogowaniu na właściwe konto synchronizacja jest wznawiana automatycznie.

Zakończona sesja nie jest już aktywną sesją i nie blokuje rozpoczęcia kolejnego treningu.

## 12.3. Synchronizacja

Synchronizacja odbywa się automatycznie po odzyskaniu połączenia z internetem.

Po ukończeniu sesji aplikacja przesyła do serwera:

- podsumowanie sesji,
- szczegółowe statystyki,
- wyniki poszczególnych ćwiczeń w Planie,
- status pominiętych ćwiczeń.

Pełny zapis każdego rzutu nie jest przechowywany ani przesyłany.

Dane ukończonej sesji na serwerze są niezmienne.

Jeżeli aplikacja zostanie zamknięta podczas wysyłania danych, po kolejnym uruchomieniu automatycznie dokończy wysyłanie. Nie może powstać drugi wpis historii dla tej samej sesji.

Do czasu synchronizacji dane nie wpływają na statystyki, rekordy ani cele.

Po poprawnej synchronizacji system automatycznie aktualizuje wszystkie odpowiednie statystyki, rekordy i cele.

## 12.4. Błędy synchronizacji

Zwykłe dane oczekujące na wysłanie mają oznaczenie „Do zsynchronizowania”.

Po poprawnej synchronizacji oznaczenie znika.

Jeżeli wystąpi błąd synchronizacji wynikający z niezgodności danych:

- lokalny rekord pozostaje,
- pojawia się oznaczenie „Błąd synchronizacji”,
- nie ma ręcznego przycisku ponowienia,
- system automatycznie próbuje ponownie w dłuższych odstępach,
- dane nie są automatycznie usuwane.

Status synchronizacji jest widoczny na liście historii, nie na ekranie szczegółów.

## 12.5. Pamięć lokalna

Domyślnie lokalnie przechowywanych jest pięć najnowszych poprawnie zsynchronizowanych sesji ze wszystkich kategorii.

Starsze dane są pobierane z serwera po użyciu „Załaduj więcej”.

Szczegóły starszej sesji są pobierane automatycznie po jej otwarciu.

Jeżeli użytkownik jest offline i nie ma lokalnej kopii szczegółów, widzi komunikat:
„Szczegóły niedostępne offline”.

Pamięć lokalna ma stały limit określony w konfiguracji aplikacji. Obejmuje wszystkie dane pobierane z serwera, w tym treningi, grafiki i miniatury.

Po osiągnięciu limitu aplikacja automatycznie usuwa najstarsze lokalne kopie już zsynchronizowanych sesji.

Sesje oczekujące na synchronizację lub mające błędy synchronizacji są chronione przed automatycznym usunięciem.

Nie ma ostrzeżenia o automatycznym usuwaniu lokalnych kopii ani możliwości ręcznego zarządzania pamięcią.

## 12.6. Ładowanie historii

Historia pobiera dane z serwera po pięć pozycji.

Przy aktywnych filtrach serwer zwraca wyłącznie pozycje spełniające warunki.

Podczas pobierania wyświetlany jest wskaźnik ładowania. Przycisk „Załaduj więcej” jest wtedy nieaktywny.

Jeśli pobieranie się nie powiedzie:

- dotychczas załadowana lista pozostaje,
- wyświetlany jest komunikat o błędzie.

Offline historia pokazuje dane dostępne lokalnie. Próba pobrania kolejnych pozycji pokazuje komunikat o braku połączenia.

Nie ma ręcznego odświeżania ani gestu przeciągnięcia w dół.

Jeżeli nowa sesja zsynchronizuje się, gdy historia jest już otwarta, lista nie aktualizuje się automatycznie. Nie pokazujemy powiadomienia ani komunikatu. Użytkownik musi opuścić historię i wejść do niej ponownie.

Przy ponownym wejściu pobieranych jest pięć najnowszych pozycji spełniających aktualne filtry.

# 13. Usuwanie całej historii treningowej

Użytkownik może usunąć całą historię treningową jednym działaniem.

Nie może usuwać pojedynczych sesji.

Usunięcie wymaga potwierdzenia modalnego.

Po potwierdzeniu:

- historia znika natychmiast z widoku użytkownika,
- dane są usuwane z lokalnych kopii na wszystkich urządzeniach,
- dane serwerowe przechodzą do 30-dniowego okresu przechowywania umożliwiającego przywrócenie przez programistę.

Po 30 dniach dane są trwale usuwane. Nie ma możliwości ich przywrócenia.

Usunięcie resetuje:

- licznik ukończonych ćwiczeń,
- statystyki,
- rekordy,
- wszystkie cele,
- wszystkie odznaki,
- wszystkie opublikowane wyniki we wszystkich rankingach Wyzwań.

Użytkownik znika z rankingów Wyzwań. Może ponownie rozpocząć Wyzwanie i opublikować nowy wynik.

Przywrócenie danych w ciągu 30 dni wykonuje wyłącznie programista. Przywraca ono cały poprzedni stan, w tym historię, statystyki, rekordy, cele, odznaki i opublikowane wyniki w rankingach.

Nie ma osobnej sekcji w panelu administratora do przywracania usuniętych danych. Przywrócenie nie wysyła automatycznego powiadomienia push.

Usunięcie konta jest osobną, istniejącą funkcją aplikacji. Usunięcie konta powoduje natychmiastowe usunięcie danych treningowych bez 30-dniowego okresu przechowywania i bez możliwości przywrócenia.

Nie ma eksportu danych treningowych.

# 14. Rekordy, statystyki i cele

Statystyki, rekordy i cele bazują na ukończonych, poprawnie zsynchronizowanych treningach.

Wyniki z ukończonych ćwiczeń w Planach, Wyzwaniach i Eventach są uwzględniane tak samo jak wyniki pojedynczych ćwiczeń.

Jeśli Plan zostanie ukończony z pominiętymi ćwiczeniami, wyniki ukończonych ćwiczeń są uwzględniane. Pominięte ćwiczenia nie wnoszą żadnych danych.

Przerwany Plan nie wpływa na statystyki, rekordy ani cele.

Rekordy są przypisane do konkretnego ćwiczenia i wariantu niezależnie od tego, czy ćwiczenie wykonano samodzielnie, czy w ramach Planu.

W sekcji „Moje rekordy” użytkownik widzi wyłącznie:

- nazwę ćwiczenia,
- najlepszy wynik,
- datę uzyskania.

Nie pokazujemy informacji, który Plan doprowadził do ustanowienia rekordu.

## 14.1. Cele

Istnieje pięć ścieżek celów:

- Scoring,
- Doubles,
- Checkouty,
- Regularność,
- Wyzwania.

Cele są wspólne dla wszystkich użytkowników, progresywne i nie mają cykli tygodniowych.

W każdej ścieżce aktywny jest jeden cel. Po jego ukończeniu kolejny cel rozpoczyna postęp od zera.

Ukończone cele pozostają w systemie, ale nie są pokazywane w sekcji bieżących celów Analizy zawodnika.

Cele nie przyznają odznak.

### Scoring

Miernik: średnia liczba punktów na trzy rzuty.

Progi:
30, 40, 50, 60, 65, 70, 73, 76, 79, 82, 85, 88, 91, 94, 97, 100.

### Doubles

Miernik: skuteczność trafień D1–D20.

Progi:
5%, 8%, 11%, 14%, 17%, 20%, 23%, 26%, 29%, 32%, 35%.

### Checkouty

Miernik: skuteczność checkoutów.

Progi:
2%, 4%, 6%, 8%, 10%, 12%, 14%, 16%, 18%, 20%.

### Regularność

Miernik: liczba ukończonych sesji treningowych w kroczącym okresie 30 dni.

Progi:
5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35, 38, 41, 44, 47, 50.

Ukończony Plan liczy się jako jedna ukończona sesja, niezależnie od liczby ćwiczeń w środku.

Po odblokowaniu kolejnego celu jego postęp zaczyna się od zera. Liczą się wyłącznie ukończone sesje w odpowiednim kroczącym okresie 30 dni.

### Wyzwania

Cel bazuje na najlepszym wyniku w 54-Dart Challenge.

Progi:
od 100 do 3100 punktów, co 100 punktów.

# 15. Odznaki treningowe

Odznaki treningowe są oddzielone od ogólnych odznak aplikacji i znajdują się w sekcji Trening.

Kategorie:

- Scoring,
- Doubles,
- Checkouty.

Poziomy:

- Żelazo,
- Brąz,
- Złoto,
- Platyna,
- Diament.

Dla metryk wymagających oceny skuteczności obowiązuje minimum 20 odpowiednich prób. Próby muszą dotyczyć tego samego ćwiczenia, wariantu, wersji i celu.

Dla standardowych Wyzwań średnia jest liczona z ostatnich pięciu ukończonych prób. Wynik może się poprawiać lub pogarszać.

Zmiana zasad Wyzwania oznacza nowe, niezależne Wyzwanie.

Dokładne zatwierdzone progi poszczególnych poziomów odznak pozostają zgodne z wcześniej ustaloną tabelą progów.

# 16. Analiza zawodnika

Analiza zawodnika jest osobną sekcją na głównym ekranie Trening.

Wszystkie sekcje znajdują się na jednej przewijanej stronie, bez osobnych zakładek.

Kolejność:

1. Licznik ukończonych ćwiczeń.
2. Wykres aktywności.
3. Rekordy osobiste.
4. Odznaki.
5. Cele treningowe.

Po każdym wejściu ekran zaczyna się od góry.

## 16.1. Licznik ukończonych ćwiczeń

Licznik pokazuje łączną liczbę ukończonych indywidualnych ćwiczeń.

Zasady:

- jedno ukończone ćwiczenie = +1,
- Plan z ośmioma ukończonymi ćwiczeniami = +8,
- przerwy nie są liczone,
- pominięte ćwiczenia nie są liczone,
- ćwiczenia z Wyzwań i Eventów również są liczone.

Licznik uwzględnia wyłącznie dane poprawnie zsynchronizowane z API.

Jest to ten sam licznik, który znajduje się w „Moje treningi”.

## 16.2. Wykres aktywności

Wykres pokazuje liczbę ukończonych ćwiczeń każdego dnia.

Typ wykresu: liniowy, z zaznaczonymi punktami dla poszczególnych dat.

Dostępne zakresy:

- 7 dni,
- 30 dni,
- 60 dni.

Nie ma własnego zakresu dat ani podziału według rodzaju treningu.

Dni bez aktywności mają wartość zero.

Kliknięcie punktu na wykresie pokazuje wartość dla wybranej daty. Nie otwiera listy treningów.

Wykres bazuje wyłącznie na ukończonych, poprawnie zsynchronizowanych sesjach.

## 16.3. Rekordy

Pokazujemy wszystkie rekordy.

Kolejność alfabetyczna według nazwy ćwiczenia.

Każdy rekord zawiera wyłącznie:

- nazwę ćwiczenia,
- wynik,
- datę.

## 16.4. Odznaki

Odznaki są prezentowane jako same ikony, bez kafelków.

Na czas prac projektowych właściwe grafiki zastępują prostokąty z napisem „Ikona”. Docelowe ikony zostaną dostarczone później.

Pokazujemy wyłącznie najwyższy zdobyty poziom każdej odznaki.

Jeśli w danej kategorii jest więcej niż jedna odznaka, grupujemy je według kategorii:

- Scoring,
- Doubles,
- Checkouty.

Poziom odznaki wynika z jej koloru.

Po kliknięciu odznaki wyświetlamy:

- opis warunków zdobycia,
- datę zdobycia.

## 16.5. Cele

Wszystkie pięć kategorii celów może być pokazanych razem w jednym widoku.

Przy każdym celu widoczny jest:

- aktualny postęp,
- pasek postępu,
- wartość wymagana do ukończenia.

Nie pokazujemy osobnego komunikatu o ukończeniu celu.

Pokazujemy wyłącznie bieżące cele, bez archiwum ukończonych.

Kliknięcie celu pokazuje dokładny opis zasad jego naliczania.

## 16.6. Pobieranie i odświeżanie danych Analizy zawodnika

Przy wejściu do Analizy zawodnika aplikacja pobiera z API komplet danych potrzebnych do wyświetlenia wszystkich sekcji.

Podczas pobierania widoczny jest ekran ładowania całej Analizy zawodnika.

Analiza pojawia się dopiero po pomyślnym pobraniu wszystkich danych.

Jeżeli pobranie choć jednej części danych się nie powiedzie:

- nie wyświetlamy żadnej sekcji,
- nie pokazujemy częściowych danych,
- wyświetlamy komunikat o błędzie,
- wyświetlamy przycisk „Spróbuj ponownie”.

Ponowienie pobiera cały zestaw danych od początku.

Po udanym pobraniu widok pozostaje niezmienny aż do opuszczenia Analizy zawodnika.

Późniejsza synchronizacja treningów i zmiany w API nie aktualizują otwartego widoku.

Aby zobaczyć aktualne dane, użytkownik musi opuścić Analizę zawodnika i wejść do niej ponownie. Wtedy aplikacja wykonuje nowe pobranie z API.

# 17. Regularność a licznik ukończonych ćwiczeń

To dwa różne mierniki i nie należy ich łączyć.

**Licznik ukończonych ćwiczeń**:

- liczy pojedyncze ukończone ćwiczenia,
- Plan z ośmioma ćwiczeniami daje +8,
- jest widoczny w profilu zawodnika i „Moje treningi”.

**Cel Regularność**:

- liczy ukończone sesje treningowe,
- cały Plan liczy się jako jedna sesja,
- bazuje na kroczącym okresie 30 dni.

# 18. Panel administratora

Panel administratora zawiera narzędzia do zarządzania ćwiczeniami, Planami, Wyzwaniami, Eventami, użytkownikami i statystykami.

Blokowanie i odblokowywanie kont oraz role administratorów są już obsługiwane w istniejącej części aplikacji. Nie zmieniamy tych mechanizmów.

Na tym etapie nie wprowadzamy dziennika operacji administracyjnych.

## 18.1. Zarządzanie danymi użytkownika

Administrator ma dostęp do indywidualnych danych treningowych użytkowników.

Może przeglądać:

- historię ukończonych sesji,
- szczegółowe statystyki,
- łączny licznik ukończonych ćwiczeń,
- najlepsze rekordy,
- zdobyte odznaki wraz z poziomem i datą.

Administrator nie ma dostępu do postępu bieżących celów użytkownika.

Dane są tylko do odczytu. Administrator nie może zmieniać wyników, historii, statystyk ani rekordów.

Administrator widzi wyłącznie ukończone sesje. Nie ma dostępu do sesji przerwanych, nieukończonych, anulowanych ani unieważnionych.

Historia administratora ma filtry według rodzaju treningu i zakresu dat, które można łączyć.

Sortowanie chronologiczne od najnowszych do najstarszych.

Nie ma eksportu CSV ani Excel.

Administrator nie ma dostępu do pełnego zapisu każdego rzutu, ponieważ aplikacja nie przechowuje i nie wysyła takiego dziennika.

## 18.2. Statystyki Planów

Dla każdego Planu administrator widzi:

- liczbę rozpoczętych sesji,
- liczbę ukończonych sesji,
- liczbę unikalnych użytkowników.

Dostępna jest lista użytkowników, którzy wykonywali Plan, wraz z liczbą ukończonych sesji każdego użytkownika.

Lista umożliwia:

- filtrowanie według liczby ukończonych sesji,
- sortowanie według liczby ukończonych sesji rosnąco lub malejąco,
- sortowanie według nazwy gracza A–Z lub Z–A.

Administrator nie ma osobnej zakładki zawierającej wszystkie ukończone sesje danego Planu z pełnymi szczegółami każdej sesji.

Wykres aktywności Planu pokazuje liczbę rozpoczętych i ukończonych sesji dziennie. Administrator może wybrać zakres dat.

Nie ma eksportu CSV ani Excel.

## 18.3. Statystyki Wyzwań

Dla każdego Wyzwania administrator widzi:

- liczbę rozpoczętych prób,
- liczbę ukończonych prób,
- liczbę unikalnych uczestników,
- listę użytkowników, którzy podjęli Wyzwanie,
- liczbę ukończonych prób każdego uczestnika.

Administrator nie może przejść z listy uczestników do pełnej historii danego użytkownika.

Osobna zakładka „Ukończone próby” zawiera wszystkie ukończone próby.

Szczegóły próby pokazują:

- wynik końcowy,
- etapy i ćwiczenia,
- szczegółowe statystyki,
- uczestnika,
- datę i godzinę ukończenia.

Nie ma pełnego dziennika każdego rzutu.

Administrator nie może edytować wyników ani statystyk.

Filtrowanie:

- według użytkownika,
- według daty ukończenia.

Sortowanie:

- data ukończenia rosnąco lub malejąco,
- wynik od najlepszego do najgorszego.

Wykres aktywności pokazuje liczbę rozpoczętych i ukończonych prób dziennie. Administrator może wybrać zakres dat.

Nie ma eksportu CSV ani Excel.

## 18.4. Statystyki Eventów

Panel pokazuje uczestników, którzy ukończyli oficjalną próbę, oraz szczegółowe statystyki ich ukończonych prób.

Administrator nie może edytować wyników ani statystyk.

Nie ma eksportu CSV ani Excel.

## 18.5. Globalne statystyki treningowe

Istniejąca sekcja statystyk aplikacji zostaje rozbudowana o część „Statystyki treningowe”.

Nie zmieniamy istniejących statystyk kont i użytkowników.

Globalne statystyki treningowe pokazują wyłącznie:

- łączną liczbę ukończonych indywidualnych ćwiczeń w całej aplikacji.

Wliczamy ćwiczenia ukończone:

- samodzielnie,
- w Planach,
- w Wyzwaniach,
- w Eventach.

Nie pokazujemy liczby ukończonych sesji ani liczby rozpoczętych treningów.

Uwzględniamy wyłącznie dane poprawnie zsynchronizowane z API.

Wartość jest łączna od początku działania aplikacji. Nie ma filtrowania według dat.

Definicja licznika jest identyczna jak w profilu zawodnika.

**Usunięcie historii przez użytkownika:** jeśli użytkownik usunie całą swoją historię treningową, jego ćwiczenia przestają być uwzględniane również w globalnym liczniku administratora. Jeżeli dane zostaną przywrócone przez programistę w okresie 30 dni, odpowiedni wkład użytkownika wraca do globalnego licznika.

# 19. Uniwersalne powiadomienia push

Panel administratora ma jedno uniwersalne narzędzie do wysyłania powiadomień push.

Administrator może:

- ustawić dowolny tytuł,
- wpisać dowolną treść,
- wybrać odbiorców,
- wysłać powiadomienie natychmiast,
- wysłać wiadomość testową wyłącznie do siebie.

Grupy odbiorców:

- wszyscy użytkownicy,
- użytkownicy aktywni,
- użytkownicy nieaktywni,
- użytkownicy, którzy nigdy nie rozpoczęli żadnego treningu.

Kryteria można łączyć.

System respektuje ustawienia powiadomień i uprawnienia użytkowników.

Nie ma planowania wysyłki na późniejszą godzinę.

Wysłanie wymaga dodatkowego potwierdzenia.

Historia wysłanych powiadomień jest tylko do odczytu. Zawiera:

- tytuł,
- treść,
- datę i godzinę wysłania,
- wybraną grupę odbiorców,
- liczbę odbiorców.

Nie można ponownie wysłać powiadomienia z historii ani usuwać wpisów historii.

Nie wysyłamy automatycznych powiadomień w związku z aktywacją, dezaktywacją lub reaktywacją Planów i Wyzwań ani zmianami statusu Eventów. Administrator może samodzielnie wysłać odpowiednią wiadomość narzędziem uniwersalnym.

# 20. Bezpieczeństwo operacji administratora

Zwykłe operacje, takie jak aktywacja i dezaktywacja ćwiczenia, Planu lub Wyzwania, wymagają standardowego okna potwierdzenia.

Operacje nieodwracalne wymagają ponownego wpisania hasła administratora.

Nie ma potrzeby wyświetlania dodatkowej listy skutków operacji. Wystarczy okno wpisania hasła i końcowego potwierdzenia.

Wysłanie powiadomienia push wymaga osobnego potwierdzenia.

Automatyczna dezaktywacja wielu elementów po dezaktywacji ćwiczenia wymaga jednego potwierdzenia całej operacji.

# 21. Zasady prywatności i przechowywania danych

Dane treningowe użytkownika są prywatne.

Inni użytkownicy nie widzą:

- indywidualnej historii,
- szczegółowych wyników,
- statystyk,
- rekordów,
- celów,
- odznak.

Administrator ma dostęp do danych treningowych w zakresie opisanym w specyfikacji, wyłącznie do odczytu.

Publiczne są wyłącznie wyniki dobrowolnie opublikowane w rankingach Wyzwań oraz oficjalne wyniki klasyfikacji Eventów.

Pełny zapis każdego rzutu nie jest przechowywany ani wysyłany na serwer. Serwer przechowuje podsumowania i szczegółowe statystyki potrzebne aplikacji i panelowi administratora.

---

# 22. Zasady nadrzędne i rozstrzygnięcia spójności

Poniższe reguły są nadrzędne i należy je stosować we wszystkich częściach aplikacji.

1. **Ukończone ćwiczenia a ukończone sesje:** licznik profilu i globalne statystyki liczą indywidualne ćwiczenia. Cel Regularność liczy ukończone sesje.
2. **Synchronizacja:** statystyki, rekordy, cele i liczniki aktualizują się dopiero po poprawnej synchronizacji danych z API.
3. **Przerwane treningi:** nie trafiają do historii i nie wpływają na statystyki, rekordy, cele ani odznaki.
4. **Pominięte ćwiczenia w Planie:** nie są liczone do licznika ani punktacji. Pozostałe ukończone ćwiczenia zachowują swoje wyniki.
5. **Podczas treningu:** nie pokazujemy punktacji ani statystyk. Są dostępne dopiero po zakończeniu.
6. **Dane lokalne:** aktywna sesja jest przechowywana na urządzeniu, na którym została rozpoczęta. Zakończone, niesynchronizowane sesje są powiązane z kontem.
7. **Analiza zawodnika:** dane są pobierane przy wejściu. Otwarty widok nie aktualizuje się po zmianach API ani synchronizacji. Ponowne wejście pobiera nowy komplet danych.
8. **Dezaktywacja ćwiczenia:** automatycznie dezaktywuje zależne aktywne Plany, Wyzwania i Eventy. Ponowna aktywacja ćwiczenia nie reaktywuje zależnych elementów.
9. **Usunięcie ćwiczenia:** usuwa je również ze struktury Planów i Wyzwań, w których było wykorzystywane. Usunięcia nie należy utożsamiać z dezaktywacją ani anulowaniem całego Planu lub Wyzwania.
10. **Dezaktywacja a anulowanie:** dezaktywacja jest odwracalna, anulowanie jest nieodwracalne.
11. **Usunięcie historii:** użytkownik usuwa całą historię jednym działaniem. Dane przez 30 dni mogą zostać przywrócone przez programistę, a później są trwale usuwane. Usunięcie historii odejmuje również ćwiczenia tego użytkownika od globalnego licznika.
12. **Event zakończony wcześniej:** znika z widoku użytkowników i nie przyjmuje nowych prób, ale jego status „Zakończony” oraz ostateczna klasyfikacja pojawiają się dopiero po zakończeniu lub unieważnieniu wszystkich rozpoczętych prób.
13. **Dezaktywacja Eventu wskutek dezaktywacji ćwiczenia:** Event pozostaje nieaktywny. Nie jest automatycznie anulowany ani usuwany.
14. **Brak pełnego dziennika rzutów:** aplikacja nie wysyła i nie przechowuje pełnego zapisu każdego rzutu. Użytkownik i administrator widzą szczegółowe statystyki, nie historię każdego trafienia.
15. **Projektowanie ekranów ćwiczeń:** Cursor nie projektuje samodzielnie szczegółowych widoków pojedynczych ćwiczeń ani całego przebiegu Planów. Te elementy są uzgadniane z właścicielem projektu na bieżąco.

# 23. Elementy wymagające zachowania wcześniejszych zatwierdzonych parametrów

Nie należy zmieniać wcześniej ustalonych szczegółowych tabel i parametrów tylko dlatego, że w tym dokumencie opisano je skrótowo.

Dotyczy to w szczególności:

- dokładnych progów poziomów odznak treningowych,
- szczegółowych definicji i parametrów poszczególnych ćwiczeń,
- szczegółowych mechanik ćwiczeń wykorzystywanych w Planach, Wyzwaniach i Eventach.

Te wartości pozostają zgodne z wcześniej zatwierdzonymi definicjami projektu.

---

**Koniec specyfikacji funkcjonalnej Twenty Six Training.**
