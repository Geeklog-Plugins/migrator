# Migrator Roadmap

Migrator is a Geeklog plugin designed to import recoverable content from legacy CMS databases into a **fresh Geeklog 2.2.2 installation**.

The project deliberately favors **simple, reliable data recovery** over perfect reproduction of the source CMS.

## Project goals

- Import data from a database copy rather than requiring access to the original CMS installation.
- Target a fresh Geeklog 2.2.2 installation to reduce conflicts and simplify ID preservation. Compatibility with older Geeklog destination versions is intentionally out of scope.
- Recover as much useful content as possible, even when some source features cannot be reproduced.
- Keep migrations modular so one failing content type does not block the rest.
- Clearly report what was imported, skipped, transformed, or still requires manual action.
- Keep media file handling simple: migrate database references, then instruct the administrator where source files must be copied.
- Make it possible to add new CMS source adapters later without rewriting the migration engine.

## Priority source platforms

### 1. Legacy Geeklog

Support migration from older Geeklog installations to Geeklog 2.2.2.

Initial targets:

- users
- groups and permissions where practical
- topics
- stories
- comments
- static pages
- links
- polls
- calendar events
- plugin data when a compatible current plugin exists
- story images and other referenced files through post-migration file verification

Goals:

- preserve existing Geeklog IDs whenever safe
- preserve story IDs, authors, dates, topics, comments, and relationships
- detect schema differences between Geeklog versions
- support partial recovery from incomplete or damaged databases

### 2. glFusion

Support recovery from glFusion databases, taking advantage of its Geeklog ancestry.

Initial targets:

- users
- topics
- stories
- comments
- static pages
- links
- polls
- calendar events
- forum data
- MediaGallery data

Potential later targets:

- FileMgmt data when a suitable Geeklog destination exists
- groups and permissions
- additional compatible plugin data

Unsupported glFusion-specific features should not block migration.

### 3. WordPress

Provide a practical exit path for users who want to move content from WordPress to Geeklog.

Initial targets:

- users
- posts
- pages
- categories
- tags
- comments
- authors
- publication dates
- slugs
- featured images and media references
- basic URL mapping information for redirects

Later targets:

- custom post types where a reasonable Geeklog destination exists
- selected metadata
- attachment metadata
- taxonomy mapping
- common content shortcodes when conversion is safe

WordPress plugins and theme-specific data are out of scope by default unless a dedicated adapter is added.

---

# Migration workflow

## Phase 1 — Source selection

Administrator selects the source CMS:

- Legacy Geeklog
- glFusion
- WordPress
- future adapters

The plugin should also attempt to detect the source automatically from known tables and schema signatures.

## Phase 2 — Database import

The administrator uploads a database dump:

- .sql
- optionally .sql.gz later

The dump is imported into isolated temporary/source tables.

The source database must never overwrite Geeklog tables directly.

## Phase 3 — Analysis

Migrator scans the imported database and reports:

- detected CMS
- detected source version when possible
- table prefix
- available content types
- record counts
- supported content
- partially supported content
- unsupported content
- detected plugins or extensions
- likely media directories
- warnings and inconsistencies

Example:

```text
Source: glFusion 1.x

Users              842   supported
Stories          1,246   supported
Comments         3,810   supported
Static Pages        96   supported
Forum            6,421   supported
MediaGallery     3,842   supported
SiteTailor           -   unsupported
```

## Phase 4 — Migration selection

Administrator chooses which detected content types to migrate.

Each content type must be independently selectable.

A failure in one module must not stop unrelated modules.

## Phase 5 — Dry run

Before writing data, Migrator should offer a dry run showing:

- source records
- importable records
- skipped records
- conflicts
- missing relationships
- unsupported fields
- expected ID preservation or remapping

## Phase 6 — Import

Import selected content into the fresh Geeklog installation.

Where possible:

- preserve IDs
- preserve authors
- preserve dates
- preserve relationships
- preserve story IDs and slugs
- preserve counters where useful

When IDs cannot safely be preserved, maintain an internal source-to-target ID map.

## Phase 7 — Media instructions

Migrator does not need to retrieve files from the old server.

Instead it should display clear copy instructions for required directories.

Example:

```text
Copy source files from:

glFusion/public_html/mediagallery/mediaobjects/

to:

Geeklog/public_html/mediagallery/mediaobjects/
```

After files are copied, Migrator should be able to verify referenced files and report missing media.

