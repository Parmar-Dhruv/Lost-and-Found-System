<?php
// actions/submit_claim.php
require_once __DIR__ . '/../config/init.php';

// ── 1. CSRF check — always first ─────────────────────────────────────────────
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    header('Location: ' . BASE_URL . 'pages/home.php?error=invalid_csrf');
    exit;
}

// ── 2. Must be logged in ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

// Admin cannot submit claims — they manage them
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$item_id = (int)($_POST['item_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

// ── 3. Validate item_id ───────────────────────────────────────────────────────
if ($item_id <= 0) {
    header('Location: ' . BASE_URL . 'pages/home.php?error=invalid_item');
    exit;
}

// ── 4. Validate message — must not be empty ───────────────────────────────────
if ($message === '') {
    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&error=claim_failed&message=');
    exit;
}

$db = getDB();

// ── 5. Fetch item — must exist, not deleted, status must be 'found' ───────────
$stmt = $db->prepare("
    SELECT item_id, status, user_id
    FROM items
    WHERE item_id = ?
      AND is_deleted = 0
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: ' . BASE_URL . 'pages/home.php?error=invalid_item');
    exit;
}

if ($item['status'] !== 'found') {
    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&error=not_claimable');
    exit;
}

// ── 6. Duplicate claim check (EC-05) ─────────────────────────────────────────
// One user cannot submit two claims on the same item
$dup = $db->prepare("
    SELECT claim_id FROM claims
    WHERE item_id = ? AND claimant_id = ?
    LIMIT 1
");
$dup->execute([$item_id, $user_id]);

if ($dup->fetch()) {
    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&error=duplicate_claim');
    exit;
}

// ── 7. Insert the claim ───────────────────────────────────────────────────────
try {
    $ins = $db->prepare("
        INSERT INTO claims (item_id, claimant_id, message, status)
        VALUES (?, ?, ?, 'pending')
    ");
    $ins->execute([$item_id, $user_id, $message]);

    // ── 8. Log the action ─────────────────────────────────────────────────────
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, entity, entity_id)
        VALUES (?, 'submit_claim', 'claim', ?)
    ");
    $log->execute([$user_id, $db->lastInsertId()]);

    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&success=claim_sent');
    exit;

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&error=claim_failed');
    exit;
}
