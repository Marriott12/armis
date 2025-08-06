<?php
// Enhanced Personnel Management Configuration with Analytics Integration
// Define module constants
if (!defined('ARMIS_ADMIN_BRANCH')) {
    define('ARMIS_ADMIN_BRANCH', true);
}

// Include enhanced admin branch system
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/analytics.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

// Enhanced CSRF Token Management
if (!class_exists('Token')) {
    class Token {
        public static function generate() {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        
        public static function check($token) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}
$csrfToken = Token::generate();

// Initialize database-driven arrays with enhanced analytics
$ranks = [];
$units = [];
$corps = [];
$corpsList = [];

try {
    // Use centralized PDO connection
    $pdo = getDbConnection();
    
    error_log("Database connection established successfully");
    
    // Check if we have cached data (for performance)
    $cacheKey = 'staff_form_data_' . date('Y-m-d-H');
    $cachedData = false; // You can implement caching here if needed
    
    // Fetch ranks with staff count analytics, filtered by category and ordered by level
    $rankQuery = "
        SELECT r.id as rankID, r.name as rankName, r.level as rankIndex, 
               r.abbreviation, COUNT(s.id) as staff_count, r.category
        FROM ranks r 
        LEFT JOIN staff s ON r.id = s.rank_id AND s.svcStatus = 'Active'
        WHERE r.category IS NOT NULL
        GROUP BY r.id, r.category, r.level
        ORDER BY r.category ASC, r.level ASC
    ";
    $stmt = $pdo->prepare($rankQuery);
    $stmt->execute();
    $ranks = $stmt->fetchAll(PDO::FETCH_OBJ);
    error_log("Loaded " . count($ranks) . " ranks from database");
    
    // Fetch units with enhanced data and hierarchy support
    $unitQuery = "
        SELECT u.id as unitID, u.name as unitName, u.code as unitCode, 
               u.type as unitType, u.parent_unit_id, u.commander_id, 
               u.location, u.is_active,
               COUNT(s.id) as staff_count,
               COALESCE(c.first_name, 'No Commander') as commander_name,
               p.name as parent_unit_name
        FROM units u 
        LEFT JOIN staff s ON u.id = s.unit_id AND s.svcStatus = 'Active'
        LEFT JOIN staff c ON u.commander_id = c.id
        LEFT JOIN units p ON u.parent_unit_id = p.id
        WHERE u.is_active = 1
        GROUP BY u.id 
        ORDER BY u.parent_unit_id ASC, u.name ASC
    ";
    $stmt = $pdo->prepare($unitQuery);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_OBJ);
    error_log("Loaded " . count($units) . " units from database");
    
    // If no units found, try simpler query to check if units table exists
    if (empty($units)) {
        $stmt = $pdo->prepare("SELECT id as unitID, name as unitName, code as unitCode FROM units WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $units[] = (object)array_merge($row, ['unitType' => '', 'staff_count' => 0, 'location' => '']);
        }
    }
    
    // Fetch corps from corps table using correct field names
    $corpsQuery = "
        SELECT c.id as corpsID, c.name as corpsName, c.abbreviation as corpsAbb,
               COUNT(s.id) as usage_count
        FROM corps c
        LEFT JOIN staff s ON c.name = s.corps AND s.svcStatus = 'Active'
        GROUP BY c.id 
        ORDER BY c.name ASC
    ";
    $stmt = $pdo->prepare($corpsQuery);
    $stmt->execute();
    $corps = $stmt->fetchAll(PDO::FETCH_OBJ);
    foreach ($corps as $corp) {
        $corpsList[] = (object)['corps' => $corp->corpsName];
    }
    
    // If no corps found from corps table, try fallback from staff table
    if (empty($corps)) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT corps as corpsName, corps as corpsAbb, 
                   COUNT(*) as usage_count
            FROM staff 
            WHERE corps IS NOT NULL AND corps != '' 
            GROUP BY corps 
            ORDER BY usage_count DESC, corps ASC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $corps[] = (object)$row;
            $corpsList[] = (object)['corps' => $row['corpsName']];
        }
    }
    
    // Add default corps if none exist
    if (empty($corps)) {
        $defaultCorps = ['Infantry', 'Artillery', 'Engineers', 'Signals', 'Medical', 'Logistics'];
        foreach ($defaultCorps as $corpsName) {
            $corps[] = (object)['corpsName' => $corpsName, 'corpsAbb' => substr($corpsName, 0, 3), 'usage_count' => 0];
            $corpsList[] = (object)['corps' => $corpsName];
        }
    }
    
    // Add default units if none exist in database
    if (empty($units)) {
        $defaultUnits = [
            ['name' => 'Headquarters Company', 'code' => 'HQ', 'type' => 'Command'],
            ['name' => 'A Company', 'code' => 'A-CO', 'type' => 'Infantry'],
            ['name' => 'B Company', 'code' => 'B-CO', 'type' => 'Infantry'],
            ['name' => 'C Company', 'code' => 'C-CO', 'type' => 'Infantry'],
            ['name' => 'Support Company', 'code' => 'SUP', 'type' => 'Support'],
            ['name' => 'Training Wing', 'code' => 'TRG', 'type' => 'Training'],
            ['name' => 'Operations Department', 'code' => 'OPS', 'type' => 'Operations'],
            ['name' => 'Medical Unit', 'code' => 'MED', 'type' => 'Medical'],
            ['name' => 'Engineering Unit', 'code' => 'ENG', 'type' => 'Engineering']
        ];
        
        foreach ($defaultUnits as $index => $unit) {
            $units[] = (object)[
                'unitID' => $index + 1,
                'unitName' => $unit['name'],
                'unitCode' => $unit['code'],
                'unitType' => $unit['type'],
                'staff_count' => 0,
                'location' => '',
                'is_active' => 1
            ];
        }
    }
    
    // Add default ranks if none exist in database  
    if (empty($ranks)) {
        $defaultRanks = [
            ['name' => 'Private', 'level' => 1, 'abbr' => 'Pte', 'cat' => 'NCO'],
            ['name' => 'Corporal', 'level' => 2, 'abbr' => 'Cpl', 'cat' => 'NCO'],
            ['name' => 'Sergeant', 'level' => 3, 'abbr' => 'Sgt', 'cat' => 'NCO'],
            ['name' => 'Staff Sergeant', 'level' => 4, 'abbr' => 'SSgt', 'cat' => 'NCO'],
            ['name' => 'Warrant Officer', 'level' => 5, 'abbr' => 'WO', 'cat' => 'NCO'],
            ['name' => 'Lieutenant', 'level' => 6, 'abbr' => 'Lt', 'cat' => 'Officer'],
            ['name' => 'Captain', 'level' => 7, 'abbr' => 'Capt', 'cat' => 'Officer'],
            ['name' => 'Major', 'level' => 8, 'abbr' => 'Maj', 'cat' => 'Officer'],
            ['name' => 'Colonel', 'level' => 9, 'abbr' => 'Col', 'cat' => 'Officer']
        ];
        
        foreach ($defaultRanks as $index => $rank) {
            $ranks[] = (object)[
                'rankID' => $index + 1,
                'rankName' => $rank['name'],
                'rankIndex' => $rank['level'],
                'abbreviation' => $rank['abbr'],
                'category' => $rank['cat'],
                'staff_count' => 0
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Database error in create_staff_config: " . $e->getMessage());
    
    // Fallback data with enhanced analytics integration
    $ranks = [
        (object)['rankID' => 1, 'rankName' => 'Private', 'rankIndex' => 1, 'abbreviation' => 'Pte', 'staff_count' => 0, 'category' => 'Enlisted'],
        (object)['rankID' => 2, 'rankName' => 'Corporal', 'rankIndex' => 2, 'abbreviation' => 'Cpl', 'staff_count' => 0, 'category' => 'Enlisted'],
        (object)['rankID' => 3, 'rankName' => 'Sergeant', 'rankIndex' => 3, 'abbreviation' => 'Sgt', 'staff_count' => 0, 'category' => 'NCO'],
        (object)['rankID' => 4, 'rankName' => 'Lieutenant', 'rankIndex' => 4, 'abbreviation' => 'Lt', 'staff_count' => 0, 'category' => 'Officer'],
        (object)['rankID' => 5, 'rankName' => 'Captain', 'rankIndex' => 5, 'abbreviation' => 'Capt', 'staff_count' => 0, 'category' => 'Officer'],
        (object)['rankID' => 6, 'rankName' => 'Major', 'rankIndex' => 6, 'abbreviation' => 'Maj', 'staff_count' => 0, 'category' => 'Officer']
    ];
    
    $units = [
        (object)['unitID' => 1, 'unitName' => 'Headquarters Company', 'unitCode' => 'HQ', 'unitType' => 'Command', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 2, 'unitName' => 'A Company', 'unitCode' => 'A-CO', 'unitType' => 'Infantry', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 3, 'unitName' => 'B Company', 'unitCode' => 'B-CO', 'unitType' => 'Infantry', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 4, 'unitName' => 'C Company', 'unitCode' => 'C-CO', 'unitType' => 'Infantry', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 5, 'unitName' => 'Support Company', 'unitCode' => 'SUP', 'unitType' => 'Support', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 6, 'unitName' => 'Training Wing', 'unitCode' => 'TRG', 'unitType' => 'Training', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 7, 'unitName' => 'Operations Department', 'unitCode' => 'OPS', 'unitType' => 'Operations', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 8, 'unitName' => 'Intelligence Unit', 'unitCode' => 'INT', 'unitType' => 'Intelligence', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 9, 'unitName' => 'Medical Unit', 'unitCode' => 'MED', 'unitType' => 'Medical', 'staff_count' => 0, 'location' => ''],
        (object)['unitID' => 10, 'unitName' => 'Engineering Unit', 'unitCode' => 'ENG', 'unitType' => 'Engineering', 'staff_count' => 0, 'location' => '']
    ];
    
    $corps = [
        (object)['corpsName' => 'Infantry', 'corpsAbb' => 'Inf', 'usage_count' => 0],
        (object)['corpsName' => 'Artillery', 'corpsAbb' => 'Art', 'usage_count' => 0],
        (object)['corpsName' => 'Engineers', 'corpsAbb' => 'Eng', 'usage_count' => 0],
        (object)['corpsName' => 'Signals', 'corpsAbb' => 'Sig', 'usage_count' => 0],
        (object)['corpsName' => 'Medical', 'corpsAbb' => 'Med', 'usage_count' => 0],
        (object)['corpsName' => 'Logistics', 'corpsAbb' => 'Log', 'usage_count' => 0]
    ];
    
    $corpsList = [
        (object)['corps' => 'Infantry'],
        (object)['corps' => 'Artillery'],
        (object)['corps' => 'Engineers'],
        (object)['corps' => 'Signals'],
        (object)['corps' => 'Medical'],
        (object)['corps' => 'Logistics']
    ];
}

// Enhanced form configuration arrays
$combatSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'];
$bootSizes = range(4, 15);
$shoeSizes = range(4, 15);
$headdressSizes = range(52, 65);

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

$maritalStatusOptions = [
    'single' => 'Single',
    'married' => 'Married',
    'divorced' => 'Divorced',
    'widowed' => 'Widowed',
    'separated' => 'Separated',
    'cohabitating' => 'Cohabitating'
];

$genderOptions = [
    'M' => 'Male',
    'F' => 'Female'
];

$serviceStatusOptions = [
    'active' => 'Active Service',
    'reserve' => 'Reserve',
    'retired' => 'Retired',
    'discharged' => 'Discharged',
    'leave' => 'On Leave',
    'secondment' => 'On Secondment'
];

$employmentStatusOptions = [
    'full_time' => 'Full Time',
    'part_time' => 'Part Time',
    'contract' => 'Contract',
    'temporary' => 'Temporary',
    'intern' => 'Intern/Trainee',
    'consultant' => 'Consultant'
];

$educationLevels = [
    'primary' => 'Primary Education',
    'secondary' => 'Secondary Education', 
    'certificate' => 'Certificate',
    'diploma' => 'Diploma',
    'degree' => 'Bachelor\'s Degree',
    'masters' => 'Master\'s Degree',
    'doctorate' => 'Doctorate/PhD',
    'professional' => 'Professional Qualification'
];

$courseTypes = [
    'military' => 'Military Training',
    'technical' => 'Technical Training',
    'leadership' => 'Leadership Course',
    'specialist' => 'Specialist Training',
    'international' => 'International Course',
    'academic' => 'Academic Course',
    'certification' => 'Professional Certification'
];

$relationshipOptions = [
    'spouse' => 'Spouse',
    'father' => 'Father',
    'mother' => 'Mother',
    'son' => 'Son',
    'daughter' => 'Daughter',
    'brother' => 'Brother',
    'sister' => 'Sister',
    'uncle' => 'Uncle',
    'aunt' => 'Aunt',
    'cousin' => 'Cousin',
    'nephew' => 'Nephew',
    'niece' => 'Niece',
    'grandfather' => 'Grandfather',
    'grandmother' => 'Grandmother',
    'other' => 'Other Relative',
    'emergency' => 'Emergency Contact'
];

$contactTypes = [
    'mobile' => 'Mobile Phone',
    'home' => 'Home Phone',
    'work' => 'Work Phone',
    'email' => 'Email Address',
    'emergency' => 'Emergency Contact'
];

$addressTypes = [
    'current' => 'Current Address',
    'permanent' => 'Permanent Address',
    'next_of_kin' => 'Next of Kin Address',
    'emergency' => 'Emergency Contact Address',
    'postal' => 'Postal Address'
];

$prefixOptions = ['W', 'S', 'SW', 'Q', 'QW'];

$provinceDistricts = [
    'Central' => ['Kabwe', 'Kapiri Mposhi', 'Mkushi', 'Mumbwa', 'Chibombo', 'Chisamba', 'Serenje', 'Itezhi-Tezhi', 'Ngabwe'],
    'Copperbelt' => ['Ndola', 'Kitwe', 'Chingola', 'Mufulira', 'Luanshya', 'Kalulushi', 'Chililabombwe', 'Lufwanyama', 'Masaiti', 'Mpongwe'],
    'Eastern' => ['Chipata', 'Katete', 'Petauke', 'Lundazi', 'Mambwe', 'Nyimba', 'Chadiza', 'Sinda', 'Vubwi'],
    'Luapula' => ['Mansa', 'Samfya', 'Nchelenge', 'Kawambwa', 'Chembe', 'Milenge', 'Mwense', 'Chienge'],
    'Lusaka' => ['Lusaka', 'Chongwe', 'Kafue', 'Luangwa', 'Chilanga', 'Rufunsa', 'Shibuyunji'],
    'Muchinga' => ['Chinsali', 'Isoka', 'Mpika', 'Nakonde', 'Chama', 'Mafinga', 'Shiwangandu'],
    'Northern' => ['Kasama', 'Mbala', 'Mpulungu', 'Luwingu', 'Mporokoso', 'Chilubi', 'Kaputa', 'Senga Hill', 'Lunte'],
    'North-Western' => ['Solwezi', 'Mufumbwe', 'Zambezi', 'Kasempa', 'Kabompo', 'Mwinilunga', 'Chavuma', 'Manyinga', 'Kalumbila'],
    'Southern' => ['Livingstone', 'Choma', 'Mazabuka', 'Monze', 'Kalomo', 'Siavonga', 'Sinazongwe', 'Namwala', 'Gwembe', 'Pemba', 'Zimba', 'Chikankata'],
    'Western' => ['Mongu', 'Senanga', 'Kaoma', 'Lukulu', 'Sesheke', 'Shangombo', 'Kalabo', 'Nalolo', 'Sikongo', 'Sioma']
];
$provinceOptions = array_keys($provinceDistricts);

$countryOptions = [
    'ZM' => 'Zambia',
    'AO' => 'Angola',
    'BW' => 'Botswana',
    'CD' => 'Democratic Republic of Congo',
    'MW' => 'Malawi',
    'MZ' => 'Mozambique',
    'NA' => 'Namibia',
    'TZ' => 'Tanzania',
    'ZW' => 'Zimbabwe',
    'ZA' => 'South Africa',
    'KE' => 'Kenya',
    'UG' => 'Uganda',
    'RW' => 'Rwanda',
    'BI' => 'Burundi',
    'OTHER' => 'Other Country'
];

$religionOptions = [
    'christianity' => 'Christianity',
    'islam' => 'Islam',
    'hinduism' => 'Hinduism',
    'buddhism' => 'Buddhism',
    'judaism' => 'Judaism',
    'sikhism' => 'Sikhism',
    'bahai' => 'Baha\'i Faith',
    'traditional' => 'Traditional African Religion',
    'atheism' => 'Atheism/No Religion',
    'other' => 'Other Religion'
];

$professionOptions = [
    "Accountant", "Actor", "Actuary", "Administrator", "Advocate", "Agriculturalist", "Analyst", "Animator", "Architect", "Artist", "Auditor", "Author",
    "Baker", "Banker", "Biochemist", "Biologist", "Bricklayer", "Broker", "Builder", "Business Analyst", "Businessperson", "Butcher",
    "Carpenter", "Chef", "Chemist", "Civil Engineer", "Clerk", "Coach", "Consultant", "Counselor",
    "Dentist", "Designer", "Developer", "Dietitian", "Doctor", "Driver",
    "Economist", "Editor", "Electrician", "Engineer", "Entrepreneur",
    "Farmer", "Fashion Designer", "Filmmaker", "Firefighter", "Fisherman",
    "Geologist", "Graphic Designer",
    "Hairdresser", "Historian", "Hotelier", "HR Specialist",
    "IT Specialist",
    "Journalist", "Judge",
    "Lawyer", "Lecturer", "Librarian", "Logistician",
    "Manager", "Mason", "Mathematician", "Mechanic", "Medical Officer", "Microbiologist", "Miner", "Musician",
    "Nurse", "Nutritionist",
    "Optician",
    "Painter", "Paramedic", "Pharmacist", "Photographer", "Physician", "Physicist", "Pilot", "Plumber", "Police Officer", "Politician", "Professor", "Programmer", "Project Manager", "Psychologist", "Public Servant",
    "Receptionist", "Researcher",
    "Scientist", "Secretary", "Security Officer", "Social Worker", "Software Engineer", "Soldier", "Statistician", "Surgeon", "Surveyor",
    "Tailor", "Teacher", "Technician", "Therapist", "Translator",
    "Veterinarian",
    "Waiter", "Web Developer", "Welder", "Writer",
    "Other"
];

$medicalConditions = [
    'diabetes' => 'Diabetes',
    'hypertension' => 'Hypertension',
    'asthma' => 'Asthma',
    'heart_disease' => 'Heart Disease',
    'epilepsy' => 'Epilepsy',
    'allergies' => 'Allergies',
    'mental_health' => 'Mental Health Condition',
    'chronic_pain' => 'Chronic Pain',
    'disability' => 'Physical Disability',
    'none' => 'None Known'
];

$skillCategories = [
    'technical' => 'Technical Skills',
    'language' => 'Language Skills',
    'leadership' => 'Leadership Skills',
    'combat' => 'Combat Skills',
    'driving' => 'Driving/Operating Skills',
    'computer' => 'Computer Skills',
    'communication' => 'Communication Skills',
    'other' => 'Other Skills'
];

$languageProficiency = [
    'native' => 'Native',
    'fluent' => 'Fluent',
    'intermediate' => 'Intermediate',
    'basic' => 'Basic',
    'beginner' => 'Beginner'
];

// Enhanced form validation rules
$validationRules = [
    'personal_info' => [
        'firstName' => ['required' => true, 'min' => 2, 'max' => 50, 'pattern' => '/^[a-zA-Z\s]+$/'],
        'lastName' => ['required' => true, 'min' => 2, 'max' => 50, 'pattern' => '/^[a-zA-Z\s]+$/'],
        'email' => ['required' => true, 'type' => 'email', 'unique' => 'staff.email'],
        'phone' => ['required' => true, 'pattern' => '/^[\+]?[0-9\-\(\)\s]+$/'],
        'nationalID' => ['required' => true, 'unique' => 'staff.nationalID', 'pattern' => '/^[0-9]{9,15}$/'],
        'dateOfBirth' => ['required' => true, 'type' => 'date', 'max_age' => 65, 'min_age' => 18]
    ],
    'military_info' => [
        'serviceNumber' => ['required' => true, 'unique' => 'staff.serviceNumber', 'pattern' => '/^[A-Z0-9\/]+$/'],
        'rankID' => ['required' => true, 'exists' => 'ranks.id'],
        'unitID' => ['required' => true, 'exists' => 'units.id'],
        'corps' => ['required' => true],
        'enlistmentDate' => ['required' => true, 'type' => 'date']
    ],
    'contact_info' => [
        'currentAddress' => ['required' => true, 'min' => 10],
        'emergencyContact' => ['required' => true, 'min' => 2],
        'emergencyPhone' => ['required' => true, 'pattern' => '/^[\+]?[0-9\-\(\)\s]+$/']
    ]
];

// Field dependencies (conditional requirements)
$fieldDependencies = [
    'maritalStatus' => [
        'married' => ['spouseName' => true, 'spousePhone' => true],
        'divorced' => ['divorceDate' => true],
        'widowed' => ['spouseDeathDate' => true]
    ],
    'hasChildren' => [
        'yes' => ['numberOfChildren' => true, 'childrenDetails' => true]
    ],
    'hasMedicalConditions' => [
        'yes' => ['medicalConditionsList' => true, 'medicalNotes' => true]
    ],
    // Academic qualifications conditional requirements
    'academic_institution' => [
        'has_value' => ['academic_qualification' => true]
    ],
    'academic_qualification' => [
        'has_value' => ['academic_institution' => true]
    ],
    // Professional/Technical qualifications conditional requirements
    'proftech_profession' => [
        'has_value' => ['proftech_course' => true]
    ],
    'proftech_course' => [
        'has_value' => ['proftech_profession' => true]
    ],
    // Military courses conditional requirements
    'milcourse_name' => [
        'has_value' => ['milcourse_institution' => true, 'milcourse_type' => true]
    ],
    'milcourse_institution' => [
        'has_value' => ['milcourse_name' => true, 'milcourse_type' => true]
    ],
    'milcourse_type' => [
        'has_value' => ['milcourse_name' => true, 'milcourse_institution' => true]
    ],
    // Trade/Group classifications conditional requirements
    'tradegroup_employment' => [
        'has_value' => ['tradegroup_group' => true, 'tradegroup_class' => true]
    ],
    'tradegroup_group' => [
        'has_value' => ['tradegroup_employment' => true, 'tradegroup_class' => true]
    ],
    'tradegroup_class' => [
        'has_value' => ['tradegroup_employment' => true, 'tradegroup_group' => true]
    ]
];

// Form sections for progress tracking
$formSections = [
    'personal_info' => ['title' => 'Personal Information', 'weight' => 25],
    'military_info' => ['title' => 'Military Information', 'weight' => 30],
    'contact_info' => ['title' => 'Contact Information', 'weight' => 20],
    'additional_info' => ['title' => 'Additional Information', 'weight' => 15],
    'documents' => ['title' => 'Documents & Files', 'weight' => 10]
];

// Form helper functions
function old($name, $default = '') {
    global $form_data;
    if (strpos($name, '[]') !== false) {
        $base = str_replace('[]', '', $name);
        return isset($form_data[$base]) ? $form_data[$base] : [];
    }
    return isset($form_data[$name]) ? htmlspecialchars($form_data[$name], ENT_QUOTES) : 
           (isset($_POST[$name]) ? htmlspecialchars($_POST[$name], ENT_QUOTES) : $default);
}

function selected($name, $value, $default = false) {
    $selected = old($name, $default);
    return ($selected == $value) ? 'selected' : '';
}

function checked($name, $value, $default = false) {
    $checked = old($name, $default);
    if (is_array($checked)) {
        return in_array($value, $checked) ? 'checked' : '';
    }
    return ($checked == $value) ? 'checked' : '';
}

function hasError($field) {
    global $form_errors;
    return isset($form_errors[$field]);
}

function getError($field) {
    global $form_errors;
    return $form_errors[$field] ?? '';
}

// Form validation state
$tabErrors = [];
$success = false;
$errors = [];

// Security configurations
$securityConfig = [
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'allowed_file_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
    'csrf_token_lifetime' => 3600, // 1 hour
    'session_timeout' => 7200, // 2 hours
    'max_form_submissions_per_hour' => 10
];

// Performance optimization settings
$performanceConfig = [
    'enable_caching' => true,
    'cache_lifetime' => 300, // 5 minutes
    'lazy_load_images' => true,
    'compress_responses' => true,
    'enable_pagination' => true,
    'records_per_page' => 50
];

// Enhanced error messages for better UX
$errorMessages = [
    'required' => 'This field is required',
    'email' => 'Please enter a valid email address',
    'phone' => 'Please enter a valid phone number',
    'date' => 'Please enter a valid date',
    'unique' => 'This value already exists in the system',
    'min_length' => 'Must be at least {min} characters long',
    'max_length' => 'Must not exceed {max} characters',
    'pattern' => 'Please enter a valid format',
    'file_size' => 'File size must not exceed {max}MB',
    'file_type' => 'Only {types} files are allowed'
];
?>