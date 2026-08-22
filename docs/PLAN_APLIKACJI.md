# BandBook — plan produktu i implementacji

Status: plan, bez implementacji  
Założenie: aplikacja dla jednego zespołu muzycznego, uruchamiana początkowo na prostym hostingu PHP.

## 1. Cel produktu

BandBook ma być wspólnym, zawsze aktualnym śpiewnikiem i narzędziem do prowadzenia wydarzenia. Jedno przygotowane wydarzenie zasila trzy widoki:

1. **Przygotowanie repertuaru** — pieśni, forma, tonacje, tempo, komentarze i kolejność.
2. **Widok live dla muzyków** — aktualny przebieg utworu, chwyty, dyrygowanie oraz wspólna synchronizacja.
3. **Widok uczestników** — czytelny tekst aktualnie wykonywanej części, bez danych technicznych zespołu.

Najważniejszą zasadą domenową jest rozdzielenie:

- **pieśni źródłowej** — tekst, chwyty, części i domyślna forma;
- **wersji pieśni w wydarzeniu** — tempo, transpozycja, komentarze i forma dopasowane do konkretnego wydarzenia;
- **stanu live** — co jest grane teraz i co będzie następne.

Dzięki temu zmiany wykonane podczas grania nie niszczą domyślnej wersji pieśni, ale zapisują się w konkretnym wydarzeniu.

## 2. Użytkownicy i uprawnienia

Proponowane role:

| Rola | Uprawnienia |
|---|---|
| Administrator | użytkownicy, ustawienia zespołu, wszystkie pieśni i wydarzenia |
| Edytor | tworzenie i edycja pieśni oraz wydarzeń |
| Prowadzący live | zmiany repertuaru podczas wydarzenia, wskazywanie „teraz” i „następna” |
| Muzyk | odczyt widoku live; opcjonalnie transpozycja, jeśli otrzyma takie uprawnienie |
| Uczestnik | publiczny widok tekstu przez link lub kod QR, bez logowania |

W pierwszej wersji jedna osoba powinna być prowadzącym live. Wielu użytkowników może mieć ten ekran otwarty, ale prawo reżyserowania należy domyślnie do prowadzącego. Ograniczy to przypadkowe konflikty.

## 3. Zakres funkcjonalny

### 3.1. Biblioteka pieśni

Lista pieśni powinna oferować:

- wyszukiwanie po tytule i fragmencie tekstu;
- filtrowanie po tagach, tonacji i przeznaczeniu;
- tworzenie, edycję, duplikowanie i archiwizowanie pieśni;
- podgląd domyślnej tonacji, tempa i formy;
- ostrzeżenie przed duplikatem tytułu.

Pieśń zawiera:

- tytuł i opcjonalny alternatywny tytuł;
- domyślną tonację;
- domyślne tempo BPM;
- opcjonalne metrum;
- opcjonalny komentarz ogólny;
- tagi, np. uwielbienie, wejście, ofiarowanie, komunia;
- dowolną liczbę części: zwrotka, refren, bridge, intro, outro, instrumentalna lub typ własny;
- domyślną formę, czyli listę wystąpień części.

Każda część pieśni zawiera:

- nazwę widoczną dla muzyków, np. `Zwrotka 1`, `Refren`, `Bridge`;
- tekst;
- chwyty w osobnym, zsynchronizowanym polu edytora;
- opcjonalny komentarz bazowy;
- kolejność linii i pozycje chwytów względem tekstu.

Chwyty należy zapisywać jako rozpoznawalne symbole muzyczne, a nie jako dowolny tekst. Parser powinien obsłużyć co najmniej akordy durowe i molowe, krzyżyki i bemole, septymy, `sus`, `add`, akordy zmniejszone oraz akordy łamane, np. `D/F#`. Nierozpoznany symbol ma być oznaczony do poprawy, a nie transponowany błędnie.

Sposób wpisywania i wyświetlania chwytów jest wybierany przez użytkownika w polu wyboru. Domyślnym profilem zespołu jest **polski `H/B` z małymi literami dla akordów molowych**. System powinien dostarczyć co najmniej następujące profile:

| Profil | Przykłady |
|---|---|
| Polski `H/B`, małe molowe | `D`, `A`, `e`, `h`, `fis`, `B` |
| Międzynarodowy `B/Bb`, końcówka `m` | `D`, `A`, `Em`, `Bm`, `F#m`, `Bb` |

