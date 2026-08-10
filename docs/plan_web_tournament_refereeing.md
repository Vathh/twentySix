# Plan: sędziowanie turnieju na webie

**Status:** ✅ MVP w kodzie (sierpień 2026)  
**Cel:** wpisywanie wyniku meczu turniejowego z laptopa/przeglądarki (nie tylko tablet mobile).

## Decyzje

| Temat | Wybór |
|-------|--------|
| Auth | Kod tabletu → `POST /api/login` → Sanctum token `counter` (jak mobile) |
| UI | Suma wizyty (0–180), bez per-dart |
| Silnik | Wyłącznie istniejące API lock + scoring (group / playoff) |

## Flow

1. `/tablet-login/{code}` → CTA „Sędziuj w przeglądarce” albo `/referee/login`
2. Token + `tournamentId` w `sessionStorage`
3. `/referee/games` → `GET /api/game/active`
4. Lock → `/referee/score?type=&id=` → visits / undo / closeLeg
5. Release przy wyjściu (gdy API na to pozwala); 401 po regeneracji kodu / końcu turnieju → wylogowanie

## Pliki

- Kontroler: `app/Http/Controllers/TournamentRefereeController.php`
- Widoki: `resources/views/referee/*`
- JS: `resources/js/referee/*` (Alpine)
- Trasy: `routes/web.php` (`referee.*`)

## Poza MVP

- Per-dart, Cricket turniejowy, offline outbox, sędziowanie z konta organizatora
