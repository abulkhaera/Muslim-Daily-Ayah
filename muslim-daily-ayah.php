<?php
/**
 * Plugin Name:       Muslim Daily Ayah
 * Description:       A daily-rotating Quranic ayah (Arabic + translation) with a click-through tafsir popup. Verse list is curated by you; Arabic/translation/tafsir text is pulled live from the Al Quran Cloud API and cached for the day. Shortcode: [daily_ayah]
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL-2.0-or-later
 * Text Domain:       muslim-daily-ayah
 */

if (!defined('ABSPATH')) exit;

define('MDA_VERSION', '1.1.0');
define('MDA_DIR', plugin_dir_path(__FILE__));
define('MDA_URL', plugin_dir_url(__FILE__));

require_once MDA_DIR . 'includes/class-ayah-source.php';

/* ------------------------------------------------------------------ migration */

/**
 * v1.1.0 hardcodes translation/tafsir editions (no longer admin-configurable)
 * and switched tafsir to a different API entirely. Sites that already saved
 * settings or cached a day's fetch under the old (broken/Arabic) tafsir
 * config need that cleared once, or they'd keep showing stale data until
 * the transient happened to expire on its own.
 */
function mda_maybe_migrate() {
    if (get_option('mda_code_rev') === MDA_VERSION) return;
    MDA_Ayah_Source::clear_cache();
    delete_option('mda_last_good');
    update_option('mda_code_rev', MDA_VERSION);
}
add_action('init', 'mda_maybe_migrate');

/* ------------------------------------------------------------------ options */

function mda_defaults(): array {
    return [
        'refs'           => mda_default_refs(),
        'arabic_edition' => 'quran-uthmani',
        'theme'          => 'dark',
    ];
}

/**
 * Starter list of verse references, one "surah:ayah" per line. Widely used in
 * daily-reminder contexts, but a site publishing scripture should have a
 * qualified reviewer (asatizah/scholar) check and expand this list before
 * launch. Fully editable in Settings -> Daily Ayah.
 */
function mda_default_refs(): string {
    return implode("\n", [
        '2:255',   // Ayat al-Kursi
        '94:5',
        '94:6',
        '13:28',
        '3:103',
        '2:286',
        '65:3',
        '39:53',
        '2:152',
        '29:45',
    ]);
}

function mda_opt(string $key) {
    $o = wp_parse_args(get_option('mda_settings', []), mda_defaults());
    return $o[$key] ?? null;
}

/** Version assets by file modification time, so browsers pick up changes on deploy. */
function mda_asset_ver(string $rel_path): string {
    $path = MDA_DIR . $rel_path;
    return file_exists($path) ? MDA_VERSION . '.' . filemtime($path) : MDA_VERSION;
}

/* ------------------------------------------------------------------ assets */

function mda_enqueue() {
    wp_register_style('mda-style', MDA_URL . 'assets/daily-ayah.css', [], mda_asset_ver('assets/daily-ayah.css'));
    wp_register_script('mda-script', MDA_URL . 'assets/daily-ayah.js', [], mda_asset_ver('assets/daily-ayah.js'), true);
    // Amiri: a Naskh-style Arabic typeface, appropriate for Quranic display.
    // This is a Google Fonts CDN call — a different loading path from the
    // site's Fontshare-hosted General Sans. Flagging in case you'd rather
    // self-host Amiri alongside your other custom fonts.
    wp_register_style('mda-amiri-font', 'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap', [], null);
}
add_action('wp_enqueue_scripts', 'mda_enqueue');

function mda_maybe_load_assets() {
    wp_enqueue_style('mda-amiri-font');
    wp_enqueue_style('mda-style');
    wp_enqueue_script('mda-script');
}

/* ------------------------------------------------------------------ shortcode */