W profilu polskim:

- wielka litera oznacza akord durowy, np. `D`;
- mała litera oznacza akord molowy, np. `d`;
- `H`/`h` oznacza odpowiednio H-dur/h-moll, czyli międzynarodowe B/Bm;
- `B`/`b` oznacza odpowiednio B-dur/b-moll, czyli międzynarodowe Bb/Bbm;
- krzyżyki mogą być wpisywane w popularnej polskiej formie, np. `Fis`/`fis`, oraz opcjonalnie jako `F#`/`f#`;
- bemole mogą być wpisywane jako `B`, `Es`, `As` itd., zgodnie z profilem.

Wybrany profil jest domyślną preferencją konta użytkownika i jest widoczny jako selektor w edytorze oraz widoku live. Zmiana profilu zmienia sposób wpisywania i prezentacji, lecz nie transponuje pieśni. Edytor może mieć lokalne nadpisanie profilu na czas pracy z importowanym materiałem.

Akordy są wewnętrznie normalizowane do wysokości dźwięku, jakości akordu, rozszerzeń i dźwięku basowego. System zachowuje również oryginalny zapis na potrzeby diagnostyki importu. Dzięki temu różni użytkownicy mogą oglądać tę samą pieśń we własnej notacji, a transpozycja nie zależy od wielkości liter ani od użycia `H/B`.

Parser musi rozpoznawać akord także wewnątrz prostych znaków technicznych i zachować ich znaczenie wizualne, np. `(h)` ma zostać potraktowane jako opcjonalny akord h-moll, a po transpozycji nadal pozostać w nawiasie. Nie wolno traktować elementów tekstu takich jak `x2` jako akordów.

Edytor może prezentować tekst i chwyty w dwóch kolumnach, lecz powinien utrzymywać pary odpowiadających sobie linii. W widoku muzyka używa kroju monospace dla stabilnego wyrównania.

Oprócz ręcznego wpisywania w dwóch kolumnach edytor powinien przyjąć wklejone wiersze w układzie `tekst [tabulator] chwyty`. Przed zapisem pokazuje podgląd rozpoznanych par tekst–chwyty, ostrzeżenia i możliwość ręcznego poprawienia podziału. Jest to istotne dla obecnego formatu śpiewnika i późniejszego importu z Google Drive.

### 3.2. Domyślna forma pieśni

Forma nie kopiuje treści części. Przechowuje kolejne **wystąpienia** wskazujące na część źródłową, np.:

`Intro → Zwrotka 1 → Refren → Zwrotka 2 → Refren → Bridge → Refren → Refren`

Ta sama część może występować wiele razy. Każde wystąpienie ma własne opcjonalne ustawienia:

- dodatkowa transpozycja;
- komentarz;
- etykieta techniczna;
- opcjonalne tempo, jeśli kiedyś potrzebna będzie zmiana w środku pieśni.

„Klonowanie zwrotki” w formie oznacza dodanie kolejnego wystąpienia tej samej części. Można wtedy nadać mu inną transpozycję lub komentarz bez duplikowania tekstu.

### 3.3. Wydarzenia i repertuar

Wydarzenie zawiera:

- nazwę;
- planowaną datę i godzinę;
- miejsce;
- status: szkic, gotowe, w trakcie, zakończone, archiwalne;
- komentarz do całego repertuaru;
- osoby odpowiedzialne;
- publiczny identyfikator/link dla uczestników;
- listę pieśni w ustalonej kolejności.

Edytor repertuaru umożliwia:

- dodawanie pieśni z biblioteki;
- zmianę kolejności metodą przeciągnij i upuść oraz przyciskami dostępnymi z klawiatury;
- zapis roboczy i wyraźny stan zapisania;
- usunięcie pieśni tylko z wydarzenia, bez usuwania jej z biblioteki;
- ustawienie transpozycji całej pieśni w wydarzeniu;
- nadpisanie tempa;
- nadpisanie komentarza;
- rozpoczęcie od domyślnej formy i dostosowanie jej dla wydarzenia;
- dodawanie, usuwanie, duplikowanie i zmianę kolejności wystąpień części;
- ustawienie transpozycji i komentarza osobno dla każdego wystąpienia części;
- podgląd końcowej wersji dla muzyka i uczestnika.

