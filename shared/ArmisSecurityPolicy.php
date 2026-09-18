<?php
/**
 * Pure security-policy helpers. No database/session side effects.
 */
final class ArmisSecurityPolicy
{
    public static function isValidReturnUrl(string $url): bool
    {
        if (strpos($url, '/Armis2/') !== 0) return false;
        if (preg_match('#^https?://#i', $url)) return false;
        if (preg_match('/[<>"\'()]/', $url)) return false;
        return true;
    }

    public static function accountMayAuthenticate(?string $status): bool
    {
        return $status === 'Active';
    }

    public static function requiresPasswordChange($isFirstLogin): bool
    {
        return (int)$isFirstLogin === 1;
    }
}
