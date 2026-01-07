<?php
/**
 * Staff Creation Form Configuration
 * 
 * RANK LEVEL SYSTEM:
 * - Levels 1-13:  Officers (1=highest, 13=lowest officer rank)
 * - Level 14:     Officer Cadets
 * - Levels 15-26: Non-Commissioned Officers (15=highest NCO, 26=lowest NCO)
 * - Level 27:     Recruits
 * - Level 28:     Civilian Employees
 * 
 * SENIORITY ORDERING:
 * ORDER BY r.level ASC ensures correct seniority (lower level = higher rank)
 * Secondary sort: subWef, tempWef, attestDate, svcNo for same-rank seniority
 */

// Enhanced Personnel Management Configuration with Analytics Integration
// Define module constants
if (!defined('ARMIS_ADMIN_BRANCH')) {
    define('ARMIS_ADMIN_BRANCH', true);
}

// Include enhanced admin branch system
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/analytics.php';
require_once dirname(dirname(__DIR__)) . '/shared/rank_levels.php';
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
    
    // Fetch ranks using `rank` table (only has rankId and level columns)
    // rankId contains the actual rank name (e.g., 'General', 'Colonel', 'Private')
    $rankQuery = "
        SELECT r.rankId as rankID, 
               r.rankId as rankName, 
               r.rankId as abbreviation,
               r.level as rankIndex,
               r.level,
               COUNT(s.svcNo) as staff_count,
               " . getRankCategoryCaseSQL('r') . " as category
        FROM `rank` r
        LEFT JOIN staff s ON r.rankId = s.rankId AND s.svcStatus = 'Active'
        WHERE r.level IS NOT NULL
        GROUP BY r.rankId, r.level
        ORDER BY r.level ASC
    ";
    error_log("Executing rank query: " . str_replace("\n", " ", $rankQuery));
    $stmt = $pdo->prepare($rankQuery);
    $stmt->execute();
    $ranks = $stmt->fetchAll(PDO::FETCH_OBJ);
    error_log("Loaded " . count($ranks) . " ranks from database");
    
    // Fetch units with enhanced data and hierarchy support
    $unitQuery = "
        SELECT u.unitId as unitID, u.code as unitName, u.code as unitCode, 
               u.level as unitType, u.parentUnitId, u.commanderSvcno, 
               u.location,
               COUNT(s.svcNo) as staff_count,
               COALESCE(c.fName, 'No Commander') as commander_name,
               p.code as parent_unit_name
        FROM unit u 
        LEFT JOIN staff s ON u.unitId = s.unitId AND s.svcStatus = 'Active'
        LEFT JOIN staff c ON u.commanderSvcno = c.svcNo
        LEFT JOIN unit p ON u.parentUnitId = p.unitId
        GROUP BY u.unitId 
        ORDER BY u.parentUnitId ASC, u.code ASC
    ";
    $stmt = $pdo->prepare($unitQuery);
    $stmt->execute();
    $units = $stmt->fetchAll(PDO::FETCH_OBJ);
    error_log("Loaded " . count($units) . " units from database");
    
    // If no units found, try simpler query to check if units table exists
    if (empty($units)) {
        $stmt = $pdo->prepare("SELECT unitId as unitID, code as unitName, code as unitCode FROM unit ORDER BY code ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $units[] = (object)array_merge($row, ['unitType' => '', 'staff_count' => 0, 'location' => '']);
        }
    }
    
    // Fetch corps from corps table using correct field names
    $corpsQuery = "
        SELECT c.corpsId as corpsID, c.abbreviation as corpsAbb, c.abbreviation as corpsName,
               COUNT(s.svcNo) as usage_count
        FROM corps c
        LEFT JOIN staff s ON c.corpsId = s.corpsId AND s.svcStatus = 'Active'
        GROUP BY c.corpsId 
        ORDER BY c.abbreviation ASC
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
    
    // Add default ranks if none exist in database (use correct level system)
    if (empty($ranks)) {
        error_log("Using fallback ranks - database query returned no results");
        $defaultRanks = [
            ['name' => 'Private', 'level' => 26, 'abbr' => 'Pte', 'cat' => 'NCO'],
            ['name' => 'Lance Corporal', 'level' => 25, 'abbr' => 'LCpl', 'cat' => 'NCO'],
            ['name' => 'Corporal', 'level' => 24, 'abbr' => 'Cpl', 'cat' => 'NCO'],
            ['name' => 'Sergeant', 'level' => 20, 'abbr' => 'Sgt', 'cat' => 'NCO'],
            ['name' => 'Warrant Officer', 'level' => 15, 'abbr' => 'WO1', 'cat' => 'NCO'],
            ['name' => '2nd Lieutenant', 'level' => 13, 'abbr' => '2Lt', 'cat' => 'Officer'],
            ['name' => 'Lieutenant', 'level' => 12, 'abbr' => 'Lt', 'cat' => 'Officer'],
            ['name' => 'Captain', 'level' => 11, 'abbr' => 'Capt', 'cat' => 'Officer'],
            ['name' => 'Major', 'level' => 9, 'abbr' => 'Maj', 'cat' => 'Officer'],
            ['name' => 'Colonel', 'level' => 5, 'abbr' => 'Col', 'cat' => 'Officer'],
            ['name' => 'General', 'level' => 1, 'abbr' => 'Gen', 'cat' => 'Officer']
        ];
        
        foreach ($defaultRanks as $index => $rank) {
            $ranks[] = (object)[
                'rankID' => $rank['abbr'], // Use abbreviation as ID
                'rankName' => $rank['name'],
                'rankIndex' => $rank['level'],
                'level' => $rank['level'],
                'abbreviation' => $rank['abbr'],
                'category' => $rank['cat'],
                'staff_count' => 0
            ];
        }
    }
    
} catch (Exception $e) {
    error_log("Database error in create_staff_config: " . $e->getMessage());
    error_log("Using exception handler fallback ranks");
    
    // Fallback data with correct rank level system
    $ranks = [
        (object)['rankID' => 1, 'rankName' => 'Private', 'rankIndex' => 26, 'abbreviation' => 'Pte', 'staff_count' => 0, 'category' => 'NCO', 'level' => 26],
        (object)['rankID' => 2, 'rankName' => 'Corporal', 'rankIndex' => 24, 'abbreviation' => 'Cpl', 'staff_count' => 0, 'category' => 'NCO', 'level' => 24],
        (object)['rankID' => 3, 'rankName' => 'Sergeant', 'rankIndex' => 20, 'abbreviation' => 'Sgt', 'staff_count' => 0, 'category' => 'NCO', 'level' => 20],
        (object)['rankID' => 4, 'rankName' => 'Lieutenant', 'rankIndex' => 13, 'abbreviation' => 'Lt', 'staff_count' => 0, 'category' => 'Officer', 'level' => 13],
        (object)['rankID' => 5, 'rankName' => 'Captain', 'rankIndex' => 11, 'abbreviation' => 'Capt', 'staff_count' => 0, 'category' => 'Officer', 'level' => 11],
        (object)['rankID' => 6, 'rankName' => 'Major', 'rankIndex' => 9, 'abbreviation' => 'Maj', 'staff_count' => 0, 'category' => 'Officer', 'level' => 9]
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

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

$maritalStatusOptions = [
    'single' => 'Single',
    'married' => 'Married',
    'divorced' => 'Divorced',
    'widowed' => 'Widowed',
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
    'christian' => 'Christian',
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