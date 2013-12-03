=== Embedly ===

Contributors: Embedly

Tags: embed, oembed, video, image, pdf

Requires at least: 3.5.1

Tested up to: 3.7
Stable tag: 2.3.0


Embed videos, images, PDF, and article previews from "any" source with just the URL.
Just add your Embedly Key to the Embedly plugin settings.



== Description ==


The [Embedly](http://embed.ly) plugin allows bloggers to embed videos, images, PDF, and article previews from
"any" source with just the URL. It uses the [Embedly API](http://embed.ly/docs) to
get the embed code and display it in a post.

Write your post as normal and click the Embedly Icon in your Rich Editor
to add "any" URL to your post.

OR

Embed 250+ sources by putting the URL to the content you want to embed on
a single line like so:

    This Embedly lets me embed everything great on the web!
    
    http://twitpic.com/1owy89
    
    http://i.imgur.com/ywzpg.jpg
    
    http://www.amazon.com/gp/product/B002BRZ9G0/ref=s9_pop_gw_ir01

    http://azizisbored.tumblr.com/post/558456193/mtv-movie-awards-promo-who-is-aziz-ansari




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

If you upgrade to the Embed paid product (http://embed.ly/embed) it will 
remove the powered by Embedly logo, and allow higher levels of usage
and support.

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

2. Sample Embed.



== Changelog ==

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
