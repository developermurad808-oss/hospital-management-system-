<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('reports.manage');

$format = $_GET['format'] ?? 'excel';
$rows = [];
$rows[] = ['Section', 'Metric', 'Value'];
$rows[] = ['Generated', 'Date', date('Y-m-d H:i:s')];
$rows[] = ['Patients', 'Total', (string) (Database::query('SELECT COUNT(*) total FROM patients')->fetch()['total'] ?? 0)];
$rows[] = ['Doctors', 'Total', (string) (Database::query('SELECT COUNT(*) total FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug="doctor"')->fetch()['total'] ?? 0)];
$rows[] = ['Billing', 'Total revenue', number_format((float) (Database::query('SELECT COALESCE(SUM(amount),0) total FROM payments')->fetch()['total'] ?? 0), 2)];
$rows[] = ['Beds', 'Active admissions', (string) (Database::query('SELECT COUNT(*) total FROM admissions WHERE status="active" AND discharged_at IS NULL')->fetch()['total'] ?? 0)];

foreach (Database::query('SELECT status, COUNT(*) total FROM lab_requests GROUP BY status')->fetchAll() as $row) {
    $rows[] = ['Laboratory', $row['status'], (string) $row['total']];
}
foreach (Database::query('SELECT m.name, COALESCE(SUM(b.quantity),0) quantity FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id ORDER BY quantity ASC LIMIT 20')->fetchAll() as $row) {
    $rows[] = ['Pharmacy stock', $row['name'], (string) $row['quantity']];
}

