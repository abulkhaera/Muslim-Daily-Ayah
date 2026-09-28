<?php
/**
 * Ayah content resolution.
 *
 * Arabic + translation: Al Quran Cloud (api.alquran.cloud) — free, no key.
 *
 * Tafsir: spa5k/tafsir_api, served statically off the jsDelivr CDN — free,
 * no key, no rate limit. Switched to this deliberately after confirming
 * Al Quran Cloud does not carry an English Ibn Kathir edition (it only has
 * a handful of tafsirs, none in English at time of writing); this CDN does,
 * and its response shape was verified directly before wiring it in, rather
 * than assumed from documentation. Default target: en-tafisr-ibn-kathir
 * (Tafsir Ibn Kathir, abridged English).
 *
 * Cache: one WordPress transient per calendar day (Asia/Singapore), so
 * neither API is called more than once per day per site.
 *
 * Fallback order (fresh -> last-good cache -> static fallback), so the
 * widget degrades gracefully instead of failing outright:
 *   1. Fresh fetch for today.
 *   2. The last successful fetch, even if stale (so the widget never goes
 *      blank just because an API had a bad minute).
 *   3. A single hardcoded Arabic-only verse with no translation/tafsir —
 *      deliberately not a guessed/typed-from-memory translation, since a
 *      wrong rendering of scripture is worse than an honest "unavailable".
 */

if (!defined('ABSPATH')) exit;

class MDA_Ayah_Source {

    const API        = 'https://api.alquran.cloud/v1';
    const TAFSIR_CDN = 'https://cdn.jsdelivr.net/gh/spa5k/tafsir_api@main/tafsir';
    const TZ         = 'Asia/Singapore';

    // Fixed, not admin-configurable — verified working, locked in code per
    // request. Changing these means editing this file, not the settings UI.
    const TRANSLATION_EDITION = 'en.sahih';
    const TRANSLATION_NAME    = 'Saheeh International';
    const TAFSIR_EDITION      = 'en-tafisr-ibn-kathir';
    const TAFSIR_NAME         = 'Tafsir Ibn Kathir (abridged)';

    /** Parse the admin's newline "surah:ayah" list into a clean, deduped array. */
    public static function parse_refs(string $raw): array {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (preg_match('/^\d{1,3}:\d{1,3}$/', $line)) {
                $out[$line] = true; // dedupe via keys
            }
        }
        return array_keys($out);
    }

    private static function today_ymd(): string {
        return (new DateTime('now', new DateTimeZone(self::TZ)))->format('Y-m-d');
    }

    /** Stable per-calendar-day index into the ref list. Moves forward by 1 each SGT day. */
    private static function day_index(int $count): int {
        if ($count < 1) return 0;
        $epoch_days = (int) floor((new DateTime('now', new DateTimeZone(self::TZ)))->getTimestamp() / 86400);
        return $epoch_days % $count;
    }

    private static function get_json(string $url) {
        $res = wp_remote_get($url, ['timeout' => 8]);
        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) return null;
        return json_decode(wp_remote_retrieve_body($res), true);
    }

    /** One edition of one ayah from Al Quran Cloud. Returns ['text'=>..,'surah'=>[...]] or null. */
    private static function fetch_ayah(string $ref, string $edition) {
        if ($edition === '') return null;
        $body = self::get_json(self::API . '/ayah/' . rawurlencode($ref) . '/' . rawurlencode($edition));
        return $body['data'] ?? null;
    }

    /**
     * One tafsir edition of one ayah from the jsDelivr-hosted CDN.
     * Response shape confirmed as {"text":..,"surah":N,"ayah":N} — flat,
     * no wrapper object, unlike Al Quran Cloud's {"data":{...}}.
     */
    private static function fetch_tafsir(string $ref, string $slug) {
        if ($slug === '' || strpos($ref, ':') === false) return null;
        [$surah, $ayah] = explode(':', $ref, 2);
        $surah = (int) $surah; $ayah = (int) $ayah;
        if ($surah < 1 || $ayah < 1) return null;

        $body = self::get_json(self::TAFSIR_CDN . '/' . rawurlencode($slug) . '/' . $surah . '/' . $ayah . '.json');
        return (is_array($body) && !empty($body['text'])) ? $body : null;
    }

    private static function fallback_payload(string $reason): array {
        return [
            'ref'                 => '2:255',
            'date'                => self::today_ymd(),
            'arabic'              => 'اللَّهُ لَا إِلَٰهَ إِلَّا هُوَ الْحَيُّ الْقَيُّومُ',
            'surah_name'          => 'Al-Baqarah',
            'translation'         => __('Translation temporarily unavailable — please check back shortly.', 'muslim-daily-ayah'),
            'translation_edition' => '',
            'translation_name'    => '',
            'tafsir'              => '',
            'tafsir_edition'      => '',
            'tafsir_name'         => '',
            'source'              => 'fallback',
            'note'                => $reason,
        ];
    }

    /**
     * Today's resolved ayah: fetched fresh once per SGT day, cached in a
     * transient, falling back to the last good cache and then to the
     * static payload above if the APIs are unreachable.
     */
    public static function get_daily(): array {
        $today = self::today_ymd();
        $transient_key = 'mda_daily_' . $today;

        $cached = get_transient($transient_key);
        if (is_array($cached)) return $cached;

        $settings = wp_parse_args(get_option('mda_settings', []), mda_defaults());
        $refs = self::parse_refs($settings['refs']);

        if (empty($refs)) {
            $payload = self::fallback_payload('No verse references configured in Settings.');
            set_transient($transient_key, $payload, 6 * HOUR_IN_SECONDS);
            return $payload;
        }

        $ref = $refs[self::day_index(count($refs))];

        $arabic      = self::fetch_ayah($ref, $settings['arabic_edition'] ?: 'quran-uthmani');
        $translation = self::fetch_ayah($ref, self::TRANSLATION_EDITION);
        $tafsir      = self::fetch_tafsir($ref, self::TAFSIR_EDITION);

        if (!$arabic || !$translation) {
            // Fetch failed — fall back to the last successful day's data if we have it,
            // rather than an empty/broken card.
            $last_good = get_option('mda_last_good');
            if (is_array($last_good)) {
                $last_good['source'] = 'cache-stale';
                set_transient($transient_key, $last_good, 6 * HOUR_IN_SECONDS);
                return $last_good;
            }
            $payload = self::fallback_payload('Could not reach api.alquran.cloud.');
            set_transient($transient_key, $payload, 6 * HOUR_IN_SECONDS);
            return $payload;
        }

        $surah_name = $arabic['surah']['englishName'] ?? ($translation['surah']['englishName'] ?? '');

        $payload = [
            'ref'                 => $ref,
            'date'                => $today,
            'arabic'              => $arabic['text'] ?? '',
            'surah_name'          => $surah_name,
            'translation'         => $translation['text'] ?? '',
            'translation_edition' => self::TRANSLATION_EDITION,
            'translation_name'    => self::TRANSLATION_NAME,
            'tafsir'              => $tafsir['text'] ?? '',
            'tafsir_edition'      => self::TAFSIR_EDITION,
            'tafsir_name'         => self::TAFSIR_NAME,
            'source'              => 'live',
        ];

        set_transient($transient_key, $payload, DAY_IN_SECONDS);
        update_option('mda_last_good', $payload, false);

        return $payload;
    }

    /** Force tomorrow's (or today's, if not yet cached) fetch to hit the API again. */
    public static function clear_cache(): void {
        delete_transient('mda_daily_' . self::today_ymd());
    }
}
