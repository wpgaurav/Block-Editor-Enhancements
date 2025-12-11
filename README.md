# Advanced Block Editor

Enhance the WordPress Block Editor with custom CSS/JS injection, editor width control, word count, and more – **without affecting your frontend**.

![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-purple)
![License](https://img.shields.io/badge/License-GPLv3-green)

## Features

### 🎨 Custom CSS Injection
Add custom styles to the Block Editor to match your theme or create a better editing experience.
- **Inline CSS**: Write CSS directly in the admin settings
- **External CSS File**: Load CSS from any URL or upload to media library

### ⚡ Custom JavaScript Injection  
Extend the Block Editor with custom JavaScript to add new functionality.
- **Inline JavaScript**: Add custom scripts directly
- **External JS File**: Load JavaScript from any URL or upload to media library

### 📏 Editor Width Control
Adjust the content area width to match your theme's content width.
- Slider control in editor sidebar
- Supports px, %, and vw units
- Persists across sessions

### 📊 Word Count Display
Real-time word and character count shown at the top or bottom of the editor.

### 🎯 Focus Mode
Dim non-active blocks to focus on your current content.

### 🖥️ Fullscreen Control
Option to disable fullscreen mode by default.

## Installation

### From GitHub

1. Download or clone this repository
2. Copy the `advanced-block-editor` folder to `/wp-content/plugins/`
3. Activate the plugin in WordPress admin

### Manual

1. Download the latest release
2. Upload via **Plugins → Add New → Upload Plugin**
3. Activate the plugin

## Usage

### Settings Page

Navigate to **Settings → Block Editor+** to configure:

| Tab | Description |
|-----|-------------|
| **General** | Editor width, word count, focus mode, fullscreen settings |
| **Custom CSS** | Add inline CSS or link external CSS file |
| **Custom JS** | Add inline JavaScript or link external JS file |

### Editor Sidebar

Click the "Advanced Editor" icon in the Block Editor's More Menu to access:
- Real-time editor width slider
- Focus mode toggle

## Development

### Requirements

- PHP 7.4+
- WordPress 6.0+

### Structure

```
advanced-block-editor/
├── advanced-block-editor.php  # Main plugin file
├── assets/
│   ├── admin/
│   │   ├── css/admin.css      # Admin settings page styles
│   │   └── js/admin.js        # Admin scripts (CodeMirror, media upload)
│   └── editor/
│       ├── css/editor.css     # Block Editor styles
│       └── js/editor.js       # Block Editor enhancements
├── languages/                  # Translation files
├── readme.txt                  # WordPress.org readme
└── README.md                   # This file
```

### Hooks & Filters

The plugin uses standard WordPress hooks:

```php
// Fires when editor assets are enqueued
add_action('enqueue_block_editor_assets', 'your_function');

// Access plugin settings
$settings = get_option('abe_settings');
```

### Available WordPress APIs in Custom JS

```javascript
// Access editor state
wp.data.select('core/editor').getEditedPostContent();

// Modify editor state  
wp.data.dispatch('core/editor').editPost({ title: 'New Title' });

// Register custom plugins
wp.plugins.registerPlugin('my-plugin', { render: MyComponent });

// Add filters to blocks
wp.hooks.addFilter('blocks.registerBlockType', 'myPlugin/filter', filterFn);
```

## License

GPL v3 or later. See [LICENSE](LICENSE) for full text.

## Credits

Rebuilt with focus on simplicity and modern WordPress standards.
