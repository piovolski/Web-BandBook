#!/usr/bin/env python3
"""Uruchamia lokalny serwer PHP dla aplikacji BandBook."""

from __future__ import annotations

import argparse
import os
import shutil
import subprocess
import sys
from pathlib import Path


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Uruchom lokalny serwer BandBook.")
    parser.add_argument(
        "--port",
        type=int,
        default=8000,
        help="Port serwera (domyślnie: 8000).",
    )
    parser.add_argument(
        "--address",
        default="127.0.0.1",
        help="Adres nasłuchiwania (domyślnie: 127.0.0.1).",
    )
    parser.add_argument(
        "--php",
        dest="php_path",
        help="Opcjonalna ścieżka do pliku php lub php.exe.",
    )
    return parser.parse_args()


def find_php(explicit_path: str | None) -> str | None:
    if explicit_path:
        candidate = Path(explicit_path).expanduser().resolve()
        return str(candidate) if candidate.is_file() else None

    found = shutil.which("php")
    if found:
        return found

    if sys.platform == "win32":
        try:
            result = subprocess.run(
                ["where.exe", "php"],
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                check=False,
            )
            for line in result.stdout.splitlines():
                candidate = Path(line.strip())
                if candidate.is_file():
                    return str(candidate)
        except OSError:
            pass

        local_app_data = os.environ.get("LOCALAPPDATA")
        if local_app_data:
            packages = Path(local_app_data) / "Microsoft" / "WinGet" / "Packages"
            candidates = sorted(
                packages.glob(
                    "PHP.PHP.*_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
                ),
                reverse=True,
            )
            if candidates:
                return str(candidates[0])

    return None


def configure_console() -> None:
    for stream in (sys.stdout, sys.stderr):
        reconfigure = getattr(stream, "reconfigure", None)
        if callable(reconfigure):
            reconfigure(encoding="utf-8", errors="replace")


def main() -> int:
    configure_console()
    args = parse_arguments()

    if not 1 <= args.port <= 65535:
        print("Błąd: port musi mieścić się w zakresie 1–65535.", file=sys.stderr)
        return 2

    project_root = Path(__file__).resolve().parent
    public_directory = project_root / "public"
    entry_file = public_directory / "index.php"

    if not entry_file.is_file():
        print(f"Błąd: nie znaleziono pliku {entry_file}.", file=sys.stderr)
        return 1

    php = find_php(args.php_path)
    if php is None:
        print(
            "Błąd: nie znaleziono PHP. Dodaj php do PATH albo użyj "
            "parametru --php C:\\sciezka\\php.exe.",
            file=sys.stderr,
        )
        return 1

    server_url = f"http://{args.address}:{args.port}"
    command = [php, "-S", f"{args.address}:{args.port}", "-t", str(public_directory)]

    print()
    print("BandBook")
    print(f"Serwer działa pod adresem: {server_url}")
    print("Zatrzymaj serwer skrótem Ctrl+C.")
    print()

    try:
        completed = subprocess.run(command, cwd=project_root, check=False)
        return completed.returncode
    except KeyboardInterrupt:
        print("\nSerwer został zatrzymany.")
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
