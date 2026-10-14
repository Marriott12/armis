<?php
/**
 * ARMIS Military Name Formatting Helper Functions
 * Provides consistent military name formatting throughout the system
 */

/**
 * Format military names based on rank category and military conventions
 * 
 * @param string $rank Full rank name
 * @param string $rankAbbr Rank abbreviation
 * @param string $firstName First name(s)
 * @param string $lastName Last name
 * @param string $category Personnel category (Officer, NCO, Enlisted)
 * @return string Formatted military name
 */
function formatMilitaryName($rank, $rankAbbr = '', $firstName = '', $lastName = '', $category = '', $middleName = '') {
    $rank = trim((string)$rank);
    $rankAbbr = trim((string)$rankAbbr);
    $firstName = trim((string)$firstName);
    $middleName = trim((string)$middleName);
    $lastName = trim((string)$lastName);
    $category = trim((string)$category);

    $displayRank = $rankAbbr !== '' ? $rankAbbr : $rank;
    $displayRank = trim($displayRank);

    $initials = [];
    foreach (preg_split('/\s+/', trim($firstName . ' ' . $middleName)) as $name) {
        if ($name !== '') {
            $initials[] = strtoupper(substr($name, 0, 1));
        }
    }
    $initialsText = implode(' ', $initials);

    // Military naming convention used by the application:
    // commissioned Officers and WOI: Rank + initials + surname.
    // All other personnel: Rank + surname + initials.
    $rankKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', $displayRank));
    $fullRankKey = strtolower(preg_replace('/\s+/', ' ', $rank));
    $isOfficer = strtolower($category) === 'officer' || isOfficerRank($rank) || isOfficerRank($rankAbbr);
    $isWOI = in_array($rankKey, ['WOI', 'WO1'], true)
        || in_array($fullRankKey, ['warrant officer class 1', 'warrant officer i', 'warrant officer 1'], true);

    if ($displayRank === '') {
        return trim($isOfficer || $isWOI ? $initialsText . ' ' . $lastName : $lastName . ' ' . $initialsText);
    }
    if ($lastName === '') {
        return trim($displayRank . ' ' . $initialsText);
    }

    if ($isOfficer || $isWOI) {
        return trim($displayRank . ' ' . $initialsText . ' ' . $lastName);
    }

    return trim($displayRank . ' ' . $lastName . ' ' . $initialsText);
}

/**
 * Get rank abbreviation from full rank name
 * 
 * @param string $fullRank Full rank name
 * @return string Rank abbreviation
 */
function getRankAbbreviation($fullRank) {
    $rankMappings = [
        'General' => 'Gen',
        'Lieutenant General' => 'Lt Gen',
        'Major General' => 'Maj Gen',
        'Brigadier General' => 'Brig Gen', 
        'Colonel' => 'Col',
        'Lieutenant Colonel' => 'Lt Col',
        'Major' => 'Maj',
        'Captain' => 'Capt',
        'Lieutenant' => 'Lt',
        'Second Lieutenant' => '2Lt',
        'Warrant Officer Class 1' => 'WOI',
        'Warrant Officer Class 2' => 'WOII',
        'Staff Sergeant' => 'SSgt',
        'Sergeant' => 'Sgt',
        'Corporal' => 'Cpl',
        'Lance Corporal' => 'LCpl',
        'Private' => 'Pvt'
    ];
    
    return $rankMappings[$fullRank] ?? $fullRank;
}

/**
 * Determine if a rank is an officer rank
 * 
 * @param string $rank Rank name or abbreviation
 * @return bool True if officer rank
 */
function isOfficerRank($rank) {
    $officerRanks = [
        'General', 'Lieutenant General', 'Major General', 'Brigadier General', 'Colonel', 'Lieutenant Colonel',
        'Major', 'Captain', 'Lieutenant', 'Second Lieutenant',
        'Gen', 'Lt Gen', 'Maj Gen', 'Brig Gen', 'Col', 'Lt Col', 'Maj', 'Capt', 'Lt', '2Lt'
    ];
    
    return in_array($rank, $officerRanks);
}

/**
 * Format name for official documents
 * 
 * @param string $rank
 * @param string $firstName
 * @param string $lastName
 * @param string $serviceNumber
 * @return string Official format: "Rank LASTNAME, First Name (Service Number)"
 */
function formatOfficialName($rank, $firstName, $lastName, $serviceNumber = '') {
    $rankAbbr = getRankAbbreviation($rank);
    $officialName = $rankAbbr . ' ' . strtoupper($lastName) . ', ' . $firstName;
    
    if (!empty($serviceNumber)) {
        $officialName .= ' (' . $serviceNumber . ')';
    }
    
    return $officialName;
}

/**
 * Format name for roster display
 * 
 * @param array $staffData Array containing staff information
 * @return string Formatted name for roster
 */
function formatRosterName($staffData) {
    $rank = $staffData['rank'] ?? $staffData['rankName'] ?? '';
    $rankAbbr = $staffData['rank_abbr'] ?? getRankAbbreviation($rank);
    $firstName = $staffData['fname'] ?? $staffData['fName'] ?? $staffData['firstName'] ?? '';
    $lastName = $staffData['lname'] ?? $staffData['lName'] ?? $staffData['lastName'] ?? '';
    $category = $staffData['category'] ?? '';
    
    return formatMilitaryName($rank, $rankAbbr, $firstName, $lastName, $category);
}

/**
 * Sort personnel by military precedence
 * 
 * @param array $personnel Array of personnel data
 * @param string $rankField Field name containing rank information
 * @return array Sorted personnel array
 */
function sortByMilitaryPrecedence($personnel, $rankField = 'rank_order') {
    usort($personnel, function($a, $b) use ($rankField) {
        $rankA = $a[$rankField] ?? 0;
        $rankB = $b[$rankField] ?? 0;
        
        // Higher rank order = higher precedence (reverse sort)
        return $rankB <=> $rankA;
    });
    
    return $personnel;
}

/**
 * Get proper salutation based on rank
 * 
 * @param string $rank Rank name or abbreviation
 * @param string $gender Gender (M/F)
 * @return string Proper salutation
 */
function getMilitarySalutation($rank, $gender = '') {
    if (isOfficerRank($rank)) {
        return ($gender === 'F') ? 'Ma\'am' : 'Sir';
    } else {
        $rankAbbr = getRankAbbreviation($rank);
        return $rankAbbr;
    }
}
?>
