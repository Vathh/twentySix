# Plan: panel platformy (właściciel aplikacji)

**Status:** ✅ MVP w kodzie (sierpień 2026)  
**Cel:** narzędzie dla właściciela twentySix (nie mylić z adminem ligi).

## Dostęp

1. Zwykłe logowanie web (sesja).
2. Konto z `users.role = admin` (już jest w schemacie; demo-admin ma tę rolę).
3. Middleware na `/admin/*` — bez roli → **403**.
4. Link „Panel” w nawigacji **tylko** dla platform admina.

Później (opcjonalnie): 2FA, allowlista IP, osobne hasło — nie w MVP.

## MVP (ten sprint)

| Ekran | Funkcje |
|-------|---------|
| Dashboard `/admin` | Liczby: użytkownicy, zweryfikowani, z `can_create_leagues`, ligi, sezony, turnieje (wg statusu), lobby quick game (waiting / in progress / finished-ish) |
| Użytkownicy `/admin/users` | Lista (email, nick, data, flagi) + wyszukiwanie; przełącznik **can_create_leagues** |

## Poza MVP (kolejne kroki — sierpień 2026)

1. ~~**Ban / soft-delete**~~ ✅ — `users.banned_at`; blokada logowania web + API; revoke tokenów Sanctum; kolumna Status na `/admin/users`; nie można zablokować platform admina.
2. ~~**Szczegóły aktywności per user**~~ ✅ — `/admin/users/{id}`: konto, API (tokeny), ligi, znajomi, quick game, wyniki turniejowe, ostatnie mecze.
3. ~~Nadawanie `role=admin` z UI~~ — **odpuszczone**; tylko tinker / ręcznie (jeden właściciel).
4. Pulse / Sentry / analityka produktowa — później.
