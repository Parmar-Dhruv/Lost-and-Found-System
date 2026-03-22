<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();

// --- Fetch all claims ---
$stmt = $db->prepare("
    SELECT
        c.claim_id,
        c.message,
        c.status        AS claim_status,
        c.admin_remark,
        c.claimed_at,
        c.item_id,
        c.claimant_id,
        i.item_name,
        i.status        AS item_status,
        i.user_id       AS item_owner_id,
        u.full_name     AS claimant_name,
        u.email         AS claimant_email
    FROM claims c
    JOIN items  i ON c.item_id     = i.item_id
    JOIN users  u ON c.claimant_id = u.user_id
    ORDER BY c.claimed_at DESC
");
$stmt->execute();
$claims = $stmt->fetchAll();

// --- CSRF token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$successMessages = [
    'approved'  => 'Claim approved. All other pending claims for this item have been rejected.',
    'rejected'  => 'Claim rejected.',
    'collected' => 'Item marked as collected and closed.',
];

$errorMessages = [
    'invalid_csrf'    => 'Invalid form submission.',
    'invalid_claim'   => 'Claim not found.',
    'invalid_action'  => 'Invalid action.',
    'already_handled' => 'This claim has already been approved or rejected.',
    'not_approved'    => 'Cannot mark as collected — claim is not approved yet.',
    'db_error'        => 'Database error. Please try again.',
];

// --- Badge classes ---
function claimBadgeClass(string $status): string {
    return match($status) {
        'pending'  => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        default    => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
}

// --- Item status badge classes ---
function itemBadgeClass(string $status): string {
    return match($status) {
        'lost'    => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        'found'   => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'claimed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        'expired' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        default   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

    <!-- TOP BAR -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-8 py-4 flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Manage Claims</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Review, approve, and reject item claims</p>
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

        <?php if ($success && isset($successMessages[$success])): ?>
            <div class="px-4 py-3 rounded-lg text-sm bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300">
                <?= htmlspecialchars($successMessages[$success]) ?>
            </div>
        <?php endif; ?>

        <?php if ($error && isset($errorMessages[$error])): ?>
            <div class="px-4 py-3 rounded-lg text-sm bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300">
                <?= htmlspecialchars($errorMessages[$error]) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($claims)): ?>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                No claims have been submitted yet.
            </div>
        <?php else: ?>

            <!-- SUMMARY BAR -->
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-gray-900 dark:text-white"><?= count($claims) ?></span> total claims
                </p>
            </div>

            <!-- CLAIMS LIST -->
            <div class="space-y-4">
            <?php foreach ($claims as $c):
                $isSelfClaim  = ((int)$c['claimant_id'] === (int)$c['item_owner_id']);
                $isCollected  = str_contains($c['admin_remark'] ?? '', 'Collected and closed');
            ?>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">

                    <!-- CLAIM HEADER -->
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                    #<?= $c['claim_id'] ?>
                                </span>
                                <span class="text-base font-semibold text-gray-900 dark:text-white">
                                    <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $c['item_id'] ?>"
                                       class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                        <?= htmlspecialchars($c['item_name']) ?>
                                    </a>
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= itemBadgeClass($c['item_status']) ?>">
                                    <?= ucfirst(htmlspecialchars($c['item_status'])) ?>
                                </span>
                            </div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300"><?= htmlspecialchars($c['claimant_name']) ?></span>
                                &lt;<?= htmlspecialchars($c['claimant_email']) ?>&gt;
                                &nbsp;·&nbsp;
                                <?= htmlspecialchars($c['claimed_at']) ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if ($isSelfClaim): ?>
                                <!-- EC-06: claimant is the original reporter -->
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Original Reporter
                                </span>
                            <?php endif; ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= claimBadgeClass($c['claim_status']) ?>">
                                <?= ucfirst(htmlspecialchars($c['claim_status'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- CLAIM BODY -->
                    <div class="px-6 py-4 space-y-4">

                        <!-- Claimant message -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                            <?= nl2br(htmlspecialchars($c['message'])) ?>
                        </div>

                        <!-- Admin remark (if any) -->
                        <?php if (!empty($c['admin_remark'])): ?>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Admin remark:</span>
                                <?= nl2br(htmlspecialchars($c['admin_remark'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- ACTION BUTTONS -->
                        <?php if ($c['claim_status'] === 'pending'): ?>
                            <div class="flex flex-wrap gap-4 pt-2">

                                <!-- APPROVE form -->
                                <form action="<?= BASE_URL ?>actions/handle_claim.php" method="POST" class="flex-1 min-w-56">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="claim_id"   value="<?= $c['claim_id'] ?>">
                                    <input type="hidden" name="action"     value="approve">
                                    <textarea name="admin_remark" rows="2"
                                              placeholder="Optional approval note..."
                                              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none mb-2"></textarea>
                                    <button type="submit"
                                            onclick="return confirm('Approve this claim? All other pending claims for this item will be auto-rejected.');"
                                            class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-green-600 hover:bg-green-700 text-white transition focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                        Approve
                                    </button>
                                </form>

                                <!-- REJECT form -->
                                <form action="<?= BASE_URL ?>actions/handle_claim.php" method="POST" class="flex-1 min-w-56">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="claim_id"   value="<?= $c['claim_id'] ?>">
                                    <input type="hidden" name="action"     value="reject">
                                    <!-- EC-08: rejection reason visible to claimant -->
                                    <textarea name="admin_remark" rows="2"
                                              placeholder="Reason for rejection (visible to claimant)..."
                                              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none mb-2"></textarea>
                                    <button type="submit"
                                            onclick="return confirm('Reject this claim?');"
                                            class="w-full px-4 py-2 text-sm font-medium rounded-lg border border-red-400 text-red-600 dark:text-red-400 dark:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                        Reject
                                    </button>
                                </form>

                            </div>

                        <?php elseif ($c['claim_status'] === 'approved'): ?>
                            <!-- EC-07: Two-step collected confirmation -->
                            <?php if (!$isCollected): ?>
                                <form action="<?= BASE_URL ?>actions/handle_claim.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="claim_id"   value="<?= $c['claim_id'] ?>">
                                    <input type="hidden" name="action"     value="collected">
                                    <button type="submit"
                                            onclick="return confirm('Confirm item has been physically collected by the claimant?');"
                                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Mark as Collected
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="inline-flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Item collected and closed
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No further actions available.</p>
                        <?php endif; ?>

                    </div><!-- /claim body -->

                </div><!-- /claim card -->

            <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    </div><!-- /content-wrapper: opened in sidebar.php -->
</div><!-- /x-data wrapper: opened in sidebar.php -->

<?php require_once __DIR__ . '/footer.php'; ?>
