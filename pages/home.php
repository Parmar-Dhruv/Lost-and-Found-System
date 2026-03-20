<?php
require_once __DIR__ . '/../config/init.php';

$db = getDB();

// EC-12: Auto-expire lost items older than 30 days
// This query runs on every home.php load
// It finds any item with status='lost' that was reported more than 30 days ago
// and flips its status to 'expired' automatically — no manual admin action needed
$db->exec("
    UPDATE items
    SET status = 'expired'
    WHERE status = 'lost'
      AND is_deleted = 0
      AND date_reported < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");

// --- STATS QUERIES ---
// Each query counts a specific subset of items
// We exclude soft-deleted items in every query using is_deleted = 0

// Total items ever reported (not deleted)
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalItems = $stmt->fetchColumn();

// Items currently marked as lost
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0");
$stmt->execute();
$totalLost = $stmt->fetchColumn();

// Items currently marked as found
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0");
$stmt->execute();
$totalFound = $stmt->fetchColumn();

// Items that have been successfully claimed
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0");
$stmt->execute();
$totalClaimed = $stmt->fetchColumn();

// --- RECENT ITEMS FEED ---
// Fetch last 5 items reported, newest first
// We JOIN with users table to get the reporter's name
// We exclude soft-deleted items
$stmt = $db->prepare("
    SELECT i.item_id, i.item_name, i.category, i.status, i.location, i.date_reported,
           u.full_name AS reporter_name
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.is_deleted = 0
    ORDER BY i.created_at DESC
    LIMIT 5
");
$stmt->execute();
$recentItems = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container">

    <h2>Lost &amp; Found — Dashboard</h2>
    <p>Track lost and found items reported by the community.</p>

    <!-- STATS CARDS -->
    <!-- Four boxes showing key numbers at a glance -->
    <div class="stats-grid">

        <div class="stat-card stat-total">
            <div class="stat-number"><?= $totalItems ?></div>
            <div class="stat-label">Total Items</div>
        </div>

        <div class="stat-card stat-lost">
            <div class="stat-number"><?= $totalLost ?></div>
            <div class="stat-label">Lost</div>
        </div>

        <div class="stat-card stat-found">
            <div class="stat-number"><?= $totalFound ?></div>
            <div class="stat-label">Found</div>
        </div>

        <div class="stat-card stat-claimed">
            <div class="stat-number"><?= $totalClaimed ?></div>
            <div class="stat-label">Claimed</div>
        </div>

    </div>

    <!-- RECENT ITEMS FEED -->
    <h3>Recently Reported Items</h3>

    <?php if (empty($recentItems)): ?>
        <p>No items have been reported yet.</p>
    <?php else: ?>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Reported By</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentItems as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars($item['status']) ?></td>
                    <td><?= htmlspecialchars($item['location']) ?></td>
                    <td><?= htmlspecialchars($item['reporter_name']) ?></td>
                    <td><?= htmlspecialchars($item['date_reported']) ?></td>
                    <td><a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $item['item_id'] ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Link to full search/browse page -->
    <p><a href="<?= BASE_URL ?>pages/search.php">Browse all items →</a></p>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
