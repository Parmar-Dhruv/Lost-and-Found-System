<?php
// actions/handle_claim.php
require_once __DIR__ . '/../config/init.php';

// ── 1. CSRF check — always first ─────────────────────────────────────────────
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    header('Location: ' . BASE_URL . 'admin/claims.php?error=invalid_csrf');
    exit;
}

// ── 2. Admin only ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

$admin_id  = (int)$_SESSION['user_id'];
$claim_id  = (int)($_POST['claim_id']     ?? 0);
$action    = trim($_POST['action']        ?? '');
$remark    = trim($_POST['admin_remark']  ?? '');

// ── 3. Validate claim_id ──────────────────────────────────────────────────────
if ($claim_id <= 0) {
    header('Location: ' . BASE_URL . 'admin/claims.php?error=invalid_claim');
    exit;
}

// ── 4. Validate action ────────────────────────────────────────────────────────
$allowed_actions = ['approve', 'reject', 'collected'];
if (!in_array($action, $allowed_actions, true)) {
    header('Location: ' . BASE_URL . 'admin/claims.php?error=invalid_action');
    exit;
}

$db = getDB();

// ── 5. Fetch the claim — must exist and be pending ────────────────────────────
//      Exception: 'collected' action works on approved claims
$stmt = $db->prepare("
    SELECT c.claim_id, c.item_id, c.claimant_id, c.status
    FROM claims c
    WHERE c.claim_id = ?
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch();

if (!$claim) {
    header('Location: ' . BASE_URL . 'admin/claims.php?error=invalid_claim');
    exit;
}

// ── 6. Handle each action ─────────────────────────────────────────────────────

if ($action === 'approve') {

    // Claim must be pending to approve
    if ($claim['status'] !== 'pending') {
        header('Location: ' . BASE_URL . 'admin/claims.php?error=already_handled');
        exit;
    }

    try {
        $db->beginTransaction();

        // 6a. Approve this claim
        $upd = $db->prepare("
            UPDATE claims
            SET status = 'approved', admin_remark = ?
            WHERE claim_id = ?
        ");
        $upd->execute([$remark ?: null, $claim_id]);

        // 6b. Set item status to 'claimed' (EC-07: two-step, not 'closed' yet)
        $updItem = $db->prepare("
            UPDATE items SET status = 'claimed' WHERE item_id = ?
        ");
        $updItem->execute([$claim['item_id']]);

        // 6c. Auto-reject all other pending claims on this item (EC-05)
        $reject = $db->prepare("
            UPDATE claims
            SET status = 'rejected',
                admin_remark = 'Automatically rejected — another claim was approved for this item.'
            WHERE item_id = ?
              AND claim_id != ?
              AND status = 'pending'
        ");
        $reject->execute([$claim['item_id'], $claim_id]);

        // 6d. Log the approve action
        $log = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity, entity_id)
            VALUES (?, 'approve_claim', 'claim', ?)
        ");
        $log->execute([$admin_id, $claim_id]);

        $db->commit();

        header('Location: ' . BASE_URL . 'admin/claims.php?success=approved');
        exit;

    } catch (PDOException $e) {
        $db->rollBack();
        header('Location: ' . BASE_URL . 'admin/claims.php?error=db_error');
        exit;
    }

} elseif ($action === 'reject') {

    // Claim must be pending to reject
    if ($claim['status'] !== 'pending') {
        header('Location: ' . BASE_URL . 'admin/claims.php?error=already_handled');
        exit;
    }

    try {
        $upd = $db->prepare("
            UPDATE claims
            SET status = 'rejected', admin_remark = ?
            WHERE claim_id = ?
        ");
        // EC-08: admin_remark is where the rejection reason lives — visible to claimant
        $upd->execute([$remark ?: 'Claim rejected by admin.', $claim_id]);

        $log = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity, entity_id)
            VALUES (?, 'reject_claim', 'claim', ?)
        ");
        $log->execute([$admin_id, $claim_id]);

        header('Location: ' . BASE_URL . 'admin/claims.php?success=rejected');
        exit;

    } catch (PDOException $e) {
        header('Location: ' . BASE_URL . 'admin/claims.php?error=db_error');
        exit;
    }

} elseif ($action === 'collected') {

    // EC-07: Two-step process — claim must be approved before marking collected
    if ($claim['status'] !== 'approved') {
        header('Location: ' . BASE_URL . 'admin/claims.php?error=not_approved');
        exit;
    }

    try {
        // Mark claim as collected — we reuse admin_remark to timestamp the note
        $upd = $db->prepare("
            UPDATE claims
            SET status = 'approved',
                admin_remark = CONCAT(COALESCE(admin_remark, ''), ' | Collected and closed by admin.')
            WHERE claim_id = ?
        ");
        $upd->execute([$claim_id]);

        // Set item status to indicate it's fully resolved
        // We keep status as 'claimed' — item is already off the active list
        // The claim record itself carries the collected confirmation

        $log = $db->prepare("
            INSERT INTO activity_log (user_id, action, entity, entity_id)
            VALUES (?, 'collected_claim', 'claim', ?)
        ");
        $log->execute([$admin_id, $claim_id]);

        header('Location: ' . BASE_URL . 'admin/claims.php?success=collected');
        exit;

    } catch (PDOException $e) {
        header('Location: ' . BASE_URL . 'admin/claims.php?error=db_error');
        exit;
    }
}
