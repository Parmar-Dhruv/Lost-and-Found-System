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

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
    <div class="min-h-[70vh] flex items-center justify-center">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-8 shadow-sm">

            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">Create Account</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Join the Lost & Found community.</p>

            <?php if ($error): ?>
                <div class="mb-5 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-5 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-sm text-green-700 dark:text-green-300">
                    <?= htmlspecialchars($success) ?>
                    <br>
                    <a href="<?= BASE_URL ?>auth/login.php"
                       class="font-medium underline hover:no-underline mt-1 inline-block">
                        Click here to log in
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-4">
                    <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Full Name
                    </label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           required
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Password
                    </label>
                    <input type="password" id="password" name="password"
                           required
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Min 8 characters, one uppercase letter, one number.
                    </p>
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Confirm Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           required
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <button type="submit"
                        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Create Account
                </button>

                <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                    Already have an account?
                    <a href="<?= BASE_URL ?>auth/login.php"
                       class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        Log in here
                    </a>
                </p>
            </form>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
