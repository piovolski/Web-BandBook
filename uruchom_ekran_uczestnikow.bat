@echo off
setlocal

rem ================================================================
rem BandBook - staly ekran uczestnikow w Chrome Kiosk
rem
rem Zmien ponizszy adres na adres BandBook na Synology/nginx, np.:
rem set "SCREEN_URL=http://192.168.1.20/index.php?route=screen"
rem ================================================================
set "SCREEN_URL=http://127.0.0.1:8000/index.php?route=screen"

rem Opcjonalnie adres mozna podac w zmiennej BANDBOOK_SCREEN_URL
rem albo jako pierwszy argument skryptu.
if defined BANDBOOK_SCREEN_URL set "SCREEN_URL=%BANDBOOK_SCREEN_URL%"
if /i "%~1"=="--dry-run" (
    set "DRY_RUN=1"
) else if not "%~1"=="" (
    set "SCREEN_URL=%~1"
)

rem Domyslnie projektor jest po prawej stronie monitora Full HD.
rem Dla projektora po lewej ustaw np. -1920,0, a dla jednego ekranu 0,0.
set "WINDOW_POSITION=1920,0"
if defined BANDBOOK_WINDOW_POSITION set "WINDOW_POSITION=%BANDBOOK_WINDOW_POSITION%"

rem Daj systemowi i sieci czas na uruchomienie po zalogowaniu.
if not defined DRY_RUN timeout /t 12 /nobreak >nul

set "CHROME_EXE="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "CHROME_EXE=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined CHROME_EXE if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set "CHROME_EXE=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined CHROME_EXE if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" set "CHROME_EXE=%LocalAppData%\Google\Chrome\Application\chrome.exe"
if not defined CHROME_EXE for /f "delims=" %%I in ('where chrome.exe 2^>nul') do if not defined CHROME_EXE set "CHROME_EXE=%%I"

if not defined CHROME_EXE (
    echo Nie znaleziono Google Chrome.
    echo Zainstaluj Chrome albo popraw sciezke w pliku uruchom_ekran_uczestnikow.bat.
    pause
    exit /b 1
)

set "KIOSK_PROFILE=%LocalAppData%\BandBook\ChromeKioskProfile"

if defined DRY_RUN (
    echo Chrome: %CHROME_EXE%
    echo Adres: %SCREEN_URL%
    echo Pozycja ekranu: %WINDOW_POSITION%
    echo Profil kiosku: %KIOSK_PROFILE%
    exit /b 0
)

start "BandBook - ekran uczestnikow" "%CHROME_EXE%" ^
    --kiosk ^
    --new-window ^
    --no-first-run ^
    --no-default-browser-check ^
    --disable-session-crashed-bubble ^
    --disable-pinch ^
    --overscroll-history-navigation=0 ^
    --window-position=%WINDOW_POSITION% ^
    --user-data-dir="%KIOSK_PROFILE%" ^
    "%SCREEN_URL%"

endlocal
