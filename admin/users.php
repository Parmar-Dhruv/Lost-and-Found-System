<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();
$message = '';
$messageType = 'success';

// --- Generate CSRF token if not set ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id === (int)$_SESSION['user_id']) {
        $message = 'You cannot deactivate your own account.';
        $messageType = 'error';
    } elseif ($user_id <= 0) {
        $message = 'Invalid user ID.';
        $messageType = 'error';
    } elseif ($action === 'deactivate') {
        $stmt = $db->prepare("UPDATE users SET is_verified = 0 WHERE user_id = ? AND role = 'user'");
        $stmt->execute([$user_id]);
        $message = 'User deactivated. They can no longer report items.';
        $messageType = 'warning';
    } elseif ($action === 'activate') {
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ? AND role = 'user'");
        $stmt->execute([$user_id]);
        $message = 'User reactivated.';
        $messageType = 'success';
    } else {
        $message = 'Unknown action.';
        $messageType = 'error';
    }
}

// --- Fetch all users ---
$stmt = $db->query("
    SELECT user_id, full_name, email, role, is_verified, created_at
    FROM users
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

    <!-- TOP BAR -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-8 py-4 flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Manage Users</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">View and manage all registered accounts</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">Logged in as</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 px-8 py-8 space-y-6">

        <?php if ($message): ?>
            <?php
            $alertClasses = [
                'success' => 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300',
                'warning' => 'bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300',
                'error'   => 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300',
            ];
            $alertClass = $alertClasses[$messageType] ?? $alertClasses['success'];
            ?>
            <div class="px-4 py-3 rounded-lg text-sm <?= $alertClass ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- USERS TABLE -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">All Users</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400"><?= count($users) ?> total</span>
            </div>

            <?php if (empty($users)): ?>
                <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    No users found.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">ID</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Full Name</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Email</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Role</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Registered</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><?= $user['user_id'] ?></td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($user['full_name']) ?>
                                    <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                                            you
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">
                                            admin
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                            user
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($user['is_verified']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                                            Deactivated
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    <?= htmlspecialchars($user['created_at']) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">Protected</span>
                                    <?php elseif ($user['is_verified'] == 1): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Deactivate this user? They will no longer be able to report items.');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border border-red-400 text-red-600 dark:text-red-400 dark:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                                Deactivate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border border-green-400 text-green-600 dark:text-green-400 dark:border-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 transition">
                                                Reactivate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>

    </main>

    </div><!-- /content-wrapper: opened in sidebar.php -->
</div><!-- /x-data wrapper: opened in sidebar.php -->

<?php require_once __DIR__ . '/footer.php'; ?>