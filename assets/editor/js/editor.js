/**
 * Advanced Block Editor - Editor JavaScript
 * Enhances the WordPress Block Editor with custom features
 */

(function () {
    'use strict';

    const { createElement: el, useState, useEffect, useCallback, useRef, Fragment } = wp.element;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem, PluginMoreMenuItem } = wp.editPost;
    const { PanelBody, RangeControl, SelectControl, ToggleControl, Button, Tooltip, Icon } = wp.components;
    const { useSelect, useDispatch, subscribe } = wp.data;
    const { __ } = wp.i18n;
    const { count: wordCount } = wp.wordcount;
    const { serialize } = wp.blocks;

    // Get settings from localized data
    const settings = window.abeSettings || {};

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
     * Count paragraphs in blocks
     */
    function countParagraphs(blocks) {
        let count = 0;

        blocks.forEach(block => {
            if (block.name === 'core/paragraph' && block.attributes.content) {
                count++;
            }
            if (block.innerBlocks && block.innerBlocks.length) {
                count += countParagraphs(block.innerBlocks);
            }
        });

        return count;
    }

    /**
     * Word Count Component with enhanced stats
     */
    function WordCountDisplay() {
        const [counts, setCounts] = useState({ words: 0, characters: 0, paragraphs: 0, readingTime: 1 });
        const containerRef = useRef(null);

        const blocks = useSelect((select) => {
            return select('core/block-editor').getBlocks();
        }, []);

        useEffect(() => {
            if (blocks && blocks.length > 0) {
                const content = getBlocksText(blocks);
                const words = wordCount(content, 'words');
                const characters = content.replace(/\s/g, '').length;
                const paragraphs = countParagraphs(blocks);
                const readingTime = Math.max(1, Math.ceil(words / 200));
                setCounts({ words, characters, paragraphs, readingTime });
            } else {
                setCounts({ words: 0, characters: 0, paragraphs: 0, readingTime: 1 });
            }
        }, [blocks]);

        if (!settings.wordCountEnabled) {
            return null;
        }

        const position = settings.wordCountPosition || 'top';

        // Status bar style (floating)
        if (position === 'statusbar') {
            return el('div', {
                className: 'abe-word-count abe-word-count--statusbar',
                ref: containerRef
            },
                el('div', { className: 'abe-word-count__item' },
                    el('span', { className: 'abe-word-count__value' }, counts.words),
                    el('span', { className: 'abe-word-count__label' }, settings.i18n?.words || 'words')
                ),
                el('div', { className: 'abe-word-count__separator' }),
                el('div', { className: 'abe-word-count__item' },
                    el('span', { className: 'abe-word-count__value' }, counts.characters),
                    el('span', { className: 'abe-word-count__label' }, settings.i18n?.characters || 'characters')
                ),
                settings.paragraphCount && el(Fragment, null,
                    el('div', { className: 'abe-word-count__separator' }),
                    el('div', { className: 'abe-word-count__item' },
                        el('span', { className: 'abe-word-count__value' }, counts.paragraphs),
                        el('span', { className: 'abe-word-count__label' }, settings.i18n?.paragraphs || 'paragraphs')
                    )
                ),
                el('div', { className: 'abe-word-count__separator' }),
                el('div', { className: 'abe-word-count__item abe-word-count__reading-time' },
                    el('span', { className: 'abe-word-count__value' }, counts.readingTime),
                    el('span', { className: 'abe-word-count__label' }, settings.i18n?.readingTime || 'min read')
                )
            );
        }

        // Top/Bottom style
        const className = `abe-word-count abe-word-count--${position}`;

        return el('div', { className, ref: containerRef },
            el('div', { className: 'abe-word-count__item' },
                el('span', { className: 'abe-word-count__value' }, counts.words),
                el('span', { className: 'abe-word-count__label' }, settings.i18n?.words || 'words')
            ),
            el('div', { className: 'abe-word-count__separator' }),
            el('div', { className: 'abe-word-count__item' },
                el('span', { className: 'abe-word-count__value' }, counts.characters),
                el('span', { className: 'abe-word-count__label' }, settings.i18n?.characters || 'characters')
            ),
            settings.paragraphCount && el(Fragment, null,
                el('div', { className: 'abe-word-count__separator' }),
                el('div', { className: 'abe-word-count__item' },
                    el('span', { className: 'abe-word-count__value' }, counts.paragraphs),
                    el('span', { className: 'abe-word-count__label' }, settings.i18n?.paragraphs || 'paragraphs')
                )
            ),
            el('div', { className: 'abe-word-count__separator' }),
            el('div', { className: 'abe-word-count__item abe-word-count__reading-time' },
                el('span', { className: 'abe-word-count__value' }, counts.readingTime),
                el('span', { className: 'abe-word-count__label' }, settings.i18n?.readingTime || 'min read')
            )
        );
    }

    /**
     * Editor Width Controller
     */
    function EditorWidthControl() {
        const [width, setWidth] = useState(parseInt(settings.editorWidth) || 0);
        const [unit, setUnit] = useState(settings.editorWidthUnit || 'px');

        const applyWidth = useCallback((newWidth, newUnit) => {
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
     * Copy All Content Button
     */
    function CopyAllContentButton() {
        const [copied, setCopied] = useState(false);

        const blocks = useSelect((select) => {
            return select('core/block-editor').getBlocks();
        }, []);

        const handleCopy = useCallback(() => {
            if (!blocks || blocks.length === 0) return;

            const content = serialize(blocks);

            navigator.clipboard.writeText(content).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }, [blocks]);

        if (!settings.enableCopyAll) {
            return null;
        }

        return el(PluginMoreMenuItem, {
            icon: copied ? 'yes' : 'clipboard',
            onClick: handleCopy
        }, copied ? (settings.i18n?.copied || __('Copied!', 'advanced-block-editor')) : (settings.i18n?.copyAll || __('Copy All Content', 'advanced-block-editor')));
    }

    /**
     * Main Settings Panel Plugin
     */
    function AdvancedBlockEditorPlugin() {
        const [focusMode, setFocusMode] = useState(settings.focusMode || false);
        const [typewriterMode, setTypewriterMode] = useState(settings.typewriterMode || false);

        // Apply focus mode
        useEffect(() => {
            if (focusMode) {
                document.body.classList.add('abe-focus-mode');
            } else {
                document.body.classList.remove('abe-focus-mode');
            }
        }, [focusMode]);

        // Apply typewriter mode
        useEffect(() => {
            if (typewriterMode) {
                document.body.classList.add('abe-typewriter-mode');
            } else {
                document.body.classList.remove('abe-typewriter-mode');
            }
        }, [typewriterMode]);

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
            }, __('Block Editor+', 'advanced-block-editor')),
            el(PluginSidebar, {
                name: 'abe-settings-panel',
                icon: icon,
                title: __('Block Editor+', 'advanced-block-editor')
            },
                el(PanelBody, {
                    title: __('Editor Width', 'advanced-block-editor'),
                    initialOpen: true
                },
                    el(EditorWidthControl)
                ),
                el(PanelBody, {
                    title: __('Writing Mode', 'advanced-block-editor'),
                    initialOpen: false
                },
                    el(ToggleControl, {
                        label: __('Focus Mode', 'advanced-block-editor'),
                        help: __('Dim non-selected blocks to focus on current block.', 'advanced-block-editor'),
                        checked: focusMode,
                        onChange: setFocusMode
                    }),
                    el(ToggleControl, {
                        label: __('Typewriter Mode', 'advanced-block-editor'),
                        help: __('Keep the cursor centered while typing.', 'advanced-block-editor'),
                        checked: typewriterMode,
                        onChange: setTypewriterMode
                    })
                ),
                el(PanelBody, {
                    title: __('Quick Actions', 'advanced-block-editor'),
                    initialOpen: false
                },
                    el(Button, {
                        variant: 'secondary',
                        onClick: () => {
                            const blocks = wp.data.select('core/block-editor').getBlocks();
                            if (blocks.length) {
                                const content = serialize(blocks);
                                navigator.clipboard.writeText(content);
                            }
                        },
                        style: { marginBottom: '8px', width: '100%' }
                    }, __('Copy All Blocks', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        onClick: () => {
                            wp.data.dispatch('core/editor').undo();
                        },
                        style: { marginBottom: '8px', width: '100%' }
                    }, __('Undo Last Change', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        onClick: () => {
                            const selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                            if (selectedBlock) {
                                wp.data.dispatch('core/block-editor').duplicateBlocks([selectedBlock.clientId]);
                            }
                        },
                        style: { width: '100%' }
                    }, __('Duplicate Selected Block', 'advanced-block-editor'))
                )
            ),
            el(CopyAllContentButton)
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
            const editorWrapper = document.querySelector('.editor-styles-wrapper');
            const visualEditor = document.querySelector('.edit-post-visual-editor');

            if (editorWrapper && visualEditor) {
                unsubscribe();

                // Check if already initialized
                if (document.getElementById('abe-word-count-root')) {
                    return;
                }

                // Create word count container
                const wordCountContainer = document.createElement('div');
                wordCountContainer.id = 'abe-word-count-root';

                const position = settings.wordCountPosition || 'top';

                if (position === 'statusbar') {
                    // Floating status bar
                    visualEditor.appendChild(wordCountContainer);
                } else if (position === 'top') {
                    // Insert at top of editor wrapper
                    editorWrapper.insertBefore(wordCountContainer, editorWrapper.firstChild);
                } else {
                    // Insert at bottom
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

        // Wait for editor to be fully loaded
        wp.domReady(() => {
            setTimeout(() => {
                const isFullscreen = wp.data.select('core/edit-post')?.isFeatureActive('fullscreenMode');
                if (isFullscreen) {
                    wp.data.dispatch('core/edit-post').toggleFeature('fullscreenMode');
                }
            }, 100);
        });
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
     * Apply typewriter mode (keep cursor centered)
     */
    function initTypewriterMode() {
        if (!settings.typewriterMode) {
            return;
        }

        document.body.classList.add('abe-typewriter-mode');

        // Subscribe to selection changes
        let lastClientId = null;

        subscribe(() => {
            if (!document.body.classList.contains('abe-typewriter-mode')) {
                return;
            }

            const selectedClientId = wp.data.select('core/block-editor').getSelectedBlockClientId();

            if (selectedClientId && selectedClientId !== lastClientId) {
                lastClientId = selectedClientId;

                // Find the selected block element and scroll it into view
                setTimeout(() => {
                    const blockElement = document.querySelector(`[data-block="${selectedClientId}"]`);
                    if (blockElement) {
                        const editorContainer = document.querySelector('.interface-interface-skeleton__content');
                        if (editorContainer) {
                            const containerRect = editorContainer.getBoundingClientRect();
                            const blockRect = blockElement.getBoundingClientRect();
                            const centerOffset = (containerRect.height / 2) - (blockRect.height / 2);

                            editorContainer.scrollTo({
                                top: editorContainer.scrollTop + blockRect.top - containerRect.top - centerOffset,
                                behavior: 'smooth'
                            });
                        }
                    }
                }, 50);
            }
        });
    }

    /**
     * Apply smooth scrolling
     */
    function initSmoothScrolling() {
        if (!settings.smoothScrolling) {
            return;
        }

        const unsubscribe = subscribe(() => {
            const editorContent = document.querySelector('.interface-interface-skeleton__content');
            if (editorContent) {
                unsubscribe();
                editorContent.style.scrollBehavior = 'smooth';
            }
        });
    }

    /**
     * Enhanced block highlighting
     */
    function initBlockHighlighting() {
        if (!settings.highlightCurrentBlock) {
            return;
        }

        document.body.classList.add('abe-highlight-blocks');
    }

    /**
     * Keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        if (!settings.enableKeyboardShortcuts) {
            return;
        }

        document.addEventListener('keydown', (e) => {
            // Ctrl+Shift+D: Duplicate block
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                e.preventDefault();
                const selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                if (selectedBlock) {
                    wp.data.dispatch('core/block-editor').duplicateBlocks([selectedBlock.clientId]);
                }
            }

            // Ctrl+Shift+Backspace: Remove block
            if (e.ctrlKey && e.shiftKey && e.key === 'Backspace') {
                e.preventDefault();
                const selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                if (selectedBlock) {
                    wp.data.dispatch('core/block-editor').removeBlocks([selectedBlock.clientId]);
                }
            }

            // Ctrl+Shift+Up: Move block up
            if (e.ctrlKey && e.shiftKey && e.key === 'ArrowUp') {
                e.preventDefault();
                const selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                if (selectedBlock) {
                    wp.data.dispatch('core/block-editor').moveBlocksUp([selectedBlock.clientId]);
                }
            }

            // Ctrl+Shift+Down: Move block down
            if (e.ctrlKey && e.shiftKey && e.key === 'ArrowDown') {
                e.preventDefault();
                const selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
                if (selectedBlock) {
                    wp.data.dispatch('core/block-editor').moveBlocksDown([selectedBlock.clientId]);
                }
            }
        });
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
        initTypewriterMode();
        initSmoothScrolling();
        initBlockHighlighting();
        initKeyboardShortcuts();

        console.log('Advanced Block Editor v4.0 initialized');
    }

    // Wait for WordPress to be ready
    wp.domReady(() => {
        init();
    });

})();
