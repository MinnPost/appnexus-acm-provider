=== Appnexus ACM Provider ===
Contributors: minnpost, jonathanstegall
Donate link: https://www.minnpost.com/support/?campaign=7010G0000012fXGQAY
Tags: ads, ad code manager, appnexus
Requires at least: 4.9
Tested up to: 5.0
Stable tag: 0.0.21
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin extends the [Ad Code Manager](https://wordpress.org/plugins/ad-code-manager/) to provide support for [AppNexus](https://www.appnexus.com/en) ad codes.

== Description ==

This plugin extends the [Ad Code Manager](https://wordpress.org/plugins/ad-code-manager/) to provide support for [AppNexus](https://www.appnexus.com/en) ad codes. It enables settings for supported ad tag types, tag IDs, lazy loading, and separate settings for embed ads within articles. It also provides the ad table for the Ad Code Manager admin interface.

== Installation ==

#### Activate the plugin

In the Plugins list in WordPress, activate the plugin and find the settings link (you can also find this plugin's settings in the main Settings list in WordPress, under the AppNexus Ad Settings menu item once it is activated).

The plugin's settings URL is `https://<your site>/wp-admin/options-general.php?page=appnexus-acm-provider`. Ad codes appear in the Ad Code Manager's interface at `https://<your site>/wp-admin/tools.php?page=ad-code-manager`.

== Changelog ==

* 0.0.21 (2019-01-16)

	* If a post is disabling lazy loading via a meta field supported by WP Lozad Lazyload plugin, don't include those scripts or try to transform the markup for the ads on that post.

* 0.0.20 (2018-11-09)

	* Use filters from separate WP Lozad Lazyload plugin to lazy load ads.

* 0.0.19 (2018-11-08)

	* Begin support for lazy loading of JX ad codes using the Lozad library and the IntersectionObserver polyfill.

* 0.0.18 (2018-10-19)

	* Stop using `wpautop` to place ads. Use a combination of line breaks as the editor sees the, and a regex to stop undesired HTML tags from being seen as breaks.

* 0.0.17 (2018-10-04)

	* Implement the JX ad code type.

* 0.0.16 (2018-09-30)

	* Fix the calculation of how often to place automatic ads.

* 0.0.15 (2018-09-28)

	* Allow for a field that prevents only automatic ads, but still allows manually added ads.

* 0.0.14 (2018-09-18)

	* Fix the has_category and has_tag fix.

* 0.0.13 (2018-09-18)

	* Prevent false positives on has_category or has_tag conditionals for archive pages.

* 0.0.12 (2018-09-18)

	* Make the admin table sortable

* 0.0.11 (2018-09-01)

	* Change the URL for admin JavaScript/CSS.

* 0.0.10 (2018-08-09)

	* Add a TinyMCE plugin that changes `[cms_ad:type]` shortcodes in the editor into an image that can be moved around easier.

* 0.0.9 (2018-08-03)

	* Add [cms_ad] as an official shortcode so WordPress can find it with the `get_shortcode_regex` method. This makes the content replacer work better.

* 0.0.8 (2018-08-01)

	* Fix the ad inserter for inside the editor so it accomodates the newline breaks that the editor uses.

* 0.0.7 (2018-07-27)

	* Use `wpautop` when checking for presence of paragraphs. This fixes a bug in which the shortcode was not being added correctly for some new posts.

* 0.0.6 (2018-05-23)

	* Add a meta field that allows individual posts to skip ads.

* 0.0.5 (2018-05-11)

	* Add a hook that allows individual posts to skip ads.

* 0.0.4 (2018-02-01)

	* Add the ability to only lazy load embed ads, lazy load all ads, or lazy load no ads.
	* This also uses the setting for ad tags much more effectively, rather than hard coding anything.

* 0.0.3 (2017-12-14)

	* Rewrite plugin to use multiple classes for admin, front end, and ad panel.
	* Support for lazy loading of ads.

* 0.0.2 (2017-06-27)

	* Use the Settings API to configure the plugin and generate the code accordingly.

* 0.0.1 (2017-04-20)

	* Basic plugin that generates AppNexus style code.
