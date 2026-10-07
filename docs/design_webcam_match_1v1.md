# Mecz kamerkowy 1v1 (web) — na później

**Stan:** świadomie odłożone. **Nie implementować**, dopóki temat nie wróci z backlogu ([`NEXT_STEPS.md`](NEXT_STEPS.md)).

Osobny mecz na stronie: dwóch zalogowanych graczy, każdy przy własnej tarczy, laptop obok oche, dwie kamerki. Obaj widzą swój podgląd i podgląd przeciwnika, wynik wpisują obok obrazu.

## Poza tym tematem

- Quick game (lobby, FFA, `one_device` / `each_own`) zostaje bez kamer.
- Turniej i liga zostają przy sędzim (tablet / `/referee`) — jeden wpisuje obu, ludzie przy jednej tarczy.
- Aplikacja mobilna nie filmuje meczu.
- Brak kibica, sędziego na podglądzie, nagrania i automatycznego odczytu lotek.

## Układ stanowiska

Laptop z boku oche. Jedna kamerka na gracza (wbudowana albo USB), druga na tarczę. Przeglądarka otwiera obie naraz (`enumerateDevices` + dwa `getUserMedia` ze wskazanym `deviceId`). Telefon tego układu nie robi.

Tarcza idzie ostrzej (ok. 720p — widać lotkę). Twarz mniejsza (ok. 360p) — widać, kto rzuca. Przy słabym łączu ścina się najpierw twarz.

## Jak leci obraz

Obraz idzie **prosto między przeglądarkami** (WebRTC, jedno połączenie, dwie ścieżki wideo na gracza). Laravel i Reverb obrazu nie przenoszą.

Reverb przenosi tylko ustalenie połączenia, tym samym kanałem zdarzeń co reszta aplikacji:

1. **Oferta** — opis połączenia (SDP) gracza A, w tym dwie ścieżki.
2. **Odpowiedź** — opis gracza B.
3. **Kandydaci ICE** — adresy, pod którymi da się dojść do laptopa.

`getUserMedia` wymaga HTTPS albo localhosta.

Gdy routery puszczają ruch, pakiety idą bezpośrednio (STUN tylko podaje publiczny adres, nie niesie obrazu). Gdy nie puszczają, obraz idzie przez serwer **TURN** — jedyna nowa infrastruktura. TURN trzeba mieć od pierwszej wersji, nawet jeśli większość meczów go nie użyje. SFU (rozsyłanie do trzeciej osoby) nie wchodzi w ten zakres.

Wynik (wizyty, tura) leci osobno od obrazu, jak dziś stan meczu. Klatka wideo może nie dojść, a wizyta i tak jest zapisana.

## Mecz, gdy temat wróci

Pierwsza wersja, którą da się zagrać:

- dwóch zalogowanych, zaproszenie do pokoju na webie,
- przed startem sprawdzian kadru (tarcza w całości, gracz w kadrze),
- każdy wpisuje **swoje** wizyty; drugi patrzy na tarczę i twarz,
- „nie było widać” zatrzymuje lega do wyjaśnienia na żywo,
- zerwane łącze wznawia ten sam mecz.

Alpine + zwykły moduł JS wystarczają. Prototyp (cztery obrazki i ręczny wynik, bez reconnectu) jest rzędu tygodnia–dwóch; układ kamerek, reconnect i sprawdzian kadru to drugi taki odcinek.

Dwie kamerki nie kasują oszustwa. Robią je widocznym: twarz pilnuje, kto rzuca, tarcza pilnuje, co wpisano.
