<?php
/**
 * ARMIS RBAC compatibility shim.
 *
 * This file previously defined its OWN copy of ARMIS_ROLES and its own
 * hasModuleAccess()/requireModuleAccess()/etc., separate from
 * shared/rbac.php. Because both used function_exists()/defined() guards,
 * whichever file happened to load FIRST silently won — and shared/header.php
 * included this file directly, so any page that reached header.php without
 * first requiring rbac.php was running on a different, out-of-date set of
 * roles and modules than the rest of the app.
 *
 * Fix: this file now does nothing but load the canonical rbac.php. Nothing
 * else needs to change — every function this file used to define is still
 * callable under the same name, from the same canonical implementation.
 */

require_once __DIR__ . '/rbac.php';
