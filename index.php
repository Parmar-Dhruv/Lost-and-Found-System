<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
    <div class="nav-brand">Lost & Found</div>
    <div class="nav-links">
        <a href="index.php" class="active">Home</a>
        <a href="report.php">Report Item</a>
        <a href="search.php">Search</a>
    </div>
</nav>

<div class="container">
    <h2>All Reported Items</h2>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-msg">Item reported successfully!</div>
    <?php endif; ?>

    <?php
    include 'db.php';

    $sql = "SELECT * FROM items ORDER BY date_reported DESC";
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
        <p class="no-records">No items reported yet.</p>
    <?php endif; ?>

</div>

</body>
</html>