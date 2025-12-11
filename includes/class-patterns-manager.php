<?php
/**
 * Patterns Manager Class
 * Handles custom block patterns with shortcode support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABE_Patterns_Manager {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Stored patterns
     */
    private $patterns = [];

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->patterns = get_option( 'abe_patterns', [] );
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register patterns with WordPress
        add_action( 'init', [ $this, 'register_patterns' ], 20 );

        // Register shortcode
        add_shortcode( 'abe_pattern', [ $this, 'pattern_shortcode' ] );

        // Admin AJAX handlers
        add_action( 'wp_ajax_abe_save_pattern', [ $this, 'ajax_save_pattern' ] );
        add_action( 'wp_ajax_abe_delete_pattern', [ $this, 'ajax_delete_pattern' ] );
        add_action( 'wp_ajax_abe_get_patterns', [ $this, 'ajax_get_patterns' ] );
        add_action( 'wp_ajax_abe_toggle_pattern', [ $this, 'ajax_toggle_pattern' ] );
        add_action( 'wp_ajax_abe_duplicate_pattern', [ $this, 'ajax_duplicate_pattern' ] );

        // Enqueue editor assets
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ], 15 );
    }

    /**
     * Register patterns with WordPress
     */
    public function register_patterns() {
        // Register our custom pattern category
        register_block_pattern_category(
            'abe-custom',
            [
                'label' => __( 'Block Editor+ Patterns', 'advanced-block-editor' ),
            ]
        );

        // Register each pattern
        foreach ( $this->patterns as $pattern ) {
            if ( empty( $pattern['enabled'] ) ) {
                continue;
            }

            $pattern_name = 'abe/' . sanitize_key( $pattern['slug'] );

            register_block_pattern(
                $pattern_name,
                [
                    'title'         => $pattern['title'],
                    'description'   => $pattern['description'] ?? '',
                    'content'       => $pattern['content'],
                    'categories'    => array_merge( [ 'abe-custom' ], $pattern['categories'] ?? [] ),
                    'keywords'      => $pattern['keywords'] ?? [],
                    'viewportWidth' => $pattern['viewport_width'] ?? 1200,
                    'blockTypes'    => $pattern['block_types'] ?? [],
                ]
            );
        }
    }

    /**
     * Shortcode handler
     * Usage: [abe_pattern slug="my-pattern"] or [abe_pattern id="pattern-id"]
     */
    public function pattern_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'slug' => '',
                'id'   => '',
            ],
            $atts,
            'abe_pattern'
        );

        $pattern = null;

        // Find pattern by ID or slug
        if ( ! empty( $atts['id'] ) ) {
            $pattern = $this->patterns[ $atts['id'] ] ?? null;
        } elseif ( ! empty( $atts['slug'] ) ) {
            foreach ( $this->patterns as $p ) {
                if ( ( $p['slug'] ?? '' ) === $atts['slug'] ) {
                    $pattern = $p;
                    break;
                }
            }
        }

        if ( ! $pattern || empty( $pattern['enabled'] ) ) {
            return '';
        }

        // Parse and render the block content
        $blocks = parse_blocks( $pattern['content'] );
        $output = '';

        foreach ( $blocks as $block ) {
            $output .= render_block( $block );
        }

        return $output;
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'abe-patterns-manager',
            ABE_PLUGIN_URL . 'assets/editor/js/patterns-manager.js',
            [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-plugins', 'wp-edit-post', 'wp-compose' ],
            ABE_VERSION,
            true
        );

        wp_localize_script( 'abe-patterns-manager', 'abePatternsData', [
            'patterns'         => $this->get_all_patterns(),
            'nonce'            => wp_create_nonce( 'abe_patterns_nonce' ),
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'categories'       => $this->get_pattern_categories(),
            'coreCategories'   => $this->get_core_pattern_categories(),
        ] );
    }

    /**
     * Get all patterns
     */
    public function get_all_patterns() {
        return $this->patterns;
    }

    /**
     * Get pattern categories
     */
    private function get_pattern_categories() {
        return [
            'text'      => __( 'Text', 'advanced-block-editor' ),
            'media'     => __( 'Media', 'advanced-block-editor' ),
            'columns'   => __( 'Columns', 'advanced-block-editor' ),
            'header'    => __( 'Header', 'advanced-block-editor' ),
            'footer'    => __( 'Footer', 'advanced-block-editor' ),
            'gallery'   => __( 'Gallery', 'advanced-block-editor' ),
            'call-to-action' => __( 'Call to Action', 'advanced-block-editor' ),
            'testimonial' => __( 'Testimonial', 'advanced-block-editor' ),
            'team'      => __( 'Team', 'advanced-block-editor' ),
            'pricing'   => __( 'Pricing', 'advanced-block-editor' ),
            'contact'   => __( 'Contact', 'advanced-block-editor' ),
            'featured'  => __( 'Featured', 'advanced-block-editor' ),
        ];
    }

    /**
     * Get core WordPress pattern categories
     */
    private function get_core_pattern_categories() {
        $categories = [];

        if ( class_exists( 'WP_Block_Pattern_Categories_Registry' ) ) {
            $registry = WP_Block_Pattern_Categories_Registry::get_instance();
            foreach ( $registry->get_all_registered() as $category ) {
                $categories[ $category['name'] ] = $category['label'];
            }
        }

        return $categories;
    }

    /**
     * AJAX: Save pattern
     */
    public function ajax_save_pattern() {
        check_ajax_referer( 'abe_patterns_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );
        if ( empty( $id ) ) {
            $id = 'pattern_' . wp_generate_uuid4();
        }

        $slug = sanitize_title( $_POST['slug'] ?? $_POST['title'] ?? 'pattern' );

        // Ensure unique slug
        $base_slug = $slug;
        $counter = 1;
        while ( $this->slug_exists( $slug, $id ) ) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        $pattern = [
            'id'              => $id,
            'slug'            => $slug,
            'title'           => sanitize_text_field( $_POST['title'] ?? __( 'Untitled Pattern', 'advanced-block-editor' ) ),
            'description'     => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'content'         => wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) ),
            'categories'      => array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['categories'] ?? [] ) ) ),
            'keywords'        => array_filter( array_map( 'sanitize_text_field', explode( ',', $_POST['keywords'] ?? '' ) ) ),
            'block_types'     => array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['block_types'] ?? [] ) ) ),
            'viewport_width'  => intval( $_POST['viewport_width'] ?? 1200 ),
            'enabled'         => ! empty( $_POST['enabled'] ),
            'created'         => $this->patterns[ $id ]['created'] ?? current_time( 'mysql' ),
            'modified'        => current_time( 'mysql' ),
        ];

        // Validate required fields
        if ( empty( $pattern['title'] ) || empty( $pattern['content'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Title and content are required.', 'advanced-block-editor' ) ] );
        }

        $this->patterns[ $id ] = $pattern;
        update_option( 'abe_patterns', $this->patterns );

        // Clear any cached patterns
        delete_transient( 'abe_patterns_cache' );

        wp_send_json_success( [
            'message' => __( 'Pattern saved successfully.', 'advanced-block-editor' ),
            'pattern' => $pattern,
            'shortcode' => '[abe_pattern slug="' . $pattern['slug'] . '"]',
        ] );
    }

    /**
     * Check if slug exists
     */
    private function slug_exists( $slug, $exclude_id = '' ) {
        foreach ( $this->patterns as $pattern ) {
            if ( ( $pattern['slug'] ?? '' ) === $slug && $pattern['id'] !== $exclude_id ) {
                return true;
            }
        }
        return false;
    }

    /**
     * AJAX: Delete pattern
     */
    public function ajax_delete_pattern() {
        check_ajax_referer( 'abe_patterns_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->patterns[ $id ] ) ) {
            unset( $this->patterns[ $id ] );
            update_option( 'abe_patterns', $this->patterns );
            delete_transient( 'abe_patterns_cache' );
            wp_send_json_success( [ 'message' => __( 'Pattern deleted.', 'advanced-block-editor' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Pattern not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Toggle pattern enabled state
     */
    public function ajax_toggle_pattern() {
        check_ajax_referer( 'abe_patterns_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->patterns[ $id ] ) ) {
            $this->patterns[ $id ]['enabled'] = ! $this->patterns[ $id ]['enabled'];
            update_option( 'abe_patterns', $this->patterns );
            delete_transient( 'abe_patterns_cache' );
            wp_send_json_success( [
                'message' => __( 'Pattern toggled.', 'advanced-block-editor' ),
                'enabled' => $this->patterns[ $id ]['enabled'],
            ] );
        }

        wp_send_json_error( [ 'message' => __( 'Pattern not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Duplicate pattern
     */
    public function ajax_duplicate_pattern() {
        check_ajax_referer( 'abe_patterns_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->patterns[ $id ] ) ) {
            $original = $this->patterns[ $id ];
            $new_id = 'pattern_' . wp_generate_uuid4();

            // Create unique slug
            $base_slug = $original['slug'] . '-copy';
            $slug = $base_slug;
            $counter = 1;
            while ( $this->slug_exists( $slug, '' ) ) {
                $slug = $base_slug . '-' . $counter;
                $counter++;
            }

            $new_pattern = array_merge( $original, [
                'id'       => $new_id,
                'slug'     => $slug,
                'title'    => $original['title'] . ' (Copy)',
                'enabled'  => false,
                'created'  => current_time( 'mysql' ),
                'modified' => current_time( 'mysql' ),
            ] );

            $this->patterns[ $new_id ] = $new_pattern;
            update_option( 'abe_patterns', $this->patterns );

            wp_send_json_success( [
                'message' => __( 'Pattern duplicated.', 'advanced-block-editor' ),
                'pattern' => $new_pattern,
            ] );
        }

        wp_send_json_error( [ 'message' => __( 'Pattern not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Get all patterns
     */
    public function ajax_get_patterns() {
        check_ajax_referer( 'abe_patterns_nonce', 'nonce' );
        wp_send_json_success( [ 'patterns' => $this->get_all_patterns() ] );
    }
}
