<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// --- Whitelist: valid action filter values ---
$validActions = [
    'register', 'login', 'logout',
    'report_item', 'edit_item', 'delete_item', 'restore_item',
    'submit_claim', 'approve_claim', 'reject_claim'
];

// --- Filters from GET ---
$filterAction = $_GET['action']    ?? '';
$filterFrom   = $_GET['date_from'] ?? '';
$filterTo     = $_GET['date_to']   ?? '';

if ($filterAction !== '' && !in_array($filterAction, $validActions, true)) {
    $filterAction = '';
}

// --- Pagination ---
$perPage     = 20;
$currentPage = max(1, abs((int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]])));
$offset      = ($currentPage - 1) * $perPage;

// --- Build WHERE clauses ---
$where  = [];
$params = [];

if ($filterAction !== '') {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterFrom !== '') {
    $where[]  = 'DATE(al.created_at) >= ?';
    $params[] = $filterFrom;
}
if ($filterTo !== '') {
    $where[]  = 'DATE(al.created_at) <= ?';
    $params[] = $filterTo;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// --- Total count ---
$countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log al $whereSQL");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset      = ($currentPage - 1) * $perPage;
}

// --- Fetch logs ---
$logSQL = "
    SELECT
        al.log_id,
        al.action,
        al.entity,
        al.entity_id,
        al.created_at,
        u.full_name,
        u.email
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    $whereSQL
    ORDER BY al.log_id DESC
    LIMIT ? OFFSET ?
";

$logsStmt = $db->prepare($logSQL);
$i = 1;
foreach ($params as $p) {
    $logsStmt->bindValue($i++, $p);
}
$logsStmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$logsStmt->bindValue($i++, $offset,  PDO::PARAM_INT);
$logsStmt->execute();
$logs = $logsStmt->fetchAll();

// --- Pagination URL helper ---
function logPageURL(int $page, string $action, string $from, string $to): string {
    $q = ['page' => $page];
    if ($action !== '') $q['action']    = $action;
    if ($from   !== '') $q['date_from'] = $from;
    if ($to     !== '') $q['date_to']   = $to;
    return '?' . http_build_query($q);
}

// --- Action label map ---
function actionLabel(string $action): string {
    $map = [
        'register'      => 'Registered',
        'login'         => 'Logged In',
        'logout'        => 'Logged Out',
        'report_item'   => 'Reported Item',
        'edit_item'     => 'Edited Item',
        'delete_item'   => 'Deleted Item',
        'restore_item'  => 'Restored Item',
        'submit_claim'  => 'Submitted Claim',
        'approve_claim' => 'Approved Claim',
        'reject_claim'  => 'Rejected Claim',
    ];
    return $map[$action] ?? ucwords(str_replace('_', ' ', $action));
}

// --- Action badge Tailwind classes ---
function actionBadgeClass(string $action): string {
    if (in_array($action, ['delete_item', 'reject_claim'], true))
        return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    if (in_array($action, ['approve_claim', 'restore_item'], true))
        return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
    if (in_array($action, ['register', 'report_item', 'submit_claim'], true))
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
    if (in_array($action, ['login', 'logout'], true))
        return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
    return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

    <!-- TOP BAR -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-8 py-4 flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Activity Log</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Read-only record of every action in the system &mdash;
                <?= number_format($totalRows) ?> total entr<?= $totalRows === 1 ? 'y' : 'ies' ?>
            </p>
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

        <!-- FILTER FORM -->
        <form method="GET" action="<?= BASE_URL ?>admin/logs.php">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm px-6 py-4">
                <div class="flex flex-wrap items-end gap-4">

                    <div class="flex flex-col gap-1">
                        <label for="action" class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            Action Type
                        </label>
                        <select name="action" id="action"
                                class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <option value="">— All Actions —</option>
                            <?php foreach ($validActions as $a): ?>
                                <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>>
                                    <?= actionLabel($a) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="date_from" class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            From Date
                        </label>
                        <input type="date" name="date_from" id="date_from"
                               value="<?= htmlspecialchars($filterFrom) ?>"
                               class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="date_to" class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            To Date
                        </label>
                        <input type="date" name="date_to" id="date_to"
                               value="<?= htmlspecialchars($filterTo) ?>"
                               class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                    <div class="flex items-center gap-2 pb-0.5">
                        <button type="submit"
                                class="px-4 py-2.5 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Filter
                        </button>
                        <a href="<?= BASE_URL ?>admin/logs.php"
                           class="px-4 py-2.5 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Reset
                        </a>
                    </div>

                </div>
            </div>
        </form>

        <!-- LOG TABLE -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Log Entries</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Page <?= $currentPage ?> of <?= $totalPages ?>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">#</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">User</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Action</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Entity</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">ID</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No log entries found for the selected filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">

                                <td class="px-4 py-3 text-gray-400 dark:text-gray-500 font-mono text-xs">
                                    <?= $log['log_id'] ?>
                                </td>

                                <td class="px-4 py-3">
                                    <?php if ($log['full_name'] !== null): ?>
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($log['full_name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($log['email']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="italic text-gray-400 dark:text-gray-500 text-xs">Deleted User</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= actionBadgeClass($log['action']) ?>">
                                        <?= actionLabel($log['action']) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    <?= $log['entity'] ? htmlspecialchars(ucfirst($log['entity'])) : '<span class="text-gray-300 dark:text-gray-600">—</span>' ?>
                                </td>

                                <td class="px-4 py-3">
                                    <?php if ($log['entity_id'] !== null && $log['entity'] === 'item'): ?>
                                        <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= (int)$log['entity_id'] ?>"
                                           class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                            #<?= (int)$log['entity_id'] ?>
                                        </a>
                                    <?php elseif ($log['entity_id'] !== null): ?>
                                        <span class="text-gray-600 dark:text-gray-400">#<?= (int)$log['entity_id'] ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                                </td>

                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Page <span class="font-medium text-gray-900 dark:text-white"><?= $currentPage ?></span>
                    of <span class="font-medium text-gray-900 dark:text-white"><?= $totalPages ?></span>
                    &nbsp;·&nbsp;
                    <?= number_format($totalRows) ?> entr<?= $totalRows === 1 ? 'y' : 'ies' ?>
                </p>

                <div class="flex items-center gap-1">

                    <?php if ($currentPage > 1): ?>
                        <a href="<?= logPageURL($currentPage - 1, $filterAction, $filterFrom, $filterTo) ?>"
                           class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            &laquo; Prev
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-default">
                            &laquo; Prev
                        </span>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $currentPage - 2);
                    $end   = min($totalPages, $currentPage + 2);
                    if ($start > 1): ?>
                        <span class="px-2 text-gray-400">…</span>
                    <?php endif;
                    for ($p = $start; $p <= $end; $p++): ?>
                        <?php if ($p === $currentPage): ?>
                            <span class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg bg-blue-600 text-white font-semibold border border-blue-600">
                                <?= $p ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= logPageURL($p, $filterAction, $filterFrom, $filterTo) ?>"
                               class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <?= $p ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor;
                    if ($end < $totalPages): ?>
                        <span class="px-2 text-gray-400">…</span>
                    <?php endif; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= logPageURL($currentPage + 1, $filterAction, $filterFrom, $filterTo) ?>"
                           class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Next &raquo;
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1.5 text-sm rounded-lg border border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-default">
                            Next &raquo;
                        </span>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?>

        </div>

    </main>

    </div><!-- /content-wrapper: opened in sidebar.php -->
</div><!-- /x-data wrapper: opened in sidebar.php -->

<?php require_once __DIR__ . '/footer.php'; ?>

