<?php
require_once 'staff_auth.php';

// Logout the user
logoutUser();

// Set logout message
setMessage('You have been logged out successfully.', 'success');

// Redirect to login page
redirect('login.php');
?>