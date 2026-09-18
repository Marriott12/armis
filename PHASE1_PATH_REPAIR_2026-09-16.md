# ARMIS Phase 1 Path Repair — 2026-09-16

## Issue
`admin_branch/includes/auth.php` referenced `admin_branch/shared/session_security.php`, but the shared security helper is located at the application root under `shared/session_security.php`.

## Root cause
The include used:

`dirname(__DIR__) . '/shared/session_security.php'`

From `admin_branch/includes/auth.php`, that resolves to:

`Armis2/admin_branch/shared/session_security.php`

The correct application-root path is:

`dirname(dirname(__DIR__)) . '/shared/session_security.php'`

## Fix
Corrected the include path only. No security behavior was weakened and no Apache permissions were changed.

## Validation
PHP lint passed for the repaired authentication file and the shared session helpers.
