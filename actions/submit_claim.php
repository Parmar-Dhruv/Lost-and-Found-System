<?php
// actions/submit_claim.php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/mailer.php';

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
    SELECT i.item_id, i.status, i.user_id, i.item_name,
           u.full_name AS reporter_name, u.email AS reporter_email
    FROM items i
    JOIN users u ON u.user_id = i.user_id
    WHERE i.item_id = ?
      AND i.is_deleted = 0
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

// ── 7. Fetch claimant details for email ───────────────────────────────────────
$claimantStmt = $db->prepare("
    SELECT full_name, email FROM users WHERE user_id = ?
");
$claimantStmt->execute([$user_id]);
$claimant = $claimantStmt->fetch();

// ── 8. Insert the claim ───────────────────────────────────────────────────────
try {
    $ins = $db->prepare("
        INSERT INTO claims (item_id, claimant_id, message, status)
        VALUES (?, ?, ?, 'pending')
    ");
    $ins->execute([$item_id, $user_id, $message]);
    $new_claim_id = (int)$db->lastInsertId();

    // ── 9. Log the action ─────────────────────────────────────────────────────
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, entity, entity_id)
        VALUES (?, 'submit_claim', 'claim', ?)
    ");
    $log->execute([$user_id, $new_claim_id]);

    // ── 10. Email: notify claimant that their claim was received ──────────────
    if ($claimant) {
        $subject = 'Your Claim Has Been Submitted — ' . $item['item_name'];
        $body = '
            <p>Hi ' . htmlspecialchars($claimant['full_name']) . ',</p>
            <p>Your claim for the item <strong>' . htmlspecialchars($item['item_name']) . '</strong> has been submitted successfully.</p>
            <p>Your message: <em>' . htmlspecialchars($message) . '</em></p>
            <p>An admin will review your claim shortly. You will receive another email when a decision is made.</p>
            <br>
            <p>— Lost and Found System</p>
        ';
        sendMail($claimant['email'], $claimant['full_name'], $subject, $body);
    }

    // ── 11. Email: notify item reporter that a claim was submitted ────────────
    if ($item['reporter_email'] !== ($claimant['email'] ?? '')) {
        $subject = 'Someone Has Claimed Your Item — ' . $item['item_name'];
        $body = '
            <p>Hi ' . htmlspecialchars($item['reporter_name']) . ',</p>
            <p>A user has submitted a claim on the item you reported: <strong>' . htmlspecialchars($item['item_name']) . '</strong>.</p>
            <p>An admin will review the claim and make a decision. No action is required from you at this time.</p>
            <br>
            <p>— Lost and Found System</p>
        ';
        sendMail($item['reporter_email'], $item['reporter_name'], $subject, $body);
    }

    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&success=claim_sent');
    exit;

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'pages/item_detail.php?id=' . $item_id . '&error=claim_failed');
    exit;
}