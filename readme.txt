=== Contributors Add-on for Open Source Event Calendar ===
Contributors: digitaldonkey
Tags: events, calendar, contributors, user submitted, media privacy
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.4
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Let registered users draft events with images for review: own events and media only, private images until publication.

== Description ==

For calendars where visitors register and submit events, and editors approve them. Requires [Open Source Event Calendar](https://wordpress.org/plugins/open-source-event-calendar/).

Open Source Event Calendar brings the role **Event Contributor**: it drafts events and submits them for review. This add-on adds what such a site needs around it.

**Roles**

* **Event Contributor** (from Open Source Event Calendar): drafts events, publishing needs a review. May edit its own events after they were approved once.
* **Event Publisher** (new): like the Event Contributor, but publishes without review.
* Both may upload images (images only: JPEG, PNG, GIF, WebP, AVIF).
* Promote a user: Users → edit the user → Role → "Event Publisher" (administrators only, as for every role change in WordPress).

**A tidy admin for contributors**

* The event list shows their own events only, with matching counts.
* The media dialog shows their own images only. They can edit their images (title, alt text) and delete them while no published event uses them.
* After login they land on "My events". Dashboard and Media are hidden from the menu; the Dashboard, the media library and the upload page lead to "My events", and "+ New" in the admin bar creates an event. Images are uploaded and chosen inside the event editor.
* A clear default layout of the event editor (until they arrange it themselves).
* A shorter profile page: of the "Personal Options" only the language choice stays (editor, colour scheme, shortcuts and toolbar options are hidden).

**Private images until the event is published**

* Images contributors upload for an unpublished event are not public. Only the uploader and reviewers (Editors, Authors, Administrators) can see them.
* When the event is published, its images (featured image, attached images, images in the text) become normal public images, in all sizes. Links stored earlier keep working.
* Images added to an event that is already published are public at once.

Who counts as a contributor: users who can edit events but not other people's events. Developers can change that with the filter `osec_addon_contributors_applies_to_user`.

== Installation ==

1. Install and activate Open Source Event Calendar.
2. Install and activate this plugin.
3. Check Tools → Site Health: "Private event images cannot be downloaded directly" must be listed as passed.

**Where private images are stored**

By default in the folder `osec_private_files` one level above the WordPress folder, where no web server delivers files. When that folder cannot be created, the plugin uses `wp-content/uploads/osec_private_files/`:

* Apache: protected automatically by an .htaccess file.
* nginx: add the rule shown in the admin notice and in Site Health, for example:

`location ^~ /wp-content/uploads/osec_private_files/ { deny all; }`

As long as the folder can be downloaded from, contributors cannot upload images, and administrators see a notice.

To choose the folder yourself, set an absolute path outside the web root in wp-config.php:

`define( 'OSEC_ADDON_CONTRIBUTORS_PRIVATE_DIR', '/path/outside/webroot/osec_private_files' );`

== Frequently Asked Questions ==

= Can visitors guess the URL of a private image? =

The URL does not help: every request is checked against the logged-in user. Visitors get "403 Forbidden".

= What happens when I deactivate or delete the plugin? =

Deactivating removes the upload right of both contributor roles, the Event Publisher role stays. Deleting turns Event Publishers into Event Contributors and removes the role. Private images stay where they are, without the plugin nobody can open them. To keep them, publish their events before you delete the plugin, or move the files yourself.

= I used an earlier draft of this plugin (folder private_drafts) =

Images in `wp-content/uploads/private_drafts/` are not moved. Clean them up by hand.

== Changelog ==

= 0.1.0 =
* First release.