Efektywna transpozycja chwytu jest sumą:

`transpozycja pieśni w wydarzeniu + dodatkowa transpozycja wystąpienia części`

W bazie wartości są przechowywane jako liczba półtonów. Preferencja zapisu użytkownika określa sposób prezentacji enharmonicznej i molowości, np. `fis` albo `F#m`, ale nie zmienia wartości muzycznej.

### 3.4. Widok live dla muzyków

Widok live otwiera konkretne wydarzenie i pokazuje:

- nazwę oraz planowany termin wydarzenia;
- komentarz do całego repertuaru;
- listę pieśni i wyróżnienie bieżącej pieśni;
- całą formę bieżącej pieśni;
- tekst, chwyty, tempo i komentarze;
- wyraźne oznaczenia części „grana teraz” i „następna”;
- stan połączenia i czas ostatniej synchronizacji;
- duże elementy sterujące wygodne na tablecie lub telefonie.

Zmiany wykonane w tym widoku zapisują się w wydarzeniu:

- transpozycja całej pieśni;
- dodatkowa transpozycja wystąpienia części;
- tempo;
- komentarz repertuaru, pieśni lub wystąpienia części;
- forma i kolejność, jeśli użytkownik ma odpowiednie uprawnienie.

Reguła reżyserowania:

1. Pierwsze kliknięcie wystąpienia ustawia je jako **następne**.
2. Drugie kliknięcie tego samego wystąpienia ustawia je jako **grane teraz** i czyści stan „następne”.
3. Kliknięcie innego wystąpienia przed potwierdzeniem przenosi oznaczenie „następne”.
4. W jednej chwili istnieje najwyżej jedno wystąpienie „teraz” i jedno „następne”.
5. Akcja jest zapisywana atomowo i otrzymuje kolejny numer rewizji stanu live.

Interfejs powinien rozróżniać pojedyncze kliknięcie od świadomego potwierdzenia, ale nie może opierać się wyłącznie na technicznym zdarzeniu `double-click`, które jest niewygodne na ekranach dotykowych. Dwa kolejne kliknięcia w ten sam kafel realizują tę samą regułę.

### 3.5. Synchronizacja wielu muzyków

Pierwsza wersja powinna działać na hostingu bez procesu WebSocket:

- klient pyta lekki endpoint HTTP o zmiany, np. co 1 sekundę;
- żądanie przekazuje ostatni znany numer rewizji;
- serwer zwraca dane tylko, jeśli rewizja się zmieniła;
- zapis stanu live odbywa się w transakcji bazy danych;
- każda odpowiedź zawiera numer rewizji, autora i czas zmiany;
- po utracie sieci ekran zachowuje ostatni stan, wyświetla ostrzeżenie i nie udaje, że zmiana została zapisana.

To rozwiązanie jest wystarczające dla małego zespołu i typowego hostingu współdzielonego. Architektura API ma jednak pozwalać później zastąpić polling przez WebSocket, Server-Sent Events albo zewnętrzną usługę push bez zmiany modelu domenowego.

Konflikty edycji danych innych niż stan live rozwiązujemy przez blokadę optymistyczną: zapis zawiera numer wersji rekordu, a serwer odrzuca nadpisanie nowszych zmian i prosi o odświeżenie.

### 3.6. Widok uczestników

Publiczny widok wydarzenia:

- nie wymaga konta, ale używa trudnego do odgadnięcia tokenu;
- pokazuje nazwę wydarzenia i tekst części granej teraz;
- opcjonalnie pokazuje nazwę następnej części, bez jej tekstu;
- nie pokazuje chwytów, komentarzy technicznych ani danych użytkowników;
- automatycznie reaguje na stan live;
- ma bardzo duży, kontrastowy tekst i tryb pełnoekranowy;
- pozwala prowadzącemu wyłączyć dostęp publiczny albo odnowić token;
- po zakończeniu wydarzenia może pokazać komunikat zamiast pełnego repertuaru.

W ustawieniach wydarzenia warto przewidzieć dwa tryby: tylko aktualna część albo cała aktualna pieśń z wyróżnieniem bieżącej części.

## 4. Proponowana architektura

