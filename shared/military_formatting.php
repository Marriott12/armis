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
function formatMilitaryName($rank, $rankAbbr = '', $firstName = '', $lastName = '', $category = '') {
    if (empty($firstName) || empty($lastName)) {
        return trim($rank . ' ' . $firstName . ' ' . $lastName);
    }
    
    // Get first initials from first name(s)
    $firstInitials = '';
    $names = explode(' ', trim($firstName));
    foreach ($names as $name) {
        if (!empty($name)) {
            $firstInitials .= strtoupper(substr($name, 0, 1)) . ' ';
        }
    }
    
    // Use rank abbreviation if available, otherwise full rank
    $displayRank = !empty($rankAbbr) ? $rankAbbr : $rank;
    
    // Define officer ranks (typically higher in hierarchy)
    $officerRanks = [
        // Full names
        'General', 'Brigadier General', 'Colonel', 'Lieutenant Colonel', 
        'Major', 'Captain', 'Lieutenant', 'Second Lieutenant',
        // Abbreviations
        'Gen', 'Brig Gen', 'Col', 'Lt Col', 'Maj', 'Capt', 'Lt', '2Lt'
    ];
    
    $isOfficer = in_array($rank, $officerRanks) || 
                 in_array($rankAbbr, $officerRanks) || 
                 strtolower($category) === 'officer';
    
    if ($isOfficer) {
        // Officers: Rank + First Name Initials + Last Name
        return $displayRank . ' ' . $firstInitials . ' ' . $lastName;
    } else {
        // NCOs and Enlisted: Rank + Last Name + First Name Initials
        return $displayRank . ' ' . $lastName . ' ' . $firstInitials;
    }
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
        'Brigadier General' => 'Brig Gen', 
        'Colonel' => 'Col',
        'Lieutenant Colonel' => 'Lt Col',
        'Major' => 'Maj',
        'Captain' => 'Capt',
        'Lieutenant' => 'Lt',
        'Second Lieutenant' => '2Lt',
        'Sergeant Major' => 'SM',
        'Warrant Officer Class 1' => 'WO1',
        'Warrant Officer Class 2' => 'WO2',
        'Staff Sergeant' => 'SSgt',
        'Sergeant' => 'Sgt',
        'Corporal' => 'Cpl',
        'Lance Corporal' => 'LCpl',
        'Private First Class' => 'PFC',
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
        'General', 'Brigadier General', 'Colonel', 'Lieutenant Colonel', 
        'Major', 'Captain', 'Lieutenant', 'Second Lieutenant',
        'Gen', 'Brig Gen', 'Col', 'Lt Col', 'Maj', 'Capt', 'Lt', '2Lt'
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
    $firstName = $staffData['fname'] ?? $staffData['first_name'] ?? $staffData['firstName'] ?? '';
    $lastName = $staffData['lname'] ?? $staffData['last_name'] ?? $staffData['lastName'] ?? '';
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
