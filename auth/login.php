<?php
require_once __DIR__ . '/../config/init.php';

// If already logged in, redirect to homepage
if (isset($_SESSION['user_id'])) {
    header('Location: /lost-and-found/pages/home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Both fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                $db = getDB();

                $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // EC-15: Use password_verify() — never compare plain text
                if (!$user || !password_verify($password, $user['password'])) {
                    // Deliberately vague — don't tell attacker which field is wrong
                    $error = 'Invalid email or password.';
                } else {
                    // Regenerate session ID on login — prevents session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['email']     = $user['email'];

                    // Log the login action
                    $stmt = $db->prepare("
                        INSERT INTO activity_log (user_id, action, entity, entity_id)
                        VALUES (?, 'login', 'user', ?)
                    ");
                    $stmt->execute([$user['user_id'], $user['user_id']]);

                    // Redirect admin to admin panel, users to homepage
                    if ($user['role'] === 'admin') {
                        header('Location: /lost-and-found/admin/index.php');
                    } else {
                        header('Location: /lost-and-found/pages/home.php');
                    }
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Something went wrong. Please try again.';
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
        <h2>Log In</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Log In</button>
            <p class="form-footer">Don't have an account? <a href="<?= BASE_URL ?>auth/register.php">Register here</a></p>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
