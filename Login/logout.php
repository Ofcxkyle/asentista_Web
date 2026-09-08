<?php
// Logs out the user and redirects to home page
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/function.php';

logoutUser();
$isSubfolder = (strpos($_SERVER['REQUEST_URI'] ?? '', '/Login/') !== false);
$prefix = $isSubfolder ? '../' : '';
header('Location: ' . $prefix . 'index.php?auth_msg=logged_out');
exit;
