<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
    <div class="nav-brand">Lost & Found</div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="report.php">Report Item</a>
        <a href="search.php" class="active">Search</a>
    </div>
</nav>

<div class="container">
    <h2>Search Items</h2>

    <form action="search.php" method="GET">
        <div class="search-bar">
            <input type="text" name="keyword" placeholder="Search by item name..." 
                value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
            <select name="status">
                <option value="">-- All Status --</option>
                <option value="lost" <?php echo (isset($_GET['status']) && $_GET['status'] == 'lost') ? 'selected' : ''; ?>>Lost</option>
                <option value="found" <?php echo (isset($_GET['status']) && $_GET['status'] == 'found') ? 'selected' : ''; ?>>Found</option>
            </select>
            <button type="submit">Search</button>
        </div>
    </form>

    <?php
    include 'db.php';

    $keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($conn, $_GET['keyword']) : '';
    $status  = isset($_GET['status'])  ? mysqli_real_escape_string($conn, $_GET['status'])  : '';

    $sql = "SELECT * FROM items WHERE 1=1";

    if (!empty($keyword)) {
        $sql .= " AND item_name LIKE '%$keyword%'";
    }

    if (!empty($status)) {
        $sql .= " AND status = '$status'";
    }

    $sql .= " ORDER BY date_reported DESC";
    $result = mysqli_query($conn, $sql);
    ?>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Date Reported</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['item_name']; ?></td>
                    <td><?php echo $row['description']; ?></td>
                    <td><?php echo $row['location']; ?></td>
                    <td><?php echo $row['contact']; ?></td>
                    <td>
                        <span class="badge <?php echo $row['status']; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $row['date_reported']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-records">No items found matching your search.</p>
    <?php endif; ?>

</div>

</body>
</html>