<?php
/**
 * Block Variations Manager Class
 * Handles creation and registration of custom block variations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABE_Block_Variations {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Stored variations
     */
    private $variations = [];

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
        $this->variations = get_option( 'abe_block_variations', [] );
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_variations_script' ], 20 );
        add_action( 'wp_ajax_abe_save_variation', [ $this, 'ajax_save_variation' ] );
        add_action( 'wp_ajax_abe_delete_variation', [ $this, 'ajax_delete_variation' ] );
        add_action( 'wp_ajax_abe_get_variations', [ $this, 'ajax_get_variations' ] );
    }

    /**
     * Enqueue script to register variations in editor
     */
    public function enqueue_variations_script() {
        wp_enqueue_script(
            'abe-block-variations',
            ABE_PLUGIN_URL . 'assets/editor/js/block-variations.js',
            [ 'wp-blocks', 'wp-dom-ready', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ],
            ABE_VERSION,
            true
        );

        wp_localize_script( 'abe-block-variations', 'abeVariations', [
            'variations' => $this->get_all_variations(),
            'nonce'      => wp_create_nonce( 'abe_variations_nonce' ),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'coreBlocks' => $this->get_core_blocks_list(),
        ] );
    }

    /**
     * Get list of core blocks for variation creation
     */
    private function get_core_blocks_list() {
        return [
            'core/paragraph'    => __( 'Paragraph', 'advanced-block-editor' ),
            'core/heading'      => __( 'Heading', 'advanced-block-editor' ),
            'core/image'        => __( 'Image', 'advanced-block-editor' ),
            'core/gallery'      => __( 'Gallery', 'advanced-block-editor' ),
            'core/list'         => __( 'List', 'advanced-block-editor' ),
            'core/quote'        => __( 'Quote', 'advanced-block-editor' ),
            'core/cover'        => __( 'Cover', 'advanced-block-editor' ),
            'core/file'         => __( 'File', 'advanced-block-editor' ),
            'core/audio'        => __( 'Audio', 'advanced-block-editor' ),
            'core/video'        => __( 'Video', 'advanced-block-editor' ),
            'core/columns'      => __( 'Columns', 'advanced-block-editor' ),
            'core/column'       => __( 'Column', 'advanced-block-editor' ),
            'core/group'        => __( 'Group', 'advanced-block-editor' ),
            'core/buttons'      => __( 'Buttons', 'advanced-block-editor' ),
            'core/button'       => __( 'Button', 'advanced-block-editor' ),
            'core/spacer'       => __( 'Spacer', 'advanced-block-editor' ),
            'core/separator'    => __( 'Separator', 'advanced-block-editor' ),
            'core/table'        => __( 'Table', 'advanced-block-editor' ),
            'core/code'         => __( 'Code', 'advanced-block-editor' ),
            'core/preformatted' => __( 'Preformatted', 'advanced-block-editor' ),
            'core/pullquote'    => __( 'Pullquote', 'advanced-block-editor' ),
            'core/verse'        => __( 'Verse', 'advanced-block-editor' ),
            'core/media-text'   => __( 'Media & Text', 'advanced-block-editor' ),
            'core/embed'        => __( 'Embed', 'advanced-block-editor' ),
            'core/search'       => __( 'Search', 'advanced-block-editor' ),
            'core/navigation'   => __( 'Navigation', 'advanced-block-editor' ),
            'core/post-title'   => __( 'Post Title', 'advanced-block-editor' ),
            'core/post-content' => __( 'Post Content', 'advanced-block-editor' ),
            'core/post-excerpt' => __( 'Post Excerpt', 'advanced-block-editor' ),
            'core/post-featured-image' => __( 'Featured Image', 'advanced-block-editor' ),
        ];
    }

    /**
     * Get all saved variations
     */
    public function get_all_variations() {
        return $this->variations;
    }

    /**
     * AJAX: Save a variation
     */
    public function ajax_save_variation() {
        check_ajax_referer( 'abe_variations_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $variation = [
            'id'          => sanitize_key( $_POST['id'] ?? wp_generate_uuid4() ),
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'title'       => sanitize_text_field( $_POST['title'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'blockType'   => sanitize_text_field( $_POST['blockType'] ?? '' ),
            'icon'        => sanitize_text_field( $_POST['icon'] ?? 'block-default' ),
            'category'    => sanitize_text_field( $_POST['category'] ?? 'common' ),
            'scope'       => array_map( 'sanitize_text_field', (array) ( $_POST['scope'] ?? [ 'inserter' ] ) ),
            'attributes'  => json_decode( stripslashes( $_POST['attributes'] ?? '{}' ), true ),
            'innerBlocks' => json_decode( stripslashes( $_POST['innerBlocks'] ?? '[]' ), true ),
            'isActive'    => ! empty( $_POST['isActive'] ),
            'keywords'    => array_map( 'sanitize_text_field', explode( ',', $_POST['keywords'] ?? '' ) ),
        ];

        // Validate required fields
        if ( empty( $variation['name'] ) || empty( $variation['blockType'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Name and Block Type are required.', 'advanced-block-editor' ) ] );
        }

        // Update or add variation
        $this->variations[ $variation['id'] ] = $variation;
        update_option( 'abe_block_variations', $this->variations );

        wp_send_json_success( [
            'message'   => __( 'Variation saved successfully.', 'advanced-block-editor' ),
            'variation' => $variation,
        ] );
    }

    /**
     * AJAX: Delete a variation
     */
    public function ajax_delete_variation() {
        check_ajax_referer( 'abe_variations_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'advanced-block-editor' ) ] );
        }

        $id = sanitize_key( $_POST['id'] ?? '' );

        if ( isset( $this->variations[ $id ] ) ) {
            unset( $this->variations[ $id ] );
            update_option( 'abe_block_variations', $this->variations );
            wp_send_json_success( [ 'message' => __( 'Variation deleted.', 'advanced-block-editor' ) ] );
        }

        wp_send_json_error( [ 'message' => __( 'Variation not found.', 'advanced-block-editor' ) ] );
    }

    /**
     * AJAX: Get all variations
     */
    public function ajax_get_variations() {
        check_ajax_referer( 'abe_variations_nonce', 'nonce' );
        wp_send_json_success( [ 'variations' => $this->get_all_variations() ] );
    }
}
