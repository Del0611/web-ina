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
$records = $stmt->fetchAll();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Laporan Sistem Informasi AI</title>
<link rel="stylesheet" href="assets/style.css">
<style>
@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .container { max-width: none; padding: 0; }
    .panel { box-shadow: none; border: none; }
    table { font-size: 10pt; }
}
</style>
</head>
<body>
<main class="container">
<section class="panel">
<div class="no-print actions">
<a href="index.php">Kembali</a>
<button class="btn" onclick="window.print()">
Cetak / Simpan PDF
</button>
</div>

<h1>Laporan Sistem Informasi AI</h1>
<p>Tanggal laporan: <?= e(date('d-m-Y H:i')) ?></p>
<p>Total data: <?= count($records) ?></p>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>No.</th><th>Judul</th><th>Kategori</th>
<th>Deskripsi</th><th>Tanggal</th><th>Dibuat oleh</th>
</tr>
</thead>
<tbody>
<?php foreach ($records as $i => $r): ?>
<tr>
<td><?= $i + 1 ?></td>
<td><?= e($r['title']) ?></td>
<td><?= e($r['category']) ?></td>
<td><?= e($r['description']) ?></td>
<td><?= e($r['created_at']) ?></td>
<td><?= e($r['creator']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$records): ?>
<tr><td colspan="6">Tidak ada data.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>
