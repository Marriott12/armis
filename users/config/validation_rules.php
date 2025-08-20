<?php
/**
 * Validation Rules Configuration for ARMIS User Profile Module
 * Defines comprehensive validation rules for all profile fields
 */

return [
    'personal_info' => [
        'first_name' => [
            'required' => true,
            'type' => 'string',
            'min_length' => 2,
            'max_length' => 50,
            'pattern' => '/^[a-zA-Z\s\-\'\.]+$/',
            'sanitize' => true,
            'error_messages' => [
                'required' => 'First name is required',
                'pattern' => 'First name can only contain letters, spaces, hyphens, and apostrophes',
                'min_length' => 'First name must be at least 2 characters',
                'max_length' => 'First name cannot exceed 50 characters'
            ]
        ],
        
        'last_name' => [
            'required' => true,
            'type' => 'string',
            'min_length' => 2,
            'max_length' => 50,
            'pattern' => '/^[a-zA-Z\s\-\'\.]+$/',
            'sanitize' => true,
            'error_messages' => [
                'required' => 'Last name is required',
                'pattern' => 'Last name can only contain letters, spaces, hyphens, and apostrophes'
            ]
        ],
        
        'email' => [
            'required' => true,
            'type' => 'email',
            'max_length' => 254,
            'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'unique' => 'staff.email',
            'error_messages' => [
                'required' => 'Email address is required',
                'pattern' => 'Please enter a valid email address',
                'unique' => 'This email address is already in use'
            ]
        ],
        
        'phone' => [
            'required' => false,
            'type' => 'phone',
            'pattern' => '/^[\+]?[0-9\-\s\(\)]{7,20}$/',
            'error_messages' => [
                'pattern' => 'Please enter a valid phone number'
            ]
        ],
        
        'date_of_birth' => [
            'required' => true,
            'type' => 'date',
            'min_age' => 18,
            'max_age' => 65,
            'error_messages' => [
                'required' => 'Date of birth is required',
                'min_age' => 'Must be at least 18 years old',
                'max_age' => 'Age cannot exceed 65 years for active service'
            ]
        ],
        
        'gender' => [
            'required' => true,
            'type' => 'enum',
            'options' => ['Male', 'Female', 'Other'],
            'error_messages' => [
                'required' => 'Gender is required',
                'options' => 'Please select a valid gender option'
            ]
        ],
        
        'nationality' => [
            'required' => true,
            'type' => 'string',
            'max_length' => 50,
            'default' => 'Zambian',
            'error_messages' => [
                'required' => 'Nationality is required'
            ]
        ]
    ],
    
    'identification' => [
        'service_number' => [
            'required' => true,
            'type' => 'service_number',
            'pattern' => '/^[A-Z]{2}[0-9]{6,8}$/',
            'unique' => 'staff.service_number',
            'format_hint' => 'Format: ZA123456 (2 letters + 6-8 digits)',
            'error_messages' => [
                'required' => 'Service number is required',
                'pattern' => 'Service number must be 2 letters followed by 6-8 digits',
                'unique' => 'This service number is already assigned'
            ]
        ],
        
        'nrc' => [
            'required' => true,
            'type' => 'nrc',
            'pattern' => '/^[0-9]{6}\/[0-9]{2}\/[0-9]$/',
            'unique' => 'staff.nrc',
            'format_hint' => 'Format: 123456/12/1',
            'error_messages' => [
                'required' => 'National Registration Card number is required',
                'pattern' => 'NRC must be in format: 123456/12/1',
                'unique' => 'This NRC number is already registered'
            ]
        ],
        
        'passport_number' => [
            'required' => false,
            'type' => 'passport',
            'pattern' => '/^[A-Z]{1,2}[0-9]{6,8}$/',
            'format_hint' => 'Format: ZN1234567',
            'error_messages' => [
                'pattern' => 'Passport number format is invalid'
            ]
        ]
    ],
    
    'military_info' => [
        'rank_id' => [
            'required' => true,
            'type' => 'integer',
            'foreign_key' => 'ranks.id',
            'error_messages' => [
                'required' => 'Military rank is required',
                'foreign_key' => 'Invalid rank selected'
            ]
        ],
        
        'unit_id' => [
            'required' => true,
            'type' => 'integer',
            'foreign_key' => 'units.id',
            'error_messages' => [
                'required' => 'Military unit is required',
                'foreign_key' => 'Invalid unit selected'
            ]
        ],
        
        'corps' => [
            'required' => true,
            'type' => 'string',
            'max_length' => 50,
            'options' => ['Infantry', 'Artillery', 'Armoured', 'Engineers', 'Signals', 'Medical', 'Logistics', 'Intelligence'],
            'error_messages' => [
                'required' => 'Corps is required',
                'options' => 'Please select a valid corps'
            ]
        ],
        
        'enlistment_date' => [
            'required' => true,
            'type' => 'date',
            'max_date' => 'today',
            'error_messages' => [
                'required' => 'Enlistment date is required',
                'max_date' => 'Enlistment date cannot be in the future'
            ]
        ]
    ],
    
    'contact_info' => [
        'permanent_address' => [
            'required' => true,
            'type' => 'text',
            'max_length' => 500,
            'error_messages' => [
                'required' => 'Permanent address is required',
                'max_length' => 'Address cannot exceed 500 characters'
            ]
        ],
        
        'current_address' => [
            'required' => false,
            'type' => 'text',
            'max_length' => 500,
            'error_messages' => [
                'max_length' => 'Address cannot exceed 500 characters'
            ]
        ],
        
        'emergency_contact_name' => [
            'required' => true,
            'type' => 'string',
            'max_length' => 100,
            'pattern' => '/^[a-zA-Z\s\-\'\.]+$/',
            'error_messages' => [
                'required' => 'Emergency contact name is required',
                'pattern' => 'Name can only contain letters, spaces, hyphens, and apostrophes'
            ]
        ],
        
        'emergency_contact_phone' => [
            'required' => true,
            'type' => 'phone',
            'pattern' => '/^[\+]?[0-9\-\s\(\)]{7,20}$/',
            'error_messages' => [
                'required' => 'Emergency contact phone is required',
                'pattern' => 'Please enter a valid phone number'
            ]
        ]
    ],
    
    'medical_info' => [
        'blood_group' => [
            'required' => false,
            'type' => 'enum',
            'options' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
            'error_messages' => [
                'options' => 'Please select a valid blood group'
            ]
        ],
        
        'height_cm' => [
            'required' => false,
            'type' => 'decimal',
            'min_value' => 120,
            'max_value' => 250,
            'decimal_places' => 1,
            'error_messages' => [
                'min_value' => 'Height must be at least 120 cm',
                'max_value' => 'Height cannot exceed 250 cm'
            ]
        ],
        
        'weight_kg' => [
            'required' => false,
            'type' => 'decimal',
            'min_value' => 40,
            'max_value' => 200,
            'decimal_places' => 1,
            'error_messages' => [
                'min_value' => 'Weight must be at least 40 kg',
                'max_value' => 'Weight cannot exceed 200 kg'
            ]
        ]
    ],
    
    'security_clearance' => [
        'clearance_level' => [
            'required' => false,
            'type' => 'enum',
            'options' => ['Confidential', 'Secret', 'Top Secret', 'SCI', 'Cosmic Top Secret'],
            'validation_function' => 'validateSecurityClearance',
            'error_messages' => [
                'options' => 'Please select a valid clearance level'
            ]
        ],
        
        'clearance_authority' => [
            'required_if' => 'clearance_level',
            'type' => 'string',
            'max_length' => 100,
            'error_messages' => [
                'required_if' => 'Clearance authority is required when clearance level is specified'
            ]
        ]
    ],
    
    'file_uploads' => [
        'cv_file' => [
            'required' => false,
            'type' => 'file',
            'allowed_types' => ['pdf', 'doc', 'docx'],
            'max_size_mb' => 10,
            'scan_for_viruses' => true,
            'error_messages' => [
                'allowed_types' => 'Only PDF, DOC, and DOCX files are allowed',
                'max_size_mb' => 'File size cannot exceed 10 MB',
                'virus_detected' => 'File contains malicious content'
            ]
        ],
        
        'photo' => [
            'required' => false,
            'type' => 'image',
            'allowed_types' => ['jpg', 'jpeg', 'png'],
            'max_size_mb' => 5,
            'max_width' => 1024,
            'max_height' => 1024,
            'error_messages' => [
                'allowed_types' => 'Only JPG and PNG images are allowed',
                'max_size_mb' => 'Image size cannot exceed 5 MB',
                'dimensions' => 'Image dimensions cannot exceed 1024x1024 pixels'
            ]
        ]
    ],
    
    'validation_groups' => [
        'basic_profile' => ['first_name', 'last_name', 'email', 'service_number', 'nrc'],
        'military_profile' => ['rank_id', 'unit_id', 'corps', 'enlistment_date'],
        'contact_profile' => ['permanent_address', 'emergency_contact_name', 'emergency_contact_phone'],
        'complete_profile' => ['basic_profile', 'military_profile', 'contact_profile'],
        'security_profile' => ['clearance_level', 'clearance_authority']
    ],
    
    'completion_weights' => [
        'basic_profile' => 40,
        'military_profile' => 30,
        'contact_profile' => 20,
        'medical_info' => 5,
        'security_profile' => 5
    ]
];
?>