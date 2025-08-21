<?php
/**
 * Session Debug Tool
 * Helps verify session variables across the ARMIS system
 */

session_start();

echo "<h2>ARMIS Session Debug Information</h2>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>Session Variables:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Session Status:</h3>";
echo "<ul>";
echo "<li><strong>Session ID:</strong> " . session_id() . "</li>";
echo "<li><strong>Session Status:</strong> " . session_status() . "</li>";
echo "<li><strong>user_id exists:</strong> " . (isset($_SESSION['user_id']) ? 'YES (' . $_SESSION['user_id'] . ')' : 'NO') . "</li>";
echo "<li><strong>staff_id exists:</strong> " . (isset($_SESSION['staff_id']) ? 'YES (' . $_SESSION['staff_id'] . ')' : 'NO') . "</li>";
echo "</ul>";

echo "<h3>Quick Navigation:</h3>";
echo "<ul>";
echo "<li><a href='index.php'>User Dashboard</a></li>";
echo "<li><a href='personal.php'>Personal Information</a></li>";
echo "<li><a href='analytics_dashboard.php'>Analytics Dashboard</a></li>";
echo "<li><a href='mobile_personal.php'>Mobile Personal Form</a></li>";
echo "<li><a href='implementation_status.php'>Implementation Status</a></li>";
echo "</ul>";

echo "<h3>Test Combat Size Options:</h3>";
$combatSizes = [
    'XS' => 'Extra Small (XS)',
    'S' => 'Small (S)', 
    'M' => 'Medium (M)',
    'L' => 'Large (L)',
    'XL' => 'Extra Large (XL)',
    'XXL' => '2X Large (XXL)',
    '3XL' => '3X Large (3XL)',
    '4XL' => '4X Large (4XL)'
];

echo "<select class='form-select' style='width: 300px; padding: 10px; margin: 10px 0;'>";
echo "<option value=''>Select Combat Size</option>";
foreach ($combatSizes as $value => $label) {
    echo "<option value='$value'>$label</option>";
}
echo "</select>";

echo "<p><em>This debug page helps verify that session variables and combat size options are working correctly.</em></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}
h2, h3 {
    color: #4a5d23;
}
pre {
    background: #fff;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #ddd;
}
ul {
    background: #fff;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #ddd;
}
a {
    color: #4a5d23;
    text-decoration: none;
    font-weight: bold;
}
a:hover {
    text-decoration: underline;
}
</style>
