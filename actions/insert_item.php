<?php
// actions/insert_item.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Only POST requests allowed ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// ── 2. User must be logged in ────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

// ── 3. CSRF check — must be first thing before any DB work ───────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header('Location: ' . BASE_URL . 'pages/report.php?error=csrf');
    exit;
}

// ── 4. EC-03: Account must be verified to report items ───────────────────────
$db   = getDB();
$stmt = $db->prepare("SELECT is_verified FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || (int)$user['is_verified'] !== 1) {
    header('Location: ' . BASE_URL . 'pages/report.php?error=not_verified');
    exit;
}

// ── 5. Collect and sanitize raw input ────────────────────────────────────────
$item_name     = trim($_POST['item_name']     ?? '');
$category      = trim($_POST['category']      ?? '');
$description   = trim($_POST['description']   ?? '');
$location      = trim($_POST['location']      ?? '');
$contact       = trim($_POST['contact']       ?? '');
$status        = trim($_POST['status']        ?? '');
$date_reported = trim($_POST['date_reported'] ?? '');

// ── 6. Required fields check ─────────────────────────────────────────────────
if (
    $item_name === '' ||
    $category  === '' ||
    $description === '' ||
    $location  === '' ||
    $contact   === '' ||
    $status    === '' ||
    $date_reported === ''
) {
    $params = http_build_query([
        'error'         => 'missing_fields',
        'item_name'     => $item_name,
        'category'      => $category,
        'description'   => $description,
        'location'      => $location,
        'contact'       => $contact,
        'status'        => $status,
        'date_reported' => $date_reported,
    ]);
    header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
    exit;
}

// ── 7. Whitelist validation — category and status ────────────────────────────
$allowed_categories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
$allowed_statuses   = ['lost', 'found'];

if (!in_array($category, $allowed_categories, true)) {
    header('Location: ' . BASE_URL . 'pages/report.php?error=invalid_cat');
    exit;
}

if (!in_array($status, $allowed_statuses, true)) {
    header('Location: ' . BASE_URL . 'pages/report.php?error=invalid_status');
    exit;
}

// ── 8. EC-09: Date cannot be in the future ───────────────────────────────────
$today = date('Y-m-d');
if ($date_reported > $today) {
    $params = http_build_query([
        'error'         => 'future_date',
        'item_name'     => $item_name,
        'category'      => $category,
        'description'   => $description,
        'location'      => $location,
        'contact'       => $contact,
        'status'        => $status,
        'date_reported' => $date_reported,
    ]);
    header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
    exit;
}

// ── 9. EC-10: Description minimum 20 characters ──────────────────────────────
if (mb_strlen($description) < 20) {
    $params = http_build_query([
        'error'         => 'desc_too_short',
        'item_name'     => $item_name,
        'category'      => $category,
        'description'   => $description,
        'location'      => $location,
        'contact'       => $contact,
        'status'        => $status,
        'date_reported' => $date_reported,
    ]);
    header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
    exit;
}

// ── 10. Image upload handling ─────────────────────────────────────────────────
$image_path = null; // Default: no image

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

    $file = $_FILES['image'];

    // PHP-level upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $params = http_build_query([
            'error'         => 'upload_error',
            'item_name'     => $item_name,
            'category'      => $category,
            'description'   => $description,
            'location'      => $location,
            'contact'       => $contact,
            'status'        => $status,
            'date_reported' => $date_reported,
        ]);
        header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
        exit;
    }

    // Size check: max 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        $params = http_build_query([
            'error'         => 'file_too_large',
            'item_name'     => $item_name,
            'category'      => $category,
            'description'   => $description,
            'location'      => $location,
            'contact'       => $contact,
            'status'        => $status,
            'date_reported' => $date_reported,
        ]);
        header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
        exit;
    }

    // MIME type check via finfo — never trust $_FILES['type'], browser can fake it
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!in_array($mime_type, $allowed_mime_types, true)) {
        $params = http_build_query([
            'error'         => 'invalid_file',
            'item_name'     => $item_name,
            'category'      => $category,
            'description'   => $description,
            'location'      => $location,
            'contact'       => $contact,
            'status'        => $status,
            'date_reported' => $date_reported,
        ]);
        header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
        exit;
    }

    // Build randomized filename — never use the original uploaded name
    $ext_map      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $extension    = $ext_map[$mime_type];
    $new_filename = uniqid('img_', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $upload_dir   = __DIR__ . '/../assets/uploads/';
    $upload_path  = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $params = http_build_query([
            'error'         => 'upload_error',
            'item_name'     => $item_name,
            'category'      => $category,
            'description'   => $description,
            'location'      => $location,
            'contact'       => $contact,
            'status'        => $status,
            'date_reported' => $date_reported,
        ]);
        header('Location: ' . BASE_URL . 'pages/report.php?' . $params);
        exit;
    }

    // Store relative path — never store absolute server paths in DB
    $image_path = 'assets/uploads/' . $new_filename;
}

// ── 11. Insert into DB ────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("
        INSERT INTO items
            (user_id, item_name, category, description, location, contact, status, image, date_reported)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int)$_SESSION['user_id'],
        $item_name,
        $category,
        $description,
        $location,
        $contact,
        $status,
        $image_path,
        $date_reported,
    ]);

    $new_item_id = (int)$db->lastInsertId();

    // ── 12. Log the action ────────────────────────────────────────────────────
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, entity, entity_id)
        VALUES (?, 'report_item', 'item', ?)
    ");
    $log->execute([(int)$_SESSION['user_id'], $new_item_id]);

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'pages/report.php?error=db_error');
    exit;
}

// ── 13. Success ───────────────────────────────────────────────────────────────
header('Location: ' . BASE_URL . 'pages/report.php?success=1');
exit;
