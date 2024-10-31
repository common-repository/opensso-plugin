=== OpenSSO ===
Contributors: pairg
Donate link: http://kir-dev.sch.bme.hu/wp-opensso
Tags: sso, opensso, login, logout, group, user, users, admin, authentication, registration
Requires at least: 2.7.0
Tested up to: 2.8.4
Stable tag: 1.0

Authenticate users using  Sun's Open Single Sign On service.

== Description ==

Functions:

* Simple LogIn (one click)
* Simple LogOut (one click)
* Create user automatically
* Update user's profile with the informations that came from Identity Provider
* Protect the user's profile, that the user can't modificate the informations that came from Identity Provider
* Syncronize the LogIn and LogOut actions between the WordPress and the Identity Provider    
* If the (Apache) agent have a long update period you can choose what to do the plugin: go to login URL or LogIn.
* SSL support
* Rules: If a regular expression match to the $_SERVER variable's element put the user to the group.
* Plus plugin: Simple Group Manager - Manage the user's groups and the group's roles.
* Admin page: Users / SSO Autentikáció

For more infromation please read this: http://kir-dev.sch.bme.hu/wp-opensso

If you don't now how it's work: https://redmine.kirdev.sch.bme.hu:8081/wiki/wordpress/OpenSSO_autentikacio (in Hungarian)

== Installation ==

1. Upload `opensso-plugin` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Users / SSO Autentikáció admin page to set up the plugin
4. Go to Users / Csoportok admin page to set up the user's groups
5. Place `<a href="<?php wp_login_url(get_permalink()); ?>">SSO Login</a>` in your templates
6. Place `<a href="<?php wp_logout_url(get_permalink()); ?>">SSO Logout</a>` in your templates

== Changelog ==

= 1.0 =
* Default functions

== Frequently Asked Questions ==

= What means SSO? =

Single Sign On. You can use one user name and password for all web application that support this function.

== Screenshots ==

1. SSO admin page.
2. Groups admin page
