# Zmiana nazw folderów repozytoriów

Produkt: **twentySix**. Foldery na dysku powinny odpowiadać marce (bez `DartScore` / `Suwalska-Liga-Darta-MobileApp`).

## Docelowe nazwy

| Było | Ma być |
|------|--------|
| `DartScore` | `twentysix-backend` |
| `Suwalska-Liga-Darta-MobileApp` | `twentysix-mobile` |

## Kroki (Windows, PowerShell)

1. **Zamknij Cursor** (żeby zwolnić uchwyty na foldery).
2. W PowerShell:

```powershell
cd d:\projects\Cursor
Rename-Item -LiteralPath "DartScore" -NewName "twentysix-backend"
Rename-Item -LiteralPath "Suwalska-Liga-Darta-MobileApp" -NewName "twentysix-mobile"
```

3. Otwórz w Cursorze **folder nadrzędny** `d:\projects\Cursor` jako workspace z oboma projektami,  
   albo dodaj oba: `twentysix-backend` + `twentysix-mobile`.
4. Sprawdź `git remote` w obu repo — ścieżki git się **nie zmieniają**, tylko folder lokalny.

## Po rename

- Dokumentacja i reguły Cursor już używają nazw `twentysix-backend` / `twentysix-mobile`.
- Jeśli coś nadal wskazuje na starą ścieżkę, zgłoś lub popraw w PR.
