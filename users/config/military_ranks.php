<?php
/**
 * Military Ranks Configuration for ARMIS
 * Defines military rank structure and progression
 */

return [
    'enlisted_ranks' => [
        1 => ['name' => 'Private', 'abbreviation' => 'Pvt', 'category' => 'enlisted', 'level' => 1],
        2 => ['name' => 'Lance Corporal', 'abbreviation' => 'LCpl', 'category' => 'enlisted', 'level' => 2],
        3 => ['name' => 'Corporal', 'abbreviation' => 'Cpl', 'category' => 'enlisted', 'level' => 3],
        4 => ['name' => 'Sergeant', 'abbreviation' => 'Sgt', 'category' => 'enlisted', 'level' => 4],
        5 => ['name' => 'Staff Sergeant', 'abbreviation' => 'SSgt', 'category' => 'enlisted', 'level' => 5],
        6 => ['name' => 'Warrant Officer Class 2', 'abbreviation' => 'WO2', 'category' => 'warrant', 'level' => 6],
        7 => ['name' => 'Warrant Officer Class 1', 'abbreviation' => 'WO1', 'category' => 'warrant', 'level' => 7],
    ],
    
    'officer_ranks' => [
        8 => ['name' => 'Second Lieutenant', 'abbreviation' => '2Lt', 'category' => 'officer', 'level' => 8],
        9 => ['name' => 'Lieutenant', 'abbreviation' => 'Lt', 'category' => 'officer', 'level' => 9],
        10 => ['name' => 'Captain', 'abbreviation' => 'Capt', 'category' => 'officer', 'level' => 10],
        11 => ['name' => 'Major', 'abbreviation' => 'Maj', 'category' => 'officer', 'level' => 11],
        12 => ['name' => 'Lieutenant Colonel', 'abbreviation' => 'Lt Col', 'category' => 'officer', 'level' => 12],
        13 => ['name' => 'Colonel', 'abbreviation' => 'Col', 'category' => 'officer', 'level' => 13],
        14 => ['name' => 'Brigadier', 'abbreviation' => 'Brig', 'category' => 'general', 'level' => 14],
        15 => ['name' => 'Major General', 'abbreviation' => 'Maj Gen', 'category' => 'general', 'level' => 15],
        16 => ['name' => 'Lieutenant General', 'abbreviation' => 'Lt Gen', 'category' => 'general', 'level' => 16],
        17 => ['name' => 'General', 'abbreviation' => 'Gen', 'category' => 'general', 'level' => 17],
    ],
    
    'progression_rules' => [
        'enlisted_to_warrant' => [
            'min_service_years' => 8,
            'required_courses' => ['Senior NCO Course'],
            'approval_level' => 'command'
        ],
        'enlisted_to_officer' => [
            'min_service_years' => 4,
            'required_education' => 'degree',
            'required_courses' => ['Officer Candidate School'],
            'approval_level' => 'ministry'
        ],
        'officer_promotion' => [
            'min_years_in_rank' => [
                '2Lt' => 2,
                'Lt' => 3,
                'Capt' => 4,
                'Maj' => 4,
                'Lt Col' => 5,
                'Col' => 6
            ],
            'required_courses' => [
                'Maj' => ['Junior Staff Course'],
                'Lt Col' => ['Senior Staff Course'],
                'Col' => ['Senior Command Course']
            ]
        ]
    ],
    
    'retirement_ranks' => [
        'compulsory_age' => [
            'enlisted' => 55,
            'warrant' => 57,
            'officer' => 60,
            'general' => 62
        ],
        'voluntary_retirement' => [
            'min_service_years' => 20,
            'pension_eligible' => true
        ]
    ],
    
    'insignia_codes' => [
        'Pvt' => 'E1',
        'LCpl' => 'E2',
        'Cpl' => 'E3',
        'Sgt' => 'E4',
        'SSgt' => 'E5',
        'WO2' => 'W2',
        'WO1' => 'W1',
        '2Lt' => 'O1',
        'Lt' => 'O2',
        'Capt' => 'O3',
        'Maj' => 'O4',
        'Lt Col' => 'O5',
        'Col' => 'O6',
        'Brig' => 'G1',
        'Maj Gen' => 'G2',
        'Lt Gen' => 'G3',
        'Gen' => 'G4'
    ]
];
?>