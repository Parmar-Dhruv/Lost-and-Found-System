<?php
require_once __DIR__ . '/../config/init.php';

// Only log the action if someone is actually logged in
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity, entity_id)
            VALUES (?, 'logout', 'user', ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Log failure silently — do not block logout
    }
}

// Destroy everything in the session
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: /lost-and-found/auth/login.php');
exit;
