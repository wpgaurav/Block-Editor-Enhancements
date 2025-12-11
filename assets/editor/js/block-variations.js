/**
 * Block Variations Manager - Editor Script
 * Registers custom block variations in the editor
 */

(function() {
    'use strict';

    const { registerBlockVariation, unregisterBlockVariation } = wp.blocks;
    const { createElement: el, useState, useEffect, Fragment } = wp.element;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { 
        PanelBody, Button, TextControl, TextareaControl, 
        SelectControl, CheckboxControl, Modal, Spinner,
        Notice, Card, CardBody, CardHeader, Flex, FlexItem,
        ToggleControl, Icon
    } = wp.components;
    const { __ } = wp.i18n;

    const variationsData = window.abeVariations || {};
    const { variations = {}, nonce, ajaxUrl, coreBlocks = {} } = variationsData;

    // Icon options for variations
    const iconOptions = [
        { label: 'Block Default', value: 'block-default' },
        { label: 'Star Filled', value: 'star-filled' },
        { label: 'Heart', value: 'heart' },
        { label: 'Flag', value: 'flag' },
        { label: 'Layout', value: 'layout' },
        { label: 'Columns', value: 'columns' },
        { label: 'Cover Image', value: 'cover-image' },
        { label: 'Format Gallery', value: 'format-gallery' },
        { label: 'Format Image', value: 'format-image' },
        { label: 'Format Quote', value: 'format-quote' },
        { label: 'Text', value: 'text' },
        { label: 'Heading', value: 'heading' },
        { label: 'Button', value: 'button' },
        { label: 'Title', value: 'admin-customizer' },
        { label: 'Settings', value: 'admin-generic' },
    ];

    // Category options
    const categoryOptions = [
        { label: 'Text', value: 'text' },
        { label: 'Media', value: 'media' },
        { label: 'Design', value: 'design' },
        { label: 'Widgets', value: 'widgets' },
        { label: 'Theme', value: 'theme' },
        { label: 'Embed', value: 'embed' },
    ];

    /**
     * Register saved variations on load
     */
    function registerSavedVariations() {
        Object.values(variations).forEach(variation => {
            if (!variation.isActive) return;
            
            try {
                registerBlockVariation(variation.blockType, {
                    name: variation.name,
                    title: variation.title,
                    description: variation.description,
                    icon: variation.icon,
                    category: variation.category,
                    scope: variation.scope,
                    attributes: variation.attributes || {},
                    innerBlocks: variation.innerBlocks || [],
                    keywords: variation.keywords || [],
                    isActive: (blockAttributes, variationAttributes) => {
                        // Custom isActive logic if needed
                        return false;
                    }
                });
            } catch (e) {
                console.error('Failed to register variation:', variation.name, e);
            }
        });
    }

    /**
     * Variation Editor Component
     */
    function VariationEditor({ variation, onSave, onCancel }) {
        const [formData, setFormData] = useState({
            id: variation?.id || '',
            name: variation?.name || '',
            title: variation?.title || '',
            description: variation?.description || '',
            blockType: variation?.blockType || 'core/paragraph',
            icon: variation?.icon || 'block-default',
            category: variation?.category || 'text',
            scope: variation?.scope || ['inserter'],
            attributes: JSON.stringify(variation?.attributes || {}, null, 2),
            innerBlocks: JSON.stringify(variation?.innerBlocks || [], null, 2),
            isActive: variation?.isActive ?? true,
            keywords: (variation?.keywords || []).join(', '),
        });
        const [saving, setSaving] = useState(false);
        const [error, setError] = useState(null);

        const handleChange = (key, value) => {
            setFormData(prev => ({ ...prev, [key]: value }));
        };

        const handleScopeChange = (scopeType, checked) => {
            const newScope = checked 
                ? [...formData.scope, scopeType]
                : formData.scope.filter(s => s !== scopeType);
            handleChange('scope', newScope);
        };

        const handleSubmit = async () => {
            setSaving(true);
            setError(null);

            try {
                // Validate JSON
                JSON.parse(formData.attributes);
                JSON.parse(formData.innerBlocks);
            } catch (e) {
                setError(__('Invalid JSON in attributes or inner blocks.', 'advanced-block-editor'));
                setSaving(false);
                return;
            }

            const data = new FormData();
            data.append('action', 'abe_save_variation');
            data.append('nonce', nonce);
            Object.entries(formData).forEach(([key, value]) => {
                if (key === 'scope') {
                    value.forEach(v => data.append('scope[]', v));
                } else {
                    data.append(key, value);
                }
            });

            try {
                const response = await fetch(ajaxUrl, { method: 'POST', body: data });
                const result = await response.json();

                if (result.success) {
                    onSave(result.data.variation);
                } else {
                    setError(result.data.message);
                }
            } catch (e) {
                setError(__('Failed to save variation.', 'advanced-block-editor'));
            }

            setSaving(false);
        };

        const blockOptions = Object.entries(coreBlocks).map(([value, label]) => ({ value, label }));

        return el('div', { className: 'abe-variation-editor' },
            error && el(Notice, { status: 'error', isDismissible: false }, error),
            
            el(TextControl, {
                label: __('Variation Name (slug)', 'advanced-block-editor'),
                value: formData.name,
                onChange: (val) => handleChange('name', val.toLowerCase().replace(/[^a-z0-9-]/g, '-')),
                help: __('Unique identifier, lowercase with dashes', 'advanced-block-editor'),
            }),

            el(TextControl, {
                label: __('Display Title', 'advanced-block-editor'),
                value: formData.title,
                onChange: (val) => handleChange('title', val),
            }),

            el(TextareaControl, {
                label: __('Description', 'advanced-block-editor'),
                value: formData.description,
                onChange: (val) => handleChange('description', val),
                rows: 2,
            }),

            el(SelectControl, {
                label: __('Block Type', 'advanced-block-editor'),
                value: formData.blockType,
                options: blockOptions,
                onChange: (val) => handleChange('blockType', val),
            }),

            el(Flex, {},
                el(FlexItem, { isBlock: true },
                    el(SelectControl, {
                        label: __('Icon', 'advanced-block-editor'),
                        value: formData.icon,
                        options: iconOptions,
                        onChange: (val) => handleChange('icon', val),
                    })
                ),
                el(FlexItem, { isBlock: true },
                    el(SelectControl, {
                        label: __('Category', 'advanced-block-editor'),
                        value: formData.category,
                        options: categoryOptions,
                        onChange: (val) => handleChange('category', val),
                    })
                )
            ),

            el('div', { className: 'abe-scope-checkboxes', style: { marginBottom: '16px' } },
                el('label', { style: { display: 'block', marginBottom: '8px', fontWeight: 500 } }, 
                    __('Scope', 'advanced-block-editor')
                ),
                el(Flex, {},
                    el(CheckboxControl, {
                        label: __('Inserter', 'advanced-block-editor'),
                        checked: formData.scope.includes('inserter'),
                        onChange: (val) => handleScopeChange('inserter', val),
                    }),
                    el(CheckboxControl, {
                        label: __('Block', 'advanced-block-editor'),
                        checked: formData.scope.includes('block'),
                        onChange: (val) => handleScopeChange('block', val),
                    }),
                    el(CheckboxControl, {
                        label: __('Transform', 'advanced-block-editor'),
                        checked: formData.scope.includes('transform'),
                        onChange: (val) => handleScopeChange('transform', val),
                    })
                )
            ),

            el(TextControl, {
                label: __('Keywords (comma-separated)', 'advanced-block-editor'),
                value: formData.keywords,
                onChange: (val) => handleChange('keywords', val),
                help: __('Search terms to find this variation', 'advanced-block-editor'),
            }),

            el(TextareaControl, {
                label: __('Default Attributes (JSON)', 'advanced-block-editor'),
                value: formData.attributes,
                onChange: (val) => handleChange('attributes', val),
                rows: 5,
                help: __('Block attributes as JSON object', 'advanced-block-editor'),
                className: 'code',
            }),

            el(TextareaControl, {
                label: __('Inner Blocks (JSON)', 'advanced-block-editor'),
                value: formData.innerBlocks,
                onChange: (val) => handleChange('innerBlocks', val),
                rows: 4,
                help: __('Nested blocks configuration as JSON array', 'advanced-block-editor'),
                className: 'code',
            }),

            el(ToggleControl, {
                label: __('Active', 'advanced-block-editor'),
                checked: formData.isActive,
                onChange: (val) => handleChange('isActive', val),
                help: __('Enable this variation in the editor', 'advanced-block-editor'),
            }),

            el(Flex, { justify: 'flex-end', style: { marginTop: '20px', gap: '10px' } },
                el(Button, { variant: 'tertiary', onClick: onCancel }, __('Cancel', 'advanced-block-editor')),
                el(Button, { 
                    variant: 'primary', 
                    onClick: handleSubmit,
                    isBusy: saving,
                    disabled: saving || !formData.name || !formData.title,
                }, saving ? __('Saving...', 'advanced-block-editor') : __('Save Variation', 'advanced-block-editor'))
            )
        );
    }

    /**
     * Variations List Component
     */
    function VariationsList({ variations, onEdit, onDelete }) {
        if (Object.keys(variations).length === 0) {
            return el('div', { className: 'abe-no-variations' },
                el('p', {}, __('No custom variations yet.', 'advanced-block-editor')),
                el('p', { className: 'description' }, 
                    __('Create variations to quickly insert blocks with preset attributes and styles.', 'advanced-block-editor')
                )
            );
        }

        return el('div', { className: 'abe-variations-list' },
            Object.values(variations).map(variation => 
                el(Card, { key: variation.id, size: 'small', className: 'abe-variation-card' },
                    el(CardBody, {},
                        el(Flex, { align: 'center' },
                            el(FlexItem, {},
                                el(Icon, { icon: variation.icon || 'block-default', size: 24 })
                            ),
                            el(FlexItem, { isBlock: true },
                                el('strong', {}, variation.title),
                                el('div', { className: 'description', style: { fontSize: '12px', color: '#757575' } },
                                    coreBlocks[variation.blockType] || variation.blockType
                                )
                            ),
                            el(FlexItem, {},
                                el('span', { 
                                    className: `abe-status-badge ${variation.isActive ? 'active' : 'inactive'}` 
                                }, variation.isActive ? __('Active', 'advanced-block-editor') : __('Inactive', 'advanced-block-editor'))
                            ),
                            el(FlexItem, {},
                                el(Button, { 
                                    icon: 'edit', 
                                    label: __('Edit', 'advanced-block-editor'),
                                    onClick: () => onEdit(variation),
                                    size: 'small',
                                })
                            ),
                            el(FlexItem, {},
                                el(Button, { 
                                    icon: 'trash', 
                                    label: __('Delete', 'advanced-block-editor'),
                                    onClick: () => onDelete(variation.id),
                                    isDestructive: true,
                                    size: 'small',
                                })
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Main Block Variations Panel
     */
    function BlockVariationsPanel() {
        const [localVariations, setLocalVariations] = useState(variations);
        const [editingVariation, setEditingVariation] = useState(null);
        const [isModalOpen, setIsModalOpen] = useState(false);

        const handleSave = (savedVariation) => {
            setLocalVariations(prev => ({
                ...prev,
                [savedVariation.id]: savedVariation
            }));
            setIsModalOpen(false);
            setEditingVariation(null);
            
            // Re-register variation if active
            if (savedVariation.isActive) {
                try {
                    registerBlockVariation(savedVariation.blockType, {
                        name: savedVariation.name,
                        title: savedVariation.title,
                        description: savedVariation.description,
                        icon: savedVariation.icon,
                        attributes: savedVariation.attributes || {},
                    });
                } catch (e) {
                    console.log('Variation may already exist, will apply on refresh');
                }
            }
        };

        const handleDelete = async (id) => {
            if (!confirm(__('Delete this variation?', 'advanced-block-editor'))) return;

            const data = new FormData();
            data.append('action', 'abe_delete_variation');
            data.append('nonce', nonce);
            data.append('id', id);

            const response = await fetch(ajaxUrl, { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                setLocalVariations(prev => {
                    const updated = { ...prev };
                    delete updated[id];
                    return updated;
                });
            }
        };

        const openEditor = (variation = null) => {
            setEditingVariation(variation);
            setIsModalOpen(true);
        };

        const icon = el('svg', { width: 20, height: 20, viewBox: '0 0 24 24' },
            el('path', { 
                fill: 'currentColor',
                d: 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10.5 0a5.5 5.5 0 100 11 5.5 5.5 0 000-11z' 
            })
        );

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, { target: 'abe-variations-panel', icon }, 
                __('Block Variations', 'advanced-block-editor')
            ),
            el(PluginSidebar, {
                name: 'abe-variations-panel',
                icon,
                title: __('Block Variations', 'advanced-block-editor'),
            },
                el(PanelBody, { title: __('Custom Variations', 'advanced-block-editor'), initialOpen: true },
                    el(Button, { 
                        variant: 'primary', 
                        onClick: () => openEditor(),
                        style: { marginBottom: '16px', width: '100%' },
                    }, __('+ Create New Variation', 'advanced-block-editor')),
                    el(VariationsList, {
                        variations: localVariations,
                        onEdit: openEditor,
                        onDelete: handleDelete,
                    })
                )
            ),
            isModalOpen && el(Modal, {
                title: editingVariation 
                    ? __('Edit Variation', 'advanced-block-editor')
                    : __('Create Block Variation', 'advanced-block-editor'),
                onRequestClose: () => { setIsModalOpen(false); setEditingVariation(null); },
                className: 'abe-variation-modal',
            },
                el(VariationEditor, {
                    variation: editingVariation,
                    onSave: handleSave,
                    onCancel: () => { setIsModalOpen(false); setEditingVariation(null); },
                })
            )
        );
    }

    // Initialize
    wp.domReady(() => {
        registerSavedVariations();
        registerPlugin('abe-block-variations', { render: BlockVariationsPanel });
    });

})();
