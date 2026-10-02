# Migrator

Migrator is a Geeklog plugin for recovering content from legacy CMS database dumps into a **fresh Geeklog 2.2.2 installation**.

The project favors simple, auditable recovery over perfect reproduction of the source CMS.

## Target

- Geeklog **2.2.2 only**
- fresh destination installation
- MySQL-compatible database
- administration-only workflow

## Planned source CMS

1. Legacy Geeklog
2. glFusion
3. WordPress

## Current 0.1.0 development state

The `develop-0.1.0` branch currently provides:

- Geeklog 2.2.2 autoinstall metadata
- `migrator.admin` permission and admin group
- isolated persistent storage under the site's `path_data`
- SQL dump upload
- explicit source CMS selection
- safe staging of `CREATE TABLE` and `INSERT INTO` statements
- staging table names under `migrator_src_*`
- blocking/skipping of unrelated SQL statements
- source-structure detection
- recoverable-content counts
- migration job tracking
- source/target ID mapping table
- migration log table
- legacy Geeklog dry run
- legacy Geeklog core migration writer
- glFusion core migration writer using the Geeklog-compatible core path
- glFusion Forum migration for categories, forums, posts, logs, moderators, user preferences, banned IPs and forum profile data
- glFusion MediaGallery migration for albums, media, album relations, queues, playback options, usage tracking, user preferences, watermarks, categories, sessions, sorting, ratings and EXIF settings
- MediaGallery `opacity` -> `wm_opacity` field conversion
- post-migration file manifest for MediaGallery mediaobjects and unsupported Forum attachments
- ID preservation for users, topics, stories, comments and Static Pages when safe
- migration of legacy user profile fields into Geeklog 2.2.2 `user_attributes`
- automatic membership of imported users in the destination core groups
- migration of article/Static Page topic assignments
- preservation of legacy password hashes supported by Geeklog 2.2.2, with normal rehash-on-login behavior
- CSRF protection
- staged-source purge
- complete autouninstall metadata for plugin-owned tables, group and feature

Staging and analysis do **not** modify Geeklog destination content.

For Legacy Geeklog and glFusion core content, the administrator can then run an explicit **Dry run** followed by a confirmed **Migration** action. Migration writes are refused when the destination no longer matches the expected fresh-install baseline.

## Safety model

The uploaded SQL file is stored outside the public web root.

Source tables are recreated under isolated names. Migrator never executes source table names directly against Geeklog destination tables.

The staging importer accepts only the subset needed to recover source tables and rows. Other statements are skipped.

Destination migration will be a separate, explicit action after analysis and dry-run.

## Development rules

Migrator follows the development and administration principles documented in:

- https://github.com/hostellerie/memorandum
- Geeklog 2.2.2 Plugin API conventions
- ACL checks on every administration entry point
- CSRF protection for state-changing actions
- Geeklog `DB_*` database abstraction
- `COM_createHTMLDocument()` page rendering
- templates for significant administration markup
- isolated persistent data storage
- repeatable installation and complete auto-uninstall cleanup

See [ROADMAP.md](ROADMAP.md) for the migration scope and milestones.


## glFusion plugin migration notes

### Forum

The current adapter migrates the compatible Forum data into the current Geeklog Forum tables.

The legacy glFusion tables `ff_attachments`, `ff_bookmarks` and `ff_rating_assoc` do not have direct equivalents in the current target schema and are reported instead of being forced into an unsafe mapping.

### MediaGallery

The database schemas are highly compatible. Most MediaGallery tables migrate through their common columns while preserving IDs and relationships.

Media files are intentionally **not copied by Migrator**. After database migration, the administrator is instructed to copy the complete source `public_html/mediagallery/mediaobjects/` directory to the corresponding Geeklog MediaGallery directory.


## WordPress migration

The current development adapter supports:

- users with deterministic UID remapping when WordPress IDs 1/2 conflict with Geeklog core users
- WordPress categories to Geeklog topics
- posts to Geeklog stories
- pages to Static Pages
- approved regular comments with parent/child relationships
- Geeklog comment tree rebuild after import
- author mapping through the migrated user map
- post/category topic assignments
- manual WordPress uploads copy to `public_html/images/wordpress/`
- URL rewriting for detected WordPress upload URLs inside migrated post/page content

Current limitations:

- WordPress password hashes are not copied; users must set a Geeklog password
- users without an email address require administrator intervention
- WordPress tags are reported but not imported yet
- pingbacks/trackbacks are not imported as comments
- attachment post records and featured-image metadata are not converted yet
- plugin-specific WordPress data is not migrated
