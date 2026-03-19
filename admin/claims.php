<?php
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims — Admin</title>
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
    <h1>Manage Claims</h1>
    <p>This section will be built in v11.0.</p>
</div>

</body>
</html>