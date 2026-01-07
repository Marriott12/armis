<?php
/**
 * Rank Level System Configuration
 * 
 * This file defines the rank level system used across ARMIS2
 * 
 * RANK LEVELS:
 * - Levels 1-13:  Officers
 * - Level 14:     Officer Cadets  
 * - Levels 15-26: Non-Commissioned Officers (NCOs)
 * - Level 27:     Recruits
 * - Level 28:     Civilian Employees
 * 
 * SENIORITY:
 * - Lower level number = Higher rank (for Officers: Level 1 is highest, Level 13 is lowest)
 * - Higher level number = Lower rank (for NCOs: Level 15 is highest NCO, Level 26 is lowest NCO)
 * - Level 27 (Recruits) is the lowest military rank
 * - Level 28 (Civilian Employees) is outside military hierarchy
 * 
 * CATEGORIES:
 * - Officer: Commissioned officers (Levels 1-13)
 * - Officer Cadet: Training to become officers (Level 14)
 * - NCO: Non-Commissioned Officers including Warrant Officers (Levels 15-26)
 * - Recruit: New enlisted personnel in basic training (Level 27)
 * - Civilian Employee / CE: Non-military staff (Level 28)
 */

// Rank level ranges
define('RANK_OFFICER_MIN', 1);
define('RANK_OFFICER_MAX', 13);
define('RANK_OFFICER_CADET', 14);
define('RANK_NCO_MIN', 15);
define('RANK_NCO_MAX', 26);
define('RANK_RECRUIT', 27);
define('RANK_CIVILIAN', 28);

/**
 * Get the category for a given rank level
 * 
 * @param int $level The rank level
 * @return string The category name ('Officer', 'Officer Cadet', 'NCO', 'Recruit', 'Civilian Employee')
 */
function getRankCategory($level) {
    $level = (int)$level;
    
    if ($level >= RANK_OFFICER_MIN && $level <= RANK_OFFICER_MAX) {
        return 'Officer';
    } elseif ($level == RANK_OFFICER_CADET) {
        return 'Officer Cadet';
    } elseif ($level >= RANK_NCO_MIN && $level <= RANK_NCO_MAX) {
        return 'NCO';
    } elseif ($level == RANK_RECRUIT) {
        return 'Recruit';
    } elseif ($level == RANK_CIVILIAN) {
        return 'Civilian Employee';
    }
    
    return 'Unknown';
}

/**
 * Get valid promotion range for a given rank level
 * 
 * @param int $currentLevel Current rank level
 * @param string $promotionType 'promotion' or 'reversion'
 * @return array Array of valid levels for promotion/reversion
 */
function getValidPromotionRange($currentLevel, $promotionType = 'promotion') {
    $category = getRankCategory($currentLevel);
    
    switch ($category) {
        case 'Officer':
            return range(RANK_OFFICER_MIN, RANK_OFFICER_MAX);
            
        case 'Officer Cadet':
            // Officer Cadets can promote to Officers or revert to Cadet
            return $promotionType === 'promotion' 
                ? range(RANK_OFFICER_MIN, RANK_OFFICER_MAX)
                : [RANK_OFFICER_CADET];
                
        case 'NCO':
            return range(RANK_NCO_MIN, RANK_NCO_MAX);
            
        case 'Recruit':
            // Recruits can promote to NCO or stay as Recruit
            return $promotionType === 'promotion'
                ? range(RANK_NCO_MIN, RANK_NCO_MAX)
                : [RANK_RECRUIT];
                
        case 'Civilian Employee':
            // Civilians don't have military promotions
            return [RANK_CIVILIAN];
            
        default:
            return [];
    }
}

/**
 * Check if a rank level is within a specific category
 * 
 * @param int $level Rank level
 * @param string $category Category to check ('Officer', 'NCO', 'Civilian Employee', etc.)
 * @return bool True if level belongs to category
 */
function isRankInCategory($level, $category) {
    return getRankCategory($level) === $category;
}

/**
 * Get SQL WHERE clause for filtering by rank category
 * 
 * @param string $category Category name
 * @param string $tableAlias Table alias for rank table (default 'r')
 * @return string SQL WHERE condition
 */
function getRankCategorySQL($category, $tableAlias = 'r') {
    switch ($category) {
        case 'Officer':
            return "$tableAlias.level BETWEEN " . RANK_OFFICER_MIN . " AND " . RANK_OFFICER_MAX;
            
        case 'Officer Cadet':
            return "$tableAlias.level = " . RANK_OFFICER_CADET;
            
        case 'NCO':
            return "$tableAlias.level BETWEEN " . RANK_NCO_MIN . " AND " . RANK_NCO_MAX;
            
        case 'Recruit':
            return "$tableAlias.level = " . RANK_RECRUIT;
            
        case 'Civilian Employee':
        case 'CE':
            return "$tableAlias.level = " . RANK_CIVILIAN;
            
        default:
            return "1=1"; // No filter
    }
}

/**
 * Get SQL CASE statement to derive category from level
 * 
 * @param string $tableAlias Table alias for rank table (default 'r')
 * @return string SQL CASE expression
 */
function getRankCategoryCaseSQL($tableAlias = 'r') {
    return "CASE 
        WHEN $tableAlias.level BETWEEN " . RANK_OFFICER_MIN . " AND " . RANK_OFFICER_MAX . " THEN 'Officer'
        WHEN $tableAlias.level = " . RANK_OFFICER_CADET . " THEN 'Officer Cadet'
        WHEN $tableAlias.level BETWEEN " . RANK_NCO_MIN . " AND " . RANK_NCO_MAX . " THEN 'NCO'
        WHEN $tableAlias.level = " . RANK_RECRUIT . " THEN 'Recruit'
        WHEN $tableAlias.level = " . RANK_CIVILIAN . " THEN 'CE'
        ELSE 'Unknown'
    END";
}

?>
