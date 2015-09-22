=== Embedly ===

Contributors: Embedly
Tags: embed, oembed, video, image, pdf, card

Requires at least: 3.8
Tested up to: 4.3
Stable tag: 4.0.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The Embedly Plugin extends Wordpress's automatic embed feature to give your blog more media types, video analytics, and style options.

== Description ==

In addition to the default Wordpress embedding, you get previews for any article,
including your own blog posts. You also get embeds for Gfycat, Twitch, Google
Maps, and Embedly’s growing list of [300+ supported
providers](http://embed.ly/providers).

You can customize the style of the embeds to optimize for darker WP themes,
change alignment, and set the width. Social buttons can be added around the embeds
to make it easier to share the embeds from your blog posts.

For most music and video player embeds (YouTube, Vimeo, Instagram, SoundCloud)
you can receive analytics on viewer behaviors. See which videos are actually
watched and how far your readers watch them.


Using it is as simple as the default Wordpress embedding. Embed media by pasting its URL in a single line when writing a post:

    This Embedly lets me embed everything great on the web!

    http://instagram.com/p/w8hB9Dn7qF/

    http://i.imgur.com/ywzpg.jpg

    http://www.amazon.com/gp/product/B002BRZ9G0/ref=s9_pop_gw_ir01

    http://azizisbored.tumblr.com/post/558456193/mtv-movie-awards-promo-who-is-aziz-ansari

The plugin automatically displays an embed of the media in the Wordpress post
editor.



== Installation ==


Using the Plugin Manager

1.
Click Plugins
1.
Click Add New
1.
Search for Embedly
1.
Click Install
1.
Click Install Now
1.
Click Activate Plugin



Manually

1.
Upload `embedly` to the `/wp-content/plugins/` directory
1.
Activate the plugin through the 'Plugins' menu in WordPress



Multi-Site

1.
Navigate to My Sites -> Network Admin
1.
Follow Steps 1-5 in Using the Plugin Manager setup above, `Do not Network Activate`
1.
Go to each site's dashboard and activate Embedly in Plugins section



== Frequently Asked Questions ==

=
Is this plugin for me?
=

Yes

=

Where do I get a key?
=

You can sign up for a free or paid plan by clicking [here](https://app.embed.ly).

=

How do I embed "any" URL?
=

In the post editor click the Embedly icon and add your URL.

=

Do I need a key?
=

You need a key to embed "any" url, otherwise the plugin will
only be available for these [providers](http://embed.ly/providers).

=

What does a paid product give me?
=

We will be providing analytics on how your embeds are doing
including plays, duration watched, hovers, and other engagement
metrics. This is currently not available in the plugin.

=
What is your support email?
=

support@embed.ly

=
Do you support multi-site?
=

Yes, see steps above to install for multi-site.
Note: You will need to activate Embedly for each site.


== Screenshots ==


1. Admin Console.

2. Embedly Post Editor.

3. Sample Post.



== Changelog ==

= 3.2 =

* Embedly TinyMCE dialog and dependencies managed server side.
* Refactor code to use class structure.
* Clean up deprecated SQL generation to make compliant with WP3.6 and above.


= 3.1.3 =

* Fix Add Post bug in IE.

= 3.1.2 =

* Enable Twitter WP OEmbed.

= 3.1 =

* Fixes issue with Embedly not loading on WP3.9.
* Load tiny_mce_popup_4_0.js when TinyMCE is v4.0.

= 3.0 =

* Upgrade Embedly TinyMce editor option to use Embedly Cards.

= 2.3.0 =

* Use TinyMCE provided by WP Core.
* Fixes issue with HTML editting and formatting.

= 2.2.2 =

* Change server side calls to HTTP to avoid issues.
* Disable rocketloader syntax.

= 2.2 =

* Update TinyMce Popup js to latest.
* Update to latest JQuery 1.10.2.
* Update Powered by link destination to code generator.
* Fix powered by logo for RSS generation.
* Add support for links to open in new window.

= 2.1.4 =

* Support for blogs using HTTPS.
* Steps for multi-site setup.

= 2.1.2 =

* Use wp-includes tiny_mce_popup.js
* Compatible with WP 3.5

= 2.1.1 =

* Providers save fix.

= 2.1 =

* Admin Redesign.
* embedly_settngs option for wp_options table.
* SQL optimizations.

= 2.0.9 =

* Fix for feature status check.

= 2.0.8 =

* Allow script tag embeds.

= 2.0.6 =

* Add Embedly providers on 'plugins_loaded' instead of 'init' and other tweaks

= 2.0.5 =

* Fixing the path to TinyMCE plugin.

= 2.0.4 =

* Updated flow for previewing and updating embeds.

* Improved previews for preview endpoint.

* Better error handling for loading plugin in Post Editor.

= 2.0.3 =

* Resolve issue with tag attributes getting stripped

* Resolve quirks with height getting set incorrectly

= 2.0.2 =

* Resolve conflict with WordPress image editing

= 2.0.1 =

* Resolves Rich Editor not showing up.

= 2.0 =

* Adds Embedly TinyMCE plugin to Rich Editor.

* Support for Embedly Key to Embed "any" URL.

= 1.0 =

A few fixes.

= 0.9 =

Initial Version



== Upgrade Notice ==

= 2.0 =

Embed "any" URL.

= 2.1 =

Admin Redesign.

= 2.2 =

Update tinymce and jquery libs.

= 3.0 =

Embedly rich post editor option now uses Embedly Cards layout.

= 3.1 =

Dynamically loading Embedly popup based on TinyMCE Version.

= 3.2 =

Refactor Embedly TinyMCE Dialog to generate via iframe request.
