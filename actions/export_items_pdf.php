<?php
// actions/export_items_pdf.php
// Admin only — exports ALL items as a PDF table.
require_once __DIR__ . '/../config/init.php';

// ── 1. Admin only ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'pages/home.php');
    exit;
}

// ── 2. Fetch all items ────────────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->query("
    SELECT i.item_id, i.item_name, i.category, i.status,
           i.location, i.date_reported, i.is_deleted,
           u.full_name AS reporter_name
    FROM items i
    LEFT JOIN users u ON u.user_id = i.user_id
    ORDER BY i.created_at DESC
");
$items = $stmt->fetchAll();

// ── 3. Load TCPDF ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// ── 4. Document metadata ──────────────────────────────────────────────────────
$pdf->SetCreator('Lost and Found System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('All Items Report');
$pdf->SetSubject('Lost and Found Items Export');

// ── 5. Remove default header/footer ──────────────────────────────────────────
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// ── 6. Page settings ──────────────────────────────────────────────────────────
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->AddPage();

// ── 7. Title ──────────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Lost and Found System — All Items Report', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Generated: ' . date('d M Y, h:i A'), 0, 1, 'C');
$pdf->Ln(4);

// ── 8. Table header ───────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(52, 58, 64);   // dark header background
$pdf->SetTextColor(255, 255, 255); // white text

$colWidths = [12, 60, 30, 22, 55, 35, 22, 18];
$headers   = ['ID', 'Item Name', 'Category', 'Status', 'Location', 'Reporter', 'Date', 'Deleted'];

foreach ($headers as $i => $h) {
    $pdf->Cell($colWidths[$i], 8, $h, 1, 0, 'C', true);
}
$pdf->Ln();

// ── 9. Table rows ─────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);

$fill = false; // alternating row colour
foreach ($items as $item) {
    // Light grey for alternate rows
    if ($fill) {
        $pdf->SetFillColor(240, 240, 240);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    $pdf->Cell($colWidths[0], 7, $item['item_id'],                              1, 0, 'C', $fill);
    $pdf->Cell($colWidths[1], 7, $item['item_name'],                            1, 0, 'L', $fill);
    $pdf->Cell($colWidths[2], 7, $item['category'],                             1, 0, 'C', $fill);
    $pdf->Cell($colWidths[3], 7, ucfirst($item['status']),                      1, 0, 'C', $fill);
    $pdf->Cell($colWidths[4], 7, $item['location'],                             1, 0, 'L', $fill);
    $pdf->Cell($colWidths[5], 7, $item['reporter_name'] ?? 'Deleted User',      1, 0, 'L', $fill);
    $pdf->Cell($colWidths[6], 7, $item['date_reported'],                        1, 0, 'C', $fill);
    $pdf->Cell($colWidths[7], 7, $item['is_deleted'] ? 'Yes' : 'No',           1, 1, 'C', $fill);

    $fill = !$fill;
}

// ── 10. Total count footer ────────────────────────────────────────────────────
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(0, 6, 'Total items: ' . count($items), 0, 1, 'R');

// ── 11. Output as download ────────────────────────────────────────────────────
$pdf->Output('all_items_' . date('Ymd') . '.pdf', 'D');
exit;
