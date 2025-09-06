=== Token Studio → Elementor Sync ===
Contributors: krawc
Tags: elementor, design tokens, design system, typography, token studio
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sync design tokens with Elementor global styles. Imports reference colors and typography. Resolves references. Updates the active Elementor Kit.

== Description ==

This plugin creates a simple interface for syncing design tokens into Elementor.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/tokenstudio-elementor/` directory, or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Elementor → Token Studio Sync** in the dashboard menu.
4. Paste your reference and system token JSON.
5. Click **Save & Sync**.

== How it works ==

The plugin: 
* Imports **reference colors** as global colors.
* Imports **system typography** as custom typography presets.
* Resolves `{reference.*}` and `{system.*}` into literal values.
* Updates the active Elementor Kit automatically.
* Provides an admin page for pasting JSON from Token Studio.

== Frequently Asked Questions ==

= Does this overwrite existing Elementor styles? =
* Reference colors are merged and updated if names match.
* Typography presets are overwritten if titles match.

= What formats of JSON are supported? =
Both `"$value"` and `"value"` keys are supported. Reference tokens and system tokens can be pasted as exported from Token Studio.

= Does this work without Elementor? =
No. Elementor must be installed and active.

== Screenshots ==

1. Admin page for pasting Reference and System JSON.
2. Example of synced colors and typography in Elementor global styles.

== Changelog ==

= 0.1.0 =
* Initial release.  
* Import reference colors into Elementor global colors.  
* Import system typography presets with resolved references.  
* Admin interface with two JSON textareas and live root key selection.  

== Upgrade Notice ==

= 0.1.0 =
First release. Sync your Token Studio JSON directly into Elementor.

