# Plan: zgłoszenia do turnieju przez QR

**Status:** ✅ fazy 1–2 w kodzie (lipiec 2026) — faza 3 opcjonalna  
**Data:** lipiec 2026  
**Źródło prawdy produktu:** [`product.md`](product.md) — sekcja „Zaproszenia do turnieju”  
**Ekran web:** `resources/views/tournaments/start.blade.php`  
**Mobile:** deep link / ekran zgłoszenia

---

## 1. Cel

Ułatwić zbieranie zawodników **na sali** przed startem turnieju:

1. Admin na stronie **startu turnieju** pokazuje **kod QR** (+ czytelny kod tekstowy).
2. Gracz skanuje QR na swoim telefonie → **zgłasza się** do turnieju (nie wchodzi od razu do składu).
3. Admin widzi listę **„Zgłoszenia”** i dla każdego: **Dołącz** (→ uczestnik turnieju) albo **Odrzuć**.

Klasyczne zaproszenia (wyszukiwarka, masowy invite ze składu ligi) **zostają** — QR to dodatkowa ścieżka.

---

## 2. Decyzje produktowe (ustalone)

| Temat | Decyzja |
|--------|--------|
| Kierunek flow | Gracz inicjuje → admin zatwierdza |
| Auto-dołączanie | **Nie** — zawsze decyzja admina |
| Kto może się zgłosić | Tylko **zalogowany** użytkownik z kontem (i `player`) |
| Goście bez konta | **Poza zakresem** — nadal dodaje admin ręcznie |
| Kiedy QR aktywny | Tylko gdy turniej **jeszcze nie wystartował** |
| Po starcie | QR / endpoint zgłoszeń → błąd; lista zgłoszeń tylko do podglądu / archiwum |
| Duplikat skanu | Idempotentnie: „już zgłoszony” / „już w turnieju” — bez drugiego rekordu `pending` |
| Relacja do invite | **Osobna encja** zgłoszeń — nie mylić ze statusami `tournament_invitations` |
| Kody tabletu | **Osobne** — QR dołączania ≠ kody logowania tabletu |
| Push | Opcjonalnie później: push do admina „nowe zgłoszenie”; **nie** w MVP tej funkcji |

---

## 3. User stories

### Admin (web — start turnieju)

- Widzi QR + krótki kod (np. 6–8 znaków) + ewentualnie link.
- Widzi listę zgłoszonych: imię, data zgłoszenia, akcje Dołącz / Odrzuć.
- Po „Dołącz” gracz pojawia się w **Uczestnikach turnieju** (jak po `accepted` invite).
- Może **wyłączyć / odświeżyć** kod (regeneracja unieważnia stary QR).

### Gracz (mobile)

- Skanuje QR (aparat systemu → deep link **lub** skaner w apce) / wpisuje kod.
- Jeśli niezalogowany → logowanie / rejestracja, potem powrót do zgłoszenia.
- Po zgłoszeniu: komunikat „Zgłoszenie wysłane — czekaj na akceptację organizatora”.
- Jeśli już uczestnik / już zgłoszony / turniej wystartował → czytelny komunikat.

---

## 4. Model danych

### 4.1 Token turnieju (QR)

Na `tournaments` (lub osobna tabela 1:1):

| Kolumna | Opis |
|---------|------|
| `join_code` | Krótki kod publiczny (np. 8 znaków, unique wśród aktywnych) |
| `join_code_generated_at` | Timestamp |
| `join_code_enabled` | bool — admin może wyłączyć przyjmowanie zgłoszeń bez regeneracji |

Generacja przy pierwszym wejściu na start (jeśli brak) lub przycisk „Wygeneruj ponownie”.

**QR payload:** deep link, np.  
`https://{app-host}/join-tournament/{join_code}`  
oraz ten sam kod w ścieżce uniwersalnej / Expo linking (`twentysix://join-tournament/{code}`).

### 4.2 Zgłoszenia

Nowa tabela `tournament_join_requests`:

| Kolumna | Opis |
|---------|------|
| `id` | PK |
| `tournament_id` | FK |
| `user_id` | FK — zgłaszający |
| `status` | `pending` \| `approved` \| `rejected` \| `cancelled` |
| `created_at` / `resolved_at` | |
| `resolved_by` | nullable FK admin |

Unikalność: jeden aktywny `pending` na `(tournament_id, user_id)`.

**Approve:** tworzy / reaktywuje `tournament_invitation` w statusie **`accepted`** (gracz od razu w puli startowej — bez drugiej akceptacji na mobile). Alternatywa równoważna: bezpośrednie dopisanie do uczestników tym samym mechanizmem co accept invite.

