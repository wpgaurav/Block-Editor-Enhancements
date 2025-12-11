<?php
/**
 * Per-Block Code Manager Class
 * Handles custom CSS/JS for specific block types
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABE_Per_Block_Code {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Stored per-block rules
     */
    private $block_rules = [];

    /**
     * Blocks found on current page
     */
    private $found_blocks = [];

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
        $this->block_rules = get_option( 'abe_per_block_rules', [] );
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Frontend: Parse content to find blocks and enqueue their code
        add_filter( 'the_content', [ $this, 'parse_content_blocks' ], 1 );
        add_action( 'wp_footer', [ $this, 'output_frontend_code' ], 100 );
        add_action( 'wp_head', [ $this, 'output_frontend_css' ], 100 );

        // Editor assets
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ], 30 );

        // AJAX handlers
        add_action( 'wp_ajax_abe_save_block_rule', [ $this, 'ajax_save_block_rule' ] );
        add_action( 'wp_ajax_abe_delete_block_rule', [ $this, 'ajax_delete_block_rule' ] );
        add_action( 'wp_ajax_abe_get_block_rules', [ $this, 'ajax_get_block_rules' ] );
        add_action( 'wp_ajax_abe_toggle_block_rule', [ $this, 'ajax_toggle_block_rule' ] );
    }

    /**
     * Parse content to identify which blocks are used
     */
    public function parse_content_blocks( $content ) {
        if ( ! has_blocks( $content ) ) {
            return $content;
        }

        $blocks = parse_blocks( $content );
        $this->collect_block_names( $blocks );

        return $content;
    }

    /**
     * Recursively collect block names
     */
    private function collect_block_names( $blocks ) {
        foreach ( $blocks as $block ) {
            if ( ! empty( $block['blockName'] ) ) {
                $this->found_blocks[ $block['blockName'] ] = true;
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $this->collect_block_names( $block['innerBlocks'] );
            }
        }
    }

    /**
     * Output CSS in head for blocks found on page
     */
    public function output_frontend_css() {
        if ( empty( $this->found_blocks ) || empty( $this->block_rules ) ) {
            return;
        }

        $css = '';

        foreach ( $this->block_rules as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }

            if ( ! isset( $this->found_blocks[ $rule['block_type'] ] ) ) {
                continue;
            }

            if ( ! in_array( 'frontend', $rule['scope'] ?? [], true ) ) {
                continue;
            }

            if ( ! empty( $rule['css'] ) ) {
                $css .= "\n/* Block: {$rule['block_type']} */\n" . $rule['css'];
            }
        }

        if ( ! empty( $css ) ) {
            echo '<style id="abe-per-block-css">' . $css . '</style>' . "\n";
        }
    }

    /**
     * Output JS in footer for blocks found on page
     */
    public function output_frontend_code() {
        if ( empty( $this->found_blocks ) || empty( $this->block_rules ) ) {
            return;
        }

        $js = '';

        foreach ( $this->block_rules as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }

            if ( ! isset( $this->found_blocks[ $rule['block_type'] ] ) ) {
                continue;
            }

            if ( ! in_array( 'frontend', $rule['scope'] ?? [], true ) ) {
                continue;
            }

            if ( ! empty( $rule['js'] ) ) {
                $js .= "\n/* Block: {$rule['block_type']} */\n" . $rule['js'];
            }
        }

        if ( ! empty( $js ) ) {
            echo '<script id="abe-per-block-js">' . "\n(function() {\n" . $js . "\n})();\n</script>\n";
        }
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'abe-per-block-code',
            ABE_PLUGIN_URL . 'assets/editor/js/per-block-code.js',
            [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-plugins', 'wp-edit-post', 'wp-compose', 'wp-hooks' ],
            ABE_VERSION,
            true
        );

        wp_localize_script( 'abe-per-block-code', 'abePerBlockCode', [
            'rules'      => $this->get_all_block_rules(),
            'nonce'      => wp_create_nonce( 'abe_per_block_nonce' ),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'blockTypes' => $this->get_available_block_types(),
        ] );

        // Apply editor-scoped CSS
        $editor_css = '';
        foreach ( $this->block_rules as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }
            if ( ! in_array( 'editor', $rule['scope'] ?? [], true ) ) {
                continue;
            }
            if ( ! empty( $rule['css'] ) ) {
                $editor_css .= "\n/* Block: {$rule['block_type']} */\n" . $rule['css'];
            }
        }

        if ( ! empty( $editor_css ) ) {
            wp_add_inline_style( 'abe-editor', $editor_css );
        }

        // Apply editor-scoped JS
        $editor_js = '';
        foreach ( $this->block_rules as $rule ) {
            if ( empty( $rule['enabled'] ) ) {
                continue;
            }
            if ( ! in_array( 'editor', $rule['scope'] ?? [], true ) ) {
                continue;
            }
            if ( ! empty( $rule['js'] ) ) {
                $editor_js .= "\n/* Block: {$rule['block_type']} */\n" . $rule['js'];
            }
        }

        if ( ! empty( $editor_js ) ) {
            wp_add_inline_script( 'abe-editor', $editor_js, 'after' );
        }
    }

    /**
     * Get all block rules
     */
    public function get_all_block_rules() {
        return $this->block_rules;
    }

    /**
     * Get available block types
     */
    private function get_available_block_types() {
        $blocks = [
            // Core text blocks
            'core/paragraph'    => __( 'Paragraph', 'advanced-block-editor' ),
            'core/heading'      => __( 'Heading', 'advanced-block-editor' ),
            'core/list'         => __( 'List', 'advanced-block-editor' ),
            'core/list-item'    => __( 'List Item', 'advanced-block-editor' ),
            'core/quote'        => __( 'Quote', 'advanced-block-editor' ),
            'core/pullquote'    => __( 'Pullquote', 'advanced-block-editor' ),
            'core/code'         => __( 'Code', 'advanced-block-editor' ),
            'core/preformatted' => __( 'Preformatted', 'advanced-block-editor' ),
            'core/verse'        => __( 'Verse', 'advanced-block-editor' ),
            'core/details'      => __( 'Details', 'advanced-block-editor' ),

            // Media blocks
            'core/image'        => __( 'Image', 'advanced-block-editor' ),
            'core/gallery'      => __( 'Gallery', 'advanced-block-editor' ),
            'core/audio'        => __( 'Audio', 'advanced-block-editor' ),
            'core/video'        => __( 'Video', 'advanced-block-editor' ),
            'core/cover'        => __( 'Cover', 'advanced-block-editor' ),
            'core/file'         => __( 'File', 'advanced-block-editor' ),
            'core/media-text'   => __( 'Media & Text', 'advanced-block-editor' ),

            // Design blocks
            'core/buttons'      => __( 'Buttons', 'advanced-block-editor' ),
            'core/button'       => __( 'Button', 'advanced-block-editor' ),
            'core/columns'      => __( 'Columns', 'advanced-block-editor' ),
            'core/column'       => __( 'Column', 'advanced-block-editor' ),
            'core/group'        => __( 'Group', 'advanced-block-editor' ),
            'core/row'          => __( 'Row', 'advanced-block-editor' ),
            'core/stack'        => __( 'Stack', 'advanced-block-editor' ),
            'core/separator'    => __( 'Separator', 'advanced-block-editor' ),
            'core/spacer'       => __( 'Spacer', 'advanced-block-editor' ),

            // Data blocks
            'core/table'        => __( 'Table', 'advanced-block-editor' ),

            // Widget blocks
            'core/search'       => __( 'Search', 'advanced-block-editor' ),
            'core/archives'     => __( 'Archives', 'advanced-block-editor' ),
            'core/categories'   => __( 'Categories', 'advanced-block-editor' ),
            'core/latest-posts' => __( 'Latest Posts', 'advanced-block-editor' ),
            'core/latest-comments' => __( 'Latest Comments', 'advanced-block-editor' ),
            'core/calendar'     => __( 'Calendar', 'advanced-block-editor' ),
            'core/tag-cloud'    => __( 'Tag Cloud', 'advanced-block-editor' ),
            'core/social-links' => __( 'Social Icons', 'advanced-block-editor' ),
            'core/social-link'  => __( 'Social Icon', 'advanced-block-editor' ),

            // Theme blocks
            'core/navigation'        => __( 'Navigation', 'advanced-block-editor' ),
            'core/navigation-link'   => __( 'Navigation Link', 'advanced-block-editor' ),
            'core/site-logo'         => __( 'Site Logo', 'advanced-block-editor' ),
            'core/site-title'        => __( 'Site Title', 'advanced-block-editor' ),
            'core/site-tagline'      => __( 'Site Tagline', 'advanced-block-editor' ),
            'core/query'             => __( 'Query Loop', 'advanced-block-editor' ),
            'core/post-template'     => __( 'Post Template', 'advanced-block-editor' ),
            'core/post-title'        => __( 'Post Title', 'advanced-block-editor' ),
            'core/post-content'      => __( 'Post Content', 'advanced-block-editor' ),
            'core/post-excerpt'      => __( 'Post Excerpt', 'advanced-block-editor' ),
            'core/post-featured-image' => __( 'Featured Image', 'advanced-block-editor' ),
            'core/post-date'         => __( 'Post Date', 'advanced-block-editor' ),
            'core/post-author'       => __( 'Post Author', 'advanced-block-editor' ),
            'core/post-terms'        => __( 'Post Terms', 'advanced-block-editor' ),

            // Embed blocks
            'core/embed'        => __( 'Embed', 'advanced-block-editor' ),
            'core/html'         => __( 'Custom HTML', 'advanced-block-editor' ),
        ];

        return $blocks;
    }

    /**
     * AJAX: Save block rule
     */
    public function ajax_save_block_rule() {
        check_ajax_referer( 'abe_per_block_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );
        if ( empty( $id ) ) {
            $id = 'block_rule_' . wp_generate_uuid4();
        }

        $rule = [
            'id'          => $id,
            'name'        => sanitize_text_field( $_POST['name'] ?? __( 'Untitled Rule', 'advanced-block-editor' ) ),
            'block_type'  => sanitize_text_field( $_POST['block_type'] ?? '' ),
            'css'         => wp_strip_all_tags( wp_unslash( $_POST['css'] ?? '' ) ),
            'js'          => wp_unslash( $_POST['js'] ?? '' ),
            'scope'       => array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['scope'] ?? [ 'frontend' ] ) ) ),
            'enabled'     => ! empty( $_POST['enabled'] ),
            'priority'    => intval( $_POST['priority'] ?? 10 ),
        ];

        // Validate required fields
        if ( empty( $rule['block_type'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Block type is required.', 'advanced-block-editor' ) ] );
        }

        $this->block_rules[ $id ] = $rule;
        update_option( 'abe_per_block_rules', $this->block_rules );

        wp_send_json_success( [
            'message' => __( 'Block rule saved successfully.', 'advanced-block-editor' ),
            'rule'    => $rule,
        ] );
    }

    /**
     * AJAX: Delete block rule
     */
    public function ajax_delete_block_rule() {
        check_ajax_referer( 'abe_per_block_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->block_rules[ $id ] ) ) {
            unset( $this->block_rules[ $id ] );
            update_option( 'abe_per_block_rules', $this->block_rules );
            wp_send_json_success( [ 'message' => __( 'Block rule deleted.', 'advanced-block-editor' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Block rule not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Toggle block rule enabled state
     */
    public function ajax_toggle_block_rule() {
        check_ajax_referer( 'abe_per_block_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->block_rules[ $id ] ) ) {
            $this->block_rules[ $id ]['enabled'] = ! $this->block_rules[ $id ]['enabled'];
            update_option( 'abe_per_block_rules', $this->block_rules );
            wp_send_json_success( [
                'message' => __( 'Block rule toggled.', 'advanced-block-editor' ),
                'enabled' => $this->block_rules[ $id ]['enabled'],
            ] );
        }

        wp_send_json_error( [ 'message' => __( 'Block rule not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Get all block rules
     */
    public function ajax_get_block_rules() {
        check_ajax_referer( 'abe_per_block_nonce', 'nonce' );
        wp_send_json_success( [ 'rules' => $this->get_all_block_rules() ] );
    }
}
