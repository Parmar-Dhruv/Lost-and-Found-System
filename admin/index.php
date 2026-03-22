<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// --- STATS QUERIES ---

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalItems = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0");
$stmt->execute();
$totalLost = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0");
$stmt->execute();
$totalFound = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0");
$stmt->execute();
$totalClaimed = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'expired' AND is_deleted = 0");
$stmt->execute();
$totalExpired = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 1");
$stmt->execute();
$totalDeleted = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM claims WHERE status = 'pending'");
$stmt->execute();
$totalPendingClaims = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM claims WHERE status = 'approved'");
$stmt->execute();
$totalApprovedClaims = $stmt->fetchColumn();

// --- RECENT ACTIVITY LOG ---
$stmt = $db->prepare("
    SELECT al.action, al.entity, al.entity_id, al.created_at,
           u.full_name AS actor_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentLogs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

    <!-- TOP BAR -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-8 py-4 flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Full system overview</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">Logged in as</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white">
                <?= htmlspecialchars($_SESSION['full_name']) ?>
            </span>
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 px-8 py-8 space-y-8">

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

            <!-- Total Items -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalItems ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Items</div>
                </div>
            </div>

            <!-- Lost -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-red-50 dark:bg-red-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalLost ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Lost Items</div>
                </div>
            </div>

            <!-- Found -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-green-50 dark:bg-green-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalFound ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Found Items</div>
                </div>
            </div>

            <!-- Claimed -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalClaimed ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Claimed Items</div>
                </div>
            </div>

            <!-- Expired -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalExpired ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Expired Items</div>
                </div>
            </div>

            <!-- Soft Deleted -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-orange-50 dark:bg-orange-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalDeleted ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Soft Deleted</div>
                </div>
            </div>

            <!-- Registered Users -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-50 dark:bg-purple-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalUsers ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Registered Users</div>
                </div>
            </div>

            <!-- Pending Claims -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalPendingClaims ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pending Claims</div>
                </div>
            </div>

            <!-- Approved Claims -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-teal-50 dark:bg-teal-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white"><?= $totalApprovedClaims ?></div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Approved Claims</div>
                </div>
            </div>

        </div>

        <!-- RECENT ACTIVITY LOG -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
                <a href="<?= BASE_URL ?>admin/logs.php"
                   class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    View all logs →
                </a>
            </div>

            <?php if (empty($recentLogs)): ?>
                <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    No activity recorded yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Who</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Action</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Entity</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">ID</th>
                                <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php foreach ($recentLogs as $log): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-3 text-gray-900 dark:text-white font-medium">
                                    <?php if ($log['actor_name']): ?>
                                        <?= htmlspecialchars($log['actor_name']) ?>
                                    <?php else: ?>
                                        <span class="italic text-gray-400 dark:text-gray-500">Deleted User</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-400">
                                    <?= $log['entity'] ? htmlspecialchars($log['entity']) : '—' ?>
                                </td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-400">
                                    <?= $log['entity_id'] ?? '—' ?>
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    <?= htmlspecialchars($log['created_at']) ?>
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
