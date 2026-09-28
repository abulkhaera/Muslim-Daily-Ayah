=== Muslim Daily Ayah ===
Contributors: yourwpusername
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

A daily-rotating Quranic ayah (Arabic + translation) with a click-through tafsir popup.

== Description ==

Shows one ayah per calendar day (Asia/Singapore), chosen in order from a
curated list you control — not randomly from the whole Quran.

- Arabic + translation: fetched live from Al Quran Cloud (api.alquran.cloud),
  free, no key. Translation is fixed to Saheeh International (en.sahih) —
  not admin-configurable.
- Tafsir: fetched live from a jsDelivr-hosted CDN mirror of spa5k/tafsir_api,
  free, no key, no rate limit. Fixed to Tafsir Ibn Kathir (abridged,
  English) — not admin-configurable. Al Quran Cloud does not carry English
  tafsir, which is why this is a separate source from the Arabic/translation
  above.

Both are cached for the day, so neither API is called more than once per
site per day. To change either edition, edit the TRANSLATION_EDITION /
TAFSIR_EDITION constants at the top of includes/class-ayah-source.php —
there is deliberately no settings-page picker for these, to avoid the
wrong edition getting selected by accident.

Shortcode: [daily_ayah]
Options:   [daily_ayah theme="light"]
REST:      /wp-json/muslim-daily-ayah/v1/daily-ayah

== Installation ==

1. Upload the folder to /wp-content/plugins/ and activate.
2. Settings -> Daily Ayah: review/edit the verse reference list (one
   "surah:ayah" per line). Translation and tafsir are fixed — nothing else
   to configure there.
3. Add [daily_ayah] wherever you want the card. In Elementor use a
   Shortcode widget. The card has no background of its own by design —
   set a background on the Elementor container/column instead.

== Upgrade notice ==

= 1.1.0 =
Translation/tafsir editions are now fixed in code instead of settings-page
pickers, and tafsir moved to a different API (the old one didn't carry
English tafsir at all). If you're upgrading from 1.0.0, this version clears
the day's cache and any previously saved translation/tafsir settings
automatically on first load — no manual cache-clear needed.

== Notes ==

- Have a qualified reviewer (asatizah/scholar) check the verse list and the
  tafsir/translation text before launch.
- If either API is briefly unreachable, the widget shows the last
  successfully fetched day's content rather than going blank; if there is
  no prior successful fetch at all, it shows a single hardcoded Arabic-only
  verse with a "translation unavailable" note, rather than a guessed
  translation.
- The closed-state card intentionally has no background, border or shadow —
  it inherits whatever the Elementor container underneath it provides.
  Background/border are zeroed out with !important on the card and the
  close button specifically because this theme applies a background to
  bare <button> elements with enough specificity to beat a plain reset —
  if that reappears on some other element, the same !important pattern is
  the fix.
- Arabic is set in Amiri, loaded from Google Fonts — a different loading
  path from the site's Fontshare-hosted General Sans. Self-host Amiri
  alongside your other custom fonts if you'd rather avoid the extra
  external request.
