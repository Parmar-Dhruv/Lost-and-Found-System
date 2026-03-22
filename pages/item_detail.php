<?php
// pages/item_detail.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Get and validate item_id from URL ─────────────────────────────────────
$item_id = (int)($_GET['id'] ?? 0);

if ($item_id <= 0) {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// ── 2. Fetch the item ─────────────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT i.*, u.full_name AS reporter_name
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.item_id = ?
      AND i.is_deleted = 0
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: ' . BASE_URL . 'pages/home.php?error=item_not_found');
    exit;
}

$is_owner = isset($_SESSION['user_id']) && ((int)$_SESSION['user_id'] === (int)$item['user_id']);
$is_admin = isset($_SESSION['role'])    && $_SESSION['role'] === 'admin';

$already_claimed = false;
if (isset($_SESSION['user_id'])) {
    $chk = $db->prepare("
        SELECT claim_id FROM claims
        WHERE item_id = ? AND claimant_id = ?
        LIMIT 1
    ");
    $chk->execute([$item_id, (int)$_SESSION['user_id']]);
    $already_claimed = (bool)$chk->fetch();
}

// ── Parse matched item IDs from URL (v12.0) ───────────────────────────────────
$matched_items = [];
if (!empty($_GET['matches'])) {
    $raw_ids  = explode(',', $_GET['matches']);
    $safe_ids = [];
    foreach ($raw_ids as $raw_id) {
        $safe_id = (int)trim($raw_id);
        if ($safe_id > 0) $safe_ids[] = $safe_id;
    }
    if (!empty($safe_ids)) {
        $placeholders = implode(',', array_fill(0, count($safe_ids), '?'));
        $match_fetch  = $db->prepare("
            SELECT item_id, item_name, location, date_reported, status
            FROM items
            WHERE item_id IN ($placeholders)
              AND is_deleted = 0
        ");
        $match_fetch->execute($safe_ids);
        $matched_items = $match_fetch->fetchAll();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$errorMessages = [
    'not_owner'       => 'You can only edit or delete your own items.',
    'db_error'        => 'Something went wrong. Please try again.',
    'not_logged_in'   => 'You must be logged in to submit a claim.',
    'invalid_item'    => 'Invalid item.',
    'not_claimable'   => 'This item is not available for claiming.',
    'duplicate_claim' => 'You have already submitted a claim for this item.',
    'claim_failed'    => 'Failed to submit claim. Please try again.',
];

$successMessages = [
    'updated'    => 'Item updated successfully.',
    'claim_sent' => 'Your claim has been submitted. The admin will review it.',
    'reported'   => 'Item reported successfully.',
];

function statusBadgeClass(string $status): string {
    return match($status) {
        'lost'    => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        'found'   => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'claimed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        'expired' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        default   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
    <div class="max-w-3xl mx-auto">

        <!-- Back link -->
        <a href="<?= BASE_URL ?>pages/home.php"
           class="inline-flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition mb-6">
            ← Back to Home
        </a>

        <!-- Success alert -->
        <?php if ($success && isset($successMessages[$success])): ?>
            <div class="mb-5 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-sm text-green-700 dark:text-green-300">
                <?= $successMessages[$success] ?>
            </div>
        <?php endif; ?>

        <!-- Match alert (v12.0) -->
        <?php if (!empty($matched_items)): ?>
            <div class="mb-5 px-4 py-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700">
                <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-300 mb-2">
                    ⚡ Possible Matches Found!
                </p>
                <p class="text-sm text-yellow-700 dark:text-yellow-400 mb-3">
                    We found <?= count($matched_items) ?> item(s) in our system that may be related to yours.
                </p>
                <ul class="space-y-1">
                    <?php foreach ($matched_items as $match): ?>
                        <li class="text-sm">
                            <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $match['item_id'] ?>"
                               class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                <?= htmlspecialchars($match['item_name']) ?>
                            </a>
                            <span class="text-gray-500 dark:text-gray-400"> — <?= htmlspecialchars($match['location']) ?></span>
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium <?= statusBadgeClass($match['status']) ?>">
                                <?= ucfirst($match['status']) ?>
                            </span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">
                                (reported: <?= htmlspecialchars($match['date_reported']) ?>)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Error alert -->
        <?php if ($error && isset($errorMessages[$error])): ?>
            <div class="mb-5 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
                <?= $errorMessages[$error] ?>
            </div>
        <?php endif; ?>

        <!-- Item card -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden mb-6">

            <!-- Header -->
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    <?= htmlspecialchars($item['item_name']) ?>
                </h1>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?= statusBadgeClass($item['status']) ?>">
                    <?= ucfirst(htmlspecialchars($item['status'])) ?>
                </span>
            </div>

            <!-- Image -->
            <div class="px-6 pt-5">
                <?php if (!empty($item['image'])): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($item['image']) ?>"
                         alt="Item Image"
                         class="w-full max-w-sm rounded-lg border border-gray-200 dark:border-gray-700 mb-5">
                <?php else: ?>
                    <div class="w-full max-w-sm h-40 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center mb-5">
                        <p class="text-sm text-gray-400 dark:text-gray-500">No image provided</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Details table -->
            <div class="px-6 pb-6">
                <dl class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php
                    $fields = [
                        'Category'      => htmlspecialchars($item['category']),
                        'Description'   => nl2br(htmlspecialchars($item['description'])),
                        'Location'      => htmlspecialchars($item['location']),
                        'Contact'       => htmlspecialchars($item['contact']),
                        'Date Reported' => htmlspecialchars($item['date_reported']),
                        'Reported By'   => htmlspecialchars($item['reporter_name']),
                        'Posted On'     => htmlspecialchars($item['created_at']),
                    ];
                    foreach ($fields as $label => $value):
                    ?>
                    <div class="py-3 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $label ?></dt>
                        <dd class="text-sm text-gray-900 dark:text-white col-span-2"><?= $value ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </div>

            <!-- Action buttons -->
            <?php if ($is_owner || $is_admin): ?>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
                <a href="<?= BASE_URL ?>pages/my_items.php?edit=<?= $item['item_id'] ?>"
                   class="px-4 py-2 text-sm font-medium rounded-lg border border-yellow-400 text-yellow-700 dark:text-yellow-300 dark:border-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/30 transition">
                    Edit
                </a>
                <form action="<?= BASE_URL ?>actions/delete_item.php" method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this item?');">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="item_id"    value="<?= $item['item_id'] ?>">
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg border border-red-400 text-red-600 dark:text-red-400 dark:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                        Delete
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div>

        <!-- Claim section -->
        <?php if ($item['status'] === 'found' && isset($_SESSION['user_id']) && !$is_admin): ?>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6">

                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Submit a Claim</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    Think this item belongs to you? Describe why below.
                </p>

                <?php if ($is_owner): ?>
                    <div class="mb-4 px-4 py-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-sm text-yellow-700 dark:text-yellow-300">
                        <strong>Note:</strong> You are the original reporter of this item.
                        You can still submit a claim if you are also the owner of the found item.
                        The admin will be notified.
                    </div>
                <?php endif; ?>

                <?php if ($already_claimed): ?>
                    <div class="px-4 py-3 rounded-lg bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 text-sm text-blue-700 dark:text-blue-300">
                        You have already submitted a claim for this item. Please wait for the admin to review it.
                    </div>
                <?php else: ?>
                    <form action="<?= BASE_URL ?>actions/submit_claim.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="item_id"    value="<?= $item['item_id'] ?>">

                        <div class="mb-4">
                            <label for="claim_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Why is this item yours? <span class="text-red-500">*</span>
                            </label>
                            <textarea id="claim_message" name="message"
                                      rows="4" required
                                      placeholder="Describe identifying features, when/where you lost it, proof of ownership..."
                                      class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"><?= htmlspecialchars($_GET['message'] ?? '') ?></textarea>
                        </div>

                        <button type="submit"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Submit Claim
                        </button>
                    </form>
                <?php endif; ?>

            </div>

        <?php elseif ($item['status'] !== 'found' && !$is_owner && !$is_admin && isset($_SESSION['user_id'])): ?>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <?php if ($item['status'] === 'claimed'): ?>
                        This item has already been claimed.
                    <?php elseif ($item['status'] === 'expired'): ?>
                        This report has expired and is no longer accepting claims.
                    <?php else: ?>
                        Claims can only be submitted on found items.
                    <?php endif; ?>
                </p>
            </div>

        <?php elseif (!isset($_SESSION['user_id']) && $item['status'] === 'found'): ?>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-6">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <a href="<?= BASE_URL ?>auth/login.php"
                       class="text-blue-600 dark:text-blue-400 hover:underline font-medium">Log in</a>
                    to submit a claim for this item.
                </p>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>