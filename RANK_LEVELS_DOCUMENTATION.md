# ARMIS2 Rank Level System Documentation

## Overview
This document describes the rank level system implemented across the ARMIS2 codebase.

## Rank Level Structure

### Level Ranges and Categories

| Levels  | Category            | Description                                    |
|---------|---------------------|------------------------------------------------|
| 1-13    | Officers            | Commissioned officers (highest to lowest rank) |
| 14      | Officer Cadets      | Training to become commissioned officers       |
| 15-26   | NCOs                | Non-Commissioned Officers (highest to lowest)  |
| 27      | Recruits            | New enlisted personnel in basic training       |
| 28      | Civilian Employees  | Non-military administrative and support staff  |

### Seniority System

**Important:** Lower level number = Higher rank

#### Officers (Levels 1-13)
- Level 1  = Highest officer rank (e.g., General, Field Marshal)
- Level 13 = Lowest officer rank (e.g., Second Lieutenant)

#### NCOs (Levels 15-26)
- Level 15 = Highest NCO rank (e.g., Warrant Officer Class 1)
- Level 26 = Lowest NCO rank (e.g., Private)

#### Special Levels
- Level 14 = Officer Cadets (in training, between enlisted and officer)
- Level 27 = Recruits (lowest military rank, in basic training)
- Level 28 = Civilian Employees (outside military hierarchy)

### Seniority Ordering in SQL

The standard seniority query pattern is:

```sql
ORDER BY r.level ASC, s.subWef ASC, s.tempWef ASC, s.attestDate ASC, s.svcNo ASC
```

**Explanation:**
1. `r.level ASC` - Primary sort by rank level (ascending = highest rank first)
2. `s.subWef ASC` - Substantive rank effective date (earlier = more senior)
3. `s.tempWef ASC` - Temporary rank effective date
4. `s.attestDate ASC` - Attestation/enlistment date (earlier = more senior)
5. `s.svcNo ASC` - Service number (lower = earlier enlisted)

This ensures that:
- Higher ranks appear first
- Within same rank, those promoted earlier are more senior
- Seniority is properly maintained across all categories

## Promotion System

### Valid Promotion Ranges

- **Officers (1-13)**: Can promote/demote within levels 1-13
- **Officer Cadets (14)**: Can promote to Officer ranks (1-13) or remain as cadet
- **NCOs (15-26)**: Can promote/demote within levels 15-26
- **Recruits (27)**: Can promote to NCO ranks (15-26) upon completion of training
- **Civilian Employees (28)**: No military promotions (outside hierarchy)

### Cross-Category Promotions

- Officer Cadet (14) → Officer (1-13): Upon commissioning
- Recruit (27) → NCO (15-26): Upon completion of basic training

## Implementation Files

### Core Configuration
- `shared/rank_levels.php` - Rank level constants and helper functions
- `admin_branch/ajax_get_next_rank.php` - Promotion/demotion logic
- `admin_branch/partials/create_staff_config.php` - Staff creation with rank configuration

### Reporting
- `admin_branch/reports_seniority.php` - Seniority list (Officers, NCOs, CE)
- `admin_branch/reports_officer_norminal.php` - Officer reports
- `admin_branch/reports_nco_seniority.php` - NCO reports
- `admin_branch/reports_ce_seniority.php` - Civilian Employee reports

### Helper Functions

#### getRankCategory($level)
Returns the category for a given rank level.

```php
$category = getRankCategory(5);  // Returns "Officer"
$category = getRankCategory(20); // Returns "NCO"
```

#### getValidPromotionRange($currentLevel, $promotionType)
Returns valid levels for promotion or reversion.

```php
$range = getValidPromotionRange(10, 'promotion');  // Returns [1,2,3...9] for officer promotion
$range = getValidPromotionRange(20, 'reversion'); // Returns [21,22...26] for NCO demotion
```

#### getRankCategorySQL($category, $tableAlias)
Returns SQL WHERE clause for filtering by category.

```php
$sql = "SELECT * FROM staff s JOIN rank r ON s.rankId = r.rankId WHERE " . getRankCategorySQL('Officer', 'r');
// Generates: WHERE r.level BETWEEN 1 AND 13
```

## Database Schema

### Rank Table
```sql
CREATE TABLE `rank` (
  `rankId` varchar(10) NOT NULL,     -- Rank identifier (e.g., 'LT', 'CPT', 'SGT')
  `level` int DEFAULT NULL,           -- Rank level (1-28)
  `createdAt` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rankId`)
);
```

**Note:** The `rank` table stores the level. Category is DERIVED from the level using the ranges defined above.

### Category Derivation

Categories are calculated from level, not stored directly:

```php
if ($level >= 1 && $level <= 13) return 'Officer';
if ($level == 14) return 'Officer Cadet';
if ($level >= 15 && $level <= 26) return 'NCO';
if ($level == 27) return 'Recruit';
if ($level == 28) return 'Civilian Employee';
```

## Migration Notes

### Updated Files (December 9, 2025)

1. **shared/rank_levels.php** - Created with rank level system constants and helpers
2. **admin_branch/ajax_get_next_rank.php** - Updated to use new level ranges
3. **admin_branch/assign_medal.php** - Updated officer/NCO level checks
4. **admin_branch/partials/create_staff_config.php** - Added documentation
5. **admin_branch/reports_seniority.php** - Added documentation

### What Changed

- **Before:** Level 14 was included in Officers (1-14)
- **After:** Level 14 is Officer Cadets (separate category)
- **Before:** NCOs were levels 15-27
- **After:** NCOs are levels 15-26, Recruits are level 27, Civilians are level 28

### Backward Compatibility

The seniority ordering (`ORDER BY r.level ASC`) remains unchanged and continues to work correctly with the new system.

## Testing Checklist

- [ ] Login functionality works
- [ ] Staff creation with correct rank levels
- [ ] Promotion system respects new level ranges
- [ ] Seniority reports show correct ordering
- [ ] Officer reports filter levels 1-13
- [ ] NCO reports filter levels 15-26
- [ ] Recruit and Civilian categories work correctly
- [ ] Cross-category promotions (Cadet→Officer, Recruit→NCO) function properly

## Support

For questions or issues related to the rank level system, refer to:
- This documentation
- Code comments in `shared/rank_levels.php`
- Implementation examples in report files
