#!/usr/bin/env python3
"""Annotate a k6 web-dashboard HTML export with test-run metadata.

Usage:
    k6-annotate-report.py <html-file> <test-name> <profile> <stamp>

Patches two things in-place:
  1. <title>  — becomes "k6 · {test} · {profile} · {stamp}"
  2. A fixed banner div is inserted just before <div id="root"> so the
     metadata is always visible above the React dashboard.
"""

import re
import sys

html_file = sys.argv[1]
test_name = sys.argv[2]
profile   = sys.argv[3]
stamp     = sys.argv[4]

with open(html_file, encoding="utf-8") as f:
    html = f.read()

# ── title ──────────────────────────────────────────────────────────────────
html = re.sub(
    r"<title>[^<]*</title>",
    f"<title>k6 \u00b7 {test_name} \u00b7 {profile} \u00b7 {stamp}</title>",
    html,
)

# ── meta banner ─────────────────────────────────────────────────────────────
# Inserted before <div id="root"> so it sits above the React app in DOM order.
# position:sticky keeps it pinned while the user scrolls the dashboard.
banner = (
    '<div id="k6-meta-banner" style="'
    "position:sticky;top:0;z-index:9999;"
    "background:#1b1b1b;color:#9e9e9e;"
    "padding:7px 20px;"
    "font:13px/1.5 'JetBrains Mono',ui-monospace,monospace;"
    "border-bottom:2px solid #f0ab00;"
    'display:flex;flex-wrap:wrap;gap:20px;align-items:center">'
    '<span style="color:#f0ab00;font-weight:700;font-size:14px">k6</span>'
    f'<span>test\u2002<strong style="color:#ffffff">{test_name}</strong></span>'
    f'<span>profile\u2002<strong style="color:#4da3ff">{profile}</strong></span>'
    f'<span>run\u2002<strong style="color:#b0b0b0">{stamp}</strong></span>'
    "</div>"
)

html = html.replace(
    '<div id="root"></div>',
    banner + "\n    " + '<div id="root"></div>',
    1,
)

with open(html_file, "w", encoding="utf-8") as f:
    f.write(html)

print(f"Annotated: {html_file}")
