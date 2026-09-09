<?php
require_once __DIR__ . '/../shared/env.php';

// SMTP credentials come from .env (never committed) or real server
// env vars — see .env.example. The previous hardcoded password here
// was live and has been removed; rotate it if it hasn't been already.
return [
    'smtp_host'     => env_get('SMTP_HOST', 'smtp.envisagezm.com'),
    'smtp_port'     => (int) env_get('SMTP_PORT', '587'),
    'smtp_username' => env_get('SMTP_USERNAME', 'support@envisagezm.com'),
    'smtp_password' => env_get('SMTP_PASSWORD', ''),
    'from_email'    => env_get('SMTP_FROM_EMAIL', 'support@envisagezm.com'),
    'from_name'     => env_get('SMTP_FROM_NAME', 'ARMIS System'),
];
