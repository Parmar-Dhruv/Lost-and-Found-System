<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// --- Stat Cards ---
$stats = [];

$stmt = $db->query("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stats['total_items'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0");
$stats['lost'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0");
$stats['found'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0");
$stats['claimed'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM items WHERE status = 'expired' AND is_deleted = 0");
$stats['expired'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM claims WHERE status = 'pending'");
$stats['pending_claims'] = $stmt->fetchColumn();

// --- Recent Activity Log (last 10 entries) ---
$stmt = $db->query("
    SELECT al.action, al.entity, al.entity_id, al.created_at,
           u.full_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Lost & Found</title>
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
    <h1>Admin Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>.</p>

    <!-- Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Items</h3>
            <p class="stat-number"><?= $stats['total_items'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Lost</h3>
            <p class="stat-number"><?= $stats['lost'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Found</h3>
            <p class="stat-number"><?= $stats['found'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Claimed</h3>
            <p class="stat-number"><?= $stats['claimed'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Expired</h3>
            <p class="stat-number"><?= $stats['expired'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Users</h3>
            <p class="stat-number"><?= $stats['total_users'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Pending Claims</h3>
            <p class="stat-number"><?= $stats['pending_claims'] ?></p>
        </div>
    </div>

    <!-- Recent Activity -->
    <h2>Recent Activity</h2>
    <?php if (empty($logs)): ?>
        <p>No activity recorded yet.</p>
    <?php else: ?>
        <table>
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
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['full_name'] ?? 'Deleted User') ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['entity'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($log['entity_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>