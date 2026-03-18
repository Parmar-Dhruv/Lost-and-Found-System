<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Item - Lost & Found</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
    <div class="nav-brand">Lost & Found</div>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="report.php" class="active">Report Item</a>
        <a href="search.php">Search</a>
    </div>
</nav>

<div class="container">
    <h2>Report a Lost or Found Item</h2>

    <form action="insert.php" method="POST">

        <div class="form-group">
            <label>Item Name</label>
            <input type="text" name="item_name" placeholder="e.g. Black Wallet" required>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="Describe the item in detail..." required></textarea>
        </div>

        <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" placeholder="e.g. Library, Ground Floor" required>
        </div>

        <div class="form-group">
            <label>Your Contact (Phone or Email)</label>
            <input type="text" name="contact" placeholder="e.g. 9876543210" required>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status" required>
                <option value="">-- Select --</option>
                <option value="lost">Lost</option>
                <option value="found">Found</option>
            </select>
        </div>

        <div class="form-group">
            <label>Date</label>
            <input type="date" name="date_reported" required>
        </div>

        <button type="submit">Submit Report</button>

    </form>
</div>

</body>
</html>