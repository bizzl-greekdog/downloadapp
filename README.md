Download App
============

This simple app allows to externalize image downloading, including scanning certain pages for metadata.
Note that it has no user management or authentication, and is designed to utilize a websocket connection with a firefox
addon to add files, as it's mostly designed for my own usage in a local network.

Setup
=====

 * Clone this repository
 * Execute `composer install` in it's root
 * Execute `npm install` in the _scanner/_ directory
 * Insert any user accounts into the _parameters.yml_
 * Add cronjobs for `app/console app:download` and `app/console app:scan`
 * Execute `app/console app:listen` as a background task