function mda_shortcode(array $atts = []): string {
    mda_maybe_load_assets();

    $a = shortcode_atts([
        'theme' => mda_opt('theme'),
    ], $atts, 'daily_ayah');

    $day = MDA_Ayah_Source::get_daily();

    static $instance = 0;
    $instance++;
    $uid = 'mda-' . $instance . '-' . wp_rand(1000, 9999);

    ob_start();
    ?>
    <div class="mda" data-theme="<?php echo esc_attr($a['theme']); ?>">
      <button type="button" class="mda__card" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($uid); ?>" data-mda-open>
        <span class="mda__eyebrow"><?php esc_html_e('Ayah of the Day', 'muslim-daily-ayah'); ?></span>
        <p class="mda__arabic" lang="ar" dir="rtl"><?php echo esc_html($day['arabic']); ?></p>
        <p class="mda__translation">&ldquo;<?php echo esc_html($day['translation']); ?>&rdquo;</p>
        <span class="mda__ref"><?php echo esc_html($day['surah_name'] . ' ' . $day['ref']); ?></span>
        <span class="mda__cta">
          <?php echo $day['tafsir'] !== '' ? esc_html__('Read Tafsir', 'muslim-daily-ayah') : esc_html__('Read More', 'muslim-daily-ayah'); ?>
          <svg viewBox="0 0 12 12" width="10" height="10" aria-hidden="true"><path d="M2 6h8M6 2l4 4-4 4" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <?php if ($day['source'] !== 'live') : ?>
          <span class="mda__badge"><?php esc_html_e('Showing saved content — live source unavailable', 'muslim-daily-ayah'); ?></span>
        <?php endif; ?>
      </button>

      <div class="mda__modal" id="<?php echo esc_attr($uid); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($uid); ?>-title" hidden>
        <div class="mda__modal-backdrop" data-mda-close></div>
        <div class="mda__modal-panel" role="document">
          <button type="button" class="mda__modal-close" data-mda-close aria-label="<?php esc_attr_e('Close', 'muslim-daily-ayah'); ?>">
            <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>
          </button>

          <p class="mda__modal-arabic" lang="ar" dir="rtl"><?php echo esc_html($day['arabic']); ?></p>
          <p class="mda__modal-translation">&ldquo;<?php echo esc_html($day['translation']); ?>&rdquo;</p>
          <p class="mda__modal-ref">
            <?php echo esc_html($day['surah_name'] . ' ' . $day['ref']); ?>
            <?php if (!empty($day['translation_name'])) : ?>
              &middot; <em><?php echo esc_html($day['translation_name']); ?></em>
            <?php endif; ?>
          </p>

          <?php if ($day['tafsir'] !== '') : ?>
            <hr class="mda__divider">
            <h3 id="<?php echo esc_attr($uid); ?>-title" class="mda__tafsir-heading"><?php esc_html_e('Tafsir', 'muslim-daily-ayah'); ?></h3>
            <div class="mda__tafsir-body"><?php echo wp_kses_post(wpautop($day['tafsir'])); ?></div>
            <p class="mda__attribution">
              <?php
              printf(
                  /* translators: %s: tafsir name/author, e.g. "Tafsir Ibn Kathir" */
                  esc_html__('Source: %s', 'muslim-daily-ayah'),
                  esc_html($day['tafsir_name'] ?: __('Selected tafsir edition', 'muslim-daily-ayah'))
              );
              ?>
              &middot;
              <a href="<?php echo esc_url('https://quran.com/' . str_replace(':', '/', $day['ref'])); ?>" target="_blank" rel="noopener">
                <?php esc_html_e('View full context on Quran.com', 'muslim-daily-ayah'); ?>
              </a>
            </p>
          <?php else: ?>
            <h3 id="<?php echo esc_attr($uid); ?>-title" class="mda__tafsir-heading screen-reader-text"><?php echo esc_html($day['surah_name']); ?></h3>
            <p class="mda__attribution">
              <?php esc_html_e('Tafsir temporarily unavailable — showing translation only.', 'muslim-daily-ayah'); ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('daily_ayah', 'mda_shortcode');

/* ------------------------------------------------------------------ REST */

add_action('rest_api_init', function () {
    register_rest_route('muslim-daily-ayah/v1', '/daily-ayah', [
        'methods'  => 'GET',
        'callback' => function () {
            return rest_ensure_response(MDA_Ayah_Source::get_daily());
        },
        'permission_callback' => '__return_true',
    ]);
});

/* ------------------------------------------------------------------ admin settings */

require_once MDA_DIR . 'includes/class-admin-settings.php';
