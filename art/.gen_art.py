"""Generate laravel-webpush-kit art SVGs (family of laravel-mysql-advisor). Accent: fuchsia."""
import os, sys

OUT = sys.argv[1]
os.makedirs(OUT, exist_ok=True)
SANS = 'Helvetica, Arial, sans-serif'
MONO = 'Menlo, Consolas, monospace'
ACC = '#E879F9'      # fuchsia-400, accent on dark
ACC_DEEP = '#C026D3' # fuchsia-600, badge fill
ACC_LIGHT_TXT = '#A21CAF'  # fuchsia-700 (not a warm hue) for text on light

ICON_DEFS = '''    <linearGradient id="iconbg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0F172A"/>
      <stop offset="100%" stop-color="#132844"/>
    </linearGradient>'''

ICON_BODY = f'''    <rect width="512" height="512" rx="112" fill="url(#iconbg)"/>
    <rect x="64" y="96" width="384" height="312" rx="30" fill="#1E293B"/>
    <path d="M64 126 a30 30 0 0 1 30 -30 H418 a30 30 0 0 1 30 30 V156 H64 Z" fill="#334155"/>
    <circle cx="98" cy="126" r="8" fill="#64748B"/>
    <circle cx="124" cy="126" r="8" fill="#64748B"/>
    <circle cx="150" cy="126" r="8" fill="#64748B"/>
    <rect x="182" y="118" width="232" height="16" rx="8" fill="#475569"/>
    <rect x="236" y="176" width="190" height="72" rx="16" fill="#0F172A" stroke="{ACC}" stroke-width="5"/>
    <circle cx="268" cy="212" r="15" fill="{ACC}"/>
    <rect x="294" y="198" width="104" height="12" rx="6" fill="#CBD5E1"/>
    <rect x="294" y="218" width="72" height="10" rx="5" fill="#475569"/>
    <rect x="96" y="184" width="104" height="12" rx="6" fill="#475569"/>
    <rect x="96" y="208" width="76" height="12" rx="6" fill="#475569"/>
    <rect x="330" y="282" width="88" height="12" rx="6" fill="#475569"/>
    <rect x="330" y="310" width="64" height="12" rx="6" fill="#475569"/>
    <rect x="330" y="338" width="80" height="12" rx="6" fill="#475569"/>
    <path d="M231 250 A124 124 0 0 1 280 320" fill="none" stroke="{ACC}" stroke-width="13" stroke-linecap="round"/>
    <path d="M246 229 A150 150 0 0 1 305 313" fill="none" stroke="{ACC}" stroke-width="13" stroke-linecap="round"/>
    <circle cx="160" cy="352" r="98" fill="{ACC_DEEP}" stroke="#0F172A" stroke-width="14"/>
    <circle cx="160" cy="292" r="10" fill="#FFFFFF"/>
    <path d="M160 298 C188 298 206 318 206 348 V370 L218 386 H102 L114 370 V348 C114 318 132 298 160 298 Z" fill="#FFFFFF"/>
    <circle cx="160" cy="395" r="13" fill="#FFFFFF"/>
    <circle cx="203" cy="306" r="17" fill="#22C55E" stroke="{ACC_DEEP}" stroke-width="6"/>'''


def svg(w, h, defs, body):
    return (f'<svg width="{w}" height="{h}" viewBox="0 0 {w} {h}" xmlns="http://www.w3.org/2000/svg">\n'
            f'  <defs>\n{defs}\n  </defs>\n{body}\n</svg>\n')


def icon_at(x, y, s):
    return f'  <g transform="translate({x},{y}) scale({s})">\n{ICON_BODY}\n</g>'


def esc(t):
    return t.replace('&', '&amp;')


def pills(x, y, items, size, h, fill, stroke, txt):
    out, cx = [], x
    for it in items:
        w = round(len(it) * size * 0.56 + size * 2.4)
        out.append(f'<g transform="translate({cx},{y})"><rect width="{w}" height="{h}" rx="{h/2}" fill="{fill}" stroke="{stroke}"/>'
                   f'<text x="{w/2}" y="{h*0.66:.2f}">{esc(it)}</text></g>')
        cx += w + 10
    return (f'  <g font-family="{SANS}" font-size="{size}" font-weight="600" fill="{txt}" text-anchor="middle">'
            + ''.join(out) + '</g>')


CARDS = [
    ('#22C55E', 'Subscribed', 'POST /webpush/subscriptions → 201'),
    (ACC, 'Push delivered', 'php artisan push:test → sent'),
    ('#EF4444', 'Endpoint gone', '410 Gone → subscription pruned'),
]


