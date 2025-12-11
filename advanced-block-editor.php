<?php
/**
 * Plugin Name: Advanced Block Editor
 * Plugin URI: https://developer.developer.developer
 * Description: Powerful WordPress Block Editor enhancements - custom patterns, per-block CSS/JS, frontend cleanup, word count, editor width control, focus mode, and much more.
 * Version: 4.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Developer Developer Developer
 * Author URI: https://developer.developer.developer
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: advanced-block-editor
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'ABE_VERSION', '4.0.0' );
define( 'ABE_PLUGIN_FILE', __FILE__ );
define( 'ABE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ABE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
final class Advanced_Block_Editor {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings = [];

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
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load settings from database
     */
    private function load_settings() {
        $defaults = [
            // Editor appearance
            'editor_width'            => '',
            'editor_width_unit'       => 'px',
            'word_count_enabled'      => true,
            'word_count_position'     => 'top',
            'focus_mode'              => false,
            'disable_fullscreen'      => false,

            // Custom CSS/JS for editor
            'custom_css_inline'       => '',
            'custom_css_file'         => '',
            'custom_js_inline'        => '',
            'custom_js_file'          => '',

            // Editor enhancements
            'enable_copy_all'         => true,
            'enable_block_navigator'  => true,
            'enable_quick_insert'     => true,
            'highlight_current_block' => true,
            'show_block_handles'      => true,
            'smooth_scrolling'        => true,
            'auto_save_interval'      => 60,
            'enable_keyboard_shortcuts' => true,
            'show_block_breadcrumbs'  => true,

            // Writing enhancements
            'typewriter_mode'         => false,
            'reading_time'            => true,
            'paragraph_count'         => true,
            'heading_anchors'         => true,

            // Block defaults
            'default_paragraph_font_size' => '',
            'default_heading_level'   => 2,
        ];

        $saved = get_option( 'abe_settings', [] );
        $this->settings = wp_parse_args( $saved, $defaults );
    }

    /**
     * Get a setting value
     */
    public function get_setting( $key, $default = null ) {
        return $this->settings[ $key ] ?? $default;
    }

    /**
     * Get all settings
     */
    public function get_all_settings() {
        return $this->settings;
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Admin hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

        // Block Editor hooks
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );

        // Frontend hooks for reading time/anchors
        add_filter( 'the_content', [ $this, 'add_reading_time' ], 5 );
        add_filter( 'the_content', [ $this, 'add_heading_anchors' ], 15 );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . ABE_PLUGIN_BASENAME, [ $this, 'add_settings_link' ] );
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'advanced-block-editor',
            false,
            dirname( ABE_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Advanced Block Editor', 'advanced-block-editor' ),
            __( 'Block Editor+', 'advanced-block-editor' ),
            'manage_options',
            'advanced-block-editor',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'abe_settings_group',
            'abe_settings',
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => [],
            ]
        );

        // Register frontend settings
        register_setting(
            'abe_frontend_settings_group',
            'abe_frontend_settings',
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_frontend_settings' ],
                'default'           => [],
            ]
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = [];

        // Editor Width
        $sanitized['editor_width'] = isset( $input['editor_width'] )
            ? sanitize_text_field( $input['editor_width'] )
            : '';

        $sanitized['editor_width_unit'] = isset( $input['editor_width_unit'] ) && in_array( $input['editor_width_unit'], [ 'px', '%', 'vw' ], true )
            ? $input['editor_width_unit']
            : 'px';

        // Word Count
        $sanitized['word_count_enabled'] = ! empty( $input['word_count_enabled'] );
        $sanitized['word_count_position'] = isset( $input['word_count_position'] ) && in_array( $input['word_count_position'], [ 'top', 'bottom', 'statusbar' ], true )
            ? $input['word_count_position']
            : 'top';

        // Custom CSS
        $sanitized['custom_css_inline'] = isset( $input['custom_css_inline'] )
            ? wp_strip_all_tags( $input['custom_css_inline'] )
            : '';

        $sanitized['custom_css_file'] = isset( $input['custom_css_file'] )
            ? esc_url_raw( $input['custom_css_file'] )
            : '';

        // Custom JS
        $sanitized['custom_js_inline'] = isset( $input['custom_js_inline'] )
            ? $input['custom_js_inline']
            : '';

        $sanitized['custom_js_file'] = isset( $input['custom_js_file'] )
            ? esc_url_raw( $input['custom_js_file'] )
            : '';

        // Editor Options
        $sanitized['disable_fullscreen'] = ! empty( $input['disable_fullscreen'] );
        $sanitized['focus_mode'] = ! empty( $input['focus_mode'] );

        // Editor enhancements
        $sanitized['enable_copy_all'] = ! empty( $input['enable_copy_all'] );
        $sanitized['enable_block_navigator'] = ! empty( $input['enable_block_navigator'] );
        $sanitized['enable_quick_insert'] = ! empty( $input['enable_quick_insert'] );
        $sanitized['highlight_current_block'] = ! empty( $input['highlight_current_block'] );
        $sanitized['show_block_handles'] = ! empty( $input['show_block_handles'] );
        $sanitized['smooth_scrolling'] = ! empty( $input['smooth_scrolling'] );
        $sanitized['auto_save_interval'] = intval( $input['auto_save_interval'] ?? 60 );
        $sanitized['enable_keyboard_shortcuts'] = ! empty( $input['enable_keyboard_shortcuts'] );
        $sanitized['show_block_breadcrumbs'] = ! empty( $input['show_block_breadcrumbs'] );

        // Writing enhancements
        $sanitized['typewriter_mode'] = ! empty( $input['typewriter_mode'] );
        $sanitized['reading_time'] = ! empty( $input['reading_time'] );
        $sanitized['paragraph_count'] = ! empty( $input['paragraph_count'] );
        $sanitized['heading_anchors'] = ! empty( $input['heading_anchors'] );

        // Block defaults
        $sanitized['default_paragraph_font_size'] = sanitize_text_field( $input['default_paragraph_font_size'] ?? '' );
        $sanitized['default_heading_level'] = intval( $input['default_heading_level'] ?? 2 );

        return $sanitized;
    }

    /**
     * Sanitize frontend settings
     */
    public function sanitize_frontend_settings( $input ) {
        $sanitized = [];

        $sanitized['remove_block_classes'] = isset( $input['remove_block_classes'] )
            ? array_map( 'sanitize_text_field', (array) $input['remove_block_classes'] )
            : [];

        $sanitized['custom_classes_to_remove'] = isset( $input['custom_classes_to_remove'] )
            ? sanitize_textarea_field( $input['custom_classes_to_remove'] )
            : '';

        $sanitized['remove_wp_block_library'] = ! empty( $input['remove_wp_block_library'] );
        $sanitized['remove_global_styles'] = ! empty( $input['remove_global_styles'] );
        $sanitized['remove_duotone_svg'] = ! empty( $input['remove_duotone_svg'] );
        $sanitized['lazy_load_images'] = ! empty( $input['lazy_load_images'] );
        $sanitized['defer_block_styles'] = ! empty( $input['defer_block_styles'] );
        $sanitized['clean_head'] = ! empty( $input['clean_head'] );
        $sanitized['remove_emoji_scripts'] = ! empty( $input['remove_emoji_scripts'] );
        $sanitized['remove_wp_embed'] = ! empty( $input['remove_wp_embed'] );

        return $sanitized;
    }

    /**
     * Admin scripts and styles
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( 'settings_page_advanced-block-editor' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-codemirror' );
        wp_enqueue_script( 'wp-codemirror' );
        wp_enqueue_script( 'csslint' );
        wp_enqueue_script( 'jshint' );

        // CodeMirror modes
        wp_enqueue_script( 'codemirror-mode-css', includes_url( 'js/codemirror/mode/css/css.min.js' ), [ 'wp-codemirror' ], ABE_VERSION, true );
        wp_enqueue_script( 'codemirror-mode-javascript', includes_url( 'js/codemirror/mode/javascript/javascript.min.js' ), [ 'wp-codemirror' ], ABE_VERSION, true );

        wp_enqueue_media();

        wp_enqueue_style(
            'abe-admin',
            ABE_PLUGIN_URL . 'assets/admin/css/admin.css',
            [],
            ABE_VERSION
        );

        wp_enqueue_script(
            'abe-admin',
            ABE_PLUGIN_URL . 'assets/admin/js/admin.js',
            [ 'jquery', 'wp-codemirror' ],
            ABE_VERSION,
            true
        );
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets() {
        // Main editor enhancement script
        wp_enqueue_script(
            'abe-editor',
            ABE_PLUGIN_URL . 'assets/editor/js/editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-plugins', 'wp-i18n', 'wp-compose', 'wp-dom-ready' ],
            ABE_VERSION,
            true
        );

        // Calculate reading time for current post
        $reading_time = 0;
        $word_count = 0;
        $post = get_post();
        if ( $post && $post->post_content ) {
            $word_count = str_word_count( strip_tags( $post->post_content ) );
            $reading_time = max( 1, ceil( $word_count / 200 ) );
        }

        // Pass settings to JavaScript
        wp_localize_script( 'abe-editor', 'abeSettings', [
            'editorWidth'           => $this->get_setting( 'editor_width' ),
            'editorWidthUnit'       => $this->get_setting( 'editor_width_unit' ),
            'wordCountEnabled'      => $this->get_setting( 'word_count_enabled' ),
            'wordCountPosition'     => $this->get_setting( 'word_count_position' ),
            'disableFullscreen'     => $this->get_setting( 'disable_fullscreen' ),
            'focusMode'             => $this->get_setting( 'focus_mode' ),
            'typewriterMode'        => $this->get_setting( 'typewriter_mode' ),
            'readingTime'           => $this->get_setting( 'reading_time' ),
            'paragraphCount'        => $this->get_setting( 'paragraph_count' ),
            'enableCopyAll'         => $this->get_setting( 'enable_copy_all' ),
            'enableBlockNavigator'  => $this->get_setting( 'enable_block_navigator' ),
            'highlightCurrentBlock' => $this->get_setting( 'highlight_current_block' ),
            'showBlockBreadcrumbs'  => $this->get_setting( 'show_block_breadcrumbs' ),
            'smoothScrolling'       => $this->get_setting( 'smooth_scrolling' ),
            'currentReadingTime'    => $reading_time,
            'currentWordCount'      => $word_count,
            'i18n'                  => [
                'characters'    => __( 'characters', 'advanced-block-editor' ),
                'words'         => __( 'words', 'advanced-block-editor' ),
                'paragraphs'    => __( 'paragraphs', 'advanced-block-editor' ),
                'readingTime'   => __( 'min read', 'advanced-block-editor' ),
                'editorWidth'   => __( 'Editor Width', 'advanced-block-editor' ),
                'copyAll'       => __( 'Copy All Content', 'advanced-block-editor' ),
                'copied'        => __( 'Copied!', 'advanced-block-editor' ),
            ],
        ] );

        // Base editor styles
        wp_enqueue_style(
            'abe-editor',
            ABE_PLUGIN_URL . 'assets/editor/css/editor.css',
            [],
            ABE_VERSION
        );

        // Custom CSS file
        $css_file = $this->get_setting( 'custom_css_file' );
        if ( ! empty( $css_file ) ) {
            wp_enqueue_style(
                'abe-custom-css-file',
                $css_file,
                [ 'abe-editor' ],
                ABE_VERSION
            );
        }

        // Inline custom CSS
        $inline_css = $this->get_setting( 'custom_css_inline' );
        if ( ! empty( $inline_css ) ) {
            wp_add_inline_style( 'abe-editor', $inline_css );
        }

        // Custom JS file
        $js_file = $this->get_setting( 'custom_js_file' );
        if ( ! empty( $js_file ) ) {
            wp_enqueue_script(
                'abe-custom-js-file',
                $js_file,
                [ 'abe-editor' ],
                ABE_VERSION,
                true
            );
        }

        // Inline custom JS
        $inline_js = $this->get_setting( 'custom_js_inline' );
        if ( ! empty( $inline_js ) ) {
            wp_add_inline_script( 'abe-editor', $inline_js, 'after' );
        }
    }

    /**
     * Add reading time before content
     */
    public function add_reading_time( $content ) {
        if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        if ( ! $this->get_setting( 'reading_time' ) ) {
            return $content;
        }

        $word_count = str_word_count( strip_tags( $content ) );
        $reading_time = max( 1, ceil( $word_count / 200 ) );

        $reading_html = sprintf(
            '<p class="abe-reading-time"><span class="abe-reading-time__icon">&#128337;</span> %s</p>',
            sprintf(
                /* translators: %d: number of minutes */
                _n( '%d min read', '%d min read', $reading_time, 'advanced-block-editor' ),
                $reading_time
            )
        );

        return $reading_html . $content;
    }

    /**
     * Add anchors to headings
     */
    public function add_heading_anchors( $content ) {
        if ( ! is_singular() || ! $this->get_setting( 'heading_anchors' ) ) {
            return $content;
        }

        // Add IDs to headings that don't have them
        $content = preg_replace_callback(
            '/<h([2-6])([^>]*)>(.*?)<\/h\1>/is',
            function( $matches ) {
                $level = $matches[1];
                $attrs = $matches[2];
                $text = $matches[3];

                // Check if already has an ID
                if ( preg_match( '/\sid=["\']/', $attrs ) ) {
                    return $matches[0];
                }

                // Generate ID from text
                $id = sanitize_title( strip_tags( $text ) );

                return sprintf(
                    '<h%s%s id="%s">%s</h%s>',
                    $level,
                    $attrs,
                    esc_attr( $id ),
                    $text,
                    $level
                );
            },
            $content
        );

        return $content;
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=advanced-block-editor' ),
            __( 'Settings', 'advanced-block-editor' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs = [
            'general'   => __( 'General', 'advanced-block-editor' ),
            'editor'    => __( 'Editor Enhancements', 'advanced-block-editor' ),
            'css'       => __( 'Custom CSS', 'advanced-block-editor' ),
            'js'        => __( 'Custom JS', 'advanced-block-editor' ),
            'patterns'  => __( 'Patterns', 'advanced-block-editor' ),
            'per-block' => __( 'Per-Block Code', 'advanced-block-editor' ),
            'frontend'  => __( 'Frontend Cleanup', 'advanced-block-editor' ),
        ];
        ?>
        <div class="wrap abe-settings-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?> <span class="abe-version">v<?php echo esc_html( ABE_VERSION ); ?></span></h1>

            <nav class="nav-tab-wrapper abe-nav-tabs">
                <?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
                    <a href="?page=advanced-block-editor&tab=<?php echo esc_attr( $tab_id ); ?>"
                       class="nav-tab <?php echo $tab_id === $active_tab ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_name ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ( in_array( $active_tab, [ 'patterns', 'per-block' ], true ) ) : ?>
                <?php $this->render_info_tab( $active_tab ); ?>
            <?php else : ?>
                <form method="post" action="options.php" class="abe-settings-form">
                    <?php
                    if ( $active_tab === 'frontend' ) {
                        settings_fields( 'abe_frontend_settings_group' );
                    } else {
                        settings_fields( 'abe_settings_group' );
                    }

                    switch ( $active_tab ) {
                        case 'general':
                            $this->render_general_tab();
                            break;
                        case 'editor':
                            $this->render_editor_tab();
                            break;
                        case 'css':
                            $this->render_css_tab();
                            break;
                        case 'js':
                            $this->render_js_tab();
                            break;
                        case 'frontend':
                            $this->render_frontend_tab();
                            break;
                    }
                    ?>
                    <?php submit_button(); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render General settings tab
     */
    private function render_general_tab() {
        ?>
        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Editor Width', 'advanced-block-editor' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Set a custom width for the block editor content area.', 'advanced-block-editor' ); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="abe_editor_width"><?php esc_html_e( 'Width', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <input type="number"
                               id="abe_editor_width"
                               name="abe_settings[editor_width]"
                               value="<?php echo esc_attr( $this->get_setting( 'editor_width' ) ); ?>"
                               class="small-text"
                               min="0"
                               step="1">
                        <select name="abe_settings[editor_width_unit]" id="abe_editor_width_unit">
                            <option value="px" <?php selected( $this->get_setting( 'editor_width_unit' ), 'px' ); ?>>px</option>
                            <option value="%" <?php selected( $this->get_setting( 'editor_width_unit' ), '%' ); ?>>%</option>
                            <option value="vw" <?php selected( $this->get_setting( 'editor_width_unit' ), 'vw' ); ?>>vw</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Leave empty to use default width.', 'advanced-block-editor' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Word Count', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Word Count', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[word_count_enabled]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'word_count_enabled' ) ); ?>>
                            <?php esc_html_e( 'Show word/character count in the editor', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="abe_word_count_position"><?php esc_html_e( 'Position', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <select name="abe_settings[word_count_position]" id="abe_word_count_position">
                            <option value="top" <?php selected( $this->get_setting( 'word_count_position' ), 'top' ); ?>>
                                <?php esc_html_e( 'Top of editor', 'advanced-block-editor' ); ?>
                            </option>
                            <option value="bottom" <?php selected( $this->get_setting( 'word_count_position' ), 'bottom' ); ?>>
                                <?php esc_html_e( 'Bottom of editor', 'advanced-block-editor' ); ?>
                            </option>
                            <option value="statusbar" <?php selected( $this->get_setting( 'word_count_position' ), 'statusbar' ); ?>>
                                <?php esc_html_e( 'Status bar (floating)', 'advanced-block-editor' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Writing Features', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Reading Time', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[reading_time]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'reading_time' ) ); ?>>
                            <?php esc_html_e( 'Show estimated reading time on frontend', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Paragraph Count', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[paragraph_count]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'paragraph_count' ) ); ?>>
                            <?php esc_html_e( 'Show paragraph count in editor stats', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Heading Anchors', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[heading_anchors]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'heading_anchors' ) ); ?>>
                            <?php esc_html_e( 'Auto-generate anchor IDs for headings', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Editor Behavior', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Fullscreen Mode', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[disable_fullscreen]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'disable_fullscreen' ) ); ?>>
                            <?php esc_html_e( 'Disable fullscreen mode by default', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Focus Mode', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[focus_mode]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'focus_mode' ) ); ?>>
                            <?php esc_html_e( 'Enable focus mode (highlights only active block)', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Typewriter Mode', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[typewriter_mode]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'typewriter_mode' ) ); ?>>
                            <?php esc_html_e( 'Keep cursor centered while typing', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render Editor Enhancements tab
     */
    private function render_editor_tab() {
        ?>
        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Block Navigation', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block Navigator', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[enable_block_navigator]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'enable_block_navigator' ) ); ?>>
                            <?php esc_html_e( 'Enable quick block navigator in sidebar', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block Breadcrumbs', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[show_block_breadcrumbs]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'show_block_breadcrumbs' ) ); ?>>
                            <?php esc_html_e( 'Show breadcrumb path for nested blocks', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Smooth Scrolling', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[smooth_scrolling]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'smooth_scrolling' ) ); ?>>
                            <?php esc_html_e( 'Enable smooth scrolling in editor', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Visual Enhancements', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Highlight Current Block', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[highlight_current_block]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'highlight_current_block' ) ); ?>>
                            <?php esc_html_e( 'Add visual highlight to selected block', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block Handles', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[show_block_handles]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'show_block_handles' ) ); ?>>
                            <?php esc_html_e( 'Show enhanced drag handles on blocks', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Productivity', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Copy All Content', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[enable_copy_all]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'enable_copy_all' ) ); ?>>
                            <?php esc_html_e( 'Add "Copy All Content" button in More Options', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Keyboard Shortcuts', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_settings[enable_keyboard_shortcuts]"
                                   value="1"
                                   <?php checked( $this->get_setting( 'enable_keyboard_shortcuts' ) ); ?>>
                            <?php esc_html_e( 'Enable additional keyboard shortcuts', 'advanced-block-editor' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Ctrl+Shift+D: Duplicate block, Ctrl+Shift+Z: Redo, etc.', 'advanced-block-editor' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render CSS settings tab
     */
    private function render_css_tab() {
        ?>
        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Custom CSS for Block Editor', 'advanced-block-editor' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Add custom CSS that will only be loaded in the Block Editor. This will NOT affect your frontend.', 'advanced-block-editor' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="abe_custom_css_inline"><?php esc_html_e( 'Inline CSS', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <textarea id="abe_custom_css_inline"
                                  name="abe_settings[custom_css_inline]"
                                  rows="15"
                                  class="large-text code abe-codemirror-css"
                                  placeholder="/* Your custom editor CSS here */"><?php echo esc_textarea( $this->get_setting( 'custom_css_inline' ) ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Example: Change block background, typography, spacing, etc.', 'advanced-block-editor' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="abe_custom_css_file"><?php esc_html_e( 'CSS File URL', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <input type="url"
                               id="abe_custom_css_file"
                               name="abe_settings[custom_css_file]"
                               value="<?php echo esc_url( $this->get_setting( 'custom_css_file' ) ); ?>"
                               class="regular-text"
                               placeholder="https://example.com/editor-styles.css">
                        <button type="button" class="button abe-media-upload" data-target="abe_custom_css_file">
                            <?php esc_html_e( 'Upload CSS File', 'advanced-block-editor' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Upload or enter the URL of an external CSS file.', 'advanced-block-editor' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section abe-css-reference">
            <h3><?php esc_html_e( 'Useful CSS Selectors', 'advanced-block-editor' ); ?></h3>
            <div class="abe-code-examples">
                <code>.editor-styles-wrapper</code> - <?php esc_html_e( 'Main editor content area', 'advanced-block-editor' ); ?><br>
                <code>.wp-block</code> - <?php esc_html_e( 'Individual blocks', 'advanced-block-editor' ); ?><br>
                <code>.edit-post-visual-editor</code> - <?php esc_html_e( 'Visual editor container', 'advanced-block-editor' ); ?><br>
                <code>.block-editor-block-list__layout</code> - <?php esc_html_e( 'Block list container', 'advanced-block-editor' ); ?><br>
                <code>.is-root-container</code> - <?php esc_html_e( 'Root content container', 'advanced-block-editor' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render JS settings tab
     */
    private function render_js_tab() {
        ?>
        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Custom JavaScript for Block Editor', 'advanced-block-editor' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Add custom JavaScript that will only run in the Block Editor. This will NOT affect your frontend.', 'advanced-block-editor' ); ?>
            </p>

            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e( 'Caution:', 'advanced-block-editor' ); ?></strong>
                    <?php esc_html_e( 'Custom JavaScript runs with full privileges. Only add code from trusted sources.', 'advanced-block-editor' ); ?>
                </p>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="abe_custom_js_inline"><?php esc_html_e( 'Inline JavaScript', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <textarea id="abe_custom_js_inline"
                                  name="abe_settings[custom_js_inline]"
                                  rows="15"
                                  class="large-text code abe-codemirror-js"
                                  placeholder="// Your custom editor JavaScript here"><?php echo esc_textarea( $this->get_setting( 'custom_js_inline' ) ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Access WordPress editor APIs via wp.data, wp.blocks, wp.element, etc.', 'advanced-block-editor' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="abe_custom_js_file"><?php esc_html_e( 'JavaScript File URL', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <input type="url"
                               id="abe_custom_js_file"
                               name="abe_settings[custom_js_file]"
                               value="<?php echo esc_url( $this->get_setting( 'custom_js_file' ) ); ?>"
                               class="regular-text"
                               placeholder="https://example.com/editor-scripts.js">
                        <button type="button" class="button abe-media-upload" data-target="abe_custom_js_file">
                            <?php esc_html_e( 'Upload JS File', 'advanced-block-editor' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'Upload or enter the URL of an external JavaScript file.', 'advanced-block-editor' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section abe-js-reference">
            <h3><?php esc_html_e( 'Available WordPress APIs', 'advanced-block-editor' ); ?></h3>
            <div class="abe-code-examples">
                <code>wp.data.select('core/editor')</code> - <?php esc_html_e( 'Access editor state', 'advanced-block-editor' ); ?><br>
                <code>wp.data.dispatch('core/editor')</code> - <?php esc_html_e( 'Modify editor state', 'advanced-block-editor' ); ?><br>
                <code>wp.blocks.registerBlockType()</code> - <?php esc_html_e( 'Register custom blocks', 'advanced-block-editor' ); ?><br>
                <code>wp.plugins.registerPlugin()</code> - <?php esc_html_e( 'Register editor plugins', 'advanced-block-editor' ); ?><br>
                <code>wp.hooks.addFilter()</code> - <?php esc_html_e( 'Modify block behavior', 'advanced-block-editor' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Frontend Cleanup tab
     */
    private function render_frontend_tab() {
        $frontend = ABE_Frontend_Cleanup::get_instance();
        $settings = $frontend->get_settings();
        $block_classes = $frontend->get_block_classes();
        ?>
        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Remove Block Classes', 'advanced-block-editor' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Select block classes to remove from frontend HTML. Useful for theme compatibility or cleaner markup.', 'advanced-block-editor' ); ?>
            </p>

            <div class="abe-checkbox-grid">
                <?php foreach ( $block_classes as $class => $label ) : ?>
                    <label class="abe-checkbox-item">
                        <input type="checkbox"
                               name="abe_frontend_settings[remove_block_classes][]"
                               value="<?php echo esc_attr( $class ); ?>"
                               <?php checked( in_array( $class, $settings['remove_block_classes'] ?? [], true ) ); ?>>
                        <span><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <table class="form-table" style="margin-top: 20px;">
                <tr>
                    <th scope="row">
                        <label for="abe_custom_classes"><?php esc_html_e( 'Custom Classes to Remove', 'advanced-block-editor' ); ?></label>
                    </th>
                    <td>
                        <textarea id="abe_custom_classes"
                                  name="abe_frontend_settings[custom_classes_to_remove]"
                                  rows="4"
                                  class="regular-text"
                                  placeholder="my-custom-class&#10;another-class"><?php echo esc_textarea( $settings['custom_classes_to_remove'] ?? '' ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Enter one class name per line (without the dot).', 'advanced-block-editor' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="abe-settings-section">
            <h2><?php esc_html_e( 'Performance Optimizations', 'advanced-block-editor' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block Library CSS', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_frontend_settings[remove_wp_block_library]"
                                   value="1"
                                   <?php checked( ! empty( $settings['remove_wp_block_library'] ) ); ?>>
                            <?php esc_html_e( 'Remove wp-block-library CSS (only if your theme provides block styles)', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Global Styles', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_frontend_settings[remove_global_styles]"
                                   value="1"
                                   <?php checked( ! empty( $settings['remove_global_styles'] ) ); ?>>
                            <?php esc_html_e( 'Remove global-styles CSS', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Duotone SVG', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_frontend_settings[remove_duotone_svg]"
                                   value="1"
                                   <?php checked( ! empty( $settings['remove_duotone_svg'] ) ); ?>>
                            <?php esc_html_e( 'Remove duotone SVG filters from page', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Emoji Scripts', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_frontend_settings[remove_emoji_scripts]"
                                   value="1"
                                   <?php checked( ! empty( $settings['remove_emoji_scripts'] ) ); ?>>
                            <?php esc_html_e( 'Remove WordPress emoji scripts and styles', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'WP Embed', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_frontend_settings[remove_wp_embed]"
                                   value="1"
                                   <?php checked( ! empty( $settings['remove_wp_embed'] ) ); ?>>
                            <?php esc_html_e( 'Remove wp-embed script', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Clean Head', 'advanced-block-editor' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="abe_frontend_settings[clean_head]"
                                   value="1"
                                   <?php checked( ! empty( $settings['clean_head'] ) ); ?>>
                            <?php esc_html_e( 'Remove RSD, wlwmanifest, shortlink, and other unnecessary head tags', 'advanced-block-editor' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render info tab for Patterns and Per-Block Code
     */
    private function render_info_tab( $tab ) {
        ?>
        <div class="abe-settings-form">
            <div class="abe-settings-section">
                <?php if ( $tab === 'patterns' ) : ?>
                    <h2><?php esc_html_e( 'Block Patterns', 'advanced-block-editor' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Create and manage custom block patterns that can be inserted via the block inserter or shortcodes.', 'advanced-block-editor' ); ?>
                    </p>

                    <div class="abe-info-box">
                        <h3><?php esc_html_e( 'How to Use Patterns', 'advanced-block-editor' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Open the Block Editor on any post or page', 'advanced-block-editor' ); ?></li>
                            <li><?php esc_html_e( 'Click the "Patterns" icon in the sidebar (or go to More Options > Patterns Manager)', 'advanced-block-editor' ); ?></li>
                            <li><?php esc_html_e( 'Create a new pattern with your desired blocks', 'advanced-block-editor' ); ?></li>
                            <li><?php esc_html_e( 'Use the pattern via the block inserter or with a shortcode', 'advanced-block-editor' ); ?></li>
                        </ol>

                        <h4><?php esc_html_e( 'Shortcode Usage', 'advanced-block-editor' ); ?></h4>
                        <p><?php esc_html_e( 'Insert patterns anywhere using:', 'advanced-block-editor' ); ?></p>
                        <code>[abe_pattern slug="your-pattern-slug"]</code>

                        <p style="margin-top: 20px;">
                            <a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="button button-primary">
                                <?php esc_html_e( 'Open Editor to Manage Patterns', 'advanced-block-editor' ); ?>
                            </a>
                        </p>
                    </div>

                    <?php
                    $patterns = get_option( 'abe_patterns', [] );
                    if ( ! empty( $patterns ) ) :
                    ?>
                        <h3 style="margin-top: 30px;"><?php esc_html_e( 'Your Patterns', 'advanced-block-editor' ); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Title', 'advanced-block-editor' ); ?></th>
                                    <th><?php esc_html_e( 'Slug', 'advanced-block-editor' ); ?></th>
                                    <th><?php esc_html_e( 'Shortcode', 'advanced-block-editor' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'advanced-block-editor' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $patterns as $pattern ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $pattern['title'] ); ?></strong></td>
                                        <td><code><?php echo esc_html( $pattern['slug'] ); ?></code></td>
                                        <td><code>[abe_pattern slug="<?php echo esc_attr( $pattern['slug'] ); ?>"]</code></td>
                                        <td>
                                            <?php if ( ! empty( $pattern['enabled'] ) ) : ?>
                                                <span class="abe-status-active"><?php esc_html_e( 'Active', 'advanced-block-editor' ); ?></span>
                                            <?php else : ?>
                                                <span class="abe-status-inactive"><?php esc_html_e( 'Inactive', 'advanced-block-editor' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php elseif ( $tab === 'per-block' ) : ?>
                    <h2><?php esc_html_e( 'Per-Block CSS/JS', 'advanced-block-editor' ); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Add custom CSS and JavaScript that only loads when specific blocks are used on a page.', 'advanced-block-editor' ); ?>
                    </p>

                    <div class="abe-info-box">
                        <h3><?php esc_html_e( 'How It Works', 'advanced-block-editor' ); ?></h3>
                        <ul>
                            <li><?php esc_html_e( 'Create rules for specific block types (Heading, Image, Gallery, etc.)', 'advanced-block-editor' ); ?></li>
                            <li><?php esc_html_e( 'CSS/JS only loads when that block type is present on the page', 'advanced-block-editor' ); ?></li>
                            <li><?php esc_html_e( 'Apply styles to editor, frontend, or both', 'advanced-block-editor' ); ?></li>
                            <li><?php esc_html_e( 'Improves performance by avoiding unnecessary code', 'advanced-block-editor' ); ?></li>
                        </ul>

                        <p style="margin-top: 20px;">
                            <a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="button button-primary">
                                <?php esc_html_e( 'Open Editor to Manage Block Rules', 'advanced-block-editor' ); ?>
                            </a>
                        </p>
                    </div>

                    <?php
                    $rules = get_option( 'abe_per_block_rules', [] );
                    if ( ! empty( $rules ) ) :
                    ?>
                        <h3 style="margin-top: 30px;"><?php esc_html_e( 'Your Block Rules', 'advanced-block-editor' ); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Name', 'advanced-block-editor' ); ?></th>
                                    <th><?php esc_html_e( 'Block Type', 'advanced-block-editor' ); ?></th>
                                    <th><?php esc_html_e( 'Scope', 'advanced-block-editor' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'advanced-block-editor' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $rules as $rule ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
                                        <td><code><?php echo esc_html( $rule['block_type'] ); ?></code></td>
                                        <td><?php echo esc_html( implode( ', ', $rule['scope'] ?? [] ) ); ?></td>
                                        <td>
                                            <?php if ( ! empty( $rule['enabled'] ) ) : ?>
                                                <span class="abe-status-active"><?php esc_html_e( 'Active', 'advanced-block-editor' ); ?></span>
                                            <?php else : ?>
                                                <span class="abe-status-inactive"><?php esc_html_e( 'Inactive', 'advanced-block-editor' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

// Include additional classes
require_once ABE_PLUGIN_DIR . 'includes/class-block-variations.php';
require_once ABE_PLUGIN_DIR . 'includes/class-custom-code.php';
require_once ABE_PLUGIN_DIR . 'includes/class-patterns-manager.php';
require_once ABE_PLUGIN_DIR . 'includes/class-per-block-code.php';
require_once ABE_PLUGIN_DIR . 'includes/class-frontend-cleanup.php';

// Initialize plugin
function abe_init() {
    // Initialize main plugin
    $plugin = Advanced_Block_Editor::get_instance();

    // Initialize block variations
    ABE_Block_Variations::get_instance();

    // Initialize custom code manager
    ABE_Custom_Code::get_instance();

    // Initialize patterns manager
    ABE_Patterns_Manager::get_instance();

    // Initialize per-block code
    ABE_Per_Block_Code::get_instance();

    // Initialize frontend cleanup
    ABE_Frontend_Cleanup::get_instance();

    return $plugin;
}
add_action( 'plugins_loaded', 'abe_init' );

// Activation hook
register_activation_hook( __FILE__, function() {
    // Set default options
    if ( ! get_option( 'abe_settings' ) ) {
        update_option( 'abe_settings', [
            'word_count_enabled' => true,
            'word_count_position' => 'top',
            'reading_time' => true,
            'enable_copy_all' => true,
            'enable_block_navigator' => true,
            'smooth_scrolling' => true,
        ] );
    }

    // Set default frontend settings
    if ( ! get_option( 'abe_frontend_settings' ) ) {
        update_option( 'abe_frontend_settings', [
            'remove_block_classes' => [],
            'remove_emoji_scripts' => false,
        ] );
    }
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // Clean up transients
    delete_transient( 'abe_patterns_cache' );
} );
