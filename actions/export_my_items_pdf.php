<?php
// actions/export_my_items_pdf.php
// Logged-in user exports their own items as a PDF table.
require_once __DIR__ . '/../config/init.php';

// ── 1. Must be logged in ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';

// ── 2. Fetch this user's items ────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare("
    SELECT item_id, item_name, category, status,
           location, date_reported
    FROM items
    WHERE user_id    = ?
      AND is_deleted = 0
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

// ── 3. Load TCPDF ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// ── 4. Document metadata ──────────────────────────────────────────────────────
$pdf->SetCreator('Lost and Found System');
$pdf->SetAuthor($user_name);
$pdf->SetTitle('My Items Report');
$pdf->SetSubject('My Lost and Found Items Export');

// ── 5. Remove default header/footer ──────────────────────────────────────────
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// ── 6. Page settings ──────────────────────────────────────────────────────────
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

// ── 7. Title ──────────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Lost and Found System — My Items Report', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'User: ' . $user_name . '   |   Generated: ' . date('d M Y, h:i A'), 0, 1, 'C');
$pdf->Ln(4);

// ── 8. Table header ───────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(52, 58, 64);
$pdf->SetTextColor(255, 255, 255);

$colWidths = [15, 90, 40, 30, 70, 32];
$headers   = ['ID', 'Item Name', 'Category', 'Status', 'Location', 'Date Reported'];

foreach ($headers as $i => $h) {
    $pdf->Cell($colWidths[$i], 8, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// ── 9. Table rows ─────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);

$fill = false;
foreach ($items as $item) {
    if ($fill) {
        $pdf->SetFillColor(240, 240, 240);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    $pdf->Cell($colWidths[0], 7, $item['item_id'],           1, 0, 'C', $fill);
    $pdf->Cell($colWidths[1], 7, $item['item_name'],         1, 0, 'L', $fill);
    $pdf->Cell($colWidths[2], 7, $item['category'],          1, 0, 'C', $fill);
    $pdf->Cell($colWidths[3], 7, ucfirst($item['status']),   1, 0, 'C', $fill);
    $pdf->Cell($colWidths[4], 7, $item['location'],          1, 0, 'L', $fill);
    $pdf->Cell($colWidths[5], 7, $item['date_reported'],     1, 1, 'C', $fill);

    $fill = !$fill;
}

// ── 10. Total count footer ────────────────────────────────────────────────────
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(0, 6, 'Total items: ' . count($items), 0, 1, 'R');

// ── 11. Output as download ────────────────────────────────────────────────────
$pdf->Output('my_items_' . date('Ymd') . '.pdf', 'D');
exit;
