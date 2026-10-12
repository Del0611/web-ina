<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$userId = (int) $_SESSION['user_id'];

$sql = 'SELECT r.title, r.category, r.description,
               r.created_at, u.name AS creator
        FROM records r
        JOIN users u ON u.id = r.created_by';

if (!$isAdmin) {
    $sql .= ' WHERE r.created_by = ?';
}

$sql .= ' ORDER BY r.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($isAdmin ? [] : [$userId]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="laporan-sistem-ai.csv"');

$out = fopen('php://output', 'w');

// BOM agar karakter UTF-8 terbaca baik di Excel.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Judul', 'Kategori', 'Deskripsi',
    'Tanggal', 'Dibuat oleh'
], ';');

while ($r = $stmt->fetch()) {
    $row = [
        $r['title'],
        $r['category'],
        $r['description'],
        $r['created_at'],
        $r['creator']
    ];

    // Hindari interpretasi nilai teks sebagai formula spreadsheet.
    foreach ($row as &$value) {
        if (is_string($value) &&
            preg_match('/^[\s]*[=+\-@\t\r]/u', $value)) {
            $value = "'" . $value;
        }
    }
    unset($value);

    fputcsv($out, $row, ';');
}

fclose($out);
exit;
