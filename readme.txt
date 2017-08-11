=== Plugin Name ===
Contributors: ZeroCool51
Donate link: http://gum.co/im-login-dongle
Tags: login dongle, login security, two step verification, two step verification, two step im login, instant messenger login, google talk login, extra security, pin login, two step authentication, two factor authentication, google authenticator
Requires at least: 3.0
Tested up to: 3.5
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple plugin that adds two step verification via selected instant messenger.

== Description ==

**THIS PLUGIN IS DEPRECATED AND IS NO LONGER SUPPORTED ON MY END**

This is a simple plugin that adds two step verification to the login. The beauty of it is, that no mobile phones are required, and pretty much anyone has an IM accout nowadays.

How does it work?

* You create an IM account (currently google talk, icq and windows live messenger are supported)
* You add this account as the bot in the plugin settings page (this bot will be sending the login pin numbers to other users)
* Users themselves disable or enable this feature

How does the login work when activated?

* You login normally, if the credentials are correct,
* you select the account you want to authorize with,
* a pin code is sent to this account (or you use the Google Authenticator code),
* you have 30 seconds to enter this pin code,
* if the code is correct, you are logged in else you are logged out

What this plugin offers:

* Two step verification via IM accounts or Google Authenticator
* Enable or disable the two step verification
* Users themselves activate or disable this feature for them (unless you make it mandatory)
* Reset feature if IM servers are down
* Customize PIN length
* Add a custom message to the IM
* Customize session time validity

== Installation ==

1. Upload the plugin directory to to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create an instant messaging acount (all is explained in the menus of the plugin) and add it
4. Enable the plugin in the 'General settings' page
5. Go to your profile settings (Admin area->Users->Your profile), in the end edit the 'IM Dongle settings' section

== Frequently Asked Questions ==

None at the moment.

== Screenshots ==

1. Normal Wordpress login
2. Selecting IM of choice (to confirm your identity)
3. Two step verification using Google Talk
4. Two step verification using ICQ
5. Two step verification using Windows Live Messenger
6. Two step verification using Google Authenticator
7. Mandatory screen - if administrator chooses that two step verification is mandatory, users have to enter their IM accounts upon first login
8. Disable IM login (if it is not mandatory and IM isn't working)
9. Disable all IM bots (admin security feature)
10. User profile settings page
11. User profile settings page (if two step verification is mandatory)
12. General settings page
13. Google Talk Bot settings page
14. Windows Live Messenger Bot settings page
15. ICQ Bot settings page
16. Reset keys settings page
17. Data management settings page
18. Session management settings page
19. Google Authenticator settings page

== Changelog ==

= 1.2 =
* [New] Added features to session manager - filter by dongle authentication type, user, time
* [New] Added [Authy](https://www.authy.com/ "Authy") as an option
* [Fix] Google Authenticator issue on some installations, wouldn't accept input code
* [Fix] Fixed a CSS issue for version 3.5 of Wordpress
* [Fix] Deleted some obsolete code

= 1.1 =
* [New] Added session manager (logout any user from anywhere)
* [New] Added new login option - Google Authenticator
* [Fix] Now we clear all sessions from the database that have expired when user logs in
* [Fix] Session time settings bug - sometimes it wouldn't update the value

= 1.0 =
* [New] Added ICQ support
* [New] Added Windows Live Messenger support
* [New] You can make the IM Login option mandatory for all users
* Users can now add multiple IM accounts
* Users can now choose the IM account they want to authorize with
* Changed authentication mechanism
* Automatic updater added
* A lot of code rewritten and optimized
* [Fix] Session time validity bug in settings (could accept all characters)
* [Fix] Dongle key length bug in settings (could accept all characters)
* [Fix] Users were able to access auth.php even if they're already logged in
* [Fix] You could enable the IM Login Dongle if none of the bots was configured yet

= 0.3 =
* Added disable codes for all users
* Fixed some little bugs
* Added option to clean all user data from database
* Added session cleaner
* Better menus for plugin administration, now separated from settings tab
* Added option for session time expiration (previously it was 60 minutes), now it is configurable
* Replaced some deprecated functions.

= 0.1 =
* The initial version of the plugin.

== Author ==

The author of this plugin is Bostjan Cigan, visit the [homepage](http://bostjan.gets-it.net "homepage").

== Homepage ==

Visit the [homepage](http://wpplugz.is-leet.com "homepage of im login dongle") of the plugin.

== Future versions ==

In the future all or some of these features might be added:

* [100%] A quick config option
* [Maybe] AIM someday?
* [Maybe] Yahoo messenger someday?

What won't be added:

* Facebook chat support (you can't create a FB chat bot that accepts friend requests automatically)
