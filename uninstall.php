<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'universal_tracking_codes_settings' );

if ( is_multisite() ) {
    $sites = get_sites();
    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );
        delete_option( 'universal_tracking_codes_settings' );
        restore_current_blog();
    }
}