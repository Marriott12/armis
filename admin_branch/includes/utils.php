<?php
/**
 * ARMIS Admin Branch Utility Functions
 * Common utility functions for admin branch operations
 */

// Include required files
require_once __DIR__ . '/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

/**
 * Load dropdown options for forms
 */
class AdminBranchUtils {
    
    /**
     * Get all ranks for dropdowns
     */
    public static function getRanks($excludeSpecial = true) {
        $sql = "SELECT id, name, level, abbreviation FROM ranks ORDER BY level ASC";
        $ranks = fetchAll($sql);
        
        if ($excludeSpecial) {
            $ranks = array_filter($ranks, function($rank) {
                return !isRankExcluded($rank->rankName);
            });
        }
        
        return $ranks;
    }
    
    /**
     * Get all units for dropdowns
     */
    public static function getUnits() {
        $sql = "SELECT id, name, code FROM units ORDER BY name ASC";
        return fetchAll($sql);
    }
    
    /**
     * Get all corps for dropdowns
     */
    public static function getCorps() {
        $sql = "SELECT corpsID, corpsName, corpsCode FROM corps ORDER BY corpsName ASC";
        return fetchAll($sql);
    }
    
    /**
     * Get all appointments for dropdowns
     */
    public static function getAppointments() {
        $sql = "SELECT appointmentID, appointmentName, appointmentCode FROM appointments ORDER BY appointmentName ASC";
        return fetchAll($sql);
    }
    
    /**
     * Get all medals for dropdowns
     */
    public static function getMedals() {
        $sql = "SELECT medalID, medalName, medalCode, medalType FROM medals ORDER BY medalType ASC, medalName ASC";
        return fetchAll($sql);
    }
    
    /**
     * Get staff member by service number
     */
    public static function getStaffByServiceNumber($svcNo) {
        $sql = "SELECT s.*, r.rankName, r.rankAbbr, r.rankIndex, u.unitName, c.corpsName, a.appointmentName
                FROM staff s
                LEFT JOIN ranks r ON s.rankID = r.rankID
                LEFT JOIN units u ON s.unitID = u.unitID
                LEFT JOIN corps c ON s.corpsID = c.corpsID
                LEFT JOIN appointments a ON s.appointmentID = a.appointmentID
                WHERE s.svcNo = ?";
        return fetchOne($sql, [$svcNo]);
    }
    