### 4.1. Wariant rekomendowany na start

- backend: aktualnie wspierana wersja PHP, docelowo PHP 8.2 lub nowszy;
- framework: Laravel w stabilnej wersji dostępnej w momencie rozpoczęcia implementacji;
- baza: MySQL lub MariaDB;
- frontend: szablony renderowane po stronie serwera oraz niewielka warstwa JavaScript (np. Alpine.js lub moduły vanilla JS);
- stylowanie: lekki system komponentów, bez konieczności budowania rozbudowanego SPA;
- komunikacja live: JSON API + wersjonowany polling;
- logowanie: sesje HTTP i ochrona CSRF;
- hosting: HTTPS, możliwość wskazania katalogu `public`, baza MySQL/MariaDB i zadanie cron.

Laravel daje gotowe migracje, walidację, autoryzację, obsługę sesji, testy i spójny sposób rozwijania integracji. Jeśli wybrany hosting nie pozwala bezpiecznie ustawić katalogu publicznego albo wdrożyć zależności Composer, należy zmienić hosting, a nie osłabiać strukturę aplikacji.

### 4.2. Granice modułów

1. **Tożsamość i uprawnienia**
2. **Biblioteka pieśni**
3. **Silnik chwytów i transpozycji**
4. **Wydarzenia i repertuary**
5. **Stan i synchronizacja live**
6. **Widok publiczny i OBS**
7. **Import/eksport oraz OpenLP**
8. **Historia zmian, kopie zapasowe i administracja**

Logika transpozycji powinna być niezależnym modułem z rozbudowanymi testami jednostkowymi. Integracje z Google Drive, OBS i OpenLP nie mogą bezpośrednio modyfikować tabel domenowych; przechodzą przez warstwę usług aplikacji i walidację.

## 5. Wstępny model danych

Nazwy są robocze i opisują odpowiedzialność, nie gotowy schemat SQL.

| Encja | Najważniejsze dane |
|---|---|
| `users` | konto, nazwa, hasło, status, preferowany profil notacji |
| `roles` / `user_roles` | role i przypisania |
| `songs` | tytuł, tonacja źródłowa, tempo, metrum, komentarz, opcjonalna preferencja enharmoniczna, wersja |
| `song_sections` | pieśń, typ, nazwa, tekst, chwyty, komentarz, kolejność |
| `song_default_form_items` | pieśń, wskazana część, kolejność, przesunięcie tonacji, komentarz |
| `tags` / `song_tags` | kategorie i przeznaczenie liturgiczne |
| `events` | nazwa, termin, miejsce, status, komentarz, publiczny token, wersja |
| `event_songs` | wydarzenie, pieśń, kolejność, transpozycja, tempo, komentarz, wersja |
| `event_song_form_items` | pieśń w wydarzeniu, część źródłowa, kolejność, transpozycja dodatkowa, komentarz |
| `live_states` | wydarzenie, bieżąca pieśń, wystąpienie „teraz”, wystąpienie „następne”, rewizja |
| `change_log` | kto, kiedy, typ obiektu, identyfikator, rodzaj zmiany |
| `integration_imports` | źródło, status, wynik walidacji i raport błędów |

Usunięcie używanej pieśni powinno oznaczać archiwizację. Wydarzenie zachowuje odwołania i swoją wersję formy. Należy zdecydować, czy późniejsza zmiana tekstu pieśni źródłowej ma być od razu widoczna w starych wydarzeniach, czy wydarzenie po publikacji dostaje migawkę treści. Rekomendacja: szkice korzystają z bieżącej pieśni, a opublikowane lub zakończone wydarzenia zachowują migawkę dla historii.

## 6. Najważniejsze ekrany

1. Logowanie i odzyskiwanie hasła.
2. Pulpit: najbliższe wydarzenie, ostatnio edytowane repertuary, szybkie przejście do live.
3. Biblioteka pieśni.
4. Edytor pieśni: dane główne, części, dwukolumnowy edytor tekst/chwyty, forma domyślna, podgląd transpozycji.
5. Lista wydarzeń z filtrem statusu i daty.
6. Edytor wydarzenia: dane, repertuar, kolejność, ustawienia poszczególnych pieśni i formy.
7. Podgląd wydarzenia dla muzyka.
8. Widok live prowadzącego.
9. Widok live muzyka w trybie tylko do odczytu.
10. Widok uczestnika.
11. Nakładka OBS.
12. Administracja użytkownikami, ustawieniami notacji i integracjami.

