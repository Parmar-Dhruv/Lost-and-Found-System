<?php
// admin/claims.php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// ── Fetch all claims with item and user info, newest first ────────────────────
$stmt = $db->prepare("
    SELECT
        c.claim_id,
        c.message,
        c.status        AS claim_status,
        c.admin_remark,
        c.claimed_at,
        c.item_id,
        c.claimant_id,
        i.item_name,
        i.status        AS item_status,
        i.user_id       AS item_owner_id,
        u.full_name     AS claimant_name,
        u.email         AS claimant_email
    FROM claims c
    JOIN items  i ON c.item_id     = i.item_id
    JOIN users  u ON c.claimant_id = u.user_id
    ORDER BY c.claimed_at DESC
");
$stmt->execute();
$claims = $stmt->fetchAll();

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$successMessages = [
    'approved'  => 'Claim approved. All other pending claims for this item have been rejected.',
    'rejected'  => 'Claim rejected.',
    'collected' => 'Item marked as collected and closed.',
];

$errorMessages = [
    'invalid_csrf'    => 'Invalid form submission.',
    'invalid_claim'   => 'Claim not found.',
    'invalid_action'  => 'Invalid action.',
    'already_handled' => 'This claim has already been approved or rejected.',
    'not_approved'    => 'Cannot mark as collected — claim is not approved yet.',
    'db_error'        => 'Database error. Please try again.',
];

// Helper: badge class for claim status
function claimBadge(string $status): string {
    return match($status) {
        'pending'  => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        default    => 'badge-secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims — Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        .claim-row { border:1px solid #333; border-radius:6px; padding:16px; margin-bottom:16px; background:#1a1a2e; }
        .claim-row h4 { margin:0 0 8px; color:#e94560; }
        .claim-meta { font-size:0.85em; color:#aaa; margin-bottom:10px; }
        .claim-message { background:#0f3460; padding:10px; border-radius:4px; margin-bottom:12px; white-space:pre-wrap; }
        .claim-actions form { display:inline-block; margin-right:8px; vertical-align:top; }
        .remark-input { width:300px; padding:6px; border-radius:4px; border:1px solid #555; background:#16213e; color:#fff; resize:vertical; }
        .self-claim-note { background:#3a2a00; border:1px solid #e6a817; color:#e6a817; padding:6px 10px; border-radius:4px; font-size:0.82em; margin-bottom:8px; display:inline-block; }
    </style>
</head>
<body>

<nav>
    <a href="<?= BASE_URL ?>pages/home.php">← Back to Site</a> |
    <a href="<?= BASE_URL ?>admin/index.php">Dashboard</a> |
    <a href="<?= BASE_URL ?>admin/items.php">Items</a> |
    <a href="<?= BASE_URL ?>admin/users.php">Users</a> |
    <a href="<?= BASE_URL ?>admin/claims.php">Claims</a> |
    <a href="<?= BASE_URL ?>admin/logs.php">Logs</a> |
    <a href="<?= BASE_URL ?>auth/logout.php">Logout</a>
</nav>

<div class="container">
    <h1>Manage Claims</h1>

    <?php if ($success && isset($successMessages[$success])): ?>
        <div class="alert alert-success"><?= $successMessages[$success] ?></div>
    <?php endif; ?>

    <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="alert alert-danger"><?= $errorMessages[$error] ?></div>
    <?php endif; ?>

    <?php if (empty($claims)): ?>
        <p>No claims have been submitted yet.</p>
    <?php else: ?>

        <p>Total claims: <strong><?= count($claims) ?></strong></p>

        <?php foreach ($claims as $c): ?>

            <div class="claim-row">

                <h4>
                    Claim #<?= $c['claim_id'] ?> —
                    <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $c['item_id'] ?>" style="color:#e94560;">
                        <?= htmlspecialchars($c['item_name']) ?>
                    </a>
                    <span style="font-size:0.75em; color:#aaa; margin-left:8px;">
                        (Item status: <?= ucfirst(htmlspecialchars($c['item_status'])) ?>)
                    </span>
                </h4>

                <!-- EC-06: Flag if claimant is the original item reporter -->
                <?php if ((int)$c['claimant_id'] === (int)$c['item_owner_id']): ?>
                    <span class="self-claim-note">
                        ⚠ This claimant is the original reporter of this item.
                    </span>
                <?php endif; ?>

                <div class="claim-meta">
                    <strong>Claimant:</strong> <?= htmlspecialchars($c['claimant_name']) ?>
                    (<?= htmlspecialchars($c['claimant_email']) ?>)
                    &nbsp;|&nbsp;
                    <strong>Submitted:</strong> <?= htmlspecialchars($c['claimed_at']) ?>
                    &nbsp;|&nbsp;
                    <strong>Status:</strong>
                    <span class="badge <?= claimBadge($c['claim_status']) ?>">
                        <?= ucfirst(htmlspecialchars($c['claim_status'])) ?>
                    </span>
                </div>

                <div class="claim-message">
                    <?= nl2br(htmlspecialchars($c['message'])) ?>
                </div>

                <?php if (!empty($c['admin_remark'])): ?>
                    <p style="color:#aaa; font-size:0.85em;">
                        <strong>Admin remark:</strong> <?= nl2br(htmlspecialchars($c['admin_remark'])) ?>
                    </p>
                <?php endif; ?>

                <!-- ── ACTION BUTTONS ──────────────────────────────────────── -->
                <div class="claim-actions">

                    <?php if ($c['claim_status'] === 'pending'): ?>

                        <!-- APPROVE -->
                        <form action="<?= BASE_URL ?>actions/handle_claim.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="claim_id"   value="<?= $c['claim_id'] ?>">
                            <input type="hidden" name="action"     value="approve">
                            <textarea
                                name="admin_remark"
                                class="remark-input"
                                rows="2"
                                placeholder="Optional approval note..."
                            ></textarea><br>
                            <button
                                type="submit"
                                class="btn btn-success"
                                style="margin-top:6px;"
                                onclick="return confirm('Approve this claim? All other pending claims for this item will be auto-rejected.');"
                            >Approve</button>
                        </form>

                        <!-- REJECT -->
                        <form action="<?= BASE_URL ?>actions/handle_claim.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="claim_id"   value="<?= $c['claim_id'] ?>">
                            <input type="hidden" name="action"     value="reject">
                            <textarea
                                name="admin_remark"
                                class="remark-input"
                                rows="2"
                                placeholder="Reason for rejection (visible to claimant)..."
                            ></textarea><br>
                            <!-- EC-08: rejection reason is stored in admin_remark and shown to claimant -->
                            <button
                                type="submit"
                                class="btn btn-danger"
                                style="margin-top:6px;"
                                onclick="return confirm('Reject this claim?');"
                            >Reject</button>
                        </form>

                    <?php elseif ($c['claim_status'] === 'approved'): ?>

                        <!-- EC-07: Two-step — approved but not yet collected -->
                        <?php
                        // Check if already marked collected
                        $is_collected = str_contains($c['admin_remark'] ?? '', 'Collected and closed');
                        ?>
                        <?php if (!$is_collected): ?>
                            <form action="<?= BASE_URL ?>actions/handle_claim.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="claim_id"   value="<?= $c['claim_id'] ?>">
                                <input type="hidden" name="action"     value="collected">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    onclick="return confirm('Confirm item has been physically collected by the claimant?');"
                                >Mark as Collected</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#4caf50;">✓ Item collected and closed.</span>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Rejected — no actions available -->
                        <span style="color:#888; font-size:0.9em;">No further actions available.</span>
                    <?php endif; ?>

                </div><!-- /.claim-actions -->

            </div><!-- /.claim-row -->

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>