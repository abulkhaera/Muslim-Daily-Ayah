<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        __('Daily Ayah', 'muslim-daily-ayah'),
        __('Daily Ayah', 'muslim-daily-ayah'),
        'manage_options',
        'muslim-daily-ayah',
        'mda_render_settings_page'
    );
});

add_action('admin_post_mda_save_settings', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'muslim-daily-ayah'));
    check_admin_referer('mda_save_settings');

    $in = $_POST['mda_settings'] ?? [];

    $clean = [
        'refs'           => sanitize_textarea_field(wp_unslash($in['refs'] ?? '')),
        'arabic_edition' => sanitize_text_field(wp_unslash($in['arabic_edition'] ?? 'quran-uthmani')),
        'theme'          => in_array($in['theme'] ?? '', ['dark', 'light'], true) ? $in['theme'] : 'dark',
    ];

    update_option('mda_settings', $clean);
    MDA_Ayah_Source::clear_cache();

    wp_safe_redirect(add_query_arg(['page' => 'muslim-daily-ayah', 'saved' => 1], admin_url('options-general.php')));
    exit;
});

add_action('admin_post_mda_clear_cache', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'muslim-daily-ayah'));
    check_admin_referer('mda_clear_cache');
    MDA_Ayah_Source::clear_cache();
    wp_safe_redirect(add_query_arg(['page' => 'muslim-daily-ayah', 'cleared' => 1], admin_url('options-general.php')));
    exit;
});

function mda_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $o = wp_parse_args(get_option('mda_settings', []), mda_defaults());
    $day = MDA_Ayah_Source::get_daily();
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Muslim Daily Ayah', 'muslim-daily-ayah'); ?></h1>

      <?php if (!empty($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved. Cache cleared — the next page load will re-fetch.', 'muslim-daily-ayah'); ?></p></div>
      <?php endif; ?>
      <?php if (!empty($_GET['cleared'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cache cleared.', 'muslim-daily-ayah'); ?></p></div>
      <?php endif; ?>

      <div class="notice notice-info"><p>
        <?php
        printf(
            esc_html__('Translation and tafsir are fixed in this build, not editable here: %1$s for translation, %2$s for tafsir. Changing either means editing the plugin code (the constants at the top of includes/class-ayah-source.php), not this settings page.', 'muslim-daily-ayah'),
            '<code>Saheeh International (' . esc_html(MDA_Ayah_Source::TRANSLATION_EDITION) . ')</code>',
            '<code>' . esc_html(MDA_Ayah_Source::TAFSIR_NAME) . ' (' . esc_html(MDA_Ayah_Source::TAFSIR_EDITION) . ')</code>'
        );
        ?>
      </p></div>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('mda_save_settings'); ?>
        <input type="hidden" name="action" value="mda_save_settings">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="mda_refs"><?php esc_html_e('Verse references', 'muslim-daily-ayah'); ?></label></th>
            <td>
              <textarea id="mda_refs" name="mda_settings[refs]" rows="10" cols="40" class="large-text code"><?php echo esc_textarea($o['refs']); ?></textarea>
              <p class="description">
                <?php esc_html_e('One "surah:ayah" per line, e.g. 2:255. One is shown per calendar day (Asia/Singapore), cycling through this list in order. Have your asatizah review this list before launch.', 'muslim-daily-ayah'); ?>
              </p>
            </td>
          </tr>

          <tr>
            <th scope="row"><label for="mda_arabic_edition"><?php esc_html_e('Arabic script edition', 'muslim-daily-ayah'); ?></label></th>
            <td>
              <input type="text" id="mda_arabic_edition" name="mda_settings[arabic_edition]" value="<?php echo esc_attr($o['arabic_edition']); ?>" class="regular-text code">
              <p class="description"><?php esc_html_e('Default "quran-uthmani" is the standard Uthmani script. Leave as-is unless you specifically need another rendering.', 'muslim-daily-ayah'); ?></p>
            </td>
          </tr>

          <tr>
            <th scope="row"><?php esc_html_e('Text colour', 'muslim-daily-ayah'); ?></th>
            <td>
              <label><input type="radio" name="mda_settings[theme]" value="dark" <?php checked($o['theme'], 'dark'); ?>> <?php esc_html_e('Dark text', 'muslim-daily-ayah'); ?></label>
              &nbsp;&nbsp;
              <label><input type="radio" name="mda_settings[theme]" value="light" <?php checked($o['theme'], 'light'); ?>> <?php esc_html_e('Light text', 'muslim-daily-ayah'); ?></label>
              <p class="description">
                <?php esc_html_e('The widget has no background of its own — this only sets the text/icon colour. Pick Dark text if you\u2019re placing it on a light Elementor background, Light text if you\u2019re placing it on a dark/navy one.', 'muslim-daily-ayah'); ?>
              </p>
            </td>
          </tr>
        </table>

        <?php submit_button(__('Save Settings', 'muslim-daily-ayah')); ?>
      </form>

      <hr>

      <h2><?php esc_html_e('Diagnostics', 'muslim-daily-ayah'); ?></h2>
      <table class="widefat striped" style="max-width:760px">
        <tbody>
          <tr><td><strong><?php esc_html_e('Today (Asia/Singapore)', 'muslim-daily-ayah'); ?></strong></td><td><code><?php echo esc_html($day['date']); ?></code></td></tr>
          <tr><td><strong><?php esc_html_e('Selected reference', 'muslim-daily-ayah'); ?></strong></td><td><code><?php echo esc_html($day['ref']); ?></code> — <?php echo esc_html($day['surah_name']); ?></td></tr>
          <tr><td><strong><?php esc_html_e('Translation', 'muslim-daily-ayah'); ?></strong></td><td><code><?php echo esc_html(MDA_Ayah_Source::TRANSLATION_EDITION); ?></code> (<?php echo esc_html(MDA_Ayah_Source::TRANSLATION_NAME); ?>)</td></tr>
          <tr><td><strong><?php esc_html_e('Tafsir', 'muslim-daily-ayah'); ?></strong></td><td><code><?php echo esc_html(MDA_Ayah_Source::TAFSIR_EDITION); ?></code> (<?php echo esc_html(MDA_Ayah_Source::TAFSIR_NAME); ?>)<?php echo $day['tafsir'] === '' ? ' — ' . esc_html__('not returned for today\'s verse; showing translation only', 'muslim-daily-ayah') : ''; ?></td></tr>
          <tr><td><strong><?php esc_html_e('Content source', 'muslim-daily-ayah'); ?></strong></td><td><code><?php echo esc_html($day['source']); ?></code>
            <?php if ($day['source'] !== 'live') : ?>
              — <span style="color:#8A5A00"><?php echo esc_html($day['note'] ?? __('Live source unavailable; showing saved/fallback content.', 'muslim-daily-ayah')); ?></span>
            <?php endif; ?>
          </td></tr>
          <tr><td><strong><?php esc_html_e('Shortcode', 'muslim-daily-ayah'); ?></strong></td><td><code>[daily_ayah]</code></td></tr>
        </tbody>
      </table>

      <p>
        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mda_clear_cache'), 'mda_clear_cache')); ?>">
          <?php esc_html_e('Force refetch now', 'muslim-daily-ayah'); ?>
        </a>
      </p>
    </div>
    <?php
}
