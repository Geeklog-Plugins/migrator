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
- CSRF protection
- staged-source purge
- complete autouninstall metadata for plugin-owned tables, group and feature

At this stage, staging and analysis do **not** modify Geeklog destination content.

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
