<?php
require_once __DIR__ . '/../config/init.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        // Backend validation
        if (empty($full_name) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one number.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $db = getDB();

                // EC-11: Check for duplicate email
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'An account with this email already exists.';
                } else {
                    // EC-15: bcrypt hash — never store plain text
                    $hashed = password_hash($password, PASSWORD_BCRYPT);

                    $stmt = $db->prepare("
                        INSERT INTO users (full_name, email, password, role, is_verified)
                        VALUES (?, ?, ?, 'user', 1)
                    ");
                    $stmt->execute([$full_name, $email, $hashed]);

                    $new_user_id = $db->lastInsertId();

                    // Log the registration action
                    $stmt = $db->prepare("
                        INSERT INTO activity_log (user_id, action, entity, entity_id)
                        VALUES (?, 'register', 'user', ?)
                    ");
                    $stmt->execute([$new_user_id, $new_user_id]);

                    $success = 'Account created successfully. You can now log in.';
                }
            } catch (PDOException $e) {
                // EC-11: Catch duplicate email at DB level as fallback
                if ($e->getCode() === '23000') {
                    $error = 'An account with this email already exists.';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="container">
    <div class="form-wrapper">
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <br><a href="<?= BASE_URL ?>auth/login.php">Click here to log in</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <small>Min 8 characters, one uppercase letter, one number.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
            <p class="form-footer">Already have an account? <a href="<?= BASE_URL ?>auth/login.php">Log in here</a></p>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
