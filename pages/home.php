<?php
require_once __DIR__ . '/../config/init.php';
define('BASE_URL', '/lost-and-found/');

$pageTitle = 'Home';
$db = getDB();

// Auto-expire items older than 30 days
$db->exec("UPDATE items SET status = 'expired' 
           WHERE status = 'lost' 
           AND date_reported < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
           AND is_deleted = 0");

// Stats
$stats = [
    'total'   => $db->query("SELECT COUNT(*) FROM items WHERE is_deleted = 0")->fetchColumn(),
    'lost'    => $db->query("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0")->fetchColumn(),
    'found'   => $db->query("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0")->fetchColumn(),
    'claimed' => $db->query("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0")->fetchColumn(),
];

// Fetch all items
$stmt = $db->prepare("SELECT i.*, u.full_name 
                       FROM items i 
                       JOIN users u ON i.user_id = u.user_id 
                       WHERE i.is_deleted = 0 
                       ORDER BY i.created_at DESC");
$stmt->execute();
$items = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="container">

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            $msg = [
                'reported' => 'Item reported successfully!',
                'updated'  => 'Item updated successfully!',
                'deleted'  => 'Item deleted successfully!'
            ];
            echo $msg[$_GET['success']] ?? '';
            ?>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card total">
            <span class="stat-number"><?php echo $stats['total']; ?></span>
            <span class="stat-label">Total Items</span>
        </div>
        <div class="stat-card lost">
            <span class="stat-number"><?php echo $stats['lost']; ?></span>
            <span class="stat-label">Lost</span>
        </div>
        <div class="stat-card found">
            <span class="stat-number"><?php echo $stats['found']; ?></span>
            <span class="stat-label">Found</span>
        </div>
        <div class="stat-card claimed">
            <span class="stat-number"><?php echo $stats['claimed']; ?></span>
            <span class="stat-label">Claimed</span>
        </div>
    </div>

    <h2>All Reported Items</h2>

    <?php if (count($items) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Location</th>
                <th>Reported By</th>
                <th>Status</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo $item['item_id']; ?></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>pages/item_detail.php?id=<?php echo $item['item_id']; ?>">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($item['category']); ?></td>
                <td><?php echo htmlspecialchars($item['location']); ?></td>
                <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                <td>
                    <span class="badge <?php echo $item['status']; ?>">
                        <?php echo ucfirst($item['status']); ?>
                    </span>
                </td>
                <td><?php echo $item['date_reported']; ?></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>pages/item_detail.php?id=<?php echo $item['item_id']; ?>" 
                       class="btn-view">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="no-records">No items reported yet.</p>
    <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>