## 7. Integracje planowane

### 7.1. Google Drive

Import powinien być osobnym, kontrolowanym procesem:

1. wskazanie folderu lub plików;
2. pobranie/eksport do obsługiwanego formatu;
3. rozpoznanie tytułu, części, tekstu i chwytów;
4. ekran mapowania oraz podgląd;
5. wykrywanie duplikatów;
6. ręczne zatwierdzenie przed zapisem;
7. raport elementów, których nie udało się rozpoznać.

Nie należy automatycznie nadpisywać pieśni. Najpierw trzeba otrzymać przykładowe pliki i ustalić, czy źródłem są Dokumenty Google, DOCX, PDF, arkusze czy inny własny format. Od tego zależy jakość i koszt importera.

### 7.2. OpenLP

Integrację trzeba poprzedzić krótkim prototypem zgodności z używaną wersją OpenLP. Możliwe kierunki:

- BandBook eksportuje repertuar/tekst do formatu akceptowanego przez OpenLP;
- BandBook steruje OpenLP przez dostępny interfejs zdalny;
- OpenLP pozostaje systemem prezentacji, a BandBook wysyła tylko aktualny tekst i sygnał zmiany.

Kierunek przepływu i sposób rozwiązywania konfliktów muszą być jednoznaczne. Rekomendacja: BandBook jest źródłem repertuaru i stanu „teraz”, a OpenLP jest odbiorcą prezentacji.

### 7.3. OBS

Najprostsza i rekomendowana integracja to dedykowany publiczny adres typu nakładka, dodany w OBS jako Browser Source. Nakładka:

- pokazuje tylko tekst aktualnej części;
- ma przezroczyste tło;
- reaguje na ten sam numer rewizji co widok uczestnika;
- przyjmuje bezpieczny token wydarzenia;
- pozwala wybrać motyw, rozmiar, margines, maksymalną liczbę linii i animację przejścia;
- nie wymaga instalowania wtyczki OBS.

## 8. Bezpieczeństwo i niezawodność

- HTTPS jest obowiązkowy.
- Hasła są haszowane mechanizmem udostępnianym przez PHP/framework.
- Wszystkie operacje edycyjne mają autoryzację po stronie serwera.
- Publiczne linki używają losowych, odnawialnych tokenów i nigdy nie ujawniają liczbowych identyfikatorów.
- Formularze sesyjne mają ochronę CSRF, API ma limity żądań.
- Treść i komentarze są kodowane przy wyświetlaniu, aby zapobiec XSS.
- Zmiany krytyczne są zapisane w historii.
- Baza jest automatycznie kopiowana co najmniej raz dziennie; należy sprawdzić odtwarzanie kopii.
- Przed wdrożeniem trzeba ustalić zasady licencyjne dotyczące przechowywania i publicznego wyświetlania tekstów pieśni.
- Widok live pokazuje wyraźnie brak połączenia i ostatni potwierdzony zapis.

## 9. Wymagania niefunkcjonalne

- Projekt „mobile first”; podstawowe urządzenia to telefon i tablet.
- Widok live powinien uzyskać pierwszą użyteczną treść w czasie poniżej 2 sekund przy typowym łączu.
- Aktualizacja stanu przy pollingu powinna pojawić się u innych muzyków zwykle w 1–2 sekundy.
- Interfejs musi obsługiwać polskie znaki, tryb ciemny i powiększanie tekstu.
- Sterowanie live ma być dostępne klawiaturą i dotykiem.
- System powinien poprawnie działać dla co najmniej kilkunastu jednoczesnych klientów jednego wydarzenia; większą skalę należy zmierzyć testem obciążenia.
- Aplikacja zachowuje ostatnio wyświetloną treść po utracie sieci, ale w MVP nie obiecuje bezpiecznej edycji offline.

## 10. Etapy realizacji

### Etap 0 — doprecyzowanie i prototyp UX

- odpowiedzi na pytania z sekcji 13;
- makiety edytora pieśni, edytora wydarzenia i live;
- test obsługi na telefonie i tablecie;
- próbka rzeczywistych pieśni i chwytów;
- wybór hostingu oraz potwierdzenie jego parametrów.

