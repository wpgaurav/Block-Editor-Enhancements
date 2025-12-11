/**
 * Advanced Block Editor - Patterns Manager
 * Allows creating and managing custom block patterns with shortcode support
 */

(function () {
    'use strict';

    const { createElement: el, useState, useEffect, useCallback, Fragment } = wp.element;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const {
        PanelBody, Button, TextControl, TextareaControl, ToggleControl,
        SelectControl, Modal, Card, CardBody, CardHeader, Notice,
        CheckboxControl, Spinner
    } = wp.components;
    const { useSelect } = wp.data;
    const { __ } = wp.i18n;
    const { serialize } = wp.blocks;

    // Get data from localized script
    const patternsData = window.abePatternsData || {};
    const { patterns: initialPatterns, nonce, ajaxUrl, categories, coreCategories } = patternsData;

    /**
     * Pattern Card Component
     */
    function PatternCard({ pattern, onEdit, onDelete, onToggle, onDuplicate }) {
        const [deleting, setDeleting] = useState(false);

        const handleDelete = async () => {
            if (!confirm(__('Are you sure you want to delete this pattern?', 'advanced-block-editor'))) {
                return;
            }

            setDeleting(true);
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_delete_pattern',
                        nonce: nonce,
                        id: pattern.id
                    })
                });
                const data = await response.json();
                if (data.success) {
                    onDelete(pattern.id);
                }
            } catch (error) {
                console.error('Delete error:', error);
            }
            setDeleting(false);
        };

        return el(Card, { className: 'abe-pattern-card', size: 'small' },
            el(CardBody, null,
                el('div', { className: 'abe-pattern-card__header' },
                    el('strong', null, pattern.title),
                    el('span', {
                        className: `abe-status-badge ${pattern.enabled ? 'active' : 'inactive'}`
                    }, pattern.enabled ? __('Active', 'advanced-block-editor') : __('Inactive', 'advanced-block-editor'))
                ),
                pattern.description && el('p', {
                    className: 'abe-pattern-card__description'
                }, pattern.description),
                el('div', { className: 'abe-pattern-card__meta' },
                    el('code', { className: 'abe-pattern-card__shortcode' },
                        `[abe_pattern slug="${pattern.slug}"]`
                    )
                ),
                el('div', { className: 'abe-pattern-card__actions' },
                    el(Button, {
                        variant: 'secondary',
                        size: 'small',
                        onClick: () => onEdit(pattern),
                        disabled: deleting
                    }, __('Edit', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        size: 'small',
                        onClick: () => onDuplicate(pattern.id),
                        disabled: deleting
                    }, __('Duplicate', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        size: 'small',
                        onClick: () => onToggle(pattern.id),
                        disabled: deleting
                    }, pattern.enabled ? __('Disable', 'advanced-block-editor') : __('Enable', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        size: 'small',
                        isDestructive: true,
                        onClick: handleDelete,
                        disabled: deleting
                    }, deleting ? el(Spinner) : __('Delete', 'advanced-block-editor'))
                )
            )
        );
    }

    /**
     * Pattern Editor Modal
     */
    function PatternEditorModal({ pattern, onSave, onClose }) {
        const [formData, setFormData] = useState({
            id: pattern?.id || '',
            slug: pattern?.slug || '',
            title: pattern?.title || '',
            description: pattern?.description || '',
            content: pattern?.content || '',
            categories: pattern?.categories || [],
            keywords: pattern?.keywords?.join(', ') || '',
            viewport_width: pattern?.viewport_width || 1200,
            enabled: pattern?.enabled !== false
        });
        const [saving, setSaving] = useState(false);
        const [error, setError] = useState(null);
        const [useCurrentBlocks, setUseCurrentBlocks] = useState(false);

        // Get current blocks from editor
        const blocks = useSelect((select) => {
            return select('core/block-editor').getBlocks();
        }, []);

        const handleChange = (key, value) => {
            setFormData(prev => ({ ...prev, [key]: value }));
        };

        const handleCategoryToggle = (category) => {
            setFormData(prev => {
                const cats = [...prev.categories];
                const index = cats.indexOf(category);
                if (index > -1) {
                    cats.splice(index, 1);
                } else {
                    cats.push(category);
                }
                return { ...prev, categories: cats };
            });
        };

        const handleUseCurrentBlocks = () => {
            if (blocks && blocks.length > 0) {
                const content = serialize(blocks);
                setFormData(prev => ({ ...prev, content }));
            }
        };

        const handleSubmit = async () => {
            if (!formData.title || !formData.content) {
                setError(__('Title and content are required.', 'advanced-block-editor'));
                return;
            }

            setSaving(true);
            setError(null);

            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_save_pattern',
                        nonce: nonce,
                        ...formData,
                        categories: JSON.stringify(formData.categories),
                        keywords: formData.keywords
                    })
                });
                const data = await response.json();

                if (data.success) {
                    onSave(data.data.pattern);
                    onClose();
                } else {
                    setError(data.data?.message || __('Failed to save pattern.', 'advanced-block-editor'));
                }
            } catch (err) {
                setError(__('An error occurred while saving.', 'advanced-block-editor'));
            }

            setSaving(false);
        };

        const allCategories = { ...categories, ...coreCategories };

        return el(Modal, {
            title: pattern ? __('Edit Pattern', 'advanced-block-editor') : __('Create Pattern', 'advanced-block-editor'),
            onRequestClose: onClose,
            className: 'abe-pattern-modal'
        },
            error && el(Notice, { status: 'error', isDismissible: false }, error),

            el('div', { className: 'abe-pattern-form' },
                el(TextControl, {
                    label: __('Title', 'advanced-block-editor'),
                    value: formData.title,
                    onChange: (value) => handleChange('title', value),
                    required: true
                }),
                el(TextControl, {
                    label: __('Slug', 'advanced-block-editor'),
                    value: formData.slug,
                    onChange: (value) => handleChange('slug', value),
                    help: __('Used in shortcode. Auto-generated if empty.', 'advanced-block-editor')
                }),
                el(TextareaControl, {
                    label: __('Description', 'advanced-block-editor'),
                    value: formData.description,
                    onChange: (value) => handleChange('description', value),
                    rows: 2
                }),
                el('div', { className: 'abe-pattern-form__content' },
                    el('label', null, __('Block Content', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        onClick: handleUseCurrentBlocks,
                        style: { marginBottom: '8px' },
                        disabled: !blocks || blocks.length === 0
                    }, __('Use Current Editor Blocks', 'advanced-block-editor')),
                    el(TextareaControl, {
                        value: formData.content,
                        onChange: (value) => handleChange('content', value),
                        rows: 8,
                        placeholder: '<!-- wp:paragraph -->\n<p>Your block content...</p>\n<!-- /wp:paragraph -->'
                    })
                ),
                el('div', { className: 'abe-pattern-form__categories' },
                    el('label', null, __('Categories', 'advanced-block-editor')),
                    el('div', { className: 'abe-checkbox-grid' },
                        Object.entries(allCategories).map(([key, label]) =>
                            el(CheckboxControl, {
                                key: key,
                                label: label,
                                checked: formData.categories.includes(key),
                                onChange: () => handleCategoryToggle(key)
                            })
                        )
                    )
                ),
                el(TextControl, {
                    label: __('Keywords', 'advanced-block-editor'),
                    value: formData.keywords,
                    onChange: (value) => handleChange('keywords', value),
                    help: __('Comma-separated search keywords.', 'advanced-block-editor')
                }),
                el(ToggleControl, {
                    label: __('Enabled', 'advanced-block-editor'),
                    checked: formData.enabled,
                    onChange: (value) => handleChange('enabled', value)
                }),
                el('div', { className: 'abe-pattern-form__actions' },
                    el(Button, {
                        variant: 'secondary',
                        onClick: onClose,
                        disabled: saving
                    }, __('Cancel', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'primary',
                        onClick: handleSubmit,
                        disabled: saving
                    }, saving ? el(Spinner) : __('Save Pattern', 'advanced-block-editor'))
                )
            )
        );
    }

    /**
     * Main Patterns Manager Panel
     */
    function PatternsManagerPanel() {
        const [patterns, setPatterns] = useState(initialPatterns || {});
        const [editingPattern, setEditingPattern] = useState(null);
        const [showEditor, setShowEditor] = useState(false);

        const patternsList = Object.values(patterns);

        const handleSave = (savedPattern) => {
            setPatterns(prev => ({
                ...prev,
                [savedPattern.id]: savedPattern
            }));
        };

        const handleDelete = (patternId) => {
            setPatterns(prev => {
                const updated = { ...prev };
                delete updated[patternId];
                return updated;
            });
        };

        const handleToggle = async (patternId) => {
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_toggle_pattern',
                        nonce: nonce,
                        id: patternId
                    })
                });
                const data = await response.json();
                if (data.success) {
                    setPatterns(prev => ({
                        ...prev,
                        [patternId]: {
                            ...prev[patternId],
                            enabled: data.data.enabled
                        }
                    }));
                }
            } catch (error) {
                console.error('Toggle error:', error);
            }
        };

        const handleDuplicate = async (patternId) => {
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_duplicate_pattern',
                        nonce: nonce,
                        id: patternId
                    })
                });
                const data = await response.json();
                if (data.success) {
                    setPatterns(prev => ({
                        ...prev,
                        [data.data.pattern.id]: data.data.pattern
                    }));
                }
            } catch (error) {
                console.error('Duplicate error:', error);
            }
        };

        const handleEdit = (pattern) => {
            setEditingPattern(pattern);
            setShowEditor(true);
        };

        const handleCreate = () => {
            setEditingPattern(null);
            setShowEditor(true);
        };

        const handleCloseEditor = () => {
            setShowEditor(false);
            setEditingPattern(null);
        };

        // Pattern icon
        const patternIcon = el('svg', {
            width: 24,
            height: 24,
            viewBox: '0 0 24 24'
        },
            el('path', {
                d: 'M4 5v14h16V5H4zm14 12H6V7h12v10zm-5-6H8v2h5v-2zm3 0h-2v2h2v-2zm-3-3H8v2h5V8zm3 0h-2v2h2V8z'
            })
        );

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, {
                target: 'abe-patterns-panel',
                icon: patternIcon
            }, __('Patterns Manager', 'advanced-block-editor')),
            el(PluginSidebar, {
                name: 'abe-patterns-panel',
                icon: patternIcon,
                title: __('Patterns Manager', 'advanced-block-editor')
            },
                el(PanelBody, { title: __('Custom Patterns', 'advanced-block-editor'), initialOpen: true },
                    el(Button, {
                        variant: 'primary',
                        onClick: handleCreate,
                        style: { width: '100%', marginBottom: '16px' }
                    }, __('Create New Pattern', 'advanced-block-editor')),

                    patternsList.length === 0
                        ? el('div', { className: 'abe-no-patterns' },
                            el('p', null, __('No patterns yet.', 'advanced-block-editor')),
                            el('p', null, __('Create your first pattern to get started!', 'advanced-block-editor'))
                        )
                        : el('div', { className: 'abe-patterns-list' },
                            patternsList.map(pattern =>
                                el(PatternCard, {
                                    key: pattern.id,
                                    pattern: pattern,
                                    onEdit: handleEdit,
                                    onDelete: handleDelete,
                                    onToggle: handleToggle,
                                    onDuplicate: handleDuplicate
                                })
                            )
                        )
                ),
                el(PanelBody, { title: __('Usage', 'advanced-block-editor'), initialOpen: false },
                    el('p', null, __('Patterns appear in the block inserter under "Block Editor+ Patterns".', 'advanced-block-editor')),
                    el('p', null, __('You can also insert patterns using shortcodes:', 'advanced-block-editor')),
                    el('code', { style: { display: 'block', marginTop: '8px' } }, '[abe_pattern slug="your-pattern"]')
                )
            ),
            showEditor && el(PatternEditorModal, {
                pattern: editingPattern,
                onSave: handleSave,
                onClose: handleCloseEditor
            })
        );
    }

    // Register the plugin
    wp.domReady(() => {
        registerPlugin('abe-patterns-manager', {
            render: PatternsManagerPanel,
            icon: null
        });
    });

})();
