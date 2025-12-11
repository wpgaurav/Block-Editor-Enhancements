=== Advanced Block Editor ===
Contributors: gauravtiwari
Donate link: https://gauravtiwari.org/donate/
Tags: block editor, gutenberg, editor, css, javascript, customization
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Enhance the WordPress Block Editor with custom CSS/JS injection, editor width control, word count, and more – without affecting your frontend.

== Description ==

**Advanced Block Editor** is a lightweight, standalone plugin that supercharges your WordPress Block Editor experience. All enhancements are applied **only in the editor** – your frontend remains completely unaffected.

= Key Features =

* **Custom CSS Injection** – Add inline CSS or load external CSS files to customize the editor appearance
* **Custom JavaScript Injection** – Add inline JS or load external JavaScript files to extend editor functionality
* **Editor Width Control** – Adjust the content area width with a convenient sidebar slider (supports px, %, vw units)
* **Word Count Display** – Real-time word and character count shown in the editor
* **Focus Mode** – Dim non-active blocks to focus on your current content
* **Fullscreen Control** – Option to disable fullscreen mode by default

= Zero Dependencies =

Unlike other plugins, Advanced Block Editor is completely standalone:

* No external PHP frameworks required
* No Composer dependencies
* Works out of the box
* Lightweight and fast

= Developer Friendly =

* Clean, well-documented code
* WordPress coding standards compliant
* Uses native WordPress APIs (wp.data, wp.plugins, wp.element)
* Easy to extend with custom hooks

= Use Cases =

* Apply custom typography to the editor
* Add theme-specific editor styles
* Register custom sidebar panels or plugins
* Modify block behavior with JavaScript
* Create a more focused writing environment

== Installation ==

1. Upload the `advanced-block-editor` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings → Block Editor+** to configure

== Frequently Asked Questions ==

= Will this affect my website's frontend? =

No! All customizations apply **only** to the Block Editor. Your frontend remains completely unchanged.

= Is this compatible with my theme? =

Yes, this plugin works with any theme that uses the WordPress Block Editor (Gutenberg).

= Can I use this with Full Site Editing (FSE)? =

Yes, the plugin works with the Site Editor and Post Editor.

= How do I add custom CSS? =

Go to **Settings → Block Editor+**, click the "Custom CSS" tab, and add your styles in the code editor or link to an external CSS file.

= How do I add custom JavaScript? =

Go to **Settings → Block Editor+**, click the "Custom JS" tab, and add your code or link to an external JavaScript file.

== Screenshots ==

1. Settings page - General tab with editor width and word count options
2. Settings page - Custom CSS tab with code editor
3. Settings page - Custom JS tab with code editor
4. Editor sidebar panel with width control
5. Word count display in the editor

== Changelog ==

= 4.0.0 =
Major Feature Release

**New Features**
* Patterns Manager
	* Visual pattern editor in settings page
	* Shortcode support: [abe_pattern slug="your-pattern"], [abe_pattern id="123"]
	* Organize patterns by custom categories
	* Enable or disable patterns without deleting
	* Automatically registers with block inserter
* Per-Block CSS/JS (conditional loading)
	* Load CSS/JS only when specific blocks are present
	* Visual rule manager in editor sidebar
	* Editor-only, frontend-only, or both scopes
* Frontend Cleanup
	* Remove unwanted block classes (35+)
	* Disable emoji scripts, wp-embed, global styles, duotone filters
	* Cleaner frontend markup
* Editor Enhancements
	* Reading time estimation
	* Paragraph count
	* Typewriter mode
	* Automatic heading anchors
	* Keyboard shortcuts
	* Copy all content tool

**Improvements**
* Real-time word count (React hooks)
* Settings page expanded to 7 tabs
* Modernized React sidebar UI
* Dark theme syntax-highlighted code editors

**New Files**
* includes/class-patterns-manager.php
* includes/class-per-block-code.php
* includes/class-frontend-cleanup.php
* assets/editor/js/patterns-manager.js
* assets/editor/js/per-block-code.js

= 3.0.0 =
Block Variations & Custom Code Panel

**New Features**
* Block Variations Manager
	* Custom variations for core blocks
	* Preset attributes (colors, typography, spacing)
	* Custom icons and titles
	* Inserter/block/transform scope
	* Inner blocks support
	* Enable/disable toggle
* Custom Code Panel
	* Fullscreen mode
	* CSS selector helper
	* Frontend/backend scope
	* Multiple snippets
	* Dark theme syntax highlighting

**New Files**
* includes/class-block-variations.php
* includes/class-custom-code.php
* assets/editor/js/block-variations.js
* assets/editor/js/custom-code-panel.js

= 2.0.0 =
Complete Rewrite

**New Features**
* Custom CSS (inline/external)
* Custom JS (inline/external)
* Modern tabbed settings page
* CodeMirror editors
* Media library integration
* Editor sidebar width control
* Focus mode
* Fullscreen control

**Improvements**
* Editor width with unit selector
* Removed external frameworks
* Zero Composer dependencies
* Requires WordPress 6.0+, PHP 7.4+

= 1.0.0 =
Initial Release

* Basic editor width control
* Word count display
* Initial plugin structure


== Credits ==

Developed by [Gaurav Tiwari](https://gauravtiwari.org)
