=== Advanced Block Editor ===
Contributors: developer
Tags: block editor, gutenberg, editor, css, javascript, customization
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

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

= 2.0.0 =
* Complete rewrite as standalone plugin
* Removed all external framework dependencies
* Added custom CSS injection (inline and file)
* Added custom JavaScript injection (inline and file)
* Added modern admin settings page with tabs
* Added CodeMirror code editors with syntax highlighting
* Added media library integration for file uploads
* Added sidebar panel in the Block Editor
* Added focus mode feature
* Added fullscreen control option
* Improved editor width control with unit selector
* Updated minimum requirements (WP 6.0, PHP 7.4)

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update! Complete rewrite as a standalone plugin with custom CSS/JS injection features. No external dependencies required.
