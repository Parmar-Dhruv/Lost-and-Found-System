<?php
require_once __DIR__ . '/../config/init.php';

// If not logged in at all — send to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// If logged in but NOT admin — send to home page
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// If we reach here — user is logged in AND is admin. Allow access.