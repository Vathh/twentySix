# Instrukcje konfiguracji nowego repozytorium Git

## Problem
Obecne repozytorium jest połączone ze starym remote. Chcemy stworzyć nowe repozytorium i zcommitować wszystkie zmiany.

## Kroki do wykonania:

### 1. Zamknij wszystkie procesy używające Git
- Zamknij Cursor/IDE
- Zamknij wszystkie terminale
- Sprawdź czy nie ma otwartych procesów git (w Task Manager)

### 2. Usuń pliki lock (jeśli istnieją)
```powershell
cd d:\projects\Cursor\DartScore
Remove-Item -Force .git\index.lock -ErrorAction SilentlyContinue
Remove-Item -Force .git\config.lock -ErrorAction SilentlyContinue
```

### 3. Usuń obecny remote
```powershell
git remote remove origin
```

### 4. Dodaj wszystkie zmiany i zrób commit
```powershell
git add .
git commit -m "Implementacja faz 1-4: szczegóły meczów, rejestracja mobilna, system znajomych, szybkie mecze

- Faza 1: Dodano szczegółowe wyniki meczów (legs) z możliwością zapisu statystyk
- Faza 2: Rejestracja użytkowników z aplikacji mobilnej z tokenem Sanctum
- Faza 3: System znajomych (dodawanie, usuwanie, wyszukiwanie)
- Faza 4: Szybkie mecze między zarejestrowanymi użytkownikami
- Refaktoryzacja: Klasa abstrakcyjna GameDomain dla wszystkich typów gier
- Rozwiązanie problemu unikalności nazw graczy (goście vs zarejestrowani)
- Architektura: Ścisłe przestrzeganie Domain-Repository-Service pattern"
```

### 5. Stwórz nowe repozytorium na GitHubie
1. Przejdź na https://github.com/new
2. Stwórz nowe repozytorium (np. `DartScore-Development` lub inna nazwa)
3. **NIE** inicjalizuj go z README, .gitignore lub licencją

### 6. Połącz lokalne repozytorium z nowym remote
```powershell
git remote add origin https://github.com/Vathh/[NAZWA_NOWEGO_REPOZYTORIUM].git
git branch -M main
git push -u origin main
```

## Alternatywnie: Jeśli chcesz całkowicie nowe repozytorium

Jeśli chcesz rozpocząć od zera (bez historii commitów):

```powershell
cd d:\projects\Cursor\DartScore
# Usuń obecny .git
Remove-Item -Recurse -Force .git

# Inicjalizuj nowe repozytorium
git init
git add .
git commit -m "Początkowy commit - implementacja faz 1-4"
```

Następnie połącz z nowym repozytorium na GitHubie (kroki 5-6 powyżej).
