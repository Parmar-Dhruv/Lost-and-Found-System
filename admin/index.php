<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// --- STATS QUERIES ---

// Total items (not deleted)
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalItems = $stmt->fetchColumn();

// Lost items
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0");
$stmt->execute();
$totalLost = $stmt->fetchColumn();

// Found items
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0");
$stmt->execute();
$totalFound = $stmt->fetchColumn();

// Claimed items
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0");
$stmt->execute();
$totalClaimed = $stmt->fetchColumn();

// Expired items
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'expired' AND is_deleted = 0");
$stmt->execute();
$totalExpired = $stmt->fetchColumn();

// Soft-deleted items (items hidden from public but recoverable)
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 1");
$stmt->execute();
$totalDeleted = $stmt->fetchColumn();

// Pending claims — claims waiting for admin decision
$stmt = $db->prepare("SELECT COUNT(*) FROM claims WHERE status = 'pending'");
$stmt->execute();
$totalPendingClaims = $stmt->fetchColumn();

// Total registered users (excluding admin accounts)
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

// Total approved claims
$stmt = $db->prepare("SELECT COUNT(*) FROM claims WHERE status = 'approved'");
$stmt->execute();
$totalApprovedClaims = $stmt->fetchColumn();

// --- RECENT ACTIVITY LOG ---
// Last 10 actions taken by any user in the system
// LEFT JOIN because user_id in activity_log can be NULL if user was deleted
$stmt = $db->prepare("
    SELECT al.action, al.entity, al.entity_id, al.created_at,
           u.full_name AS actor_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentLogs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container">

    <h2>Admin Dashboard</h2>
    <p>Full system overview. Only visible to administrators.</p>

    <!-- STATS GRID — 9 cards total -->
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

        <div class="stat-card stat-expired">
            <div class="stat-number"><?= $totalExpired ?></div>
            <div class="stat-label">Expired</div>
        </div>

        <div class="stat-card stat-deleted">
            <div class="stat-number"><?= $totalDeleted ?></div>
            <div class="stat-label">Soft Deleted</div>
        </div>

        <div class="stat-card stat-users">
            <div class="stat-number"><?= $totalUsers ?></div>
            <div class="stat-label">Registered Users</div>
        </div>

        <div class="stat-card stat-pending">
            <div class="stat-number"><?= $totalPendingClaims ?></div>
            <div class="stat-label">Pending Claims</div>
        </div>

        <div class="stat-card stat-approved">
            <div class="stat-number"><?= $totalApprovedClaims ?></div>
            <div class="stat-label">Approved Claims</div>
        </div>

    </div>

    <!-- QUICK LINKS -->
    <div class="admin-quick-links">
        <a href="<?= BASE_URL ?>admin/items.php">Manage Items</a>
        <a href="<?= BASE_URL ?>admin/users.php">Manage Users</a>
        <a href="<?= BASE_URL ?>admin/claims.php">Manage Claims</a>
        <a href="<?= BASE_URL ?>admin/logs.php">View Logs</a>
    </div>

    <!-- RECENT ACTIVITY LOG -->
    <h3>Recent Activity (Last 10 Actions)</h3>

    <?php if (empty($recentLogs)): ?>
        <p>No activity recorded yet.</p>
    <?php else: ?>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Who</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Entity ID</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?= $log['actor_name'] ? htmlspecialchars($log['actor_name']) : '<em>Deleted User</em>' ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= $log['entity'] ? htmlspecialchars($log['entity']) : '—' ?></td>
                    <td><?= $log['entity_id'] ?? '—' ?></td>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
