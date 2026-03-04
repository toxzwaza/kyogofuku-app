#!/usr/bin/env python3
"""
Google Calendar refresh トークン維持用スクリプト

Laravel アプリの /api/google-calendar/keep-token に HTTP アクセスし、
トークンを使用することで6ヶ月未使用による失効を防ぎます。

使用例:
  python3 scripts/keep_google_calendar_token_alive.py

cron で週1回実行する例:
  0 2 * * 1 cd /path/to/kyogofuku-app && python3 scripts/keep_google_calendar_token_alive.py
"""

import argparse
import json
import os
import sys
from datetime import datetime

import urllib.error
import urllib.request

# ログファイル（スクリプトと同じ階層）
LOG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "keep_google_calendar_token_alive.log")


def log(msg: str) -> None:
    """ログをファイルと標準出力に出力"""
    line = f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {msg}"
    print(line)
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(line + "\n")


def main():
    parser = argparse.ArgumentParser(description="Google Calendar refresh token keep-alive")
    parser.add_argument(
        "--url",
        default="https://kyogofuku-event.com",
        help="アプリのベースURL",
    )
    parser.add_argument(
        "--token",
        default="google_calendar_keep_token",
        help="認証トークン (GOOGLE_CALENDAR_KEEP_TOKEN_SECRET)",
    )
    args = parser.parse_args()

    token = args.token or os.environ.get("GOOGLE_CALENDAR_KEEP_TOKEN_SECRET")
    if not token:
        log("Error: --token または環境変数 GOOGLE_CALENDAR_KEEP_TOKEN_SECRET を設定してください")
        sys.exit(1)

    url = args.url.rstrip("/")
    if not url.endswith("/api/google-calendar/keep-token"):
        url = url.rstrip("/") + "/api/google-calendar/keep-token"
    url_with_token = f"{url}?token={token}"

    req = urllib.request.Request(url_with_token)
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            body = res.read().decode()
            try:
                data = json.loads(body)
                msg = data.get("message", body)
            except json.JSONDecodeError:
                msg = body
            log(f"Status: {res.status} {msg}")
            if res.status == 200:
                sys.exit(0)
            sys.exit(1)
    except urllib.error.HTTPError as e:
        err_body = e.read().decode()
        try:
            data = json.loads(err_body)
            err_msg = data.get("message", err_body)
        except json.JSONDecodeError:
            err_msg = err_body
        log(f"HTTP Error {e.code}: {e.reason} {err_msg}")
        sys.exit(1)
    except urllib.error.URLError as e:
        log(f"URL Error: {e.reason}")
        sys.exit(1)


if __name__ == "__main__":
    main()
