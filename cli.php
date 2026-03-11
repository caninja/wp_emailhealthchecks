<?php
defined('ABSPATH') || exit;

/**
 * Manage Email Health Checks settings and sending.
 */
class WEHC_CLI_Command {


    /**
     * Configure health check settings.
     *
     * ## OPTIONS
     *
     * [--email=<email>]
     * : Recipient email address.
     *
     * [--schedule=<interval>]
     * : Send interval. Use m/h/d units, e.g. 30m, 1h, 6h, 2d.
     *
     * [--subject=<subject>]
     * : Email subject line.
     *
     * [--body=<body>]
     * : Email body text.
     *
     * [--enable]
     * : Enable sending.
     *
     * [--disable]
     * : Disable sending.
     *
     * ## EXAMPLES
     *
     *     wp emailhealthchecks configure --email=pingsite@hc.example.com --schedule=1h --enable
     *
     * @when after_wp_load
     */
    public function configure( $args, $assoc_args ) {
        $changed = false;

        if ( isset( $assoc_args['email'] ) ) {
            update_option( 'wehc_email', sanitize_email( $assoc_args['email'] ) );
            WP_CLI::line( 'Email: ' . $assoc_args['email'] );
            $changed = true;
        }

        if ( isset( $assoc_args['subject'] ) ) {
            update_option( 'wehc_subject', sanitize_text_field( $assoc_args['subject'] ) );
            WP_CLI::line( 'Subject: ' . $assoc_args['subject'] );
            $changed = true;
        }

        if ( isset( $assoc_args['body'] ) ) {
            update_option( 'wehc_body', sanitize_textarea_field( $assoc_args['body'] ) );
            WP_CLI::line( 'Body updated.' );
            $changed = true;
        }

        if ( isset( $assoc_args['schedule'] ) ) {
            $value = $assoc_args['schedule'];
            if ( wehc_parse_interval( $value ) === false ) {
                WP_CLI::error( 'Invalid interval. Use a number followed by m, h, or d — e.g. 30m, 1h, 6h, 2d.' );
                return;
            }
            update_option( 'wehc_interval', $value );
            WP_CLI::line( 'Schedule: ' . $value );
            $changed = true;
        }

        if ( isset( $assoc_args['enable'] ) ) {
            update_option( 'wehc_enabled', 1 );
            WP_CLI::line( 'Enabled.' );
            $changed = true;
        } elseif ( isset( $assoc_args['disable'] ) ) {
            update_option( 'wehc_enabled', 0 );
            WP_CLI::line( 'Disabled.' );
            $changed = true;
        }

        if ( $changed ) {
            wehc_reschedule();
            WP_CLI::success( 'Configuration saved.' );
        } else {
            WP_CLI::line( 'No changes. Use --email, --schedule, --subject, --body, --enable, or --disable.' );
        }
    }

    /**
     * Send the health check email immediately.
     *
     * ## EXAMPLES
     *
     *     wp emailhealthchecks send
     *
     * @when after_wp_load
     */
    public function send( $args, $assoc_args ) {
        $email   = get_option( 'wehc_email', '' );
        $subject = get_option( 'wehc_subject', 'Health Check' );
        $body    = get_option( 'wehc_body', 'Health check ping.' );

        if ( empty( $email ) ) {
            WP_CLI::error( 'No email configured. Run: wp emailhealthchecks configure --email=<email>' );
            return;
        }

        if ( wp_mail( $email, $subject, $body ) ) {
            update_option( 'wehc_last_sent', current_time( 'mysql' ) );
            WP_CLI::success( 'Email sent to: ' . $email );
        } else {
            WP_CLI::error( 'wp_mail() returned false — check your SMTP configuration.' );
        }
    }

    /**
     * Show current configuration and schedule status.
     *
     * ## EXAMPLES
     *
     *     wp emailhealthchecks status
     *
     * @when after_wp_load
     */
    public function status( $args, $assoc_args ) {
        $next = wp_next_scheduled( 'wehc_send_email' );

        WP_CLI\Utils\format_items( 'table', [[
            'Enabled'        => get_option( 'wehc_enabled' ) ? 'yes' : 'no',
            'Email'          => get_option( 'wehc_email', '(not set)' ),
            'Subject'        => get_option( 'wehc_subject', '(not set)' ),
            'Schedule'       => get_option( 'wehc_interval', '1h' ),
            'Last Sent'      => get_option( 'wehc_last_sent', '(never)' ),
            'Next Scheduled' => $next ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ) ) : '(not scheduled)',
        ]], [ 'Enabled', 'Email', 'Subject', 'Schedule', 'Last Sent', 'Next Scheduled' ] );
    }
}

WP_CLI::add_command( 'emailhealthchecks', 'WEHC_CLI_Command' );
// Have a great day!