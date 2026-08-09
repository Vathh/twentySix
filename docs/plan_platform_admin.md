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

## Poza MVP

- Nadawanie `role=admin` z UI (świadomie ręczne / tinker — unikamy przypadkowego „drugiego właściciela”)
- Ban / soft-delete
- Szczegóły aktywności per user
- Pulse / Sentry / analityka produktowa
