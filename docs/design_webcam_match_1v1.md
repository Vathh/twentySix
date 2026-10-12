# Mecz kamerkowy 1v1 (web)

**Stan:** w toku na gałęzi `feature/webcam-match` (osobny worktree, poza `main`). Na `main` tego nie ma i nie mergujemy, dopóki temat nie wróci świadomie.

Osobny mecz na stronie: dwóch zalogowanych znajomych, każdy przy własnej tarczy, laptop obok oche, dwie kamerki. Obaj widzą swój podgląd i podgląd przeciwnika, wynik wpisują obok obrazu.

## Jest w tej wersji

- Lista `/webcam` i pokój ze znajomym (Konto → Mecz kamerkowy). Drugi raz z tą samą osobą wraca do otwartego meczu.
- Dwie kamerki na gracza: tarcza (ostrzejszy obraz) i twarz. Domyślnie pierwsza kamera systemu to tarcza, druga to gracz; wybór zostaje w przeglądarce.
- Obraz: jedno połączenie WebRTC, dwie ścieżki. Ofertę składa gracz z niższym `user id`. Oferta, odpowiedź, kandydaci ICE i prośba o ponowne połączenie lecą przez Reverb (`signal`). Laravel obrazu nie przenosi.
- Autoryzacja kanału `private-webcam-match.{id}` idzie sesją (`POST /webcam/broadcasting/auth`), obok istniejącego `/broadcasting/auth` dla aplikacji.
- Wynik osobno od obrazu. Format sztywny: 501, double out, 1 set, pierwszy do 2 legów. Każdy wpisuje tylko swoją wizytę, serwer sprawdza turę. Bust, checkout i remaining: `VisitRecorder`. Leg 1 otwiera host, kolejny leg — drugi gracz.
- Zapis w `webcam_matches` i `webcam_match_visits`. Bez kariery, bez `quick_games`, bez meczu turniejowego.
- STUN domyślnie (`config/webcam.php`). TURN dopisuje się w `WEBCAM_ICE_SERVERS`. Bez TURN część par z różnych sieci domowych się nie zobaczy.
- Cofnięcie ostatniej własnej wizyty. „Połącz obraz ponownie” składa nową ofertę.

## Poza tym tematem

- Quick game (lobby, FFA, `one_device` / `each_own`) zostaje bez kamer.
- Turniej i liga zostają przy sędzim (tablet / `/referee`).
- Aplikacja mobilna nie filmuje meczu.
- Brak kibica, sędziego na podglądzie, nagrania i automatycznego odczytu lotek.
- Jeszcze nie ma: „nie było widać”, sprawdzianu kadru jako bramki startu (gotowość jest przyciskiem, nie oceną kadru), statystyk.

## Jak leci obraz

Obraz idzie prosto między przeglądarkami. Reverb przenosi tylko ustalenie połączenia:

1. **Oferta** — opis połączenia (SDP) plus która ścieżka jest tarczą, a która twarzą.
2. **Odpowiedź** — to samo od drugiej strony.
3. **Kandydaci ICE** — adresy, pod którymi da się dojść do laptopa.

`getUserMedia` wymaga HTTPS albo localhosta.

Gdy routery puszczają ruch, pakiety idą bezpośrednio (STUN tylko podaje publiczny adres). Gdy nie puszczają, obraz idzie przez serwer TURN.

Wynik (wizyty, tura) leci zdarzeniem `state` i jest do odczytu po HTTP, także gdy klatka wideo nie dojdzie.

Dwie kamerki nie kasują oszustwa. Robią je widocznym: twarz pilnuje, kto rzuca, tarcza pilnuje, co wpisano.