## Phase 8 — Verification report

After migration, display a detailed summary:

```text
Users
842 / 842 imported

Stories
1,245 / 1,246 imported
1 skipped

Comments
3,798 / 3,810 imported
12 skipped

MediaGallery
3,842 database records imported
3,794 files found
48 files missing
```

The report should be exportable or downloadable.

---

# Architecture

Keep the initial implementation small and modular.

Suggested structure:

```text
migrator/
├── adapters/
│   ├── geeklog.php
│   ├── glfusion.php
│   └── wordpress.php
├── classes/
│   ├── Migration.php
│   ├── DatabaseImport.php
│   ├── MigrationMap.php
│   ├── MigrationReport.php
│   └── MediaVerifier.php
├── admin/
└── migration.php
```

Each source adapter should be responsible for:

- source detection
- version detection
- schema inspection
- source queries
- source-to-Geeklog field mapping
- source-specific cleanup

The migration engine should remain independent from source CMS details.

---

# Data safety

Migrator must be designed for a **fresh Geeklog 2.2.2 installation**.

Before migration it should verify that the destination does not already contain significant user or content data.

If existing content is detected:

- show a warning
- do not silently overwrite it
- advanced merge/remapping support may be considered later

Source tables must remain isolated from Geeklog destination tables.

Migrator should never modify the uploaded source database copy.

---

# ID strategy

For fresh installations, preserving source identifiers should be preferred whenever compatible.

Priority:

1. preserve user IDs where safe
2. preserve story IDs and content identifiers
3. preserve relationships between imported records
4. remap only when required

When remapping is required, keep a migration mapping table containing:

- source CMS
- entity type
- source ID
- target ID

---

# Passwords

Password migration must be handled conservatively.

For each source:

- detect known password hash formats
- preserve hashes only when Geeklog can safely validate them
- otherwise require a password reset
- never weaken Geeklog password security for compatibility

---

# URLs and redirects

Where source URLs can be determined, Migrator should record enough information to help preserve SEO and inbound links.

Later versions may generate:

- redirect maps
- Apache rewrite rules
- Nginx redirect rules

Priority should be given to:

- article/post URLs
- pages
- category/topic URLs
- forum topics when possible
- media pages when possible

---

# Error handling

Migration must be fault tolerant.

A malformed record should normally be:

- skipped
- logged
- reported

It should not abort the complete migration unless database integrity would be at risk.

Migration should be resumable where practical.

---

# Development milestones

## 0.1.0 — Migration foundation\n\nDestination compatibility: Geeklog 2.2.2 only.

- Geeklog plugin skeleton
- fresh-installation safety check
- database dump import
- source CMS detection
- adapter interface
- migration report infrastructure
- dry-run mode
- migration log
- source/target ID mapping

Initial source support:

- legacy Geeklog
- glFusion

Initial content support:

- users
- topics
- stories
- comments
- static pages
- links

## 0.2.0 — Geeklog and glFusion extended content

Current development status: the initial glFusion Forum and MediaGallery database migration path has been implemented on `develop-0.1.0`. Unsupported Forum attachment/bookmark/rating tables are reported rather than force-mapped.

- polls
- calendar
- groups and permissions where safe
- forum migration
- MediaGallery migration
- media path reporting
- missing-file verification
- improved partial migration recovery

## 0.3.0 — WordPress support

Current development status: an initial WordPress migration writer is already implemented on `develop-0.1.0` for users, categories, posts, pages and approved comments. Media attachment records, tags and plugin-specific data remain future work.

- WordPress detection
- users
- posts
- pages
- categories
- tags
- comments
- authors and dates
- slugs
- featured image/media references
- WordPress-to-Geeklog content mapping report

## 0.4.0 — Migration quality and redirects

- URL mapping
- redirect export
- improved password compatibility handling
- resumable migrations
- duplicate/conflict detection
- downloadable migration report
- better media verification

## 1.0.0 — Stable migration plugin

- tested legacy Geeklog migration
- tested glFusion migration
- tested WordPress migration
- documented supported versions
- documented unsupported features
- recovery and rollback guidance
- stable adapter API for future CMS sources

---

# Future possibilities

Possible additional source adapters may include other abandoned or legacy CMS platforms when there is a realistic need and enough source data can be mapped safely.

The project should avoid becoming a universal CMS converter. New adapters should only be added when they provide a practical path for users to recover valuable content into Geeklog.
