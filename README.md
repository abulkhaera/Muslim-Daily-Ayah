# Muslim Daily Ayah

A lightweight WordPress plugin that displays a rotating Quranic ayah (Arabic text + English translation) each day, with a click-through popup showing the tafsir (explanation). Drop the `[daily_ayah]` shortcode anywhere on your site and it handles the rest — no manual updates required.

## Features

- **One ayah per day** — cycles in order through a verse list you control, based on the calendar date (Asia/Singapore timezone). Not random, so the sequence is predictable and repeatable.
- **Arabic + translation**, fetched live from the free [Al Quran Cloud API](https://alquran.cloud/api) (no API key required). Translation is fixed to Saheeh International.
- **Tafsir on click**, fetched from a free, keyless CDN mirror of the [spa5k/tafsir_api](https://github.com/spa5k/tafsir_api) project. Fixed to an abridged English Tafsir Ibn Kathir.
- **Resilient by design** — if an API call fails, the widget falls back to the last successfully fetched day's content instead of going blank, and as a last resort shows a single hardcoded verse rather than ever guessing at scripture.
- **Daily caching** — each source is only fetched once per calendar day per site (via WordPress transients), so the widget stays fast and doesn't hammer external APIs.
- **Accessible modal** — the tafsir popup traps focus, closes on `Esc`, and respects `prefers-reduced-motion`.
- **Simple settings page** (Settings → Daily Ayah) to edit the verse list, the Arabic script edition, and a light/dark text theme.
- **REST endpoint** at `/wp-json/muslim-daily-ayah/v1/daily-ayah` if you want to pull today's ayah into your own theme or app.

## Installation

1. Download or clone this repository.
2. Zip the `muslim-daily-ayah` folder, or copy it directly into `wp-content/plugins/`.
3. Activate **Muslim Daily Ayah** from the WordPress Plugins screen.
4. Go to **Settings → Daily Ayah** to review the default verse list (10 commonly referenced ayat are included as a starting point) and adjust it to your needs.
5. Add the shortcode `[daily_ayah]` to any page, post, or widget area.

## Usage

**Shortcode:**

```
[daily_ayah]
[daily_ayah theme="light"]
```

`theme` accepts `dark` (default) or `light` — it only affects text/icon colour, since the closed-state card has no background of its own by design and is meant to sit on top of whatever background your page section already has.

**REST API:**

```
GET /wp-json/muslim-daily-ayah/v1/daily-ayah
```

Returns today's resolved ayah as JSON (Arabic text, translation, surah name, reference, tafsir, and source status).

## Configuration notes

- Verse references are entered one per line in `surah:ayah` format (e.g. `2:255`), and are cycled through in the order listed — one new verse per calendar day.
- The translation and tafsir editions are intentionally **fixed in code**, not exposed as settings-page dropdowns, to prevent an incorrect edition being selected by accident. To change them, edit the `TRANSLATION_EDITION` / `TAFSIR_EDITION` constants near the top of `includes/class-ayah-source.php`.
- **Please have a qualified reviewer check the default verse list and the translation/tafsir text before publishing on a live site.** This plugin pulls content from third-party APIs; it does not curate or vet religious accuracy.

## File structure

```
muslim-daily-ayah/
├── muslim-daily-ayah.php        # Plugin bootstrap, shortcode, REST route, asset loading
├── includes/
│   ├── class-ayah-source.php    # Fetching, caching, and fallback logic for ayah/tafsir content
│   └── class-admin-settings.php # Settings → Daily Ayah admin page
├── assets/
│   ├── daily-ayah.css           # Styles for the card + modal (namespaced under .mda)
│   └── daily-ayah.js            # Modal open/close + focus-trap behaviour
└── readme.txt                   # WordPress.org-style plugin readme
```

## Requirements

- WordPress 6.0+
- PHP 7.4+
- An outbound internet connection from your server (to reach `api.alquran.cloud` and the tafsir CDN)

## License

GPL-2.0-or-later
