<?php
// pages/report.php
require_once __DIR__ . '/../config/init.php';

// Block non-logged-in users immediately
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

// Generate CSRF token if one doesn't exist yet
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Grab any error or old input passed back from insert_item.php
$error   = $_GET['error']   ?? '';
$success = $_GET['success'] ?? '';

// Re-populate fields if the form was rejected
$old = [
    'item_name'     => htmlspecialchars($_GET['item_name']     ?? ''),
    'category'      => htmlspecialchars($_GET['category']      ?? ''),
    'description'   => htmlspecialchars($_GET['description']   ?? ''),
    'location'      => htmlspecialchars($_GET['location']      ?? ''),
    'contact'       => htmlspecialchars($_GET['contact']       ?? ''),
    'status'        => htmlspecialchars($_GET['status']        ?? 'lost'),
    'date_reported' => htmlspecialchars($_GET['date_reported'] ?? ''),
];

// Human-readable error messages
$errorMessages = [
    'csrf'           => 'Security token mismatch. Please try again.',
    'missing_fields' => 'All fields are required. Please fill everything in.',
    'future_date'    => 'Date reported cannot be in the future.',
    'desc_too_short' => 'Description must be at least 20 characters.',
    'invalid_status' => 'Invalid status selected.',
    'invalid_cat'    => 'Invalid category selected.',
    'not_verified'   => 'Your account must be verified to report items.',
    'db_error'       => 'Something went wrong saving your report. Please try again.',
    'upload_error'   => 'File upload failed. Please try again.',
    'invalid_file'   => 'Only JPG, PNG, and GIF images are allowed.',
    'file_too_large' => 'Image must be under 2MB.',
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="container">
    <h2>Report a Lost or Found Item</h2>

    <?php if ($success === '1'): ?>
        <div class="alert alert-success">
            Item reported successfully! <a href="<?= BASE_URL ?>pages/my_items.php">View your items</a>.
        </div>
    <?php endif; ?>

    <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="alert alert-danger">
            <?= $errorMessages[$error] ?>
        </div>
    <?php endif; ?>

    <!-- enctype="multipart/form-data" is REQUIRED for file uploads -->
    <form action="<?= BASE_URL ?>actions/insert_item.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="form-group">
            <label for="item_name">Item Name <span class="text-danger">*</span></label>
            <input
                type="text"
                id="item_name"
                name="item_name"
                class="form-control"
                maxlength="100"
                required
                value="<?= $old['item_name'] ?>"
                placeholder="e.g. Black iPhone 13, Blue Backpack"
            >
        </div>

        <div class="form-group">
            <label for="category">Category <span class="text-danger">*</span></label>
            <select id="category" name="category" class="form-control" required>
                <option value="">-- Select Category --</option>
                <?php
                $categories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
                foreach ($categories as $cat):
                    $selected = ($old['category'] === $cat) ? 'selected' : '';
                ?>
                    <option value="<?= $cat ?>" <?= $selected ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status <span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-control" required>
                <option value="lost"  <?= ($old['status'] === 'lost')  ? 'selected' : '' ?>>Lost</option>
                <option value="found" <?= ($old['status'] === 'found') ? 'selected' : '' ?>>Found</option>
            </select>
            <small class="form-text text-muted">Select "Lost" if you lost this item. Select "Found" if you found it.</small>
        </div>

        <div class="form-group">
            <label for="description">Description <span class="text-danger">*</span></label>
            <textarea
                id="description"
                name="description"
                class="form-control"
                rows="4"
                required
                placeholder="Describe the item in detail — colour, size, brand, any identifying marks. Minimum 20 characters."
            ><?= $old['description'] ?></textarea>
            <small class="form-text text-muted">Minimum 20 characters required.</small>
        </div>

        <div class="form-group">
            <label for="location">Location <span class="text-danger">*</span></label>
            <input
                type="text"
                id="location"
                name="location"
                class="form-control"
                maxlength="150"
                required
                value="<?= $old['location'] ?>"
                placeholder="e.g. Library Block B, Main Canteen, Bus Stop 12"
            >
        </div>

        <div class="form-group">
            <label for="contact">Contact Info <span class="text-danger">*</span></label>
            <input
                type="text"
                id="contact"
                name="contact"
                class="form-control"
                maxlength="100"
                required
                value="<?= $old['contact'] ?>"
                placeholder="e.g. your email or phone number"
            >
        </div>

        <div class="form-group">
            <label for="date_reported">Date Reported <span class="text-danger">*</span></label>
            <input
                type="date"
                id="date_reported"
                name="date_reported"
                class="form-control"
                required
                value="<?= $old['date_reported'] ?>"
                max="<?= date('Y-m-d') ?>"
            >
            <small class="form-text text-muted">Cannot be a future date.</small>
        </div>

        <div class="form-group">
            <label for="image">Item Image <span class="text-muted">(optional)</span></label>
            <input type="file" id="image" name="image" class="form-control-file" accept=".jpg,.jpeg,.png,.gif">
            <small class="form-text text-muted">JPG, PNG or GIF only. Maximum size: 2MB. Uploading a photo helps others identify the item.</small>
        </div>

        <button type="submit" class="btn btn-primary">Submit Report</button>
        <a href="<?= BASE_URL ?>pages/home.php" class="btn btn-secondary">Cancel</a>

    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
1: