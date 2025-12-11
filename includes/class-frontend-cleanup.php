<?php
/**
 * Frontend Cleanup Class
 * Handles removal of core block classes and other frontend optimizations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABE_Frontend_Cleanup {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Settings
     */
    private $settings = [];

    /**
     * Available block classes for removal
     */
    private $block_classes = [];

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
        $this->settings = get_option( 'abe_frontend_settings', $this->get_defaults() );
        $this->block_classes = $this->define_block_classes();
        $this->init_hooks();
    }

    /**
     * Default settings
     */
    private function get_defaults() {
        return [
            'remove_block_classes'      => [],
            'remove_wp_block_library'   => false,
            'remove_global_styles'      => false,
            'remove_duotone_svg'        => false,
            'lazy_load_images'          => true,
            'defer_block_styles'        => false,
            'clean_head'                => false,
            'remove_emoji_scripts'      => false,
            'remove_wp_embed'           => false,
            'custom_classes_to_remove'  => '',
        ];
    }

    /**
     * Define available block classes
     */
    private function define_block_classes() {
        return [
            // Text blocks
            'wp-block-paragraph'    => __( 'Paragraph (.wp-block-paragraph)', 'advanced-block-editor' ),
            'wp-block-heading'      => __( 'Heading (.wp-block-heading)', 'advanced-block-editor' ),
            'wp-block-list'         => __( 'List (.wp-block-list)', 'advanced-block-editor' ),
            'wp-block-quote'        => __( 'Quote (.wp-block-quote)', 'advanced-block-editor' ),
            'wp-block-pullquote'    => __( 'Pullquote (.wp-block-pullquote)', 'advanced-block-editor' ),
            'wp-block-code'         => __( 'Code (.wp-block-code)', 'advanced-block-editor' ),
            'wp-block-preformatted' => __( 'Preformatted (.wp-block-preformatted)', 'advanced-block-editor' ),
            'wp-block-verse'        => __( 'Verse (.wp-block-verse)', 'advanced-block-editor' ),

            // Media blocks
            'wp-block-image'        => __( 'Image (.wp-block-image)', 'advanced-block-editor' ),
            'wp-block-gallery'      => __( 'Gallery (.wp-block-gallery)', 'advanced-block-editor' ),
            'wp-block-audio'        => __( 'Audio (.wp-block-audio)', 'advanced-block-editor' ),
            'wp-block-video'        => __( 'Video (.wp-block-video)', 'advanced-block-editor' ),
            'wp-block-cover'        => __( 'Cover (.wp-block-cover)', 'advanced-block-editor' ),
            'wp-block-file'         => __( 'File (.wp-block-file)', 'advanced-block-editor' ),
            'wp-block-media-text'   => __( 'Media & Text (.wp-block-media-text)', 'advanced-block-editor' ),

            // Design blocks
            'wp-block-buttons'      => __( 'Buttons (.wp-block-buttons)', 'advanced-block-editor' ),
            'wp-block-button'       => __( 'Button (.wp-block-button)', 'advanced-block-editor' ),
            'wp-block-columns'      => __( 'Columns (.wp-block-columns)', 'advanced-block-editor' ),
            'wp-block-column'       => __( 'Column (.wp-block-column)', 'advanced-block-editor' ),
            'wp-block-group'        => __( 'Group (.wp-block-group)', 'advanced-block-editor' ),
            'wp-block-row'          => __( 'Row (.wp-block-row)', 'advanced-block-editor' ),
            'wp-block-stack'        => __( 'Stack (.wp-block-stack)', 'advanced-block-editor' ),
            'wp-block-separator'    => __( 'Separator (.wp-block-separator)', 'advanced-block-editor' ),
            'wp-block-spacer'       => __( 'Spacer (.wp-block-spacer)', 'advanced-block-editor' ),

            // Data blocks
            'wp-block-table'        => __( 'Table (.wp-block-table)', 'advanced-block-editor' ),

            // Widget blocks
            'wp-block-search'       => __( 'Search (.wp-block-search)', 'advanced-block-editor' ),
            'wp-block-archives'     => __( 'Archives (.wp-block-archives)', 'advanced-block-editor' ),
            'wp-block-categories'   => __( 'Categories (.wp-block-categories)', 'advanced-block-editor' ),
            'wp-block-latest-posts' => __( 'Latest Posts (.wp-block-latest-posts)', 'advanced-block-editor' ),
            'wp-block-calendar'     => __( 'Calendar (.wp-block-calendar)', 'advanced-block-editor' ),
            'wp-block-tag-cloud'    => __( 'Tag Cloud (.wp-block-tag-cloud)', 'advanced-block-editor' ),
            'wp-block-social-links' => __( 'Social Icons (.wp-block-social-links)', 'advanced-block-editor' ),

            // Navigation blocks
            'wp-block-navigation'   => __( 'Navigation (.wp-block-navigation)', 'advanced-block-editor' ),
            'wp-block-site-logo'    => __( 'Site Logo (.wp-block-site-logo)', 'advanced-block-editor' ),
            'wp-block-site-title'   => __( 'Site Title (.wp-block-site-title)', 'advanced-block-editor' ),
            'wp-block-site-tagline' => __( 'Site Tagline (.wp-block-site-tagline)', 'advanced-block-editor' ),

            // Post blocks
            'wp-block-post-title'   => __( 'Post Title (.wp-block-post-title)', 'advanced-block-editor' ),
            'wp-block-post-content' => __( 'Post Content (.wp-block-post-content)', 'advanced-block-editor' ),
            'wp-block-post-excerpt' => __( 'Post Excerpt (.wp-block-post-excerpt)', 'advanced-block-editor' ),
            'wp-block-post-featured-image' => __( 'Featured Image (.wp-block-post-featured-image)', 'advanced-block-editor' ),
            'wp-block-post-date'    => __( 'Post Date (.wp-block-post-date)', 'advanced-block-editor' ),
            'wp-block-post-author'  => __( 'Post Author (.wp-block-post-author)', 'advanced-block-editor' ),
        ];
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Only run cleanup on frontend
        if ( is_admin() ) {
            return;
        }

        // Remove block classes from content
        if ( ! empty( $this->settings['remove_block_classes'] ) || ! empty( $this->settings['custom_classes_to_remove'] ) ) {
            add_filter( 'the_content', [ $this, 'remove_block_classes' ], 999 );
            add_filter( 'render_block', [ $this, 'filter_block_render' ], 10, 2 );
        }

        // Remove block library CSS
        if ( ! empty( $this->settings['remove_wp_block_library'] ) ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'remove_block_library' ], 100 );
        }

        // Remove global styles
        if ( ! empty( $this->settings['remove_global_styles'] ) ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'remove_global_styles' ], 100 );
        }

        // Remove duotone SVG
        if ( ! empty( $this->settings['remove_duotone_svg'] ) ) {
            remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
            remove_action( 'in_admin_header', 'wp_global_styles_render_svg_filters' );
        }

        // Clean head
        if ( ! empty( $this->settings['clean_head'] ) ) {
            $this->clean_head();
        }

        // Remove emoji scripts
        if ( ! empty( $this->settings['remove_emoji_scripts'] ) ) {
            $this->remove_emoji_scripts();
        }

        // Remove wp-embed
        if ( ! empty( $this->settings['remove_wp_embed'] ) ) {
            add_action( 'wp_footer', [ $this, 'remove_wp_embed' ] );
        }
    }

    /**
     * Remove block classes from content
     */
    public function remove_block_classes( $content ) {
        $classes_to_remove = $this->settings['remove_block_classes'] ?? [];

        // Add custom classes
        if ( ! empty( $this->settings['custom_classes_to_remove'] ) ) {
            $custom = array_map( 'trim', explode( "\n", $this->settings['custom_classes_to_remove'] ) );
            $classes_to_remove = array_merge( $classes_to_remove, $custom );
        }

        if ( empty( $classes_to_remove ) ) {
            return $content;
        }

        foreach ( $classes_to_remove as $class ) {
            $class = trim( $class );
            if ( empty( $class ) ) {
                continue;
            }

            // Escape for regex
            $escaped = preg_quote( $class, '/' );

            // Remove class from class="" attributes
            // Handle: class="wp-block-heading other-class"
            $content = preg_replace(
                '/\bclass="([^"]*)\b' . $escaped . '\b([^"]*)"/',
                'class="$1$2"',
                $content
            );

            // Clean up double spaces
            $content = preg_replace( '/class="\s+/', 'class="', $content );
            $content = preg_replace( '/\s+"/', '"', $content );
            $content = preg_replace( '/\s{2,}/', ' ', $content );

            // Remove empty class attributes
            $content = preg_replace( '/\s*class=""\s*/', ' ', $content );
        }

        return $content;
    }

    /**
     * Filter block render to remove classes
     */
    public function filter_block_render( $block_content, $block ) {
        if ( empty( $block_content ) ) {
            return $block_content;
        }

        $classes_to_remove = $this->settings['remove_block_classes'] ?? [];

        // Add custom classes
        if ( ! empty( $this->settings['custom_classes_to_remove'] ) ) {
            $custom = array_map( 'trim', explode( "\n", $this->settings['custom_classes_to_remove'] ) );
            $classes_to_remove = array_merge( $classes_to_remove, $custom );
        }

        if ( empty( $classes_to_remove ) ) {
            return $block_content;
        }

        foreach ( $classes_to_remove as $class ) {
            $class = trim( $class );
            if ( empty( $class ) ) {
                continue;
            }

            $escaped = preg_quote( $class, '/' );

            $block_content = preg_replace(
                '/\bclass="([^"]*)\b' . $escaped . '\b([^"]*)"/',
                'class="$1$2"',
                $block_content
            );
        }

        // Clean up
        $block_content = preg_replace( '/class="\s+/', 'class="', $block_content );
        $block_content = preg_replace( '/\s+"/', '"', $block_content );
        $block_content = preg_replace( '/\s{2,}/', ' ', $block_content );
        $block_content = preg_replace( '/\s*class=""\s*/', ' ', $block_content );

        return $block_content;
    }

    /**
     * Remove block library CSS
     */
    public function remove_block_library() {
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wc-blocks-style' ); // WooCommerce blocks
    }

    /**
     * Remove global styles
     */
    public function remove_global_styles() {
        wp_dequeue_style( 'global-styles' );
        wp_dequeue_style( 'wp-global-styles' );
    }

    /**
     * Clean WordPress head
     */
    private function clean_head() {
        // Remove RSD link
        remove_action( 'wp_head', 'rsd_link' );

        // Remove wlwmanifest link
        remove_action( 'wp_head', 'wlwmanifest_link' );

        // Remove WordPress generator tag
        remove_action( 'wp_head', 'wp_generator' );

        // Remove shortlink
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );

        // Remove REST API link
        remove_action( 'wp_head', 'rest_output_link_wp_head' );

        // Remove oEmbed discovery links
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

        // Remove rel=prev/next
        remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
    }

    /**
     * Remove emoji scripts and styles
     */
    private function remove_emoji_scripts() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

        // Remove TinyMCE emoji plugin
        add_filter( 'tiny_mce_plugins', function( $plugins ) {
            if ( is_array( $plugins ) ) {
                return array_diff( $plugins, [ 'wpemoji' ] );
            }
            return [];
        } );

        // Remove emoji DNS prefetch
        add_filter( 'wp_resource_hints', function( $urls, $relation_type ) {
            if ( 'dns-prefetch' === $relation_type ) {
                $urls = array_filter( $urls, function( $url ) {
                    return strpos( $url, 'https://s.w.org/images/core/emoji/' ) === false;
                } );
            }
            return $urls;
        }, 10, 2 );
    }

    /**
     * Remove wp-embed script
     */
    public function remove_wp_embed() {
        wp_dequeue_script( 'wp-embed' );
    }

    /**
     * Get settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get block classes list
     */
    public function get_block_classes() {
        return $this->block_classes;
    }

    /**
     * Update settings
     */
    public function update_settings( $new_settings ) {
        $this->settings = wp_parse_args( $new_settings, $this->get_defaults() );
        update_option( 'abe_frontend_settings', $this->settings );
    }
}
