# BandBook

BandBook to samodzielna aplikacja PHP dla zespołu muzycznego. Pozwala zarządzać biblioteką pieśni, przygotować repertuar wydarzenia, prowadzić zespół w trybie live i udostępnić uczestnikom aktualny tekst.

## Gotowe funkcje

- biblioteka pieśni z podziałem na zwrotki, refreny, bridge, intro i inne części;
- osobne pola tekstu i chwytów oraz wklejanie par `tekst [TAB] chwyty`;
- domyślna forma pieśni z wielokrotnym użyciem tej samej części;
- polska notacja `H/B` z małymi akordami molowymi oraz notacja `B/Bb` z końcówką `m`;
- transpozycja całej pieśni i każdego wystąpienia części;
- wydarzenia, komentarze, tempo, kolejność repertuaru zmieniana przeciąganiem lub strzałkami i formy wydarzeniowe;
- edycja nazwy, tekstu i chwytów pojedynczego wystąpienia części bez zmiany pieśni źródłowej;
- pełny edytor pieśni dostępny z podglądu przed dodaniem, repertuaru oraz z ukrytego menu narzędzi w Live;
- przeglądarka doboru repertuaru z wyszukiwaniem po tytule, tekście, autorze i kategorii oraz automatycznym doczytywaniem wyników podczas przewijania;
- oryginalne działy Śpiewnika guanelliańskiego, źródła OpenLP oraz filtry dostępności chwytów;
- wielokrotne kategorie edytowane przy pieśni, własne kategorie i chronione metadane importu;
- pełny podgląd tekstu, chwytów i domyślnej formy bez opuszczania wydarzenia;
- tryb live ze stanem „następna” i „teraz” potwierdzanym dwoma kliknięciami;
- edycja części podczas grania, opcjonalny zapis zmian do pieśni źródłowej i synchronizacja urządzeń co około sekundę;
- publiczny widok tekstu z linkiem dla uczestników i trybem pełnoekranowym;
- przezroczysta nakładka tekstowa do dodania w OBS jako Browser Source;
- konto administratora tworzone przy pierwszym uruchomieniu;
- opcjonalna biblioteka startowa: 257 pozycji ze Śpiewnika guanelliańskiego oraz 632 rekordy OpenLP;
- zachowane alternatywne tytuły, autorzy, śpiewniki i kolejność części z formatu OpenLyrics.

## Wymagania hostingu

- PHP 8.1 lub nowszy;
- rozszerzenie PDO;
- PDO SQLite albo PDO MySQL;
- HTTPS;
- możliwość ustawienia katalogu `public` jako katalogu strony;
- zapisywalny przez PHP katalog `storage` przy korzystaniu z SQLite.

Aplikacja nie wymaga Node.js, Composera, procesu WebSocket ani zewnętrznych bibliotek.

## Instalacja z SQLite

1. Wgraj wszystkie pliki na serwer.
2. Ustaw katalog `public` jako document root domeny lub subdomeny.
3. Nadaj procesowi PHP prawo zapisu do katalogu `storage`.
4. Otwórz stronę przez HTTPS.
5. Formularz pierwszego uruchomienia utworzy bazę oraz konto administratora.

Domyślna konfiguracja znajduje się w `config.php`. Baza SQLite powstanie automatycznie jako `storage/bandbook.sqlite`.

## Instalacja z MySQL lub MariaDB

Ustaw zmienne środowiskowe hostingu:

```text
DB_DSN=mysql:host=localhost;dbname=bandbook;charset=utf8mb4
DB_USER=nazwa_uzytkownika
DB_PASSWORD=bezpieczne_haslo
APP_TIMEZONE=Europe/Warsaw
```

Pusta baza musi już istnieć. Tabele są tworzone automatycznie przy pierwszym żądaniu. Konto bazy powinno mieć prawo tworzenia tabel oraz odczytu i zapisu danych.

## Pierwsze użycie

1. Utwórz administratora i wybierz domyślną notację chwytów.
2. Pozostaw zaznaczony import biblioteki, aby od razu dodać 889 pozycji z Dokumentów Google i OpenLP. Import można bezpiecznie uruchomić ponownie — istniejące tytuły są pomijane.
3. Sprawdź lub edytuj formy w zakładce **Pieśni**.
4. Utwórz wydarzenie i dodaj pieśni do repertuaru.
5. Dostosuj transpozycję, tempo, komentarze i formę każdej pozycji.
6. Otwórz **Live** na urządzeniach muzyków.
7. Udostępnij publiczny link uczestnikom.

Adres nakładki OBS znajduje się obok publicznego linku wydarzenia. Dodaj go w OBS jako **Browser Source** i ustaw rozdzielczość zgodną z transmisją. Tło nakładki jest przezroczyste.

W trybie live pierwsze kliknięcie części oznacza ją jako następną. Drugie kliknięcie tej samej części potwierdza ją jako graną teraz.

## Uruchomienie lokalne

Jeżeli Python i polecenie `php` są dostępne w PATH, uruchom w głównym katalogu projektu:

```text
python start_server.py
```

Aplikacja będzie dostępna pod adresem `http://127.0.0.1:8000`. Inny port można wskazać parametrem:

```text
python start_server.py --port 8080
```

Jeżeli PHP nie znajduje się w PATH, podaj jego lokalizację:

```text
python start_server.py --php C:\php\php.exe
```

Pełny śpiewnik można też zaimportować do skonfigurowanej bazy z wiersza poleceń:

```text
php scripts/import_songbook.php
```

Sam zestaw OpenLP można zaimportować poleceniem:

```text
php scripts/import_openlp.php
```

Pliki eksportu OpenLP nie zawierały chwytów. Import zachowuje tekst, części, formę, alternatywne tytuły, autorów i przypisanie do śpiewników. Warianty kolidujące z wcześniejszą biblioteką otrzymują dopisek `OpenLP`, zamiast zastępować wersje zawierające chwyty.

Źródłowy eksport można ponownie przetworzyć poleceniem opisanym na początku pliku `scripts/parse_songbook.php`. Rekordy, w których układ Dokumentów Google był niejednoznaczny, mają w komentarzu oznaczenie **Do sprawdzenia**; żadna treść nie została z tego powodu pominięta.

## Bezpieczeństwo i kopie

- Nie umieszczaj katalogów `src`, `storage` ani pliku `config.php` w publicznie dostępnym katalogu serwera. Document root powinien wskazywać `public`.
- Regularnie kopiuj bazę. Dla SQLite wystarczy bezpieczna kopia pliku `storage/bandbook.sqlite` wykonywana poza trwającym zapisem.
- W produkcji używaj HTTPS i długiego, unikalnego hasła administratora.
- Publiczny adres wydarzenia zawiera losowy token. Nie pokazuje chwytów ani komentarzy technicznych.

## Zakres kolejnych etapów

Plan dalszego rozwoju, w tym import z Google Drive, OpenLP, OBS, role użytkowników i historia wersji, znajduje się w [docs/PLAN_APLIKACJI.md](docs/PLAN_APLIKACJI.md).
