# Contributors Add-on for Open Source Event Calendar

Let registered users draft events with images for review, in a calendar run with
[Open Source Event Calendar](https://wordpress.org/plugins/open-source-event-calendar/) (OSEC).
What it does for site owners is described in [`readme.txt`](readme.txt) (the WordPress.org page).
This file is for developers.

- WordPress 7.1+, PHP 8.4+, requires OSEC (`Requires Plugins` header)
- License: GPL-3.0-or-later

## How it works

One check decides who is restricted (`src/Restriction.php`): users who **can** `edit_osec_events` but
**not** `edit_others_osec_events`. That is OSEC's `osec_event_assistant` and this plugin's
`osec_event_publisher`. Filter: `osec_addon_contributors_applies_to_user( bool $applies, WP_User $user )`.

```
osec-addon-contributors.php   header, class loader for src/ (no vendor/ needed at runtime), boot
uninstall.php                 publishers → Event Contributor, role removed
src/
├── Plugin.php                registers every part
├── Restriction.php           the check above
├── Admin/                    the restricted admin
│   ├── Roles.php             osec_event_publisher; upload_files for both roles
│   ├── OwnEvents.php         event list + status counts: own events only
│   ├── OwnMedia.php          media dialog, REST, upload.php: own uploads; edit/delete own images
│   ├── AdminMenu.php         Dashboard + Media hidden, "+ New" → event, "My events"
│   ├── PageRedirect.php      after login, Dashboard, Media library, upload page → event list
│   ├── Uploads.php           image types only
│   ├── EditorLayout.php      default box order / hidden boxes (defaults, nothing stored)
│   ├── MediaDialog.php       media dialog without gallery, playlists, insert from URL
│   └── ProfilePage.php       own profile: "Personal Options" except language hidden (CSS), no application passwords
└── PrivateUploads/           images of unpublished events
    ├── Storage.php           private folder, which attachments are private, deleting their files
    ├── StorageCheck.php      fail closed: test download, admin notice, Site Health
    ├── UploadRouter.php      restricted users' uploads → private folder
    ├── Endpoint.php          ?osec_private_file=<ID>/<file>: uploader + reviewers, 403/404, 301 once public
    ├── UrlFilter.php         endpoint URLs for private images; hidden from REST for visitors
    └── Publisher.php         on publish: images → uploads/<Y>/<m>/, text URLs rewritten
```

Private images: files in the private folder plus the post meta `_osec_addon_contributors_private`.
The folder is chosen once and remembered in the option `osec_addon_contributors_private_root`
(constant `OSEC_ADDON_CONTRIBUTORS_PRIVATE_DIR` overrides it).

**DDEV:** the default folder `/var/www/osec_private_files` is outside the project mount, so it is
emptied on `ddev restart`. Fine for testing; for a lasting local setup point the constant to a
mounted folder and protect it with the nginx rule from the readme.

## Running the tests

The tests use WordPress's own test tooling, as created by `wp scaffold plugin-tests`:
the WordPress test library (`WP_UnitTestCase`, factories for users and posts) on a separate
database, which is emptied on every run.

```bash
composer install
bash bin/install-wp-tests.sh wordpress_tests root root db latest
vendor/bin/phpunit
```

1. `composer install` installs the development tools into `vendor/`: PHPUnit, the PHPUnit Polyfills
   (required by the WordPress test library) and the coding standards.
2. `bin/install-wp-tests.sh <db-name> <db-user> <db-password> <db-host> <wp-version>` downloads
   WordPress to `/tmp/wordpress/` and the test library (from the GitHub mirror `WordPress/wordpress-develop`,
   no Subversion needed) to `/tmp/wordpress-tests-lib/`, writes the
   database credentials into `wp-tests-config.php` and creates the database. Run it once; again after
   the temp folder was emptied (a DDEV restart does that). The values above are DDEV's.
3. `vendor/bin/phpunit` runs every `tests/test-*.php`. `tests/bootstrap.php` loads the test library,
   then OSEC and this plugin, and creates the roles as activation would.

OSEC is loaded from the sibling folder `../open-source-event-calendar`. Elsewhere set
`OSEC_PLUGIN_DIR` to an OSEC folder, e.g. the unzipped release from WordPress.org (as CI does).

A single test: `vendor/bin/phpunit --filter test_endpoint_access`.

## Coding standard

```bash
vendor/bin/phpcs    # check (WordPress Coding Standards, .phpcs.xml.dist)
vendor/bin/phpcbf   # fix what can be fixed automatically
```

## Translations

English source strings, German bundled in `languages/`. After changing strings:

```bash
wp i18n make-pot . languages/osec-addon-contributors.pot --exclude=vendor,tests
wp i18n update-po languages/osec-addon-contributors.pot languages/
wp i18n make-mo languages/
```

## CI

`.github/workflows/testing.yml` runs the tests on GitHub Actions for every push and pull request.

## Releasing

Pushing a version tag publishes a GitHub release (`.github/workflows/release.yml`):

1. Set the new version in three places: the `Version` header and `OSEC_ADDON_CONTRIBUTORS_VERSION` in
   `osec-addon-contributors.php`, `Stable tag` in `readme.txt` (plus a changelog entry there). Commit.
2. `git tag 0.2.0 && git push origin 0.2.0` (a leading `v` works too).
3. The workflow checks the tag against the three versions (`bin/check-version.sh`), runs the tests,
   builds the zip (`bin/build-zip.sh`, everything except `.distignore`) and creates the release with it.

Build the zip locally: `bash bin/build-zip.sh 0.2.0` → `build/osec-addon-contributors-0.2.0.zip`.
