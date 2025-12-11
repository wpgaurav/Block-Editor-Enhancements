/**
 * Advanced Block Editor - Editor JavaScript
 * Enhances the WordPress Block Editor with custom features
 */

(function () {
    'use strict';

    const { createElement: el, useState, useEffect, useCallback, Fragment } = wp.element;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, RangeControl, SelectControl, ToggleControl, Button } = wp.components;
    const { useSelect, useDispatch, subscribe } = wp.data;
    const { __ } = wp.i18n;
    const { count: wordCount } = wp.wordcount;

    // Get settings from localized data
    const settings = window.abeSettings || {};

    /**
     * Word Count Component
     */
    function WordCountDisplay() {
        const [counts, setCounts] = useState({ words: 0, characters: 0 });

        const content = useSelect((select) => {
            const blocks = select('core/block-editor').getBlocks();
            return getBlocksText(blocks);
        }, []);

        useEffect(() => {
            if (content) {
                const words = wordCount(content, 'words');
                const characters = content.replace(/\s/g, '').length;
                setCounts({ words, characters });
            }
        }, [content]);

        if (!settings.wordCountEnabled) {
            return null;
        }

        const position = settings.wordCountPosition || 'top';
        const className = `abe-word-count abe-word-count--${position}`;

        return el('div', { className },
            el('div', { className: 'abe-word-count__item' },
                el('span', { className: 'abe-word-count__value' }, counts.words),
                el('span', { className: 'abe-word-count__label' }, settings.i18n?.words || 'words')
            ),
            el('div', { className: 'abe-word-count__separator' }),
            el('div', { className: 'abe-word-count__item' },
                el('span', { className: 'abe-word-count__value' }, counts.characters),
                el('span', { className: 'abe-word-count__label' }, settings.i18n?.characters || 'characters')
            )
        );
    }

    /**
     * Extract text content from blocks recursively
     */
    function getBlocksText(blocks) {
        let text = '';

        blocks.forEach(block => {
            // Get text from block attributes
            if (block.attributes) {
                Object.values(block.attributes).forEach(value => {
                    if (typeof value === 'string') {
                        // Strip HTML tags
                        const strippedText = value.replace(/<[^>]*>/g, ' ');
                        text += strippedText + ' ';
                    }
                });
            }

            // Recursively get text from inner blocks
            if (block.innerBlocks && block.innerBlocks.length) {
                text += getBlocksText(block.innerBlocks);
            }
        });

        return text;
    }

    /**
     * Editor Width Controller
     */
    function EditorWidthControl() {
        const [width, setWidth] = useState(parseInt(settings.editorWidth) || 0);
        const [unit, setUnit] = useState(settings.editorWidthUnit || 'px');

        const applyWidth = useCallback((newWidth, newUnit) => {
            const editor = document.querySelector('.edit-post-visual-editor');
            const wrapper = document.querySelector('.editor-styles-wrapper');

            if (newWidth > 0) {
                document.body.classList.add('abe-custom-width');
                document.documentElement.style.setProperty('--abe-editor-width', `${newWidth}${newUnit}`);
            } else {
                document.body.classList.remove('abe-custom-width');
                document.documentElement.style.removeProperty('--abe-editor-width');
            }
        }, []);

        useEffect(() => {
            applyWidth(width, unit);
        }, [width, unit, applyWidth]);

        const getMaxValue = () => {
            switch (unit) {
                case '%': return 100;
                case 'vw': return 100;
                default: return 2000;
            }
        };

        return el(Fragment, null,
            el(RangeControl, {
                label: settings.i18n?.editorWidth || __('Editor Width', 'advanced-block-editor'),
                value: width,
                onChange: setWidth,
                min: 0,
                max: getMaxValue(),
                step: unit === 'px' ? 10 : 1,
                allowReset: true,
                resetFallbackValue: 0
            }),
            el(SelectControl, {
                label: __('Unit', 'advanced-block-editor'),
                value: unit,
                options: [
                    { label: 'Pixels (px)', value: 'px' },
                    { label: 'Percentage (%)', value: '%' },
                    { label: 'Viewport Width (vw)', value: 'vw' }
                ],
                onChange: (newUnit) => {
                    setUnit(newUnit);
                    // Reset to a sensible default when changing units
                    if (newUnit === 'px' && width <= 100) {
                        setWidth(800);
                    } else if (newUnit !== 'px' && width > 100) {
                        setWidth(80);
                    }
                }
            }),
            width > 0 && el('p', {
                className: 'components-base-control__help',
                style: { marginTop: '-8px' }
            }, `Current: ${width}${unit}`)
        );
    }

    /**
     * Main Settings Panel Plugin
     */
    function AdvancedBlockEditorPlugin() {
        const [focusMode, setFocusMode] = useState(settings.focusMode || false);

        // Apply focus mode
        useEffect(() => {
            if (focusMode) {
                document.body.classList.add('abe-focus-mode');
            } else {
                document.body.classList.remove('abe-focus-mode');
            }
        }, [focusMode]);

        // Plugin icon
        const icon = el('svg', {
            width: 24,
            height: 24,
            viewBox: '0 0 24 24',
            className: 'abe-panel-icon'
        },
            el('path', {
                d: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14H6v-2h6v2zm4-4H6v-2h10v2zm0-4H6V7h10v2z'
            })
        );

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, {
                target: 'abe-settings-panel',
                icon: icon
            }, __('Advanced Editor', 'advanced-block-editor')),
            el(PluginSidebar, {
                name: 'abe-settings-panel',
                icon: icon,
                title: __('Advanced Editor', 'advanced-block-editor')
            },
                el(PanelBody, {
                    title: __('Editor Width', 'advanced-block-editor'),
                    initialOpen: true
                },
                    el(EditorWidthControl)
                ),
                el(PanelBody, {
                    title: __('Display Options', 'advanced-block-editor'),
                    initialOpen: false
                },
                    el(ToggleControl, {
                        label: __('Focus Mode', 'advanced-block-editor'),
                        help: __('Dim non-selected blocks to focus on current block.', 'advanced-block-editor'),
                        checked: focusMode,
                        onChange: setFocusMode
                    })
                )
            )
        );
    }

    /**
     * Initialize Word Count Display
     */
    function initWordCount() {
        if (!settings.wordCountEnabled) {
            return;
        }

        // Wait for editor to be ready
        const unsubscribe = subscribe(() => {
            const editorWrapper = document.querySelector('.edit-post-visual-editor');

            if (editorWrapper) {
                unsubscribe();

                // Create word count container
                const wordCountContainer = document.createElement('div');
                wordCountContainer.id = 'abe-word-count-root';

                const position = settings.wordCountPosition || 'top';

                if (position === 'top') {
                    editorWrapper.insertBefore(wordCountContainer, editorWrapper.firstChild);
                } else {
                    editorWrapper.appendChild(wordCountContainer);
                }

                // Render React component
                if (wp.element.createRoot) {
                    // WordPress 6.2+
                    const root = wp.element.createRoot(wordCountContainer);
                    root.render(el(WordCountDisplay));
                } else {
                    // Older versions
                    wp.element.render(el(WordCountDisplay), wordCountContainer);
                }
            }
        });
    }

    /**
     * Disable fullscreen mode if configured
     */
    function maybeDisableFullscreen() {
        if (!settings.disableFullscreen) {
            return;
        }

        const unsubscribe = subscribe(() => {
            const isFullscreen = useSelect ?
                wp.data.select('core/edit-post')?.isFeatureActive('fullscreenMode') :
                false;

            if (isFullscreen) {
                wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
                unsubscribe();
            }
        });

        // Also check immediately
        setTimeout(() => {
            const isFullscreen = wp.data.select('core/edit-post')?.isFeatureActive('fullscreenMode');
            if (isFullscreen) {
                wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
            }
        }, 100);
    }

    /**
     * Apply initial editor width from settings
     */
    function applyInitialEditorWidth() {
        if (!settings.editorWidth) {
            return;
        }

        const unsubscribe = subscribe(() => {
            const editor = document.querySelector('.edit-post-visual-editor');

            if (editor) {
                unsubscribe();

                const width = parseInt(settings.editorWidth);
                const unit = settings.editorWidthUnit || 'px';

                if (width > 0) {
                    document.body.classList.add('abe-custom-width');
                    document.documentElement.style.setProperty('--abe-editor-width', `${width}${unit}`);
                }
            }
        });
    }

    /**
     * Apply initial focus mode from settings
     */
    function applyInitialFocusMode() {
        if (settings.focusMode) {
            document.body.classList.add('abe-focus-mode');
        }
    }

    /**
     * Initialize everything when DOM is ready
     */
    function init() {
        // Register the sidebar plugin
        registerPlugin('advanced-block-editor', {
            render: AdvancedBlockEditorPlugin,
            icon: null
        });

        // Initialize features
        initWordCount();
        maybeDisableFullscreen();
        applyInitialEditorWidth();
        applyInitialFocusMode();

        console.log('Advanced Block Editor initialized');
    }

    // Wait for WordPress to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
