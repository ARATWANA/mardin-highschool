<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/includes/config.php';
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