if ($format === 'pdf') {
    $hospital = null;
    try {
        $hospital = Database::query('SELECT * FROM hospital_profiles ORDER BY id LIMIT 1')->fetch();
    } catch (Throwable) {}

    $pdfText = static function (string $text): string {
        $text = str_replace(["\r", "\n"], ' ', $text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };
    $cellText = static function (string $text, int $limit) use ($pdfText): string {
        $text = trim($text);
        if (strlen($text) > $limit) {
            $text = substr($text, 0, max(0, $limit - 3)) . '...';
        }
        return $pdfText($text);
    };
    $textAt = static function (int $x, int $y, string $text, string $font = 'F1', int $size = 9, string $color = '0.08 0.20 0.15') use ($pdfText): string {
        return "{$color} rg BT /{$font} {$size} Tf {$x} {$y} Td (" . $pdfText($text) . ') Tj ET';
    };

    $headers = $rows[0];
    $bodyRows = array_slice($rows, 1);
    $pages = [];
    $chunks = array_chunk($bodyRows, 22);
    $pageCount = max(1, count($chunks));
    $reportTitle = (($hospital['name'] ?? null) ?: 'UltimateCooperative HMS') . ' Report';
    $subtitle = 'Operational summary export';
    $generatedAt = date('M j, Y h:i A');

    foreach ($chunks as $pageIndex => $chunk) {
        $content = [];
        $content[] = '0.96 0.99 0.97 rg 0 0 595 842 re f';
        $content[] = '0.18 0.62 0.41 rg 0 742 595 100 re f';
        $content[] = $textAt(42, 800, $reportTitle, 'F2', 20, '1 1 1');
        $content[] = $textAt(42, 778, $subtitle, 'F1', 11, '1 1 1');
        $content[] = $textAt(430, 800, 'Generated', 'F1', 9, '1 1 1');
        $content[] = $textAt(430, 782, $generatedAt, 'F2', 10, '1 1 1');
        $content[] = '1 1 1 rg 42 690 511 36 re f';
        $content[] = '0.85 0.92 0.88 RG 42 690 511 36 re S';
        $content[] = $textAt(58, 711, 'Professional table export', 'F2', 11);
        $content[] = $textAt(58, 696, 'Includes patients, doctors, billing, beds, laboratory and pharmacy stock metrics.', 'F1', 8, '0.38 0.48 0.43');
        $content[] = $textAt(468, 704, 'Page ' . ($pageIndex + 1) . ' of ' . $pageCount, 'F2', 9, '0.18 0.62 0.41');

        $x = 42;
        $y = 644;
        $widths = [126, 254, 131];
        $rowHeight = 24;
        $tableWidth = array_sum($widths);

        $content[] = '0.18 0.62 0.41 rg ' . $x . ' ' . ($y - 7) . ' ' . $tableWidth . ' ' . $rowHeight . ' re f';
        $content[] = $textAt($x + 10, $y, (string) $headers[0], 'F2', 9, '1 1 1');
        $content[] = $textAt($x + $widths[0] + 10, $y, (string) $headers[1], 'F2', 9, '1 1 1');
        $content[] = $textAt($x + $widths[0] + $widths[1] + 10, $y, (string) $headers[2], 'F2', 9, '1 1 1');
        $content[] = '0.18 0.62 0.41 RG ' . $x . ' ' . ($y - 7) . ' ' . $tableWidth . ' ' . $rowHeight . ' re S';
        $y -= $rowHeight;

        foreach ($chunk as $index => $row) {
            $fill = $index % 2 === 0 ? '1 1 1' : '0.93 0.98 0.95';
            $content[] = $fill . ' rg ' . $x . ' ' . ($y - 7) . ' ' . $tableWidth . ' ' . $rowHeight . ' re f';
            $content[] = '0.85 0.92 0.88 RG ' . $x . ' ' . ($y - 7) . ' ' . $tableWidth . ' ' . $rowHeight . ' re S';
            $lineOneX = $x + $widths[0];
            $lineTwoX = $x + $widths[0] + $widths[1];
            $lineY = $y - 7;
            $content[] = '0.85 0.92 0.88 RG ' . $lineOneX . ' ' . $lineY . ' m ' . $lineOneX . ' ' . ($lineY + $rowHeight) . ' l S';
            $content[] = '0.85 0.92 0.88 RG ' . $lineTwoX . ' ' . $lineY . ' m ' . $lineTwoX . ' ' . ($lineY + $rowHeight) . ' l S';
            $content[] = '0.08 0.20 0.15 rg BT /F1 9 Tf ' . ($x + 10) . ' ' . $y . ' Td (' . $cellText((string) $row[0], 24) . ') Tj ET';
            $content[] = '0.08 0.20 0.15 rg BT /F1 9 Tf ' . ($x + $widths[0] + 10) . ' ' . $y . ' Td (' . $cellText((string) $row[1], 45) . ') Tj ET';
            $content[] = '0.08 0.20 0.15 rg BT /F2 9 Tf ' . ($x + $widths[0] + $widths[1] + 10) . ' ' . $y . ' Td (' . $cellText((string) $row[2], 20) . ') Tj ET';
            $y -= $rowHeight;
        }

        $content[] = '0.38 0.48 0.43 rg BT /F1 8 Tf 42 44 Td (Exported from UltimateCooperative HMS) Tj ET';
        $pages[] = implode("\n", $content);
    }

    $objects = [
        1 => '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        2 => '',
        3 => '3 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        4 => '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj',
    ];
    $kids = [];
    foreach ($pages as $stream) {
        $pageId = count($objects) + 1;
        $contentId = $pageId + 1;
        $kids[] = "{$pageId} 0 R";
        $objects[$pageId] = "{$pageId} 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >> endobj";
        $objects[$contentId] = "{$contentId} 0 obj << /Length " . strlen($stream) . " >> stream\n{$stream}\nendstream endobj";
    }
    $objects[2] = '2 0 obj << /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >> endobj';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="hms-report.pdf"');
    echo $pdf;
    exit;
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="hms-report.xls"');
echo "<table>";
foreach ($rows as $row) {
    echo '<tr><td>' . implode('</td><td>', array_map('e', $row)) . '</td></tr>';
}
echo "</table>";
