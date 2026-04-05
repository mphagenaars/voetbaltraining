#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from typing import List
from urllib.error import URLError
from urllib.request import urlopen

from playwright.sync_api import sync_playwright


@dataclass
class ScreenSpec:
    slug: str
    path_template: str
    auth: str


VIEWPORTS = [
    {
        "name": "desktop",
        "width": 1440,
        "height": 900,
        "is_mobile": False,
        "has_touch": False,
    },
    {
        "name": "mobile",
        "width": 390,
        "height": 844,
        "is_mobile": True,
        "has_touch": True,
    },
]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Capture MUST-11 visual baseline screenshots.")
    parser.add_argument(
        "--base-url",
        default="http://127.0.0.1:18080",
        help="Base URL for the local app server.",
    )
    parser.add_argument(
        "--screens-file",
        default="scripts/quality/must11_screens.txt",
        help="Screen definition file (slug|path|auth).",
    )
    parser.add_argument(
        "--output-dir",
        default="scripts/quality/visual_baseline",
        help="Baseline output directory.",
    )
    parser.add_argument(
        "--server-host",
        default="127.0.0.1",
        help="Host for temporary PHP server.",
    )
    parser.add_argument(
        "--server-port",
        type=int,
        default=18080,
        help="Port for temporary PHP server.",
    )
    return parser.parse_args()


def read_screens(path: Path) -> List[ScreenSpec]:
    if not path.exists():
        raise FileNotFoundError(f"Screens file ontbreekt: {path}")

    screens: List[ScreenSpec] = []
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        parts = [part.strip() for part in line.split("|")]
        if len(parts) < 3:
            raise ValueError(f"Ongeldige regel in screens file: {raw_line}")
        slug, path_template, auth = parts[0], parts[1], parts[2]
        if auth not in {"guest", "admin"}:
            raise ValueError(f"Ongeldige auth rol '{auth}' voor screen '{slug}'")
        screens.append(ScreenSpec(slug=slug, path_template=path_template, auth=auth))

    if not screens:
        raise ValueError("Geen schermen gevonden in must11_screens.txt")
    return screens


def prepare_fixture(root_dir: Path) -> dict:
    command = ["php", "scripts/prepare_must11_fixture.php"]
    result = subprocess.run(
        command,
        cwd=str(root_dir),
        check=True,
        capture_output=True,
        text=True,
    )
    payload = (result.stdout or "").strip()
    if not payload:
        raise RuntimeError("Lege fixture response ontvangen.")
    fixture = json.loads(payload)
    required = ["username", "password", "admin_user_id", "team_id", "match_id"]
    for key in required:
        if key not in fixture:
            raise RuntimeError(f"Fixture mist sleutel: {key}")
    return fixture


def wait_for_server(base_url: str, timeout_seconds: float = 20.0) -> None:
    deadline = time.time() + timeout_seconds
    test_url = f"{base_url.rstrip('/')}/login"
    while time.time() < deadline:
        try:
            with urlopen(test_url, timeout=2.0) as response:
                if response.status < 500:
                    return
        except URLError:
            time.sleep(0.2)
            continue
        except Exception:
            time.sleep(0.2)
            continue
    raise TimeoutError(f"PHP server niet bereikbaar op {test_url}")


def start_php_server(root_dir: Path, host: str, port: int) -> subprocess.Popen:
    command = [
        "php",
        "-S",
        f"{host}:{port}",
        "-t",
        "public",
        "public/index.php",
    ]
    return subprocess.Popen(
        command,
        cwd=str(root_dir),
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )


def ensure_dirs(output_dir: Path) -> None:
    output_dir.mkdir(parents=True, exist_ok=True)
    for viewport in VIEWPORTS:
        (output_dir / viewport["name"]).mkdir(parents=True, exist_ok=True)


def login_admin(page, base_url: str, username: str, password: str) -> None:
    page.goto(f"{base_url}/logout", wait_until="domcontentloaded")
    page.goto(f"{base_url}/login", wait_until="domcontentloaded")
    page.fill("#username", username)
    page.fill("#password", password)
    page.locator("button[type='submit']").click()
    page.wait_for_load_state("networkidle")
    if "/login" in page.url:
        raise RuntimeError("Admin login mislukt; nog op /login na submit.")


