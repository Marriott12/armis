Admin Import & Rank Tools
=========================

This document describes the new admin import options and rank update tools.

Features
- Import options per-upload: `Skip` (default), `Update`, `Merge`.
- Dry-run support: preview of inserts/updates/skips without applying DB changes.
- Admin Rank Tools page: run `general_update_rankid.php`, download backups, restore from backup.
- Migration helper: `tools/migration_add_indexes.php` to add a unique index on `staff.svcNo` and an index on `staff.email` (safe-guarded by duplicate checks).

Usage
- Admin page: `/Armis2/admin_branch/admin_rank_tools.php`
- Run general update from CLI: `php tools/general_update_rankid.php`
- Restore backup from CLI: `php tools/restore_rank_backup.php /full/path/to/backup.csv`
- Report unmapped ranks: `php tools/report_unmapped_ranks.php`
- Add indexes (careful - will abort if duplicates exist): `php tools/migration_add_indexes.php`

Notes
- Dry-run currently does not apply `tempRank` -> `rankId` updates; rank update runs must be executed explicitly.
- Backups are written to `tmp/` and should be archived off-server after running sensitive operations.
