<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$userId = (int) $_SESSION['user_id'];
$error = '';

$scope = $isAdmin ? '1=1' : 'created_by = ?';
$scopeParams = $isAdmin ? [] : [$userId];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);

    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Umum');
    $description = trim($_POST['description'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if (!in_array($action, ['create', 'update'], true)) {
        http_response_code(400);
        exit('Aksi tidak valid.');
    }

    if ($title === '' || mb_strlen($title) > 200 ||
        $category === '' || mb_strlen($category) > 100) {
        $error = 'Judul atau kategori tidak valid.';
    } else {
        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO records
                 (title, category, description, created_by)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $title, $category, $description, $userId
            ]);
        } else {
            $sql = "UPDATE records
                    SET title=?, category=?, description=?
                    WHERE id=? AND $scope";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge(
                [$title, $category, $description, $id],
                $scopeParams
            ));
        }

        header('Location: index.php');
        exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $sql = "SELECT * FROM records WHERE id=? AND $scope";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(
        [(int) $_GET['edit']], $scopeParams
    ));
    $edit = $stmt->fetch() ?: null;
}

$q = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$where = [$scope];
$params = $scopeParams;

if ($q !== '') {
    $where[] = '(title LIKE ? OR description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($categoryFilter !== '') {
    $where[] = 'category = ?';
    $params[] = $categoryFilter;
}

$sqlWhere = implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT * FROM records WHERE $sqlWhere
     ORDER BY created_at DESC"
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM records WHERE $scope"
);
$stmt->execute($scopeParams);
$total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT category)
     FROM records WHERE $scope"
);
$stmt->execute($scopeParams);
$totalCategories = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT DISTINCT category FROM records
     WHERE $scope ORDER BY category"
);
$stmt->execute($scopeParams);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - Sistem AI</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
<a class="brand" href="index.php">Sistem AI</a>
<nav>
<a href="index.php">Dashboard</a>
<a href="assistant.php">Asisten AI</a>
<a href="report.php">Laporan PDF</a>
<a href="export.php">Ekspor Excel</a>
</nav>
<div class="user-info">
<?= e($_SESSION['name']) ?>
<form method="post" action="logout.php" class="inline">
<input type="hidden" name="csrf_token"
 value="<?= e(csrf_token()) ?>">
<button class="btn btn-light" type="submit">Keluar</button>
</form>
</div>
</header>

<main class="container">
<section class="page-heading">
<div>
<p class="eyebrow">SISTEM INFORMASI</p>
<h1>Dashboard</h1>
<p>Kelola informasi Anda dalam satu tempat.</p>
</div>
<span class="role"><?= $isAdmin ? 'Administrator' : 'Pengguna' ?></span>
</section>

<?php if ($error): ?>
<p class="error"><?= e($error) ?></p>
<?php endif; ?>

<section class="stats">
<div class="stat-card">
<span>Total data</span><strong><?= $total ?></strong>
</div>
<div class="stat-card">
<span>Kategori</span><strong><?= $totalCategories ?></strong>
</div>
<div class="stat-card">
<span>Akses</span>
<strong><?= $isAdmin ? 'Admin' : 'User' ?></strong>
</div>
</section>

<section class="panel">
<h2><?= $edit ? 'Edit data' : 'Tambah data baru' ?></h2>
<form method="post" class="record-form">
<input type="hidden" name="csrf_token"
 value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action"
 value="<?= $edit ? 'update' : 'create' ?>">
<?php if ($edit): ?>
<input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
<?php endif; ?>

<label>Judul</label>
<input name="title" maxlength="200" required
 value="<?= e($edit['title'] ?? '') ?>">

<label>Kategori</label>
<input name="category" maxlength="100" required
 value="<?= e($edit['category'] ?? 'Umum') ?>">

<label>Deskripsi</label>
<textarea name="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea>

<div class="actions">
<button class="btn" type="submit">
<?= $edit ? 'Simpan perubahan' : 'Tambah data' ?>
</button>
<?php if ($edit): ?>
<a class="btn btn-light" href="index.php">Batal</a>
<?php endif; ?>
</div>
</form>
</section>

<section class="panel">
<div class="panel-heading">
<div>
<h2>Daftar data</h2>
<p><?= count($records) ?> hasil ditemukan</p>
</div>
</div>

<form method="get" class="filters">
<input name="q" placeholder="Cari judul atau deskripsi..."
 value="<?= e($q) ?>">
<select name="category">
<option value="">Semua kategori</option>
<?php foreach ($categories as $cat): ?>
<option value="<?= e($cat) ?>"
 <?= $categoryFilter === $cat ? 'selected' : '' ?>>
<?= e($cat) ?>
</option>
<?php endforeach; ?>
</select>
<button class="btn" type="submit">Cari</button>
<a class="btn btn-light" href="index.php">Reset</a>
</form>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>Judul</th><th>Kategori</th>
<th>Deskripsi</th><th>Tanggal</th><th>Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($records as $r): ?>
<tr>
<td><?= e($r['title']) ?></td>
<td><span class="tag"><?= e($r['category']) ?></span></td>
<td><?= e($r['description']) ?></td>
<td><?= e($r['created_at']) ?></td>
<td>
<div class="actions">
<a href="index.php?edit=<?= (int) $r['id'] ?>">Edit</a>
<form method="post" action="delete.php"
 onsubmit="return confirm('Hapus data ini?')">
<input type="hidden" name="csrf_token"
 value="<?= e(csrf_token()) ?>">
<input type="hidden" name="id"
 value="<?= (int) $r['id'] ?>">
<button class="link-danger" type="submit">Hapus</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$records): ?>
<tr><td colspan="5">Belum ada data yang cocok.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>
