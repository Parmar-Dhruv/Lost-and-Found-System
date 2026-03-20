<?php
// actions/delete_item.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Only POST requests allowed (EC-02: never GET) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// ── 2. Must be logged in ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

// ── 3. CSRF check — first thing before any DB work ───────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=csrf');
    exit;
}

// ── 4. Collect and cast IDs immediately ──────────────────────────────────────
$item_id  = (int)($_POST['item_id'] ?? 0);
$user_id  = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($item_id <= 0) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_found');
    exit;
}

// ── 5. EC-02: Fetch item and verify ownership ─────────────────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT * FROM items
    WHERE item_id  = ?
      AND is_deleted = 0
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

// Item must exist and not already be deleted
if (!$item) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_found');
    exit;
}

// Only owner or admin can delete
if ((int)$item['user_id'] !== $user_id && !$is_admin) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_owner');
    exit;
}

// ── 6. Soft delete — set is_deleted = 1, never actual DELETE query ────────────
try {
    $stmt = $db->prepare("
        UPDATE items
        SET is_deleted = 1
        WHERE item_id = ?
    ");
    $stmt->execute([$item_id]);

    // ── 7. Log the action ─────────────────────────────────────────────────────
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, entity, entity_id)
        VALUES (?, 'delete_item', 'item', ?)
    ");
    $log->execute([$user_id, $item_id]);

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=db_error');
    exit;
}

// ── 8. Redirect based on who deleted ─────────────────────────────────────────
if ($is_admin) {
    header('Location: ' . BASE_URL . 'admin/items.php?success=deleted');
} else {
    header('Location: ' . BASE_URL . 'pages/my_items.php?success=deleted');
}
exit;
