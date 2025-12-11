<?php
/**
 * Custom Code Manager Class
 * Handles custom CSS/JS with frontend and backend execution
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABE_Custom_Code {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Stored code snippets
     */
    private $snippets = [];

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
        $this->snippets = get_option( 'abe_code_snippets', [] );
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Editor assets
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_code' ], 25 );
        
        // Frontend assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_code' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_abe_save_snippet', [ $this, 'ajax_save_snippet' ] );
        add_action( 'wp_ajax_abe_delete_snippet', [ $this, 'ajax_delete_snippet' ] );
        add_action( 'wp_ajax_abe_get_snippets', [ $this, 'ajax_get_snippets' ] );
        add_action( 'wp_ajax_abe_toggle_snippet', [ $this, 'ajax_toggle_snippet' ] );
    }

    /**
     * Enqueue code in editor
     */
    public function enqueue_editor_code() {
        // Enqueue the custom code panel script
        wp_enqueue_script(
            'abe-custom-code-panel',
            ABE_PLUGIN_URL . 'assets/editor/js/custom-code-panel.js',
            [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-compose' ],
            ABE_VERSION,
            true
        );

        wp_localize_script( 'abe-custom-code-panel', 'abeCustomCode', [
            'snippets'    => $this->get_all_snippets(),
            'nonce'       => wp_create_nonce( 'abe_custom_code_nonce' ),
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'cssSelectors' => $this->get_css_selectors_reference(),
        ] );

        // Apply editor-scoped snippets
        foreach ( $this->snippets as $snippet ) {
            if ( ! $snippet['enabled'] ) continue;
            if ( ! in_array( 'editor', $snippet['scope'], true ) ) continue;

            if ( $snippet['type'] === 'css' ) {
                wp_add_inline_style( 'abe-editor', $snippet['code'] );
            } elseif ( $snippet['type'] === 'js' ) {
                wp_add_inline_script( 'abe-editor', $snippet['code'], 'after' );
            }
        }
    }

    /**
     * Enqueue code on frontend
     */
    public function enqueue_frontend_code() {
        $has_css = false;
        $has_js = false;
        $css_code = '';
        $js_code = '';

        foreach ( $this->snippets as $snippet ) {
            if ( ! $snippet['enabled'] ) continue;
            if ( ! in_array( 'frontend', $snippet['scope'], true ) ) continue;

            if ( $snippet['type'] === 'css' ) {
                $css_code .= "\n/* Snippet: {$snippet['name']} */\n" . $snippet['code'];
                $has_css = true;
            } elseif ( $snippet['type'] === 'js' ) {
                $js_code .= "\n/* Snippet: {$snippet['name']} */\n" . $snippet['code'];
                $has_js = true;
            }
        }

        if ( $has_css ) {
            wp_register_style( 'abe-frontend-custom', false );
            wp_enqueue_style( 'abe-frontend-custom' );
            wp_add_inline_style( 'abe-frontend-custom', $css_code );
        }

        if ( $has_js ) {
            wp_register_script( 'abe-frontend-custom', false, [], ABE_VERSION, true );
            wp_enqueue_script( 'abe-frontend-custom' );
            wp_add_inline_script( 'abe-frontend-custom', $js_code );
        }
    }

    /**
     * Get CSS selectors reference for helper
     */
    private function get_css_selectors_reference() {
        return [
            'layout' => [
                '.wp-site-blocks' => __( 'Site wrapper', 'advanced-block-editor' ),
                '.entry-content' => __( 'Post/page content', 'advanced-block-editor' ),
                '.wp-block-post-content' => __( 'Post content block', 'advanced-block-editor' ),
            ],
            'blocks' => [
                '.wp-block-paragraph' => __( 'Paragraph block', 'advanced-block-editor' ),
                '.wp-block-heading' => __( 'Heading block', 'advanced-block-editor' ),
                '.wp-block-image' => __( 'Image block', 'advanced-block-editor' ),
                '.wp-block-gallery' => __( 'Gallery block', 'advanced-block-editor' ),
                '.wp-block-columns' => __( 'Columns block', 'advanced-block-editor' ),
                '.wp-block-group' => __( 'Group block', 'advanced-block-editor' ),
                '.wp-block-cover' => __( 'Cover block', 'advanced-block-editor' ),
                '.wp-block-buttons' => __( 'Buttons wrapper', 'advanced-block-editor' ),
                '.wp-block-button' => __( 'Button block', 'advanced-block-editor' ),
                '.wp-block-button__link' => __( 'Button link', 'advanced-block-editor' ),
                '.wp-block-quote' => __( 'Quote block', 'advanced-block-editor' ),
                '.wp-block-list' => __( 'List block', 'advanced-block-editor' ),
                '.wp-block-table' => __( 'Table block', 'advanced-block-editor' ),
                '.wp-block-media-text' => __( 'Media & Text block', 'advanced-block-editor' ),
                '.wp-block-separator' => __( 'Separator block', 'advanced-block-editor' ),
                '.wp-block-spacer' => __( 'Spacer block', 'advanced-block-editor' ),
            ],
            'editor' => [
                '.editor-styles-wrapper' => __( 'Editor content area', 'advanced-block-editor' ),
                '.edit-post-visual-editor' => __( 'Visual editor container', 'advanced-block-editor' ),
                '.block-editor-block-list__layout' => __( 'Block list', 'advanced-block-editor' ),
                '.is-root-container' => __( 'Root container', 'advanced-block-editor' ),
                '.wp-block.is-selected' => __( 'Selected block', 'advanced-block-editor' ),
            ],
            'typography' => [
                'h1, .has-large-font-size' => __( 'Large text', 'advanced-block-editor' ),
                'h2, h3, h4' => __( 'Subheadings', 'advanced-block-editor' ),
                'p, .has-regular-font-size' => __( 'Body text', 'advanced-block-editor' ),
                '.has-small-font-size' => __( 'Small text', 'advanced-block-editor' ),
            ],
        ];
    }

    /**
     * Get all snippets
     */
    public function get_all_snippets() {
        return $this->snippets;
    }

    /**
     * AJAX: Save snippet
     */
    public function ajax_save_snippet() {
        check_ajax_referer( 'abe_custom_code_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );
        if ( empty( $id ) ) {
            $id = 'snippet_' . wp_generate_uuid4();
        }

        $snippet = [
            'id'      => $id,
            'name'    => sanitize_text_field( $_POST['name'] ?? __( 'Untitled Snippet', 'advanced-block-editor' ) ),
            'type'    => in_array( $_POST['type'], [ 'css', 'js' ], true ) ? $_POST['type'] : 'css',
            'code'    => wp_unslash( $_POST['code'] ?? '' ),
            'scope'   => array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['scope'] ?? [ 'editor' ] ) ) ),
            'enabled' => ! empty( $_POST['enabled'] ),
            'priority' => intval( $_POST['priority'] ?? 10 ),
        ];

        $this->snippets[ $id ] = $snippet;
        update_option( 'abe_code_snippets', $this->snippets );

        wp_send_json_success( [
            'message' => __( 'Snippet saved successfully.', 'advanced-block-editor' ),
            'snippet' => $snippet,
        ] );
    }

    /**
     * AJAX: Delete snippet
     */
    public function ajax_delete_snippet() {
        check_ajax_referer( 'abe_custom_code_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->snippets[ $id ] ) ) {
            unset( $this->snippets[ $id ] );
            update_option( 'abe_code_snippets', $this->snippets );
            wp_send_json_success( [ 'message' => __( 'Snippet deleted.', 'advanced-block-editor' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Snippet not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Toggle snippet enabled state
     */
    public function ajax_toggle_snippet() {
        check_ajax_referer( 'abe_custom_code_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->snippets[ $id ] ) ) {
            $this->snippets[ $id ]['enabled'] = ! $this->snippets[ $id ]['enabled'];
            update_option( 'abe_code_snippets', $this->snippets );
            wp_send_json_success( [
                'message' => __( 'Snippet toggled.', 'advanced-block-editor' ),
                'enabled' => $this->snippets[ $id ]['enabled'],
            ] );
        }

        wp_send_json_error( [ 'message' => __( 'Snippet not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Get all snippets
     */
    public function ajax_get_snippets() {
        check_ajax_referer( 'abe_custom_code_nonce', 'nonce' );
        wp_send_json_success( [ 'snippets' => $this->get_all_snippets() ] );
    }
}