Warunek zakończenia: zaakceptowane przepływy i jednoznaczne reguły transpozycji oraz reżyserowania.

### Etap 1 — fundament i biblioteka pieśni

- konta i role;
- CRUD oraz archiwizacja pieśni;
- części pieśni i edytor tekst/chwyty;
- parser i testy transpozycji;
- forma domyślna;
- wyszukiwanie oraz tagi.

Warunek zakończenia: użytkownik potrafi wprowadzić rzeczywistą pieśń, zbudować jej formę i poprawnie wyświetlić ją w dowolnej obsługiwanej tonacji.

### Etap 2 — wydarzenia i repertuar

- tworzenie i edycja wydarzeń;
- kolejność pieśni;
- forma wydarzeniowa;
- transpozycja, tempo i komentarze na wymaganych poziomach;
- podgląd dla muzyka i uczestnika;
- historia wersji rekordu i obsługa konfliktów.

Warunek zakończenia: cały repertuar można przygotować i ponownie otworzyć bez utraty ustawień.

### Etap 3 — tryb live i synchronizacja

- role prowadzącego i muzyka;
- stan „teraz”/„następne”;
- wersjonowany polling;
- zapisywanie zmian do wydarzenia;
- obsługa utraty połączenia;
- test kilku urządzeń i test obciążenia.

Warunek zakończenia: zmiana prowadzącego jest widoczna na wszystkich urządzeniach w zakładanym czasie, a jednoczesne żądania nie tworzą dwóch stanów „teraz”.

### Etap 4 — widok uczestników, OBS i wdrożenie

- publiczny tokenowany widok;
- tryb pełnoekranowy;
- nakładka OBS;
- procedura kopii i odtwarzania;
- monitoring błędów;
- instrukcja wdrożenia i obsługi;
- pilotaż na prawdziwym wydarzeniu.

Warunek zakończenia: zespół przeprowadza wydarzenie na docelowym hostingu i urządzeniach bez ręcznych obejść.

### Etap 5 — import i OpenLP

- importer na podstawie rzeczywistych plików z Google Drive;
- raport oraz ręczne zatwierdzanie importu;
- prototyp i docelowa integracja z OpenLP;
- ewentualna zmiana synchronizacji na push, jeśli pomiary wykażą taką potrzebę.

## 11. Zakres MVP i elementy późniejsze

### MVP

- użytkownicy i podstawowe role;
- biblioteka pieśni, części, chwyty, komentarze, tempo i forma;
- transpozycja całości i wystąpień części;
- wydarzenia i uporządkowany repertuar;
- widok live z zapisem zmian;
- synchronizacja wielu muzyków;
- publiczny widok uczestników;
- podstawowa historia zmian, kopia zapasowa i wdrożenie.

### Po stabilizacji MVP

- Google Drive;
- OpenLP;
- rozbudowane motywy OBS;
- tryb PWA i zaawansowana praca offline;
- załączniki audio, nuty, linki i nagrania referencyjne;
- wiele zespołów/organizacji w jednej instalacji;
- statystyki i planowanie obsady;
- automatyczny metronom lub MIDI.

## 12. Strategia testów i odbioru

- testy jednostkowe parsera chwytów, profili `H/B` i `B/Bb`, wielkości liter, enharmonii, nawiasów i transpozycji;
- testy domenowe formy, klonowania wystąpień i obliczania końcowej tonacji;
- testy integracyjne uprawnień, zapisów wydarzenia i wersjonowania;
- test współbieżności stanu live;
- testy end-to-end trzech głównych przepływów;
- testy na telefonie, tablecie, laptopie oraz w przeglądarce OBS;
- test przy wolnej i przerywanej sieci;
- test eksportu/odtwarzania bazy;
- pilotaż z kilkoma realnymi pieśniami, a następnie pełna próba zespołu.

Trzy scenariusze odbiorowe MVP:

1. Edytor tworzy pieśń z refrenem użytym trzy razy, a każde wystąpienie może mieć inną transpozycję i komentarz.
2. Prowadzący zmienia „następne”, potwierdza „teraz”, a wszystkie otwarte widoki muzyków i uczestników dostają jeden spójny stan.
3. Muzyk zmienia tonację i tempo z live, odświeża stronę, a wartości pozostają zapisane w wydarzeniu bez zmiany pieśni źródłowej.

