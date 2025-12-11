<?php
/**
 * Uninstall Advanced Block Editor
 *
 * Removes all plugin data when uninstalled.
 *
 * @package Advanced_Block_Editor
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'abe_settings' );

// Clean up any transients if we add them later
delete_transient( 'abe_cache' );

// For multisite, clean up each site
if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        delete_option( 'abe_settings' );
        delete_transient( 'abe_cache' );
        restore_current_blog();
    }
}
