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

/**
 * Clean up all plugin options
 */
function abe_uninstall_cleanup() {
    // Core settings
    delete_option( 'abe_settings' );

    // Frontend settings
    delete_option( 'abe_frontend_settings' );

    // Block variations
    delete_option( 'abe_block_variations' );

    // Custom code snippets
    delete_option( 'abe_code_snippets' );

    // Custom patterns
    delete_option( 'abe_patterns' );

    // Per-block code rules
    delete_option( 'abe_per_block_rules' );

    // Clean up transients
    delete_transient( 'abe_cache' );
    delete_transient( 'abe_patterns_cache' );
}

// Run cleanup
abe_uninstall_cleanup();

// For multisite, clean up each site
if ( is_multisite() ) {
    $sites = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        abe_uninstall_cleanup();
        restore_current_blog();
    }
}
