Changelog
=========

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
