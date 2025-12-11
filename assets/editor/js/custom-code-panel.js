/**
 * Custom Code Panel - Editor Script
 * Provides an in-editor interface for managing CSS/JS snippets
 */

(function () {
    'use strict';

    const { createElement: el, useState, useEffect, useRef, Fragment } = wp.element;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const {
        PanelBody, Button, TextControl, SelectControl,
        CheckboxControl, Modal, ToggleControl, TabPanel,
        Card, CardBody, Flex, FlexItem, Notice, Tooltip,
        __experimentalInputControl as InputControl
    } = wp.components;
    const { __ } = wp.i18n;

    const codeData = window.abeCustomCode || {};
    const { snippets: initialSnippets = {}, nonce, ajaxUrl, cssSelectors = {} } = codeData;

    /**
     * Simple Code Editor Component
     */
    function CodeEditor({ value, onChange, language, placeholder }) {
        const textareaRef = useRef(null);
        const [localValue, setLocalValue] = useState(value);

        useEffect(() => {
            setLocalValue(value);
        }, [value]);

        const handleChange = (e) => {
            setLocalValue(e.target.value);
            onChange(e.target.value);
        };

        const handleKeyDown = (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = e.target.selectionStart;
                const end = e.target.selectionEnd;
                const newValue = localValue.substring(0, start) + '    ' + localValue.substring(end);
                setLocalValue(newValue);
                onChange(newValue);
                setTimeout(() => {
                    e.target.selectionStart = e.target.selectionEnd = start + 4;
                }, 0);
            }
        };

        return el('div', { className: 'abe-code-editor' },
            el('div', { className: 'abe-code-editor__header' },
                el('span', { className: 'abe-code-editor__language' }, language.toUpperCase())
            ),
            el('textarea', {
                ref: textareaRef,
                className: 'abe-code-editor__textarea',
                value: localValue,
                onChange: handleChange,
                onKeyDown: handleKeyDown,
                placeholder: placeholder || (language === 'css'
                    ? '/* Enter your CSS here */\n.my-class {\n    color: red;\n}'
                    : '// Enter your JavaScript here\nconsole.log("Hello!");'),
                spellCheck: false,
            })
        );
    }

    /**
     * Fullscreen Code Editor Modal
     */
    function FullscreenEditor({ snippet, onSave, onClose }) {
        const [formData, setFormData] = useState({
            ...snippet,
            code: snippet.code || '',
        });
        const [saving, setSaving] = useState(false);

        const handleSave = async () => {
            setSaving(true);
            await onSave(formData);
            setSaving(false);
        };

        return el(Modal, {
            title: formData.name || __('Edit Snippet', 'advanced-block-editor'),
            onRequestClose: onClose,
            className: 'abe-fullscreen-editor-modal',
            isFullScreen: true,
        },
            el('div', { className: 'abe-fullscreen-editor' },
                el('div', { className: 'abe-fullscreen-editor__sidebar' },
                    el(TextControl, {
                        label: __('Snippet Name', 'advanced-block-editor'),
                        value: formData.name,
                        onChange: (val) => setFormData(prev => ({ ...prev, name: val })),
                    }),
                    el(SelectControl, {
                        label: __('Type', 'advanced-block-editor'),
                        value: formData.type,
                        options: [
                            { label: 'CSS', value: 'css' },
                            { label: 'JavaScript', value: 'js' },
                        ],
                        onChange: (val) => setFormData(prev => ({ ...prev, type: val })),
                    }),
                    el('div', { className: 'abe-scope-section' },
                        el('label', { className: 'components-base-control__label' },
                            __('Execute In', 'advanced-block-editor')
                        ),
                        el(CheckboxControl, {
                            label: __('Block Editor (Backend)', 'advanced-block-editor'),
                            checked: formData.scope?.includes('editor'),
                            onChange: (val) => {
                                const scope = val
                                    ? [...(formData.scope || []), 'editor']
                                    : (formData.scope || []).filter(s => s !== 'editor');
                                setFormData(prev => ({ ...prev, scope }));
                            },
                        }),
                        el(CheckboxControl, {
                            label: __('Frontend (Website)', 'advanced-block-editor'),
                            checked: formData.scope?.includes('frontend'),
                            onChange: (val) => {
                                const scope = val
                                    ? [...(formData.scope || []), 'frontend']
                                    : (formData.scope || []).filter(s => s !== 'frontend');
                                setFormData(prev => ({ ...prev, scope }));
                            },
                        })
                    ),
                    el(ToggleControl, {
                        label: __('Enabled', 'advanced-block-editor'),
                        checked: formData.enabled,
                        onChange: (val) => setFormData(prev => ({ ...prev, enabled: val })),
                    }),
                    el('div', { className: 'abe-selector-reference' },
                        el('h4', {}, __('CSS Selectors Reference', 'advanced-block-editor')),
                        Object.entries(cssSelectors).map(([category, selectors]) =>
                            el('div', { key: category, className: 'abe-selector-category' },
                                el('h5', {}, category.charAt(0).toUpperCase() + category.slice(1)),
                                Object.entries(selectors).map(([selector, desc]) =>
                                    el(Tooltip, { key: selector, text: desc },
                                        el('code', {
                                            className: 'abe-selector-item',
                                            onClick: () => {
                                                const newCode = formData.code + '\n' + selector + ' {\n    \n}';
                                                setFormData(prev => ({ ...prev, code: newCode }));
                                            }
                                        }, selector)
                                    )
                                )
                            )
                        )
                    )
                ),
                el('div', { className: 'abe-fullscreen-editor__main' },
                    el(CodeEditor, {
                        value: formData.code,
                        onChange: (code) => setFormData(prev => ({ ...prev, code })),
                        language: formData.type,
                    })
                ),
                el('div', { className: 'abe-fullscreen-editor__footer' },
                    el(Button, { variant: 'tertiary', onClick: onClose },
                        __('Cancel', 'advanced-block-editor')
                    ),
                    el(Button, {
                        variant: 'primary',
                        onClick: handleSave,
                        isBusy: saving,
                        disabled: saving,
                    }, saving ? __('Saving...', 'advanced-block-editor') : __('Save Snippet', 'advanced-block-editor'))
                )
            )
        );
    }

    /**
     * Snippet Card Component
     */
    function SnippetCard({ snippet, onEdit, onToggle, onDelete }) {
        const [toggling, setToggling] = useState(false);

        const handleToggle = async () => {
            setToggling(true);
            await onToggle(snippet.id);
            setToggling(false);
        };

        return el(Card, { className: 'abe-snippet-card', size: 'small' },
            el(CardBody, {},
                el(Flex, { align: 'center', gap: 3 },
                    el(FlexItem, {},
                        el('span', {
                            className: `abe-snippet-type abe-snippet-type--${snippet.type}`
                        }, snippet.type.toUpperCase())
                    ),
                    el(FlexItem, { isBlock: true },
                        el('strong', {}, snippet.name),
                        el('div', { className: 'abe-snippet-scope' },
                            (snippet.scope || []).map(s =>
                                el('span', { key: s, className: 'abe-scope-badge' },
                                    s === 'editor' ? '📝 Editor' : '🌐 Frontend'
                                )
                            )
                        )
                    ),
                    el(FlexItem, {},
                        el(ToggleControl, {
                            checked: snippet.enabled,
                            onChange: handleToggle,
                            disabled: toggling,
                            __nextHasNoMarginBottom: true,
                        })
                    ),
                    el(FlexItem, {},
                        el(Button, {
                            icon: 'fullscreen-alt',
                            label: __('Edit Fullscreen', 'advanced-block-editor'),
                            onClick: () => onEdit(snippet),
                            size: 'small',
                        })
                    ),
                    el(FlexItem, {},
                        el(Button, {
                            icon: 'trash',
                            label: __('Delete', 'advanced-block-editor'),
                            onClick: () => onDelete(snippet.id),
                            isDestructive: true,
                            size: 'small',
                        })
                    )
                )
            )
        );
    }

    /**
     * Main Custom Code Panel
     */
    function CustomCodePanel() {
        const [snippets, setSnippets] = useState(initialSnippets);
        const [editingSnippet, setEditingSnippet] = useState(null);
        const [notice, setNotice] = useState(null);

        const createNewSnippet = (type) => {
            setEditingSnippet({
                id: '',
                name: type === 'css' ? __('New CSS Snippet', 'advanced-block-editor') : __('New JS Snippet', 'advanced-block-editor'),
                type: type,
                code: '',
                scope: ['editor'],
                enabled: true,
                priority: 10,
            });
        };

        const saveSnippet = async (snippetData) => {
            const data = new FormData();
            data.append('action', 'abe_save_snippet');
            data.append('nonce', nonce);
            data.append('id', snippetData.id);
            data.append('name', snippetData.name);
            data.append('type', snippetData.type);
            data.append('code', snippetData.code);
            data.append('enabled', snippetData.enabled ? '1' : '');
            data.append('priority', snippetData.priority || 10);
            (snippetData.scope || []).forEach(s => data.append('scope[]', s));

            try {
                const response = await fetch(ajaxUrl, { method: 'POST', body: data });
                const result = await response.json();

                if (result.success) {
                    setSnippets(prev => ({
                        ...prev,
                        [result.data.snippet.id]: result.data.snippet,
                    }));
                    setEditingSnippet(null);
                    setNotice({ type: 'success', message: __('Snippet saved! Refresh to apply changes.', 'advanced-block-editor') });
                    setTimeout(() => setNotice(null), 3000);
                } else {
                    setNotice({ type: 'error', message: result.data.message });
                }
            } catch (e) {
                setNotice({ type: 'error', message: __('Failed to save snippet.', 'advanced-block-editor') });
            }
        };

        const toggleSnippet = async (id) => {
            const data = new FormData();
            data.append('action', 'abe_toggle_snippet');
            data.append('nonce', nonce);
            data.append('id', id);

            try {
                const response = await fetch(ajaxUrl, { method: 'POST', body: data });
                const result = await response.json();

                if (result.success) {
                    setSnippets(prev => ({
                        ...prev,
                        [id]: { ...prev[id], enabled: result.data.enabled },
                    }));
                }
            } catch (e) {
                console.error('Toggle failed:', e);
            }
        };

        const deleteSnippet = async (id) => {
            if (!confirm(__('Delete this snippet?', 'advanced-block-editor'))) return;

            const data = new FormData();
            data.append('action', 'abe_delete_snippet');
            data.append('nonce', nonce);
            data.append('id', id);

            try {
                const response = await fetch(ajaxUrl, { method: 'POST', body: data });
                const result = await response.json();

                if (result.success) {
                    setSnippets(prev => {
                        const updated = { ...prev };
                        delete updated[id];
                        return updated;
                    });
                }
            } catch (e) {
                console.error('Delete failed:', e);
            }
        };

        const icon = el('svg', { width: 20, height: 20, viewBox: '0 0 24 24' },
            el('path', {
                fill: 'currentColor',
                d: 'M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z'
            })
        );

        const snippetList = Object.values(snippets);
        const cssSnippets = snippetList.filter(s => s.type === 'css');
        const jsSnippets = snippetList.filter(s => s.type === 'js');

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, { target: 'abe-custom-code-panel', icon },
                __('Custom Code', 'advanced-block-editor')
            ),
            el(PluginSidebar, {
                name: 'abe-custom-code-panel',
                icon,
                title: __('Custom Code', 'advanced-block-editor'),
            },
                notice && el(Notice, {
                    status: notice.type,
                    isDismissible: true,
                    onRemove: () => setNotice(null),
                }, notice.message),

                el(PanelBody, { title: __('Add New Snippet', 'advanced-block-editor'), initialOpen: true },
                    el(Flex, { gap: 2 },
                        el(FlexItem, { isBlock: true },
                            el(Button, {
                                variant: 'secondary',
                                onClick: () => createNewSnippet('css'),
                                style: { width: '100%' },
                            }, '+ CSS')
                        ),
                        el(FlexItem, { isBlock: true },
                            el(Button, {
                                variant: 'secondary',
                                onClick: () => createNewSnippet('js'),
                                style: { width: '100%' },
                            }, '+ JavaScript')
                        )
                    )
                ),

                cssSnippets.length > 0 && el(PanelBody, {
                    title: __('CSS Snippets', 'advanced-block-editor') + ` (${cssSnippets.length})`,
                    initialOpen: true
                },
                    cssSnippets.map(snippet =>
                        el(SnippetCard, {
                            key: snippet.id,
                            snippet,
                            onEdit: setEditingSnippet,
                            onToggle: toggleSnippet,
                            onDelete: deleteSnippet,
                        })
                    )
                ),

                jsSnippets.length > 0 && el(PanelBody, {
                    title: __('JavaScript Snippets', 'advanced-block-editor') + ` (${jsSnippets.length})`,
                    initialOpen: true
                },
                    jsSnippets.map(snippet =>
                        el(SnippetCard, {
                            key: snippet.id,
                            snippet,
                            onEdit: setEditingSnippet,
                            onToggle: toggleSnippet,
                            onDelete: deleteSnippet,
                        })
                    )
                ),

                snippetList.length === 0 && el('div', {
                    style: { padding: '20px', textAlign: 'center', color: '#757575' }
                },
                    el('p', {}, __('No code snippets yet.', 'advanced-block-editor')),
                    el('p', { className: 'description' },
                        __('Create CSS or JavaScript snippets that run in the editor or on your website frontend.', 'advanced-block-editor')
                    )
                )
            ),

            editingSnippet && el(FullscreenEditor, {
                snippet: editingSnippet,
                onSave: saveSnippet,
                onClose: () => setEditingSnippet(null),
            })
        );
    }

    // Initialize
    wp.domReady(() => {
        registerPlugin('abe-custom-code', { render: CustomCodePanel });
    });

})();
