# Migrator synthetic database fixtures

These dumps contain **synthetic test data only**. They are intended for destructive migration testing on a disposable fresh Geeklog 2.2.2 installation.

## Files

### geeklog_legacy_test.sql

Exercises:

- legacy Geeklog detection
- users 1/2 preservation and users 3/4 import
- legacy user profile tables
- topics
- stories with legacy `tid` topic field
- nested comments
- Static Pages

Destination dependency before migration:

- `staticpages` installed and active

Expected source highlights:

- 4 users
- 2 topics
- 2 stories
- 2 comments
- 1 Static Page

### glfusion_test.sql

Exercises:

- glFusion detection
- core Geeklog-compatible content
- Static Pages
- Forum categories/forums/posts/preferences
- unsupported Forum attachment reporting
- MediaGallery album/media/album relation
- `opacity` -> `wm_opacity` conversion
- media copy manifest

Destination dependencies before migration:

- `staticpages` installed and active
- `forum` installed and active
- `mediagallery` installed and active

Expected source highlights:

- 3 users
- 1 topic
- 1 story
- 1 comment
- 1 Static Page
- 1 Forum category
- 1 Forum
- 2 Forum posts
- 1 unsupported Forum attachment record
- 1 MediaGallery album
- 1 MediaGallery media item

### wordpress_test.sql

Exercises:

- WordPress detection with a custom prefix (`demo_`)
- WordPress user ID collision remapping for IDs 1 and 2
- users with and without email
- categories and nested categories
- posts
- page -> Static Pages
- category assignments
- tag detection/reporting
- approved comments and nested reply
- pending comment exclusion
- pingback exclusion
- attachment and revision exclusion
- WordPress uploads URL rewriting

Destination dependency before migration:

- `staticpages` installed and active

Expected source highlights:

- 4 WordPress users
- 2 posts
- 1 page
- 1 attachment
- 1 revision
- 3 categories
- 1 tag
- 2 approved normal comments
- 1 pingback
- 1 pending comment

## Usage

1. Install Migrator on a disposable fresh Geeklog 2.2.2 installation.
2. Install and enable the destination plugins listed above before the real migration.
3. Upload one of the extracted `.sql` files to Migrator.
4. Review the preflight and plugin dependency warnings.
5. Run the dry run.
6. Run the migration.
7. Inspect the result.
8. Use **Reset test installation**, type `RESET MIGRATOR`, and confirm the destructive reset.
9. Test the next fixture or a new Migrator build.

The generated ZIP archives are convenience packages. Migrator 1.0.0 currently accepts the extracted `.sql` file, not the ZIP directly.
