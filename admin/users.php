<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();
$message = '';

// --- Generate CSRF token if not set ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Prevent admin from deactivating themselves
    if ($user_id === (int)$_SESSION['user_id']) {
        $message = 'You cannot deactivate your own account.';
    } elseif ($user_id <= 0) {
        $message = 'Invalid user ID.';
    } elseif ($action === 'deactivate') {
        $stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE user_id = ? AND role = 'user'");
        $stmt->execute([$user_id]);
        $message = 'User deactivated. They can no longer report items.';
    } elseif ($action === 'activate') {
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ? AND role = 'user'");
        $stmt->execute([$user_id]);
        $message = 'User reactivated.';
    } else {
        $message = 'Unknown action.';
    }
}

// --- Fetch all users ---
$stmt = $db->query("
    SELECT user_id, full_name, email, role, is_verified, created_at
    FROM users
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — Admin</title>
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
    <h1>Manage Users</h1>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <p>No users found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['user_id'] ?></td>
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td><?= $user['is_verified'] ? 'Active' : 'Deactivated' ?></td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                            <em>Protected</em>
                        <?php elseif ($user['is_verified'] == 1): ?>
                            <form method="POST" action="" onsubmit="return confirm('Deactivate this user?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                <button type="submit">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                <button type="submit">Reactivate</button>
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