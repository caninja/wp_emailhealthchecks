<?php
defined('ABSPATH') || exit;

// ── Register settings ─────────────────────────────────────────────────────────

add_action('admin_init', function () {
    register_setting('wehc_settings_group', 'wehc_email',    ['sanitize_callback' => 'sanitize_email']);
    register_setting('wehc_settings_group', 'wehc_subject',  ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('wehc_settings_group', 'wehc_body',     ['sanitize_callback' => 'sanitize_textarea_field']);
    register_setting('wehc_settings_group', 'wehc_interval', ['sanitize_callback' => function ($value) {
        $value = sanitize_text_field($value);
        if (wehc_parse_interval($value) === false) {
            add_settings_error('wehc_interval', 'invalid_interval', __('Invalid interval. Use a number followed by m, h, or d — e.g. 1h, 30m, 2d.', 'wp-emailhealthchecks'));
            return get_option('wehc_interval', '1h');
        }
        return $value;
    }]);
    register_setting('wehc_settings_group', 'wehc_enabled',  ['sanitize_callback' => 'absint']);
});

// ── Reschedule when relevant options change ───────────────────────────────────

add_action('update_option_wehc_enabled',  function () { wehc_reschedule(); });
add_action('update_option_wehc_interval', function () { wehc_reschedule(); });
add_action('add_option_wehc_enabled',     function () { wehc_reschedule(); });
add_action('add_option_wehc_interval',    function () { wehc_reschedule(); });

// ── Admin menu ────────────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_options_page(
        __('Email Health Checks', 'wp-emailhealthchecks'),
        __('Email Health Checks', 'wp-emailhealthchecks'),
        'manage_options',
        'wehc-settings',
        'wehc_render_settings_page'
    );
});

// ── Settings page ─────────────────────────────────────────────────────────────

function wehc_render_settings_page() {
    $enabled        = get_option('wehc_enabled', false);
    $last_sent      = get_option('wehc_last_sent', '');
    $next_scheduled = wp_next_scheduled('wehc_send_email');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Email Health Checks', 'wp-emailhealthchecks'); ?></h1>
        <p><?php esc_html_e('Sends a test email on a configurable schedule to confirm your outbound mail delivery is working. Designed for use with a healthchecks service, which alerts you if the expected ping stops arriving. Sending directly to your own inbox works too, if you are brave.', 'wp-emailhealthchecks'); ?></p>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields('wehc_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="wehc_enabled"><?php esc_html_e('Enabled', 'wp-emailhealthchecks'); ?></label></th>
                    <td><input type="checkbox" name="wehc_enabled" id="wehc_enabled" value="1" <?php checked(1, $enabled); ?> /></td>
                </tr>
                <tr>
                    <th><label for="wehc_email"><?php esc_html_e('Recipient Email', 'wp-emailhealthchecks'); ?></label></th>
                    <td><input type="email" name="wehc_email" id="wehc_email" value="<?php echo esc_attr(get_option('wehc_email')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="wehc_subject"><?php esc_html_e('Subject', 'wp-emailhealthchecks'); ?></label></th>
                    <td><input type="text" name="wehc_subject" id="wehc_subject" value="<?php echo esc_attr(get_option('wehc_subject', 'Health Check')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="wehc_body"><?php esc_html_e('Body', 'wp-emailhealthchecks'); ?></label></th>
                    <td><textarea name="wehc_body" id="wehc_body" rows="4" class="large-text"><?php echo esc_textarea(get_option('wehc_body', 'Health check ping.')); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="wehc_interval"><?php esc_html_e('Schedule', 'wp-emailhealthchecks'); ?></label></th>
                    <td>
                        <input type="text" name="wehc_interval" id="wehc_interval"
                               value="<?php echo esc_attr(get_option('wehc_interval', '1h')); ?>"
                               class="small-text" placeholder="1h" />
                        <p class="description">
                            Shortcuts: <code>5m</code> &middot; <code>15m</code> &middot; <code>30m</code> &middot;
                            <code>1h</code> &middot; <code>6h</code> &middot; <code>12h</code> &middot; <code>1d</code>
                            &mdash; or any custom value like <code>3h</code>, <code>90m</code>, <code>2d</code>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <?php if ($last_sent) : ?>
            <p><?php esc_html_e('Last sent:', 'wp-emailhealthchecks'); ?> <strong><?php echo esc_html($last_sent); ?></strong></p>
        <?php endif; ?>

        <?php if ($next_scheduled) : ?>
            <p><?php esc_html_e('Next scheduled:', 'wp-emailhealthchecks'); ?> <strong><?php echo esc_html(get_date_from_gmt(date('Y-m-d H:i:s', $next_scheduled))); ?></strong></p>
        <?php endif; ?>
    </div>
    <?php
}
// Have a great day!