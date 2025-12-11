# Advanced Block Editor

Enhance the WordPress Block Editor with custom CSS/JS injection, **Core Block Variations**, editor width control, word count, and more – **with options to run code on both backend and frontend**.

![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-purple)
![License](https://img.shields.io/badge/License-GPLv3-green)

## Features

### 🧩 Core Block Variations Manager (NEW!)
Create custom variations of any core WordPress block without writing code.
- **Visual Interface**: Create variations directly in the Block Editor sidebar
- **Preset Attributes**: Set default values for blocks (colors, typography, spacing)
- **Custom Icons & Titles**: Make your variations easily identifiable
- **Scope Control**: Choose where variations appear (inserter, block, transform)
- **Inner Blocks Support**: Pre-configure nested block structures
- **Enable/Disable**: Toggle variations on or off without deleting them

### 💻 Advanced Custom Code Panel (NEW!)
Enhanced code editor with fullscreen mode and dual-scope execution.
- **Fullscreen Editor**: Distraction-free code editing experience
- **CSS Selector Helper**: Click-to-insert common selectors reference
- **Frontend + Backend**: Choose to run code in editor, frontend, or both
- **Multiple Snippets**: Manage multiple independent code snippets
- **Enable/Disable Toggle**: Turn snippets on/off without deletion
- **Syntax Highlighting**: Dark-themed code editor with proper formatting

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

### Block Variations (Editor Sidebar)

1. Open the Block Editor for any post or page
2. Click the **Block Variations** icon in the editor toolbar/more menu
3. Click **"+ Create New Variation"**
4. Configure:
   - **Block Type**: Select the core block to create a variation for
   - **Name/Title**: Give your variation a unique identifier
   - **Icon & Category**: Customize appearance in inserter
   - **Default Attributes**: Set preset values (as JSON)
   - **Scope**: Choose where the variation appears
5. Save and use your variation from the block inserter!

### Custom Code Panel (Editor Sidebar)

1. Open the Block Editor
2. Click the **Custom Code** icon (</>) in the toolbar
3. Click **"+ CSS"** or **"+ JavaScript"** to create a snippet
4. Use the fullscreen editor for a better coding experience
5. Choose execution scope:
   - **Editor**: Code runs only in the Block Editor
   - **Frontend**: Code runs on the public website
   - **Both**: Code runs everywhere
6. Toggle snippets on/off as needed

## Development

### Requirements

- PHP 7.4+
- WordPress 6.0+

### Structure

```
advanced-block-editor/
├── advanced-block-editor.php  # Main plugin file
├── includes/
│   ├── class-block-variations.php  # Block Variations Manager
│   └── class-custom-code.php       # Custom Code Manager
├── assets/
│   ├── admin/
│   │   ├── css/admin.css      # Admin settings page styles
│   │   └── js/admin.js        # Admin scripts (CodeMirror, media upload)
│   └── editor/
│       ├── css/editor.css     # Block Editor styles
│       ├── js/editor.js       # Block Editor enhancements
│       ├── js/block-variations.js  # Block Variations UI
│       └── js/custom-code-panel.js # Custom Code Panel UI
├── languages/                  # Translation files
├── readme.txt                  # WordPress.org readme
└── README.md                   # This file
```

### Creating Block Variations Programmatically

```javascript
// Access saved variations via localized data
const variations = window.abeVariations.variations;

// Example: Register a variation manually
wp.blocks.registerBlockVariation('core/button', {
    name: 'cta-button',
    title: 'CTA Button',
    attributes: {
        backgroundColor: 'vivid-cyan-blue',
        textColor: 'white',
        className: 'is-style-cta'
    }
});
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

## Author

**Gaurav Tiwari**
- Website: [developer.developer.developer](https://developer.developer.developer)
- GitHub: [@developer.developer.developer](https://github.com/developer.developer.developer)

## Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/developer.developer.developer/advanced-block-editor/issues) page.

