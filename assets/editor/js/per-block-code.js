/**
 * Advanced Block Editor - Per-Block Code Manager
 * Allows adding CSS/JS specific to block types
 */

(function () {
    'use strict';

    const { createElement: el, useState, useEffect, useCallback, Fragment } = wp.element;
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const {
        PanelBody, Button, TextControl, TextareaControl, ToggleControl,
        SelectControl, Modal, Card, CardBody, Notice, CheckboxControl, Spinner
    } = wp.components;
    const { __ } = wp.i18n;

    // Get data from localized script
    const perBlockData = window.abePerBlockCode || {};
    const { rules: initialRules, nonce, ajaxUrl, blockTypes } = perBlockData;

    /**
     * Block Rule Card Component
     */
    function BlockRuleCard({ rule, onEdit, onDelete, onToggle }) {
        const [deleting, setDeleting] = useState(false);

        const handleDelete = async () => {
            if (!confirm(__('Are you sure you want to delete this rule?', 'advanced-block-editor'))) {
                return;
            }

            setDeleting(true);
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_delete_block_rule',
                        nonce: nonce,
                        id: rule.id
                    })
                });
                const data = await response.json();
                if (data.success) {
                    onDelete(rule.id);
                }
            } catch (error) {
                console.error('Delete error:', error);
            }
            setDeleting(false);
        };

        const blockLabel = blockTypes[rule.block_type] || rule.block_type;
        const hasCSS = rule.css && rule.css.trim().length > 0;
        const hasJS = rule.js && rule.js.trim().length > 0;

        return el(Card, { className: 'abe-block-rule-card', size: 'small' },
            el(CardBody, null,
                el('div', { className: 'abe-block-rule-card__header' },
                    el('strong', null, rule.name),
                    el('span', {
                        className: `abe-status-badge ${rule.enabled ? 'active' : 'inactive'}`
                    }, rule.enabled ? __('Active', 'advanced-block-editor') : __('Inactive', 'advanced-block-editor'))
                ),
                el('div', { className: 'abe-block-rule-card__meta' },
                    el('span', { className: 'abe-block-rule-card__block-type' }, blockLabel),
                    el('div', { className: 'abe-block-rule-card__types' },
                        hasCSS && el('span', { className: 'abe-snippet-type abe-snippet-type--css' }, 'CSS'),
                        hasJS && el('span', { className: 'abe-snippet-type abe-snippet-type--js' }, 'JS')
                    )
                ),
                el('div', { className: 'abe-block-rule-card__scope' },
                    el('span', null, __('Scope:', 'advanced-block-editor') + ' '),
                    el('span', null, (rule.scope || []).join(', '))
                ),
                el('div', { className: 'abe-block-rule-card__actions' },
                    el(Button, {
                        variant: 'secondary',
                        size: 'small',
                        onClick: () => onEdit(rule),
                        disabled: deleting
                    }, __('Edit', 'advanced-block-editor')),
                    el(Button, {
                        variant: 'secondary',
                        size: 'small',
                        onClick: () => onToggle(rule.id),
                        disabled: deleting
                    }, rule.enabled ? __('Disable', 'advanced-block-editor') : __('Enable', 'advanced-block-editor')),
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
     * Block Rule Editor Modal
     */
    function BlockRuleEditorModal({ rule, onSave, onClose }) {
        const [formData, setFormData] = useState({
            id: rule?.id || '',
            name: rule?.name || '',
            block_type: rule?.block_type || 'core/paragraph',
            css: rule?.css || '',
            js: rule?.js || '',
            scope: rule?.scope || ['frontend'],
            enabled: rule?.enabled !== false,
            priority: rule?.priority || 10
        });
        const [saving, setSaving] = useState(false);
        const [error, setError] = useState(null);

        const handleChange = (key, value) => {
            setFormData(prev => ({ ...prev, [key]: value }));
        };

        const handleScopeToggle = (scope) => {
            setFormData(prev => {
                const scopes = [...prev.scope];
                const index = scopes.indexOf(scope);
                if (index > -1) {
                    scopes.splice(index, 1);
                } else {
                    scopes.push(scope);
                }
                return { ...prev, scope: scopes };
            });
        };

        const handleSubmit = async () => {
            if (!formData.block_type) {
                setError(__('Block type is required.', 'advanced-block-editor'));
                return;
            }

            if (!formData.css && !formData.js) {
                setError(__('Please add some CSS or JavaScript.', 'advanced-block-editor'));
                return;
            }

            setSaving(true);
            setError(null);

            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_save_block_rule',
                        nonce: nonce,
                        ...formData,
                        scope: JSON.stringify(formData.scope)
                    })
                });
                const data = await response.json();

                if (data.success) {
                    onSave(data.data.rule);
                    onClose();
                } else {
                    setError(data.data?.message || __('Failed to save rule.', 'advanced-block-editor'));
                }
            } catch (err) {
                setError(__('An error occurred while saving.', 'advanced-block-editor'));
            }

            setSaving(false);
        };

        const blockOptions = Object.entries(blockTypes).map(([value, label]) => ({
            value,
            label
        }));

        return el(Modal, {
            title: rule ? __('Edit Block Rule', 'advanced-block-editor') : __('Create Block Rule', 'advanced-block-editor'),
            onRequestClose: onClose,
            className: 'abe-block-rule-modal',
            isFullScreen: true
        },
            el('div', { className: 'abe-block-rule-editor' },
                el('div', { className: 'abe-block-rule-editor__sidebar' },
                    error && el(Notice, { status: 'error', isDismissible: false }, error),

                    el(TextControl, {
                        label: __('Rule Name', 'advanced-block-editor'),
                        value: formData.name,
                        onChange: (value) => handleChange('name', value),
                        placeholder: __('e.g., Custom Heading Styles', 'advanced-block-editor')
                    }),
                    el(SelectControl, {
                        label: __('Block Type', 'advanced-block-editor'),
                        value: formData.block_type,
                        options: blockOptions,
                        onChange: (value) => handleChange('block_type', value)
                    }),
                    el('div', { className: 'abe-scope-section' },
                        el('label', null, __('Apply To', 'advanced-block-editor')),
                        el(CheckboxControl, {
                            label: __('Frontend (public website)', 'advanced-block-editor'),
                            checked: formData.scope.includes('frontend'),
                            onChange: () => handleScopeToggle('frontend')
                        }),
                        el(CheckboxControl, {
                            label: __('Editor (block editor)', 'advanced-block-editor'),
                            checked: formData.scope.includes('editor'),
                            onChange: () => handleScopeToggle('editor')
                        })
                    ),
                    el(ToggleControl, {
                        label: __('Enabled', 'advanced-block-editor'),
                        checked: formData.enabled,
                        onChange: (value) => handleChange('enabled', value)
                    }),
                    el('div', { className: 'abe-block-rule-editor__actions' },
                        el(Button, {
                            variant: 'secondary',
                            onClick: onClose,
                            disabled: saving
                        }, __('Cancel', 'advanced-block-editor')),
                        el(Button, {
                            variant: 'primary',
                            onClick: handleSubmit,
                            disabled: saving
                        }, saving ? el(Spinner) : __('Save Rule', 'advanced-block-editor'))
                    )
                ),
                el('div', { className: 'abe-block-rule-editor__main' },
                    el('div', { className: 'abe-code-editor' },
                        el('div', { className: 'abe-code-editor__header' },
                            el('span', { className: 'abe-code-editor__language' }, 'CSS')
                        ),
                        el('textarea', {
                            className: 'abe-code-editor__textarea',
                            value: formData.css,
                            onChange: (e) => handleChange('css', e.target.value),
                            placeholder: `.wp-block-${formData.block_type.replace('core/', '')} {\n    /* Your styles here */\n}`
                        })
                    ),
                    el('div', { className: 'abe-code-editor' },
                        el('div', { className: 'abe-code-editor__header' },
                            el('span', { className: 'abe-code-editor__language' }, 'JavaScript')
                        ),
                        el('textarea', {
                            className: 'abe-code-editor__textarea',
                            value: formData.js,
                            onChange: (e) => handleChange('js', e.target.value),
                            placeholder: '// Your JavaScript here\n// Runs when this block type is present'
                        })
                    )
                )
            )
        );
    }

    /**
     * Main Per-Block Code Panel
     */
    function PerBlockCodePanel() {
        const [rules, setRules] = useState(initialRules || {});
        const [editingRule, setEditingRule] = useState(null);
        const [showEditor, setShowEditor] = useState(false);

        const rulesList = Object.values(rules);

        const handleSave = (savedRule) => {
            setRules(prev => ({
                ...prev,
                [savedRule.id]: savedRule
            }));
        };

        const handleDelete = (ruleId) => {
            setRules(prev => {
                const updated = { ...prev };
                delete updated[ruleId];
                return updated;
            });
        };

        const handleToggle = async (ruleId) => {
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'abe_toggle_block_rule',
                        nonce: nonce,
                        id: ruleId
                    })
                });
                const data = await response.json();
                if (data.success) {
                    setRules(prev => ({
                        ...prev,
                        [ruleId]: {
                            ...prev[ruleId],
                            enabled: data.data.enabled
                        }
                    }));
                }
            } catch (error) {
                console.error('Toggle error:', error);
            }
        };

        const handleEdit = (rule) => {
            setEditingRule(rule);
            setShowEditor(true);
        };

        const handleCreate = () => {
            setEditingRule(null);
            setShowEditor(true);
        };

        const handleCloseEditor = () => {
            setShowEditor(false);
            setEditingRule(null);
        };

        // Code icon
        const codeIcon = el('svg', {
            width: 24,
            height: 24,
            viewBox: '0 0 24 24'
        },
            el('path', {
                d: 'M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z'
            })
        );

        return el(Fragment, null,
            el(PluginSidebarMoreMenuItem, {
                target: 'abe-per-block-code-panel',
                icon: codeIcon
            }, __('Per-Block Code', 'advanced-block-editor')),
            el(PluginSidebar, {
                name: 'abe-per-block-code-panel',
                icon: codeIcon,
                title: __('Per-Block CSS/JS', 'advanced-block-editor')
            },
                el(PanelBody, { title: __('Block Rules', 'advanced-block-editor'), initialOpen: true },
                    el(Button, {
                        variant: 'primary',
                        onClick: handleCreate,
                        style: { width: '100%', marginBottom: '16px' }
                    }, __('Create New Rule', 'advanced-block-editor')),

                    rulesList.length === 0
                        ? el('div', { className: 'abe-no-patterns' },
                            el('p', null, __('No block rules yet.', 'advanced-block-editor')),
                            el('p', null, __('Add custom CSS/JS that loads only when specific blocks are used.', 'advanced-block-editor'))
                        )
                        : el('div', { className: 'abe-rules-list' },
                            rulesList.map(rule =>
                                el(BlockRuleCard, {
                                    key: rule.id,
                                    rule: rule,
                                    onEdit: handleEdit,
                                    onDelete: handleDelete,
                                    onToggle: handleToggle
                                })
                            )
                        )
                ),
                el(PanelBody, { title: __('How It Works', 'advanced-block-editor'), initialOpen: false },
                    el('p', null, __('Per-block code only loads when the specified block type is present on a page.', 'advanced-block-editor')),
                    el('ul', null,
                        el('li', null, __('Improves performance by loading code only when needed', 'advanced-block-editor')),
                        el('li', null, __('Apply different styles to editor vs frontend', 'advanced-block-editor')),
                        el('li', null, __('Add interactive JavaScript to specific blocks', 'advanced-block-editor'))
                    )
                )
            ),
            showEditor && el(BlockRuleEditorModal, {
                rule: editingRule,
                onSave: handleSave,
                onClose: handleCloseEditor
            })
        );
    }

    // Register the plugin
    wp.domReady(() => {
        registerPlugin('abe-per-block-code', {
            render: PerBlockCodePanel,
            icon: null
        });
    });

})();
