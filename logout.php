<?php
/**
 * Asentista Bakery - Logout Controller
 */
require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/database/function.php';

logoutUser();
header('Location: index.php?auth_msg=logged_out');
exit;
