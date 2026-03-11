<?php

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'wehc_email' );
delete_option( 'wehc_subject' );
delete_option( 'wehc_body' );
delete_option( 'wehc_interval' );
delete_option( 'wehc_enabled' );
delete_option( 'wehc_last_sent' );