## 13. Decyzje potrzebne przed kodowaniem

Nie blokują przygotowania makiet, ale muszą zostać rozstrzygnięte przed implementacją właściwych modułów:

1. Jakie formaty znajdują się na Google Drive: Dokumenty Google, DOCX, PDF, tekst, ChordPro, arkusze?
2. Czy aplikacja jest wyłącznie dla jednego zespołu, czy od początku ma obsługiwać wiele niezależnych zespołów?
3. Kto może zmieniać dane z widoku live: wyłącznie prowadzący czy każdy muzyk?
4. Czy uczestnik widzi tylko aktualną część, całą pieśń, czy wybiera tryb?
5. Czy zakończone wydarzenie ma być niezmienną migawką, czy zawsze pokazywać najnowszą wersję pieśni?
6. Jakiej wersji OpenLP używacie i czy BandBook ma sterować OpenLP, czy tylko eksportować do niego dane?
7. Jakie parametry ma docelowy hosting: wersja PHP, MySQL/MariaDB, cron, SSH, Composer, możliwość ustawienia katalogu publicznego i limity równoczesnych żądań?
8. Czy publiczny widok może pokazywać tekst każdemu z linkiem i czy macie uregulowane prawa do takiego wyświetlania?

## 14. Rekomendowane pierwsze następne działanie

Przed kodowaniem należy przygotować klikalne makiety trzech kluczowych ekranów na podstawie 3–5 prawdziwych pieśni:

1. edytor pieśni i formy;
2. edytor wydarzenia;
3. live na telefonie/tablecie wraz z widokiem uczestnika.

Makiety powinny rozstrzygnąć najtrudniejsze interakcje: wpisywanie chwytów, duplikowanie wystąpień części, transpozycję na dwóch poziomach oraz dwustopniowe wskazywanie „następne”/„teraz”. Dopiero po ich akceptacji warto zamrozić schemat danych i rozpocząć implementację Etapu 1.

## 15. Wnioski z przykładowych pieśni

Przekazane przykłady potwierdzają, że podstawowy profil wejściowy zespołu to polska notacja `H/B` z małymi literami dla akordów molowych.

### 15.1. Przypadki, które parser musi obsłużyć

| Zapis źródłowy | Interpretacja wewnętrzna | Prezentacja międzynarodowa |
|---|---|---|
| `D A e h` | D-dur, A-dur, e-moll, h-moll | `D A Em Bm` |
| `fis h fis h` | fis-moll, h-moll, fis-moll, h-moll | `F#m Bm F#m Bm` |
| `F f C` | F-dur, f-moll, C-dur | `F Fm C` |
| `G D G A (h)` | cztery akordy i opcjonalny h-moll | `G D G A (Bm)` |
| `C G a` | C-dur, G-dur, a-moll | `C G Am` |

Wielkość litery jest semantyczna i nie może zostać utracona podczas importu. Parser nie może automatycznie zamieniać całego wejścia na wielkie lub małe litery.

### 15.2. Proponowany podział pieśni na części

**Każdy wschód słońca**

- Refren
- Zwrotka 1
- Zwrotka 2
- Zwrotka 3
- Zwrotka 4
- Zwrotka 5

Robocza forma domyślna do potwierdzenia: `Refren → Zwrotka 1 → Refren → Zwrotka 2 → Refren → Zwrotka 3 → Refren → Zwrotka 4 → Refren → Zwrotka 5 → Refren`.

**Niechaj miłość Twa**

- jedna część, roboczo `Refren` lub `Całość`;
- cztery linie tekstu, każda z osobną linią chwytów.

**Otwórz me oczy Panie**

- Zwrotka: od „Otwórz me oczy…” do „Chcę widzieć Ciebie x2”;
- Refren: od „Wywyższonego widzieć chcę…” do „Chcę widzieć Ciebie”;
- zapis `x2` pozostaje elementem tekstu lub znacznikiem powtórzenia linii, nie akordem.

Ten podział jest materiałem do makiet i testów parsera. Przed zapisaniem pieśni do docelowej biblioteki użytkownik zawsze zatwierdza wykryte części i formę.
