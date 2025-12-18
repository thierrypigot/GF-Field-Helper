=== GF Field Helper – Help Tooltips ===
Contributors: wearewp
Tags: gravity forms, tooltips, help, forms, accessibility
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds customizable help tooltips to Gravity Forms fields, directly from the form editor.

== Description ==

GF Field Helper allows you to easily add contextual help (tooltips) to your Gravity Forms fields, directly from the form editor.

The plugin is lightweight, accessible, and designed to improve form usability without cluttering the interface.

Developed and maintained by **WeAre[WP] – Thierry Pigot**.

== Features ==

* Native integration: Help settings appear directly in the Gravity Forms field editor
* Short text + Long text: Display a visible teaser text and a detailed explanation
* Modern design: Styled tooltips with smooth animations
* Accessible: WCAG-friendly (keyboard navigation, aria attributes, Escape to close)
* HTML support: Use basic HTML in help texts (links, formatting)
* Link mode: Option to redirect users to a help page instead of a tooltip
* Responsive: Works perfectly on mobile devices

== Supported Field Types ==

* Text fields (text, textarea, email, phone, website)
* Select fields (dropdown, multi-select)
* Radio buttons and checkboxes
* Date and time fields
* File uploads
* Address and name fields
* And more…

== Installation ==

1. Make sure Gravity Forms is installed and activated
2. Upload the `gf-field-helper` folder to `/wp-content/plugins/`
3. Activate the plugin from the WordPress Plugins menu
4. Edit a Gravity Forms form and configure help tooltips on your fields

== Usage ==

1. Go to **Forms > Edit** a form
2. Click on a field to display its settings
3. Open the **Contextual Help (Tooltip)** section
4. Configure:
   - Short text (visible near the label)
   - Long text (displayed in the tooltip)
   - Display type: Tooltip or Link
5. Save the form

== Frequently Asked Questions ==

= Can I use HTML in help texts? =
Yes. Allowed tags include: `<strong>`, `<em>`, `<br>`, `<a>`, `<p>`, `<ul>`, `<li>`.

= Do tooltips work on mobile? =
Yes. Tooltips open on tap and close automatically.

= Is the plugin accessible? =
Yes. It follows accessibility best practices:
- Keyboard navigation
- Escape key to close
- ARIA attributes
- Visible focus indicators

== Screenshots ==

1. Help settings in Gravity Forms editor
2. Tooltip rendering on frontend
3. Mobile display example

== Changelog ==

= 1.0.1 =
* Minor improvements and cleanup

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.1 =
Maintenance update.
