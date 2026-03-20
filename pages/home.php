<?php
require_once __DIR__ . '/../config/init.php';

$db = getDB();

// EC-12: Auto-expire lost items older than 30 days
$db->exec("
    UPDATE items
    SET status = 'expired'
    WHERE status = 'lost'
      AND is_deleted = 0
      AND date_reported < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");

// --- STATS QUERIES ---
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalItems = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0");
$stmt->execute();
$totalLost = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0");
$stmt->execute();
$totalFound = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0");
$stmt->execute();
$totalClaimed = $stmt->fetchColumn();

// --- PAGINATION SETUP ---
// How many items to show per page
$perPage = 10;

// Get current page from URL — default to 1 if not set or invalid
// Cast to int immediately, floor at 1
$page = max(1, (int)($_GET['page'] ?? 1));

// Calculate how many rows to skip
// Page 1: offset 0, Page 2: offset 10, Page 3: offset 20, etc.
$offset = ($page - 1) * $perPage;

// Count total items for pagination math
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalCount = (int)$stmt->fetchColumn();

// How many pages total — ceil() rounds UP so partial pages still get a page
$totalPages = (int)ceil($totalCount / $perPage);

// Fetch only the items for the current page using LIMIT and OFFSET
$stmt = $db->prepare("
    SELECT i.item_id, i.item_name, i.category, i.status, i.location, i.date_reported,
           u.full_name AS reporter_name
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.is_deleted = 0
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
");
// LIMIT and OFFSET must be bound as integers — PDO needs explicit type for these
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset,  PDO::PARAM_INT);
$stmt->execute();
$recentItems = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container">

    <h2>Lost &amp; Found — Dashboard</h2>
    <p>Track lost and found items reported by the community.</p>

    <!-- STATS CARDS -->
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

    <!-- ALL ITEMS FEED (paginated) -->
    <h3>
        All Reported Items
        <small style="font-size:14px; font-weight:normal; color:#666;">
            (<?= $totalCount ?> total)
        </small>
    </h3>

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

        <!-- PAGINATION CONTROLS -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="page-btn">← Previous</a>
                <?php else: ?>
                    <span class="page-btn page-btn-disabled">← Previous</span>
                <?php endif; ?>

                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="page-btn">Next →</a>
                <?php else: ?>
                    <span class="page-btn page-btn-disabled">Next →</span>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    <?php endif; ?>

    <p><a href="<?= BASE_URL ?>pages/search.php">Search and filter items →</a></p>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
