/**
 * Advanced Block Editor - Admin Scripts
 * Handles CodeMirror initialization and media uploads
 */

(function($) {
    'use strict';

    /**
     * Initialize CodeMirror editors
     */
    function initCodeMirror() {
        // CSS Editor
        const cssTextarea = document.getElementById('abe_custom_css_inline');
        if (cssTextarea && typeof wp !== 'undefined' && wp.codeEditor) {
            wp.codeEditor.initialize(cssTextarea, {
                codemirror: {
                    mode: 'css',
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 4,
                    tabSize: 4,
                    indentWithTabs: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    lint: true,
                    gutters: ['CodeMirror-lint-markers'],
                    styleActiveLine: true,
                    extraKeys: {
                        'Ctrl-Space': 'autocomplete',
                        'Cmd-/': 'toggleComment',
                        'Ctrl-/': 'toggleComment'
                    }
                }
            });
        }

        // JavaScript Editor
        const jsTextarea = document.getElementById('abe_custom_js_inline');
        if (jsTextarea && typeof wp !== 'undefined' && wp.codeEditor) {
            wp.codeEditor.initialize(jsTextarea, {
                codemirror: {
                    mode: 'javascript',
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 4,
                    tabSize: 4,
                    indentWithTabs: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    lint: {
                        esversion: 6,
                        asi: true
                    },
                    gutters: ['CodeMirror-lint-markers'],
                    styleActiveLine: true,
                    extraKeys: {
                        'Ctrl-Space': 'autocomplete',
                        'Cmd-/': 'toggleComment',
                        'Ctrl-/': 'toggleComment'
                    }
                }
            });
        }
    }

    /**
     * Initialize Media Upload buttons
     */
    function initMediaUpload() {
        $('.abe-media-upload').on('click', function(e) {
            e.preventDefault();

            const button = $(this);
            const targetId = button.data('target');
            const targetInput = $('#' + targetId);

            if (!targetInput.length) {
                return;
            }

            // Determine file type based on target
            const isCSS = targetId.includes('css');
            const fileType = isCSS ? 'text/css' : 'application/javascript';
            const fileTypeLabel = isCSS ? 'CSS' : 'JavaScript';

            // Create media frame
            const frame = wp.media({
                title: `Select ${fileTypeLabel} File`,
                button: {
                    text: `Use this ${fileTypeLabel} file`
                },
                library: {
                    type: '' // Allow all types since CSS/JS might not be recognized
                },
                multiple: false
            });

            // When file is selected
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                targetInput.val(attachment.url);
            });

            frame.open();
        });
    }

    /**
     * Form validation and user feedback
     */
    function initFormValidation() {
        $('.abe-settings-form').on('submit', function() {
            // Basic validation - ensure URLs are valid if provided
            const cssFileInput = $('#abe_custom_css_file');
            const jsFileInput = $('#abe_custom_js_file');

            if (cssFileInput.val() && !isValidURL(cssFileInput.val())) {
                alert('Please enter a valid CSS file URL');
                cssFileInput.focus();
                return false;
            }

            if (jsFileInput.val() && !isValidURL(jsFileInput.val())) {
                alert('Please enter a valid JavaScript file URL');
                jsFileInput.focus();
                return false;
            }

            return true;
        });
    }

    /**
     * Simple URL validation
     */
    function isValidURL(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initCodeMirror();
        initMediaUpload();
        initFormValidation();
    });

})(jQuery);
