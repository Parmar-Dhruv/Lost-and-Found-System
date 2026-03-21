<?php
// pages/item_detail.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Get and validate item_id from URL ─────────────────────────────────────
$item_id = (int)($_GET['id'] ?? 0);

if ($item_id <= 0) {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// ── 2. Fetch the item — must not be soft-deleted ─────────────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT i.*, u.full_name AS reporter_name
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.item_id = ?
      AND i.is_deleted = 0
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

// ── 3. If item doesn't exist or is deleted, bounce to home ───────────────────
if (!$item) {
    header('Location: ' . BASE_URL . 'pages/home.php?error=item_not_found');
    exit;
}

// ── 4. Is the logged-in user the owner of this item? ─────────────────────────
$is_owner = isset($_SESSION['user_id']) && ((int)$_SESSION['user_id'] === (int)$item['user_id']);
$is_admin = isset($_SESSION['role'])    && $_SESSION['role'] === 'admin';

// ── 5. Has the logged-in user already submitted a claim on this item? ─────────
$already_claimed = false;
if (isset($_SESSION['user_id'])) {
    $chk = $db->prepare("
        SELECT claim_id FROM claims
        WHERE item_id = ? AND claimant_id = ?
        LIMIT 1
    ");
    $chk->execute([$item_id, (int)$_SESSION['user_id']]);
    $already_claimed = (bool)$chk->fetch();
}

// ── 6. CSRF token ─────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$errorMessages = [
    'not_owner'       => 'You can only edit or delete your own items.',
    'db_error'        => 'Something went wrong. Please try again.',
    // Claim errors
    'not_logged_in'   => 'You must be logged in to submit a claim.',
    'invalid_item'    => 'Invalid item.',
    'not_claimable'   => 'This item is not available for claiming.',
    'duplicate_claim' => 'You have already submitted a claim for this item.',
    'claim_failed'    => 'Failed to submit claim. Please try again.',
];

$successMessages = [
    'updated'      => 'Item updated successfully.',
    'claim_sent'   => 'Your claim has been submitted. The admin will review it.',
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';

// Status badge colour helper
function statusBadge(string $status): string {
    return match($status) {
        'lost'    => 'badge-danger',
        'found'   => 'badge-success',
        'claimed' => 'badge-primary',
        'expired' => 'badge-secondary',
        default   => 'badge-light',
    };
}
?>

<div class="container">

    <?php if ($success && isset($successMessages[$success])): ?>
        <div class="alert alert-success"><?= $successMessages[$success] ?></div>
    <?php endif; ?>

    <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="alert alert-danger"><?= $errorMessages[$error] ?></div>
    <?php endif; ?>

    <div class="item-detail-card">

        <div class="item-detail-header">
            <h2><?= htmlspecialchars($item['item_name']) ?></h2>
            <span class="badge <?= statusBadge($item['status']) ?>">
                <?= ucfirst(htmlspecialchars($item['status'])) ?>
            </span>
        </div>

        <!-- Item image — shown only if one was uploaded -->
        <?php if (!empty($item['image'])): ?>
            <div class="item-image" style="margin-bottom:16px;">
                <!--
                    DB stores: assets/uploads/filename.jpg
                    BASE_URL is: /lost-and-found/
                    Result:      /lost-and-found/assets/uploads/filename.jpg
                    DO NOT add 'assets/uploads/' here — it is already in the DB value.
                -->
                <img
                    src="<?= BASE_URL . htmlspecialchars($item['image']) ?>"
                    alt="Item Image"
                    style="max-width:400px; border:1px solid #ccc; border-radius:4px;"
                >
            </div>
        <?php else: ?>
            <p><em>No image provided for this item.</em></p>
        <?php endif; ?>

        <table class="table table-bordered">
            <tr>
                <th style="width:200px;">Category</th>
                <td><?= htmlspecialchars($item['category']) ?></td>
            </tr>
            <tr>
                <th>Description</th>
                <td><?= nl2br(htmlspecialchars($item['description'])) ?></td>
            </tr>
            <tr>
                <th>Location</th>
                <td><?= htmlspecialchars($item['location']) ?></td>
            </tr>
            <tr>
                <th>Contact</th>
                <td><?= htmlspecialchars($item['contact']) ?></td>
            </tr>
            <tr>
                <th>Date Reported</th>
                <td><?= htmlspecialchars($item['date_reported']) ?></td>
            </tr>
            <tr>
                <th>Reported By</th>
                <td><?= htmlspecialchars($item['reporter_name']) ?></td>
            </tr>
            <tr>
                <th>Posted On</th>
                <td><?= htmlspecialchars($item['created_at']) ?></td>
            </tr>
        </table>

        <div class="item-actions" style="margin-top:20px;">

            <?php if ($is_owner || $is_admin): ?>
                <a href="<?= BASE_URL ?>pages/my_items.php?edit=<?= $item['item_id'] ?>" class="btn btn-warning">Edit</a>

                <form
                    action="<?= BASE_URL ?>actions/delete_item.php"
                    method="POST"
                    style="display:inline;"
                    onsubmit="return confirm('Are you sure you want to delete this item? This cannot be undone.');"
                >
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="item_id"    value="<?= $item['item_id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>pages/home.php" class="btn btn-secondary">← Back to Home</a>

        </div>

        <!-- ── CLAIM SECTION ─────────────────────────────────────────────────
             Show claim form only when ALL of these are true:
             1. Item status is 'found' (lost/claimed/expired cannot be claimed)
             2. A user is logged in
             3. User has NOT already submitted a claim on this item
             Admin is intentionally excluded — admins manage claims, they don't submit them
        ──────────────────────────────────────────────────────────────────── -->
        <?php if ($item['status'] === 'found' && isset($_SESSION['user_id']) && !$is_admin): ?>

            <div class="claim-section" style="margin-top:30px; border-top:2px solid #e94560; padding-top:20px;">

                <h3>Submit a Claim</h3>

                <?php if ($is_owner): ?>
                    <!-- EC-06: Owner is allowed to claim — but we flag it clearly -->
                    <div class="alert alert-warning" style="margin-bottom:12px;">
                        <strong>Note:</strong> You are the original reporter of this item.
                        You can still submit a claim if you are also the owner of the found item.
                        The admin will be notified of this.
                    </div>
                <?php endif; ?>

                <?php if ($already_claimed): ?>
                    <div class="alert alert-info">
                        You have already submitted a claim for this item.
                        Please wait for the admin to review it.
                    </div>
                <?php else: ?>
                    <form action="<?= BASE_URL ?>actions/submit_claim.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="item_id"    value="<?= $item['item_id'] ?>">

                        <div class="form-group">
                            <label for="claim_message">
                                Why is this item yours? Provide as much detail as possible.
                                <span style="color:#e94560;">*</span>
                            </label>
                            <textarea
                                id="claim_message"
                                name="message"
                                rows="4"
                                class="form-control"
                                placeholder="Describe identifying features, when/where you lost it, proof of ownership..."
                                required
                            ><?= htmlspecialchars($_GET['message'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                            Submit Claim
                        </button>
                    </form>
                <?php endif; ?>

            </div>

        <?php elseif ($item['status'] !== 'found' && !$is_owner && !$is_admin && isset($_SESSION['user_id'])): ?>
            <!-- Item exists but is not claimable — tell the user why -->
            <div class="claim-section" style="margin-top:30px; border-top:2px solid #ccc; padding-top:20px;">
                <p style="color:#888;">
                    <?php if ($item['status'] === 'claimed'): ?>
                        This item has already been claimed.
                    <?php elseif ($item['status'] === 'expired'): ?>
                        This report has expired and is no longer accepting claims.
                    <?php else: ?>
                        Claims can only be submitted on found items.
                    <?php endif; ?>
                </p>
            </div>

        <?php elseif (!isset($_SESSION['user_id']) && $item['status'] === 'found'): ?>
            <!-- Not logged in but item is claimable — nudge them to log in -->
            <div class="claim-section" style="margin-top:30px; border-top:2px solid #e94560; padding-top:20px;">
                <p>
                    <a href="<?= BASE_URL ?>auth/login.php">Log in</a> to submit a claim for this item.
                </p>
            </div>

        <?php endif; ?>

    </div><!-- /.item-detail-card -->

</div><!-- /.container -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
