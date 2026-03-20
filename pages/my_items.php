<?php
// pages/my_items.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Must be logged in ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];

// ── 2. CSRF token ─────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$errorMessages = [
    'not_owner'     => 'You can only edit or delete your own items.',
    'db_error'      => 'Something went wrong. Please try again.',
    'not_found'     => 'Item not found.',
    'missing_fields'=> 'All fields are required.',
    'future_date'   => 'Date reported cannot be in the future.',
    'desc_too_short'=> 'Description must be at least 20 characters.',
    'invalid_cat'   => 'Invalid category selected.',
    'invalid_status'=> 'Invalid status selected.',
    'csrf'          => 'Security token mismatch. Please try again.',
];

// ── 3. Check if we are in edit mode ──────────────────────────────────────────
$edit_id   = (int)($_GET['edit'] ?? 0);
$edit_item = null;

if ($edit_id > 0) {
    // Fetch the item — must belong to this user and not be deleted
    $stmt = $db->prepare("
        SELECT * FROM items
        WHERE item_id  = ?
          AND user_id  = ?
          AND is_deleted = 0
    ");
    $stmt->execute([$edit_id, $user_id]);
    $edit_item = $stmt->fetch();

    // EC-01: If item doesn't exist OR doesn't belong to this user — reject
    if (!$edit_item) {
        header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_owner');
        exit;
    }
}

// ── 4. Fetch all items belonging to this user ─────────────────────────────────
$stmt = $db->prepare("
    SELECT * FROM items
    WHERE user_id   = ?
      AND is_deleted = 0
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

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
    <h2>My Reported Items</h2>

    <?php if ($success === 'updated'): ?>
        <div class="alert alert-success">Item updated successfully.</div>
    <?php elseif ($success === 'deleted'): ?>
        <div class="alert alert-success">Item deleted successfully.</div>
    <?php endif; ?>

    <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="alert alert-danger"><?= $errorMessages[$error] ?></div>
    <?php endif; ?>

    <!-- ── EDIT FORM (only shown when ?edit=ID is in URL) ────────────────── -->
    <?php if ($edit_item): ?>
        <div class="edit-form-section" style="margin-bottom:40px; padding:20px; border:1px solid #ccc; border-radius:6px;">
            <h3>Edit Item: <?= htmlspecialchars($edit_item['item_name']) ?></h3>

            <form action="<?= BASE_URL ?>actions/update_item.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="item_id"    value="<?= $edit_item['item_id'] ?>">

                <!-- Item Name -->
                <div class="form-group">
                    <label for="item_name">Item Name <span class="text-danger">*</span></label>
                    <input type="text" id="item_name" name="item_name" class="form-control"
                           maxlength="100" required
                           value="<?= htmlspecialchars($edit_item['item_name']) ?>">
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label for="category">Category <span class="text-danger">*</span></label>
                    <select id="category" name="category" class="form-control" required>
                        <?php
                        $categories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
                        foreach ($categories as $cat):
                            $selected = ($edit_item['category'] === $cat) ? 'selected' : '';
                        ?>
                            <option value="<?= $cat ?>" <?= $selected ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="status">Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <?php
                        $statuses = ['lost', 'found'];
                        foreach ($statuses as $s):
                            $selected = ($edit_item['status'] === $s) ? 'selected' : '';
                        ?>
                            <option value="<?= $s ?>" <?= $selected ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description <span class="text-danger">*</span></label>
                    <textarea id="description" name="description" class="form-control"
                              rows="4" required><?= htmlspecialchars($edit_item['description']) ?></textarea>
                    <small class="form-text text-muted">Minimum 20 characters.</small>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label for="location">Location <span class="text-danger">*</span></label>
                    <input type="text" id="location" name="location" class="form-control"
                           maxlength="150" required
                           value="<?= htmlspecialchars($edit_item['location']) ?>">
                </div>

                <!-- Contact -->
                <div class="form-group">
                    <label for="contact">Contact Info <span class="text-danger">*</span></label>
                    <input type="text" id="contact" name="contact" class="form-control"
                           maxlength="100" required
                           value="<?= htmlspecialchars($edit_item['contact']) ?>">
                </div>

                <!-- Date Reported -->
                <div class="form-group">
                    <label for="date_reported">Date Reported <span class="text-danger">*</span></label>
                    <input type="date" id="date_reported" name="date_reported" class="form-control"
                           required
                           max="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($edit_item['date_reported']) ?>">
                    <small class="form-text text-muted">Cannot be a future date.</small>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= BASE_URL ?>pages/my_items.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    <?php endif; ?>

    <!-- ── ITEMS TABLE ────────────────────────────────────────────────────── -->
    <?php if (empty($items)): ?>
        <div class="alert alert-info">
            You have not reported any items yet.
            <a href="<?= BASE_URL ?>pages/report.php">Report one now</a>.
        </div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Date Reported</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int)$item['item_id'] ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= (int)$item['item_id'] ?>">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($item['category']) ?></td>
                        <td>
                            <span class="badge <?= statusBadge($item['status']) ?>">
                                <?= ucfirst(htmlspecialchars($item['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($item['location']) ?></td>
                        <td><?= htmlspecialchars($item['date_reported']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>pages/my_items.php?edit=<?= (int)$item['item_id'] ?>" class="btn btn-warning btn-sm">Edit</a>

                            <form action="<?= BASE_URL ?>actions/delete_item.php" method="POST" style="display:inline;"
                                  onsubmit="return confirm('Delete this item? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="item_id"    value="<?= (int)$item['item_id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
