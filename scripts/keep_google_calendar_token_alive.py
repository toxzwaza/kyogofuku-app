#!/usr/bin/env python3
"""
Google Calendar refresh トークン維持用スクリプト

Laravel アプリの /api/google-calendar/keep-token に HTTP アクセスし、
トークンを使用することで6ヶ月未使用による失効を防ぎます。

使用例:
  python scripts/keep_google_calendar_token_alive.py
  python scripts/keep_google_calendar_token_alive.py --url https://example.com
  python scripts/keep_google_calendar_token_alive.py --url https://example.com --token YOUR_SECRET_TOKEN

cron で週1回実行する例:
  0 2 * * 1 python3 /path/to/kyogofuku-app/scripts/keep_google_calendar_token_alive.py
"""

import argparse
import sys
import urllib.request
import urllib.error


def main():
    parser = argparse.ArgumentParser(description="Google Calendar refresh token keep-alive")
    parser.add_argument(
        "--url",
        default="http://localhost/api/google-calendar/keep-token",
        help="アプリのベースURL + /api/google-calendar/keep-token",
    )
    parser.add_argument(
        "--token",
        help="認証トークン (GOOGLE_CALENDAR_KEEP_TOKEN_SECRET)。省略時は環境変数 GOOGLE_CALENDAR_KEEP_TOKEN_SECRET を使用",
    )
    args = parser.parse_args()

    token = args.token
    if not token:
        import os
        token = os.environ.get("GOOGLE_CALENDAR_KEEP_TOKEN_SECRET")

    if not token:
        print("Error: --token または環境変数 GOOGLE_CALENDAR_KEEP_TOKEN_SECRET を設定してください", file=sys.stderr)
        sys.exit(1)

    url = args.url.rstrip("/")
    if not url.endswith("/api/google-calendar/keep-token"):
        url = url.rstrip("/") + "/api/google-calendar/keep-token"
    url_with_token = f"{url}?token={token}"

    req = urllib.request.Request(url_with_token)
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            body = res.read().decode()
            print(f"Status: {res.status}")
            print(body)
            if res.status == 200:
                sys.exit(0)
            sys.exit(1)
    except urllib.error.HTTPError as e:
        print(f"HTTP Error {e.code}: {e.reason}", file=sys.stderr)
        print(e.read().decode(), file=sys.stderr)
        sys.exit(1)
    except urllib.error.URLError as e:
        print(f"URL Error: {e.reason}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
