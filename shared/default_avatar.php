<?php
// Simple profile image generator
header('Content-Type: image/svg+xml');
header('Cache-Control: max-age=31536000'); // Cache for 1 year

$initials = 'NA';
if (isset($_GET['name'])) {
    $name = trim($_GET['name']);
    $nameParts = explode(' ', $name);
    if (count($nameParts) >= 2) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
    } elseif (count($nameParts) == 1) {
        $initials = strtoupper(substr($nameParts[0], 0, 2));
    }
}

$colors = [
    '#007bff', '#6c757d', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6f42c1', '#e83e8c'
];
$color = $colors[crc32($initials) % count($colors)];

echo <<<SVG
<svg width="120" height="120" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
    <rect width="120" height="120" fill="{$color}" rx="60"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="40" font-weight="bold" 
          fill="white" text-anchor="middle" dy="0.35em">{$initials}</text>
</svg>
SVG;
?>