def capture_baselines(
    base_url: str,
    output_dir: Path,
    screens: List[ScreenSpec],
    fixture: dict,
    browser_env: dict,
) -> None:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, env=browser_env)
        try:
            for viewport in VIEWPORTS:
                context = browser.new_context(
                    viewport={"width": viewport["width"], "height": viewport["height"]},
                    is_mobile=viewport["is_mobile"],
                    has_touch=viewport["has_touch"],
                    device_scale_factor=1,
                    locale="nl-NL",
                    timezone_id="Europe/Amsterdam",
                )
                page = context.new_page()
                admin_logged_in = False

                for screen in screens:
                    if screen.auth == "guest":
                        if admin_logged_in:
                            page.goto(f"{base_url}/logout", wait_until="networkidle")
                            admin_logged_in = False
                    elif screen.auth == "admin":
                        if not admin_logged_in:
                            login_admin(page, base_url, fixture["username"], fixture["password"])
                            admin_logged_in = True

                    rendered_path = screen.path_template.format(**fixture)
                    target_url = f"{base_url}{rendered_path}"
                    page.goto(target_url, wait_until="networkidle")
                    page.add_style_tag(
                        content="""
                            *, *::before, *::after {
                                animation: none !important;
                                transition: none !important;
                                caret-color: transparent !important;
                            }
                        """
                    )
                    page.evaluate("window.scrollTo(0, 0)")
                    page.wait_for_timeout(250)

                    screenshot_path = output_dir / viewport["name"] / f"{screen.slug}.png"
                    page.screenshot(path=str(screenshot_path), full_page=False)
                    print(f"[baseline] {viewport['name']} {screen.slug}")

                context.close()
        finally:
            browser.close()


def write_manifest(output_dir: Path, base_url: str, fixture: dict, screens: List[ScreenSpec]) -> None:
    manifest = {
        "generated_at": time.strftime("%Y-%m-%dT%H:%M:%S%z"),
        "base_url": base_url,
        "viewports": [
            {"name": vp["name"], "width": vp["width"], "height": vp["height"]} for vp in VIEWPORTS
        ],
        "fixture": {
            "username": fixture["username"],
            "admin_user_id": int(fixture["admin_user_id"]),
            "team_id": int(fixture["team_id"]),
            "match_id": int(fixture["match_id"]),
        },
        "screens": [
            {"slug": screen.slug, "path": screen.path_template, "auth": screen.auth} for screen in screens
        ],
    }
    (output_dir / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def main() -> int:
    args = parse_args()
    root_dir = Path(__file__).resolve().parents[1]
    screens_file = (root_dir / args.screens_file).resolve()
    output_dir = (root_dir / args.output_dir).resolve()
    base_url = args.base_url.rstrip("/")

    screens = read_screens(screens_file)
    fixture = prepare_fixture(root_dir)
    ensure_dirs(output_dir)

    local_lib_dir = root_dir / ".cache" / "must11-libs" / "root" / "usr" / "lib" / "x86_64-linux-gnu"
    browser_env = dict(os.environ)
    if local_lib_dir.exists():
        current_ld = browser_env.get("LD_LIBRARY_PATH", "").strip()
        if current_ld:
            browser_env["LD_LIBRARY_PATH"] = f"{local_lib_dir}:{current_ld}"
        else:
            browser_env["LD_LIBRARY_PATH"] = str(local_lib_dir)

    server_process = start_php_server(root_dir, args.server_host, args.server_port)
    try:
        wait_for_server(base_url)
        capture_baselines(base_url, output_dir, screens, fixture, browser_env)
        write_manifest(output_dir, base_url, fixture, screens)
    finally:
        server_process.terminate()
        try:
            server_process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server_process.kill()
            server_process.wait(timeout=5)

    print("MUST-11 baseline capture voltooid.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