    /**
     * Get staff list with filters
     */
    public static function getStaffList($filters = [], $limit = 25, $offset = 0) {
        $where = [];
        $params = [];
        
        // Build WHERE conditions
        if (!empty($filters['search'])) {
            $where[] = "(s.svcNo LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['rankID'])) {
            $where[] = "s.rankID = ?";
            $params[] = $filters['rankID'];
        }
        
        if (!empty($filters['unitID'])) {
            $where[] = "s.unitID = ?";
            $params[] = $filters['unitID'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        // Exclude special ranks
        $excludedRanks = EXCLUDED_RANKS;
        if (!empty($excludedRanks)) {
            $placeholders = str_repeat('?,', count($excludedRanks) - 1) . '?';
            $where[] = "r.rankName NOT IN ($placeholders)";
            $params = array_merge($params, $excludedRanks);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT s.*, r.rankName, r.rankAbbr, r.rankIndex, u.unitName, c.corpsName
                FROM staff s
                LEFT JOIN ranks r ON s.rankID = r.rankID
                LEFT JOIN units u ON s.unitID = u.unitID
                LEFT JOIN corps c ON s.corpsID = c.corpsID
                $whereClause
                ORDER BY r.rankIndex ASC, s.lname ASC, s.fname ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return fetchAll($sql, $params);
    }
    
    /**
     * Get staff count with filters
     */
    public static function getStaffCount($filters = []) {
        $where = [];
        $params = [];
        
        // Build WHERE conditions (same as getStaffList)
        if (!empty($filters['search'])) {
            $where[] = "(s.svcNo LIKE ? OR s.fname LIKE ? OR s.lname LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['rankID'])) {
            $where[] = "s.rankID = ?";
            $params[] = $filters['rankID'];
        }
        
        if (!empty($filters['unitID'])) {
            $where[] = "s.unitID = ?";
            $params[] = $filters['unitID'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        
        // Exclude special ranks
        $excludedRanks = EXCLUDED_RANKS;
        if (!empty($excludedRanks)) {
            $placeholders = str_repeat('?,', count($excludedRanks) - 1) . '?';
            $where[] = "r.rankName NOT IN ($placeholders)";
            $params = array_merge($params, $excludedRanks);
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) as total
                FROM staff s
                LEFT JOIN ranks r ON s.rankID = r.rankID
                $whereClause";
        
        $result = fetchOne($sql, $params);
        return $result ? $result->total : 0;
    }
    
    /**
     * Generate next service number
     */
    public static function generateServiceNumber() {
        $sql = "SELECT MAX(CAST(SUBSTRING(svcNo, 3) AS UNSIGNED)) as max_num 
                FROM staff 
                WHERE svcNo REGEXP '^AR[0-9]+$'";
        $result = fetchOne($sql);
        
        $nextNum = ($result && $result->max_num) ? $result->max_num + 1 : 1;
        return 'AR' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Validate service number format
     */
    public static function validateServiceNumber($svcNo) {
        return preg_match('/^AR\d{6}$/', $svcNo);
    }
    
    /**
     * Check if service number exists
     */
    public static function serviceNumberExists($svcNo, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM staff WHERE svcNo = ?";
        $params = [$svcNo];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = fetchOne($sql, $params);
        return $result && $result->count > 0;
    }
    
    /**
     * Get dashboard statistics
     */
    public static function getDashboardStats() {
        $stats = [];
        
        // Total personnel
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status != 'Discharged'");
        $stats['total_personnel'] = $result ? $result->total : 0;
        
        // Active personnel
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status = 'Active'");
        $stats['active_personnel'] = $result ? $result->total : 0;
        
        // New recruits (last 30 days)
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['new_recruits'] = $result ? $result->total : 0;
        
        // On leave
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status = 'On Leave'");
        $stats['on_leave'] = $result ? $result->total : 0;
        
        // Training
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status = 'Training'");
        $stats['training'] = $result ? $result->total : 0;
        
        // Deployed
        $result = fetchOne("SELECT COUNT(*) as total FROM staff WHERE status = 'Deployed'");
        $stats['deployed'] = $result ? $result->total : 0;
        
        return $stats;
    }
    
    /**
     * Get recent activities
     */
    public static function getRecentActivities($limit = 10) {
        $sql = "SELECT * FROM activity_log 
                ORDER BY created_at DESC 
                LIMIT ?";
        return fetchAll($sql, [$limit]);
    }
    
    /**
     * Format military name using utility
     */
    public static function formatMilitaryName($staff) {
        if (is_object($staff)) {
            $rankAbbr = $staff->rankAbbr ?? '';
            $fname = $staff->fname ?? '';
            $lname = $staff->lname ?? '';
            $category = getRankCategory($staff->rankIndex ?? 0);
        } else {
            $rankAbbr = $staff['rankAbbr'] ?? '';
            $fname = $staff['fname'] ?? '';
            $lname = $staff['lname'] ?? '';
            $category = getRankCategory($staff['rankIndex'] ?? 0);
        }
        
        // Use global military formatting function
        if (function_exists('formatMilitaryName')) {
            return formatMilitaryName($rankAbbr, $fname, $lname, $category);
        }
        
        // Fallback formatting
        if ($category === 'Officer') {
            return $rankAbbr . ' ' . substr($fname, 0, 1) . '.' . substr($fname, 1, 1) . '. ' . $lname;
        } else {
            return $rankAbbr . ' ' . $lname . ' ' . substr($fname, 0, 1) . '.';
        }
    }
    
    /**
     * Create pagination array
     */
    public static function createPagination($currentPage, $totalRecords, $perPage = 25) {
        $totalPages = ceil($totalRecords / $perPage);
        $pagination = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
            'offset' => ($currentPage - 1) * $perPage
        ];
        
        // Generate page numbers for display
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        $pagination['page_numbers'] = range($start, $end);
        
        return $pagination;
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate required fields
     */
    public static function validateRequired($data, $required_fields) {
        $errors = [];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        return $errors;
    }
}
