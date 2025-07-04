<?php
$combatSizes = ['XS','S','M','L','XL','XXL'];
$bootSizes = range(4, 13);
$shoeSizes = range(4, 13);
$headdressSizes = range(54, 62);

if (!isset($_SESSION)) { session_start(); }
if (!class_exists('Token')) {
    class Token {
        public static function generate() {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        public static function check($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}
$csrfToken = Token::generate();

$ranks = $db->query("SELECT rankID, rankName, rankIndex FROM ranks ORDER BY rankIndex ASC")->results();
$units = $db->query("SELECT unitID, unitName FROM units ORDER BY unitName ASC")->results();
$corps = $db->query("SELECT corpsID, corpsName, corpsAbb FROM corps ORDER BY corpsName ASC")->results();
$corpsList = $db->query("SELECT DISTINCT corps FROM staff WHERE corps IS NOT NULL AND corps != '' ORDER BY corps ASC")->results();

$professionOptions = [
    "Accountant","Actor","Actuary","Administrator","Advocate","Agriculturalist","Analyst","Animator","Architect","Artist","Auditor","Author","Baker","Banker","Biochemist","Biologist","Bricklayer","Broker","Builder","Business Analyst","Businessperson","Butcher","Carpenter","Chef","Chemist","Civil Engineer","Clerk","Coach","Consultant","Counselor","Dentist","Designer","Developer","Dietitian","Doctor","Driver","Economist","Editor","Electrician","Engineer","Entrepreneur","Farmer","Fashion Designer","Filmmaker","Firefighter","Fisherman","Geologist","Graphic Designer","Hairdresser","Historian","Hotelier","HR Specialist","IT Specialist","Journalist","Judge","Lawyer","Lecturer","Librarian","Logistician","Manager","Mason","Mathematician","Mechanic","Medical Officer","Microbiologist","Miner","Musician","Nurse","Nutritionist","Optician","Painter","Paramedic","Pharmacist","Photographer","Physician","Physicist","Pilot","Plumber","Police Officer","Politician","Professor","Programmer","Project Manager","Psychologist","Public Servant","Receptionist","Researcher","Scientist","Secretary","Security Officer","Social Worker","Software Engineer","Soldier","Statistician","Surgeon","Surveyor","Tailor","Teacher","Technician","Therapist","Translator","Veterinarian","Waiter","Web Developer","Welder","Writer","Other"
];

function old($name, $default = '') {
    if (strpos($name, '[]') !== false) {
        $base = str_replace('[]', '', $name);
        return isset($_POST[$base]) ? $_POST[$base] : [];
    }
    return isset($_POST[$name]) ? htmlspecialchars($_POST[$name], ENT_QUOTES) : $default;
}

$relationshipOptions = [
    'Spouse', 'Father', 'Mother', 'Son', 'Daughter', 'Brother', 'Sister', 'Uncle', 'Aunt', 'Cousin', 'Nephew', 'Niece', 'Grandfather', 'Grandmother', 'Other'
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
    'Western' => ['Mongu', 'Senanga', 'Kaoma', 'Lukulu', 'Sesheke', 'Shangombo', 'Kalabo', 'Nalolo', 'Sikongo', 'Sioma'],
];
$provinceOptions = array_keys($provinceDistricts);

$religionOptions = ['Atheism', 'Baha\'i', 'Buddhism', 'Christianity', 'Hinduism', 'Islam', 'Judaism', 'Sikhism', 'Traditional African Religion'];

$tabErrors = [];
$success = false;
$errors = [];