<?php
/**
 * Security Clearances Configuration for ARMIS
 * Defines security clearance levels and requirements
 */

return [
    'clearance_levels' => [
        'Confidential' => [
            'level' => 1,
            'description' => 'Information that could reasonably be expected to cause damage to national security',
            'duration_years' => 5,
            'investigation_type' => 'Basic Security Investigation',
            'min_rank_level' => 1,
            'min_service_years' => 1,
            'issuing_authorities' => [
                'Military Intelligence',
                'Command Security Officer',
                'Unit Security Officer'
            ],
            'requirements' => [
                'Background check',
                'Character references',
                'Employment verification',
                'Credit check'
            ]
        ],
        
        'Secret' => [
            'level' => 2,
            'description' => 'Information that could reasonably be expected to cause serious damage to national security',
            'duration_years' => 5,
            'investigation_type' => 'Enhanced Security Investigation',
            'min_rank_level' => 3,
            'min_service_years' => 2,
            'issuing_authorities' => [
                'Military Intelligence',
                'Defense Security Agency',
                'Command Security Officer'
            ],
            'requirements' => [
                'Extended background check',
                'Personal interviews',
                'Financial investigation',
                'Foreign travel verification',
                'Family member checks'
            ]
        ],
        
        'Top Secret' => [
            'level' => 3,
            'description' => 'Information that could reasonably be expected to cause exceptionally grave damage to national security',
            'duration_years' => 5,
            'investigation_type' => 'Comprehensive Security Investigation',
            'min_rank_level' => 6,
            'min_service_years' => 5,
            'issuing_authorities' => [
                'Defense Ministry Security',
                'National Security Agency',
                'Intelligence Services'
            ],
            'requirements' => [
                'Comprehensive background investigation',
                'Polygraph examination',
                'Psychological evaluation',
                'Extended family investigations',
                'Foreign contact verification',
                'Continuous monitoring'
            ]
        ],
        
        'SCI' => [
            'level' => 4,
            'description' => 'Sensitive Compartmented Information - Special access required',
            'duration_years' => 5,
            'investigation_type' => 'Special Compartmented Information Investigation',
            'min_rank_level' => 8,
            'min_service_years' => 8,
            'issuing_authorities' => [
                'National Security Agency',
                'Intelligence Services Director',
                'Defense Ministry Clearance Authority'
            ],
            'requirements' => [
                'Top Secret clearance prerequisite',
                'Specialized polygraph',
                'Compartment-specific training',
                'Regular reinvestigation',
                'Special access justification'
            ]
        ],
        
        'Cosmic Top Secret' => [
            'level' => 5,
            'description' => 'NATO classified information at the highest level',
            'duration_years' => 5,
            'investigation_type' => 'NATO Security Investigation',
            'min_rank_level' => 10,
            'min_service_years' => 10,
            'issuing_authorities' => [
                'NATO Security Office',
                'National Security Director',
                'Defense Minister'
            ],
            'requirements' => [
                'Top Secret SCI clearance prerequisite',
                'NATO-specific investigation',
                'International background check',
                'Continuous monitoring program',
                'Special access authorization'
            ]
        ]
    ],
    
    'investigation_requirements' => [
        'Basic Security Investigation' => [
            'duration_months' => 3,
            'depth_years' => 5,
            'checks' => ['criminal', 'employment', 'education', 'references']
        ],
        'Enhanced Security Investigation' => [
            'duration_months' => 6,
            'depth_years' => 7,
            'checks' => ['criminal', 'employment', 'education', 'references', 'financial', 'travel']
        ],
        'Comprehensive Security Investigation' => [
            'duration_months' => 12,
            'depth_years' => 10,
            'checks' => ['criminal', 'employment', 'education', 'references', 'financial', 'travel', 'psychological', 'polygraph']
        ]
    ],
    
    'adjudication_guidelines' => [
        'disqualifiers' => [
            'criminal_conviction' => [
                'felony' => 'automatic_disqualification',
                'misdemeanor' => 'case_by_case',
                'military_offense' => 'case_by_case'
            ],
            'financial_issues' => [
                'bankruptcy' => 'review_required',
                'delinquent_debt' => 'mitigation_possible',
                'foreign_financial_interests' => 'automatic_disqualification'
            ],
            'foreign_influence' => [
                'foreign_citizenship' => 'case_by_case',
                'foreign_family' => 'review_required',
                'foreign_business' => 'automatic_disqualification'
            ],
            'substance_abuse' => [
                'alcohol_abuse' => 'rehabilitation_required',
                'drug_use' => 'case_by_case',
                'current_use' => 'automatic_disqualification'
            ]
        ],
        
        'mitigating_factors' => [
            'time_passage',
            'demonstrated_reliability',
            'rehabilitation_evidence',
            'changed_circumstances',
            'character_witnesses'
        ]
    ],
    
    'monitoring_requirements' => [
        'Confidential' => [
            'periodic_review' => 'annual',
            'incident_reporting' => 'required',
            'travel_reporting' => 'foreign_only'
        ],
        'Secret' => [
            'periodic_review' => 'annual',
            'incident_reporting' => 'required',
            'travel_reporting' => 'all_international',
            'financial_disclosure' => 'annual'
        ],
        'Top Secret' => [
            'periodic_review' => 'bi_annual',
            'incident_reporting' => 'immediate',
            'travel_reporting' => 'all_travel',
            'financial_disclosure' => 'annual',
            'continuous_monitoring' => 'enabled'
        ],
        'SCI' => [
            'periodic_review' => 'quarterly',
            'incident_reporting' => 'immediate',
            'travel_reporting' => 'all_travel_pre_approval',
            'financial_disclosure' => 'quarterly',
            'continuous_monitoring' => 'enhanced',
            'polygraph_schedule' => 'bi_annual'
        ],
        'Cosmic Top Secret' => [
            'periodic_review' => 'quarterly',
            'incident_reporting' => 'immediate',
            'travel_reporting' => 'all_travel_pre_approval',
            'financial_disclosure' => 'quarterly',
            'continuous_monitoring' => 'comprehensive',
            'polygraph_schedule' => 'annual',
            'international_coordination' => 'required'
        ]
    ],
    
    'access_control' => [
        'compartments' => [
            'HUMINT' => 'Human Intelligence',
            'SIGINT' => 'Signals Intelligence',
            'GEOINT' => 'Geospatial Intelligence',
            'MASINT' => 'Measurement and Signature Intelligence',
            'OSINT' => 'Open Source Intelligence'
        ],
        
        'special_access_programs' => [
            'black' => 'Unacknowledged Special Access Program',
            'gray' => 'Acknowledged Special Access Program',
            'white' => 'Regular Classified Program'
        ]
    ]
];
?>