def cards(x, y, w, fill, stroke, title, mono):
    out = []
    for i, (dot, t, m) in enumerate(CARDS):
        out.append(f'<g transform="translate({x},{y + i*64})"><rect width="{w}" height="54" rx="12" fill="{fill}" stroke="{stroke}"/>'
                   f'<circle cx="22" cy="27" r="7" fill="{dot}"/>'
                   f'<text x="40" y="23" font-family="{SANS}" font-size="14" font-weight="700" fill="{title}">{t}</text>'
                   f'<text x="40" y="42" font-family="{MONO}" font-size="11.5" fill="{mono}">{esc(m)}</text></g>')
    return '  ' + ''.join(out)


def banner(dark):
    bg = ('#0B1220', '#111C33') if dark else ('#F8FAFC', '#EEF2F7')
    t1, t2 = ('#F8FAFC', ACC) if dark else ('#0F172A', ACC_LIGHT_TXT)
    pf, ps, pt = ('#16213A', '#24324F', '#CBD5E1') if dark else ('#FFFFFF', '#E2E8F0', '#334155')
    mono = '#F0ABFC' if dark else ACC_LIGHT_TXT
    foot = '#64748B' if dark else '#94A3B8'
    defs = (f'''    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{bg[0]}"/>
      <stop offset="100%" stop-color="{bg[1]}"/>
    </linearGradient>\n''' + ICON_DEFS)
    body = '\n'.join([
        '  <rect width="1200" height="300" fill="url(#bg)"/>',
        icon_at(60, 75, 0.29297),
        f'  <text x="235" y="148" font-family="{SANS}" font-size="52" font-weight="700" fill="{t1}">Laravel WebPush Kit</text>',
        f'  <text x="238" y="188" font-family="{SANS}" font-size="21" font-weight="500" fill="{t2}" letter-spacing="0.3">The browser side of Laravel web push notifications</text>',
        pills(238, 214, ['subscribe button', 'service worker', 'iOS home screen'], 15, 32, pf, ps, pt),
        cards(890, 52, 280, pf, ps, pt, mono),
        f'  <text x="1170" y="275" text-anchor="end" font-family="{SANS}" font-size="15" fill="{foot}">amjadiqbal/laravel-webpush-kit</text>',
    ])
    return svg(1200, 300, defs, body)


def social():
    defs = ('''    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0B1220"/>
      <stop offset="100%" stop-color="#111C33"/>
    </linearGradient>\n''' + ICON_DEFS)
    body = '\n'.join([
        '  <rect width="1280" height="640" fill="url(#bg)"/>',
        icon_at(110, 96, 0.5),
        cards(110, 380, 300, '#16213A', '#24324F', '#CBD5E1', '#F0ABFC'),
        f'  <text x="470" y="200" font-family="{SANS}" font-size="66" font-weight="700" fill="#F8FAFC">Laravel WebPush</text>',
        f'  <text x="470" y="276" font-family="{SANS}" font-size="66" font-weight="700" fill="#F8FAFC">Kit</text>',
        f'  <text x="472" y="330" font-family="{SANS}" font-size="26" font-weight="600" fill="{ACC}" letter-spacing="0.3">Browser push for Laravel notifications.</text>',
        f'  <text x="472" y="366" font-family="{SANS}" font-size="26" font-weight="600" fill="{ACC}" letter-spacing="0.3">Button, service worker, iOS and cleanup.</text>',
        pills(472, 410, ['polite permission prompt', 'service worker', 'Blade · Livewire'], 17, 38, '#16213A', '#24324F', '#CBD5E1'),
        pills(472, 460, ['iOS home-screen guide', 'push:test', '404/410 pruning'], 17, 38, '#16213A', '#24324F', '#CBD5E1'),
        f'  <text x="472" y="560" font-family="{SANS}" font-size="19" font-weight="600" fill="#94A3B8">Built on laravel-notification-channels/webpush</text>',
        f'  <text x="1170" y="605" text-anchor="end" font-family="{SANS}" font-size="19" fill="#64748B">github.com/amjadiqbal/laravel-webpush-kit</text>',
    ])
    return svg(1280, 640, defs, body)


files = {
    'icon.svg': svg(512, 512, ICON_DEFS, ICON_BODY),
    'banner-dark.svg': banner(True),
    'banner-light.svg': banner(False),
    'social-preview.svg': social(),
}
for n, c in files.items():
    open(os.path.join(OUT, n), 'w').write(c)
print('ok')
