<?php
/**
 * Plugin Name: Advanced Block Editor
 * Plugin URI: https://developer.developer.developer
 * Description: Enhances the WordPress Block Editor with custom CSS/JS injection, editor width control, word count, and more - without affecting the frontend.
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Developer Developer Developer
 * Author URI: https://developer.developer.developer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-block-editor
 * Domain Path: /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'ABE_VERSION', '3.0.0' );
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
            'editor_width'        => '',
            'editor_width_unit'   => 'px',
            'word_count_enabled'  => true,
            'word_count_position' => 'top',
            'custom_css_inline'   => '',
            'custom_css_file'     => '',
            'custom_js_inline'    => '',
            'custom_js_file'      => '',
            'disable_fullscreen'  => false,
            'focus_mode'          => false,
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
     * Initialize hooks
     */
    private function init_hooks() {
        // Load textdomain
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Admin hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

        // Block Editor hooks - ONLY in editor context
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );

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
        $sanitized['word_count_position'] = isset( $input['word_count_position'] ) && in_array( $input['word_count_position'], [ 'top', 'bottom' ], true )
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
            ? $input['custom_js_inline'] // JS needs special handling, we'll sanitize on output
            : '';
        
        $sanitized['custom_js_file'] = isset( $input['custom_js_file'] ) 
            ? esc_url_raw( $input['custom_js_file'] ) 
            : '';

        // Editor Options
        $sanitized['disable_fullscreen'] = ! empty( $input['disable_fullscreen'] );
        $sanitized['focus_mode'] = ! empty( $input['focus_mode'] );

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
     * Enqueue editor assets - THIS ONLY RUNS IN THE BLOCK EDITOR
     */
    public function enqueue_editor_assets() {
        // Main editor enhancement script
        wp_enqueue_script(
            'abe-editor',
            ABE_PLUGIN_URL . 'assets/editor/js/editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-plugins', 'wp-i18n', 'wp-compose' ],
            ABE_VERSION,
            true
        );

        // Pass settings to JavaScript
        wp_localize_script( 'abe-editor', 'abeSettings', [
            'editorWidth'       => $this->get_setting( 'editor_width' ),
            'editorWidthUnit'   => $this->get_setting( 'editor_width_unit' ),
            'wordCountEnabled'  => $this->get_setting( 'word_count_enabled' ),
            'wordCountPosition' => $this->get_setting( 'word_count_position' ),
            'disableFullscreen' => $this->get_setting( 'disable_fullscreen' ),
            'focusMode'         => $this->get_setting( 'focus_mode' ),
            'i18n'              => [
                'characters' => __( 'characters', 'advanced-block-editor' ),
                'words'      => __( 'words', 'advanced-block-editor' ),
                'editorWidth' => __( 'Editor Width', 'advanced-block-editor' ),
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
        ?>
        <div class="wrap abe-settings-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <nav class="nav-tab-wrapper abe-nav-tabs">
                <a href="?page=advanced-block-editor&tab=general" 
                   class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'advanced-block-editor' ); ?>
                </a>
                <a href="?page=advanced-block-editor&tab=css" 
                   class="nav-tab <?php echo 'css' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Custom CSS', 'advanced-block-editor' ); ?>
                </a>
                <a href="?page=advanced-block-editor&tab=js" 
                   class="nav-tab <?php echo 'js' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Custom JS', 'advanced-block-editor' ); ?>
                </a>
            </nav>

            <form method="post" action="options.php" class="abe-settings-form">
                <?php settings_fields( 'abe_settings_group' ); ?>
                
                <?php if ( 'general' === $active_tab ) : ?>
                    <?php $this->render_general_tab(); ?>
                <?php elseif ( 'css' === $active_tab ) : ?>
                    <?php $this->render_css_tab(); ?>
                <?php elseif ( 'js' === $active_tab ) : ?>
                    <?php $this->render_js_tab(); ?>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
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
                        </select>
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
}

// Include additional classes
require_once ABE_PLUGIN_DIR . 'includes/class-block-variations.php';
require_once ABE_PLUGIN_DIR . 'includes/class-custom-code.php';

// Initialize plugin
function abe_init() {
    // Initialize main plugin
    $plugin = Advanced_Block_Editor::get_instance();
    
    // Initialize block variations
    ABE_Block_Variations::get_instance();
    
    // Initialize custom code manager
    ABE_Custom_Code::get_instance();
    
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
        ] );
    }
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function() {
    // Clean up if needed
} );