**Reject:** status `rejected`; gracz może zgłosić się ponownie (nowy `pending` albo reaktywacja — do ustalenia w implementacji: **tak, ponowne zgłoszenie dozwolone**).

---

## 5. API / trasy

### Publiczne / mobile (auth)

| Metoda | Ścieżka | Opis |
|--------|---------|------|
| `GET` | `/api/tournaments/join/{code}` | Podgląd: nazwa turnieju, liga, czy można się zgłosić |
| `POST` | `/api/tournaments/join/{code}` | Utwórz zgłoszenie (`pending`) |

### Web (admin, sesja)

| Metoda | Ścieżka | Opis |
|--------|---------|------|
| `POST` | `tournaments/{id}/join-code/regenerate` | Nowy kod |
| `POST` | `tournaments/{id}/join-code/toggle` | Włącz/wyłącz |
| `POST` | `tournaments/{id}/join-requests/{id}/approve` | Dołącz do turnieju |
| `POST` | `tournaments/{id}/join-requests/{id}/reject` | Odrzuć |

Lista zgłoszeń: w payloadzie strony startu (SSR) + opcjonalnie lekkie odświeżanie (poll co ~5–10 s albo ręczne „Odśwież”) — **bez** wymogu WebSocket w MVP.

---

## 6. UI

### Web — start turnieju

W sekcji **„Dodaj uczestników”** (obok / nad wyszukiwarką):

1. Karta **„Dołącz przez QR”**: duży QR, kod tekstowy, przyciski Regeneruj / Włącz·Wyłącz.
2. Podkartą lub zakładka **„Zgłoszenia”**: tabela pending + licznik.
3. Istniejące zakładki Zarejestrowani / Goście bez zmian w logice.

### Mobile

- Obsługa deep linku `join-tournament/{code}`.
- Ekran potwierdzenia: nazwa turnieju + „Zgłoś się”.
- Opcjonalnie (faza 2): wpisanie kodu z Home / Zaproszenia bez skanera.
- Po approve: gracz widzi turniej w swoich zaproszeniach jako accepted (lub w liście turniejów — zależnie od obecnego UX).

---

## 7. Fazy implementacji

### Faza 1 — Backend + web admin ✅

- Migracje (`join_code`, `tournament_join_requests`).
- Serwis: generate code, apply, approve, reject.
- QR na `start.blade.php` (img QR z URL join-landing).
- Lista zgłoszeń + approve/reject.
- Testy Feature (gdy MySQL testowy działa).

### Faza 2 — Mobile ✅

- Scheme `twentysix://` + deep link `join-tournament/{code}`.
- Ekran `JoinTournament` + wpisanie kodu z Home.
- Landing web `/join-tournament/{code}` → „Otwórz w aplikacji”.

### Faza 3 — Udogodnienia (opcjonalnie)

- Wpisz kod ręcznie w apce.
- Poll listy zgłoszeń na webie / dźwięk przy nowym.
- Push do adminów turnieju.
- Regeneracja kodu z historią.

---

## 8. Poza zakresem

- Dołączanie gości (bez konta) przez QR.
- Otwarty turniej bez akceptacji admina.
- Mieszanie z kodami tabletu.
- QR do quick game lobby (nadal tylko invite).
- Dołączanie **po** starcie turnieju.

---

## 9. Checklist testów manualnych

- [ ] Admin widzi QR na starcie turnieju przed startem
- [ ] Skan / link → zalogowany gracz → widać w „Zgłoszeniach”
- [ ] Drugi skan tego samego → brak duplikatu pending
- [ ] Dołącz → gracz w „Uczestnikach”; znika z pending
- [ ] Odrzuć → znika z pending; ponowne zgłoszenie możliwe
- [ ] Po starcie turnieju POST join → błąd; QR oznaczony jako nieaktywny
- [ ] Regeneracja kodu → stary link nie działa
- [ ] Klasyczne zaproszenie nadal działa równolegle
- [ ] Kod tabletu ≠ kod dołączania

---

## 10. Powiązane pliki (orientacyjnie)

- `TournamentController` / start view + `TournamentInvitationService` (approve → accepted)
- Nowy `TournamentJoinRequestService` (+ repo / model)
- Mobile: `App` / linking + nowy ekran lub modal
- [`product.md`](product.md) — dopisać podsekcję po wdrożeniu

---

*Plan zatwierdzony produktowo: QR = zgłoszenie, admin Dołącz/Odrzuć, bez auto-join.*
