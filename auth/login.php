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

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
    <div class="min-h-[70vh] flex items-center justify-center">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-8 shadow-sm">

            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">Log In</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Welcome back. Enter your credentials to continue.</p>

            <?php if ($error): ?>
                <div class="mb-5 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Password
                    </label>
                    <input type="password" id="password" name="password"
                           required
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <button type="submit"
                        class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Log In
                </button>

                <p class="mt-5 text-center text-sm text-gray-500 dark:text-gray-400">
                    Don't have an account?
                    <a href="<?= BASE_URL ?>auth/register.php"
                       class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                        Register here
                    </a>
                </p>
            </form>

        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
