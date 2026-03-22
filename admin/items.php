<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();
$message = '';

// --- Handle POST actions (delete / restore) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $action  = $_POST['action']  ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);

    if ($item_id <= 0) {
        $message = 'Invalid item ID.';
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("UPDATE items SET is_deleted = 1 WHERE item_id = ?");
        $stmt->execute([$item_id]);

        $logStmt = $db->prepare("INSERT INTO activity_log (user_id, action, entity, entity_id) VALUES (?, 'delete_item', 'item', ?)");
        $logStmt->execute([$_SESSION['user_id'], $item_id]);

        $message = 'Item deleted (soft delete). It can be restored.';

    } elseif ($action === 'restore') {
        $stmt = $db->prepare("UPDATE items SET is_deleted = 0 WHERE item_id = ?");
        $stmt->execute([$item_id]);

        $logStmt = $db->prepare("INSERT INTO activity_log (user_id, action, entity, entity_id) VALUES (?, 'restore_item', 'item', ?)");
        $logStmt->execute([$_SESSION['user_id'], $item_id]);

        $message = 'Item restored successfully.';
    } else {
        $message = 'Unknown action.';
    }
}

// --- Generate CSRF token if not set ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Fetch ALL items including soft-deleted ---
$stmt = $db->query("
    SELECT i.*, u.full_name AS reporter_name
    FROM items i
    LEFT JOIN users u ON i.user_id = u.user_id
    ORDER BY i.created_at DESC
");
$items = $stmt->fetchAll();

// --- Duplicate Detection (EC-04) ---
$duplicateIds = [];
$dupStmt = $db->query("
    SELECT i1.item_id
    FROM items i1
    JOIN items i2
        ON i1.item_id != i2.item_id
        AND LOWER(TRIM(i1.item_name)) = LOWER(TRIM(i2.item_name))
        AND LOWER(TRIM(i1.location))  = LOWER(TRIM(i2.location))
        AND ABS(DATEDIFF(i1.date_reported, i2.date_reported)) <= 2
    WHERE i1.is_deleted = 0 AND i2.is_deleted = 0
");
foreach ($dupStmt->fetchAll() as $row) {
    $duplicateIds[$row['item_id']] = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items — Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
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
    <h1>Manage Items</h1>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (!empty($duplicateIds)): ?>
        <div class="alert">
            ⚠️ Some items below are flagged as possible duplicates (same name + location within 2 days). They are highlighted in yellow.
        </div>
    <?php endif; ?>

    <!-- Export button -->
    <p>
        <a href="<?= BASE_URL ?>actions/export_items_pdf.php" class="btn btn-secondary">
            ⬇ Export All Items to PDF
        </a>
    </p>

    <?php if (empty($items)): ?>
        <p>No items in the system yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Reporter</th>
                    <th>Date Reported</th>
                    <th>Deleted?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr <?= isset($duplicateIds[$item['item_id']]) ? 'class="duplicate-flag"' : '' ?>>
                    <td><?= $item['item_id'] ?></td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars($item['status']) ?></td>
                    <td><?= htmlspecialchars($item['location']) ?></td>
                    <td><?= htmlspecialchars($item['reporter_name'] ?? 'Deleted User') ?></td>
                    <td><?= htmlspecialchars($item['date_reported']) ?></td>
                    <td><?= $item['is_deleted'] ? 'Yes' : 'No' ?></td>
                    <td>
                        <?php if ($item['is_deleted'] == 0): ?>
                            <form method="POST" action="" onsubmit="return confirm('Soft-delete this item?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <button type="submit">Delete</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <button type="submit">Restore</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
