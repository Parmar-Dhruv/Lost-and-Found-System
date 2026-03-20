<?php
// actions/update_item.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Only POST requests allowed ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// ── 2. Must be logged in ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

// ── 3. CSRF check — first thing before any DB work ───────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=csrf');
    exit;
}

// ── 4. Collect and cast IDs immediately ──────────────────────────────────────
$item_id  = (int)($_POST['item_id'] ?? 0);
$user_id  = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($item_id <= 0) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_found');
    exit;
}

// ── 5. EC-01: Ownership check — fetch item and verify owner ──────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT * FROM items
    WHERE item_id  = ?
      AND is_deleted = 0
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_found');
    exit;
}

if ((int)$item['user_id'] !== $user_id && !$is_admin) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_owner');
    exit;
}

// ── 6. Collect and sanitize raw input ────────────────────────────────────────
$item_name     = trim($_POST['item_name']     ?? '');
$category      = trim($_POST['category']      ?? '');
$description   = trim($_POST['description']   ?? '');
$location      = trim($_POST['location']      ?? '');
$contact       = trim($_POST['contact']       ?? '');
$status        = trim($_POST['status']        ?? '');
$date_reported = trim($_POST['date_reported'] ?? '');

// ── 7. Required fields check ──────────────────────────────────────────────────
if (
    $item_name     === '' ||
    $category      === '' ||
    $description   === '' ||
    $location      === '' ||
    $contact       === '' ||
    $status        === '' ||
    $date_reported === ''
) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=missing_fields');
    exit;
}

// ── 8. Whitelist validation — category and status ────────────────────────────
$allowed_categories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
$allowed_statuses   = ['lost', 'found'];

if (!in_array($category, $allowed_categories, true)) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=invalid_cat');
    exit;
}

if (!in_array($status, $allowed_statuses, true)) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=invalid_status');
    exit;
}

// ── 9. EC-09: Date cannot be in the future ───────────────────────────────────
$today = date('Y-m-d');
if ($date_reported > $today) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=future_date');
    exit;
}

// ── 10. EC-10: Description minimum 20 characters ─────────────────────────────
if (mb_strlen($description) < 20) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=desc_too_short');
    exit;
}

// ── 11. Image upload handling ─────────────────────────────────────────────────
$new_image_path = $item['image']; // Keep existing image by default

$remove_image   = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
$has_new_upload = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

if ($has_new_upload) {

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=upload_error');
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=file_too_large');
        exit;
    }

    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!in_array($mime_type, $allowed_mime_types, true)) {
        header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=invalid_file');
        exit;
    }

    $ext_map      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $extension    = $ext_map[$mime_type];
    $new_filename = uniqid('img_', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $upload_dir   = __DIR__ . '/../assets/uploads/';
    $upload_path  = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=upload_error');
        exit;
    }

    // Delete old image file from disk — no orphan files
    if (!empty($item['image'])) {
        $old_file = __DIR__ . '/../' . $item['image'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    $new_image_path = 'assets/uploads/' . $new_filename;

} elseif ($remove_image) {

    if (!empty($item['image'])) {
        $old_file = __DIR__ . '/../' . $item['image'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }
    $new_image_path = null;
}

// ── 12. Update the item in DB ─────────────────────────────────────────────────
try {
    $stmt = $db->prepare("
        UPDATE items
        SET item_name     = ?,
            category      = ?,
            description   = ?,
            location      = ?,
            contact       = ?,
            status        = ?,
            image         = ?,
            date_reported = ?
        WHERE item_id  = ?
          AND is_deleted = 0
    ");
    $stmt->execute([
        $item_name,
        $category,
        $description,
        $location,
        $contact,
        $status,
        $new_image_path,
        $date_reported,
        $item_id,
    ]);

    // ── 13. Log the action ────────────────────────────────────────────────────
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, entity, entity_id)
        VALUES (?, 'edit_item', 'item', ?)
    ");
    $log->execute([$user_id, $item_id]);

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'pages/my_items.php?edit=' . $item_id . '&error=db_error');
    exit;
}

// ── 14. Success ───────────────────────────────────────────────────────────────
header('Location: ' . BASE_URL . 'pages/my_items.php?success=updated');
exit;
