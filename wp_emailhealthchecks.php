<?php
/**
 * Plugin Name: Email Health Checks
 * Plugin URI:  https://github.com/caninja/wp_emailhealthchecks
 * Description: Sends scheduled emails to verify mail delivery is working.
 * Version:     1.0.0
 * Author:      Caninja
 * Author URI:  https://github.com/caninja
 * License:     MIT
 * Text Domain: wp-emailhealthchecks
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('WEHC_PLUGIN_DIR', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    load_plugin_textdomain('wp-emailhealthchecks', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// ── Interval helpers ──────────────────────────────────────────────────────────

function wehc_parse_interval( $value ) {
    if ( preg_match( '/^(\d+)(m|h|d)$/i', trim( $value ), $m ) ) {
        $n = (int) $m[1];
        switch ( strtolower( $m[2] ) ) {
            case 'm': return $n * 60;
            case 'h': return $n * 3600;
            case 'd': return $n * 86400;
        }
    }
    return false;
}

function wehc_schedule_key( $value ) {
    $presets = [
        '5m'  => 'wehc_5min',
        '15m' => 'wehc_15min',
        '30m' => 'wehc_30min',
        '1h'  => 'hourly',
        '6h'  => 'wehc_6hours',
        '12h' => 'wehc_12hours',
        '1d'  => 'daily',
    ];
    return $presets[ $value ] ?? 'wehc_custom';
}

// ── Cron intervals ────────────────────────────────────────────────────────────

add_filter('cron_schedules', function ($schedules) {
    $schedules['wehc_5min']    = ['interval' => 300,   'display' => __('Every 5 minutes',  'wp-emailhealthchecks')];
    $schedules['wehc_15min']   = ['interval' => 900,   'display' => __('Every 15 minutes', 'wp-emailhealthchecks')];
    $schedules['wehc_30min']   = ['interval' => 1800,  'display' => __('Every 30 minutes', 'wp-emailhealthchecks')];
    $schedules['wehc_6hours']  = ['interval' => 21600, 'display' => __('Every 6 hours',    'wp-emailhealthchecks')];
    $schedules['wehc_12hours'] = ['interval' => 43200, 'display' => __('Every 12 hours',   'wp-emailhealthchecks')];

    $value   = get_option( 'wehc_interval', '1h' );
    $seconds = wehc_parse_interval( $value );
    if ( $seconds && wehc_schedule_key( $value ) === 'wehc_custom' ) {
        $schedules['wehc_custom'] = ['interval' => $seconds, 'display' => 'Custom (' . $value . ')'];
    }

    return $schedules;
});

// ── Send action ───────────────────────────────────────────────────────────────

add_action('wehc_send_email', function () {
    $email   = get_option('wehc_email', '');
    $subject = get_option('wehc_subject', 'Health Check');
    $body    = get_option('wehc_body', 'Health check ping.');

    if (empty($email)) {
        return;
    }

    if (wp_mail($email, $subject, $body)) {
        update_option('wehc_last_sent', current_time('mysql'));
    }
});

// ── Scheduling helper (used by admin and CLI) ─────────────────────────────────

function wehc_reschedule() {
    wp_clear_scheduled_hook('wehc_send_email');

    if (get_option('wehc_enabled')) {
        $value = get_option('wehc_interval', '1h');
        wp_schedule_event(time(), wehc_schedule_key($value), 'wehc_send_email');
    }
}

// ── Activation / deactivation ─────────────────────────────────────────────────

register_activation_hook(__FILE__, function () {
    if (get_option('wehc_enabled') && ! wp_next_scheduled('wehc_send_email')) {
        $value = get_option('wehc_interval', '1h');
        wp_schedule_event(time(), wehc_schedule_key($value), 'wehc_send_email');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('wehc_send_email');
});

// ── Settings link in plugin list ──────────────────────────────────────────────

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = esc_url(add_query_arg('page', 'wehc-settings', admin_url('options-general.php')));
    array_unshift($links, '<a href="' . $url . '">' . __('Settings', 'wp-emailhealthchecks') . '</a>');
    return $links;
});

// ── Load admin UI and CLI ─────────────────────────────────────────────────────

if (is_admin()) {
    require_once WEHC_PLUGIN_DIR . 'admin.php';
}

if (defined('WP_CLI') && WP_CLI) {
    require_once WEHC_PLUGIN_DIR . 'cli.php';
}

// Have a great day!