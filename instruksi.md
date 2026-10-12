# Sistem Informasi AI Assistant — PHP + MySQL

Kita akan membuat website sistem informasi bernama Sistem Informasi AI Assistant, yaitu aplikasi web yang menggabungkan dashboard admin, pengelolaan data, autentikasi pengguna, laporan, dan asisten AI yang dapat menjawab pertanyaan dalam bahasa Indonesia.

Teknologi utama yang digunakan adalah PHP dan MySQL, sehingga proyek dapat dijalankan secara lokal menggunakan XAMPP dan dikembangkan untuk hosting online.

## 1. Gambaran website

Welcome to Front - Admin & Dashboard Template!

Dashboard admin

Ringkasan data, jumlah pengguna, dan statistik aktivitas.

Manajemen data

Tambah, edit, hapus, cari, dan filter data.

Asisten AI

Tanya jawab melalui API AI, termasuk percakapan berkelanjutan.

Laporan

Ekspor Excel-compatible CSV dan cetak atau simpan laporan sebagai PDF.

## 2. Arsitektur sistem

mermaid
```

flowchart TD
    A[Pengguna / Admin] --> B[Frontend HTML CSS JavaScript]
    B --> C[Backend PHP]
    C --> D[(MySQL Database)]
    C --> E[AI API]
    C --> F[Laporan CSV / PDF]
    D --> C
    E --> C
    F --> A
```

Perlu diperhatikan: PHP menangani autentikasi dan akses database. Asisten AI menggunakan API eksternal, sehingga Anda perlu API key dari penyedia AI yang kompatibel. API key disimpan di server, bukan di JavaScript frontend.

## 3. Struktur folder proyek

Buat folder bernama `sistem-ai` di dalam direktori `htdocs` XAMPP.

text
```
sistem-ai/
├── assets/
│   └── style.css
├── config/
│   ├── config.php
│   └── database.php
├── includes/
│   └── auth.php
├── database.sql
├── index.php
├── login.php
├── register.php
├── logout.php
├── delete.php
├── export.php
├── report.php
├── assistant.php
└── ai.php
```

## 4. Membuat database MySQL

Buka phpMyAdmin melalui `http://localhost/phpmyadmin`, pilih menu SQL, lalu jalankan skrip berikut.

sql
```

CREATE DATABASE sistem_ai
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE sistem_ai;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'Umum',
    description TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_records_title (title),
    INDEX idx_records_category (category),
    INDEX idx_records_created_by (created_by)
);

CREATE TABLE ai_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    question TEXT NOT NULL,
    answer MEDIUMTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);
```

Database memiliki tiga tabel utama:

- `users`: akun pengguna, kata sandi yang sudah di-hash, dan peran.
- `records`: data yang dikelola melalui dashboard.
- `ai_logs`: riwayat pertanyaan dan jawaban AI.

## 5. Konfigurasi koneksi database

Buat `config/config.php`.

php
```

<?php
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'sistem_ai');
define('DB_USER', 'root');
define('DB_PASS', '');

// Isi API key melalui environment variable.
// Jangan menaruh API key di frontend.
define('AI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('AI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o-mini');
```

Buat `config/database.php`.

php
```

<?php
require_once __DIR__ . '/config.php';

$dsn = 'mysql:host=' . DB_HOST
     . ';dbname=' . DB_NAME
     . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi server.');
}
```

Jika menggunakan XAMPP standar, biasanya username database adalah `root` dan password kosong. Sesuaikan jika konfigurasi MySQL Anda berbeda.

## 6. Sistem autentikasi

Buat `includes/auth.php` untuk menangani sesi, perlindungan CSRF, dan pemeriksaan login.

php
```

<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(?string $token): void {
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Token keamanan tidak valid. Muat ulang halaman.');
    }
}

function e(?string $value): string {
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
```

### Registrasi pengguna

Buat `register.php`.

php
```

<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 8) {
        $error = 'Nama, email, atau password tidak valid. Password minimal 8 karakter.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $error = 'Email sudah terdaftar.';
            } else {
                throw $ex;
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Registrasi - Sistem AI</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
<h1>Buat akun</h1>
<p>Daftar untuk menggunakan Sistem Informasi AI.</p>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label>Nama</label>
<input name="name" maxlength="100" required>
<label>Email</label>
<input type="email" name="email" maxlength="190" required>
<label>Password (minimal 8 karakter)</label>
<input type="password" name="password" minlength="8" required>
<button class="btn" type="submit">Daftar</button>
</form>
<p>Sudah punya akun? <a href="login.php">Masuk</a></p>
</main>
</body>
</html>
```

### Login dan logout

Buat `login.php`.

php
```

<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf_token'] ?? null);

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password, role
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        header('Location: index.php');
        exit;
    }

    $error = 'Email atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - Sistem AI</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-card">
<h1>Selamat datang</h1>
<p>Masuk ke Sistem Informasi AI Assistant.</p>
<?php if (isset($_GET['registered'])): ?>
<p class="success">Registrasi berhasil. Silakan masuk.</p>
<?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label>Email</label>
<input type="email" name="email" required autocomplete="email">
<label>Password</label>
<input type="password" name="password" required autocomplete="current-password">
<button class="btn" type="submit">Login</button>
</form>
<p>Belum punya akun? <a href="register.php">Registrasi</a></p>
</main>
</body>
</html>
```

Buat `logout.php`.

php
```

<?php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

csrf_check($_POST['csrf_token'] ?? null);

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
```

## 7. Dashboard admin dan manajemen data (CRUD)

Buat `index.php`. File ini menjadi halaman utama setelah pengguna login. Admin dapat melihat seluruh data, sedangkan pengguna biasa hanya melihat data miliknya.

Fitur di dalamnya:

- Dashboard statistik.
- Tambah dan edit data.
- Hapus data dengan konfirmasi.
- Pencarian berdasarkan judul atau deskripsi.
- Filter berdasarkan kategori.
- Tautan laporan dan asisten AI.

php
```

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
```

### Fitur hapus data

Buat `delete.php`.

php
```

<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

csrf_check($_POST['csrf_token'] ?? null);

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('ID data tidak valid.');
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

if ($isAdmin) {
    $stmt = $pdo->prepare('DELETE FROM records WHERE id=?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare(
        'DELETE FROM records WHERE id=? AND created_by=?'
    );
    $stmt->execute([$id, (int) $_SESSION['user_id']]);
}

header('Location: index.php');
exit;
```

## 8. Integrasi asisten AI

Bagian ini membuat asisten yang menerima pertanyaan dari pengguna, mengirimkannya ke API AI melalui backend PHP, kemudian menampilkan jawaban. Riwayat percakapan akan disimpan ke tabel `ai_logs`.

AI Assistant

Contoh pertanyaan yang bisa ditangani

Pengguna

Bagaimana cara membuat laporan data bulanan?

Asisten AI

Anda dapat menyaring data berdasarkan periode, memeriksa hasilnya, lalu mengekspor data ke Excel atau mencetak halaman laporan menjadi PDF.

Ilustrasi jawaban, bukan hasil panggilan AI secara langsung.

### Backend AI — `ai.php`

Contoh berikut memakai endpoint Chat Completions yang kompatibel dengan OpenAI. API key harus tersedia pada environment variable server `OPENAI_API_KEY`.

php
```

<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Gunakan metode POST.']);
}

$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    respond(400, ['error' => 'Format permintaan tidak valid.']);
}

csrf_check($body['csrf_token'] ?? null);

$question = trim($body['question'] ?? '');

if ($question === '' || mb_strlen($question) > 4000) {
    respond(422, ['error' => 'Pertanyaan wajib diisi, maksimal 4000 karakter.']);
}

if (!AI_API_KEY) {
    respond(503, [
        'error' => 'API AI belum dikonfigurasi oleh administrator.'
    ]);
}

$payload = [
    'model' => AI_MODEL,
    'messages' => [
        [
            'role' => 'system',
            'content' => 'Anda adalah asisten Sistem Informasi AI.
Jawab dalam bahasa Indonesia yang jelas, sopan, dan praktis.
Jangan mengaku telah membaca database jika data tidak diberikan.'
        ],
        ['role' => 'user', 'content' => $question]
    ],
    'temperature' => 0.5
];

$ch = curl_init(
    'https://api.openai.com/v1/chat/completions'
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$result = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($result === false || $curlError !== '') {
    error_log('AI request failed: ' . $curlError);
    respond(502, ['error' => 'Layanan AI tidak dapat dihubungi.']);
}

$json = json_decode($result, true);
$answer = $json['choices'][0]['message']['content'] ?? '';

if ($status < 200 || $status >= 300 || !is_string($answer)
    || trim($answer) === '') {
    error_log('AI API returned HTTP ' . $status);
    respond(502, ['error' => 'AI gagal menghasilkan jawaban.']);
}

$stmt = $pdo->prepare(
    'INSERT INTO ai_logs (user_id, question, answer)
     VALUES (?, ?, ?)'
);
$stmt->execute([
    (int) $_SESSION['user_id'],
    $question,
    $answer
]);

respond(200, ['answer' => $answer]);
```

### Tampilan chat — `assistant.php`

php
```

<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$stmt = $pdo->prepare(
    'SELECT question, answer, created_at
     FROM ai_logs WHERE user_id=?
     ORDER BY id DESC LIMIT 20'
);
$stmt->execute([(int) $_SESSION['user_id']]);
$history = array_reverse($stmt->fetchAll());
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Asisten AI</title>
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
</header>

<main class="container chat-layout">
<section class="panel">
<h1>Asisten AI</h1>
<p>Tanyakan sesuatu dalam bahasa Indonesia.</p>

<div id="chat" class="chat-messages" aria-live="polite">
<?php foreach ($history as $h): ?>
<div class="bubble user-bubble">
<strong>Anda</strong>
<p><?= e($h['question']) ?></p>
</div>
<div class="bubble ai-bubble">
<strong>AI Assistant</strong>
<p><?= e($h['answer']) ?></p>
</div>
<?php endforeach; ?>
</div>

<form id="chat-form">
<input type="hidden" id="csrf"
 value="<?= e(csrf_token()) ?>">
<label for="question">Pertanyaan Anda</label>
<textarea id="question" maxlength="4000" rows="3"
 placeholder="Contoh: Bagaimana mengelola data dengan baik?"
 required></textarea>
<button class="btn" id="send" type="submit">Kirim pertanyaan</button>
<p id="status" role="status"></p>
</form>
</section>
</main>

<script>
const form = document.getElementById('chat-form');
const input = document.getElementById('question');
const chat = document.getElementById('chat');
const statusEl = document.getElementById('status');
const send = document.getElementById('send');

function addBubble(label, message, type) {
    const box = document.createElement('div');
    box.className = 'bubble ' + type;

    const strong = document.createElement('strong');
    strong.textContent = label;

    const p = document.createElement('p');
    p.textContent = message;

    box.append(strong, p);
    chat.appendChild(box);
    box.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const question = input.value.trim();
    if (!question) return;

    addBubble('Anda', question, 'user-bubble');
    input.value = '';
    send.disabled = true;
    statusEl.textContent = 'AI sedang berpikir...';

    try {
        const response = await fetch('ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                question,
                csrf_token: document.getElementById('csrf').value
            })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Permintaan gagal.');
        }

        addBubble('AI Assistant', data.answer, 'ai-bubble');
        statusEl.textContent = '';
    } catch (error) {
        statusEl.textContent = error.message ||
            'Terjadi kesalahan. Silakan coba lagi.';
    } finally {
        send.disabled = false;
        input.focus();
    }
});
</script>
</body>
</html>
```

Catatan integrasi AI: kode di atas membuat AI menjawab pertanyaan umum. Jika Anda ingin AI menjawab pertanyaan spesifik tentang isi database—misalnya, “Berapa jumlah data bulan ini?”—perlu ditambahkan fungsi backend khusus untuk mengambil data MySQL yang relevan dan memberikannya sebagai konteks kepada AI. Jangan memberikan akses SQL bebas kepada model AI.

## 9. Ekspor Excel dan laporan PDF

Untuk Excel, kita menggunakan CSV UTF-8 yang dapat dibuka di Microsoft Excel atau LibreOffice. Untuk PDF, kita membuat halaman laporan yang dapat dicetak atau disimpan sebagai PDF melalui browser.

### `export.php` — ekspor Excel

File ini mengekspor data sesuai hak akses pengguna.

php
```

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
```

### `report.php` — cetak atau simpan sebagai PDF

php
```

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
```

Cara membuat PDF: buka menu Laporan PDF, klik Cetak / Simpan PDF, lalu pilih Save as PDF pada dialog pencetakan browser.

Jika ingin menghasilkan berkas PDF secara otomatis di server, library seperti Dompdf dapat ditambahkan kemudian.

## 10. Desain frontend — `assets/style.css`

CSS berikut memberikan tampilan dashboard responsif, form, tabel, kartu statistik, dan halaman chat.

css
```

:root {
  --primary: #315efb;
  --primary-dark: #2448d8;
  --bg: #f4f7fc;
  --text: #182338;
  --muted: #718096;
  --border: #e3e9f2;
  --white: #ffffff;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  font-family: Inter, "Segoe UI", Arial, sans-serif;
  font-size: 15px;
  line-height: 1.6;
}

a { color: var(--primary); text-decoration: none; }
a:hover { text-decoration: underline; }

.topbar {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 16px 5%;
  background: var(--white);
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.brand {
  color: var(--text);
  font-size: 21px;
  font-weight: 800;
}

.topbar nav {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
}

.topbar nav a { color: #526078; }

.user-info {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
}

.container {
  width: min(1180px, 92%);
  margin: 34px auto;
}

.page-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 24px;
}

h1, h2 { line-height: 1.25; }
h1 { font-size: clamp(25px, 4vw, 34px); margin: 4px 0 8px; }
h2 { font-size: 19px; margin-top: 0; }
p { color: var(--muted); }

.eyebrow {
  color: var(--primary);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 1.5px;
}

.role, .tag {
  background: #eaf0ff;
  color: #3151b5;
  border-radius: 8px;
  padding: 5px 10px;
  font-size: 12px;
  font-weight: 700;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin: 24px 0;
}

.stat-card, .panel, .auth-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 5px 20px rgba(30, 50, 90, .035);
}

.stat-card span {
  display: block;
  color: var(--muted);
  font-size: 13px;
}

.stat-card strong {
  display: block;
  margin-top: 8px;
  font-size: 30px;
}

.panel { margin-bottom: 24px; }
.panel-heading { display: flex; justify-content: space-between; }

label {
  display: block;
  font-size: 13px;
  font-weight: 650;
  margin: 12px 0 6px;
}

input, textarea, select {
  width: 100%;
  padding: 11px 13px;
  font: inherit;
  color: var(--text);
  background: white;
  border: 1px solid #d8e0ed;
  border-radius: 9px;
}

input:focus, textarea:focus, select:focus {
  outline: 2px solid #c6d5ff;
  border-color: var(--primary);
}

.record-form { max-width: 800px; }

.btn {
  display: inline-block;
  border: 0;
  border-radius: 9px;
  padding: 10px 16px;
  background: var(--primary);
  color: white;
  font: inherit;
  font-weight: 650;
  cursor: pointer;
  text-align: center;
}

.btn:hover { background: var(--primary-dark); text-decoration: none; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
.btn-light { background: #edf1f8; color: #34425a; }

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 16px;
}

.actions form { margin: 0; }

.filters {
  display: grid;
  grid-template-columns: minmax(180px, 2fr) minmax(140px, 1fr) auto auto;
  gap: 10px;
  margin: 20px 0;
}

.table-wrap { overflow-x: auto; }

table {
  width: 100%;
  border-collapse: collapse;
  background: white;
}

th, td {
  text-align: left;
  vertical-align: top;
  padding: 13px 12px;
  border-bottom: 1px solid var(--border);
}

th {
  background: #f8faff;
  color: #68758c;
  font-size: 12px;
  text-transform: uppercase;
}

.link-danger {
  border: 0;
  background: none;
  color: #c53030;
  font: inherit;
  cursor: pointer;
}

.error, .success {
  padding: 12px;
  border-radius: 9px;
  background: #fff0f0;
  color: #b42318;
}

.success { background: #e9f9ef; color: #167647; }

.auth-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 20px;
  background: linear-gradient(140deg, #e8eeff, #f7f9ff);
}

.auth-card { width: min(440px, 100%); }
.auth-card .btn { width: 100%; margin-top: 18px; }
.inline { display: inline; }

.chat-layout { max-width: 850px; }
.chat-messages {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-height: 52vh;
  overflow-y: auto;
  padding: 12px 0 20px;
}

.bubble {
  padding: 14px 16px;
  border-radius: 12px;
  max-width: 95%;
  overflow-wrap: anywhere;
}

.bubble p { white-space: pre-wrap; margin: 6px 0 0; color: var(--text); }
.user-bubble { align-self: flex-end; background: #eaf0ff; }
.ai-bubble { align-self: flex-start; background: #f1f4f8; }

@media (max-width: 760px) {
  .topbar { align-items: flex-start; gap: 12px; }
  .topbar nav { order: 3; width: 100%; gap: 12px; }
  .user-info { margin-left: 0; }
  .stats { grid-template-columns: 1fr; gap: 12px; }
  .filters { grid-template-columns: 1fr; }
  .page-heading { align-items: flex-start; flex-direction: column; }
  .panel, .auth-card { padding: 18px; }
}

@media print {
  .topbar, .no-print, .actions { display: none !important; }
  body { background: white; }
  .panel { box-shadow: none; border: 0; }
}
```

## 11. Cara menjalankan website di XAMPP

Ikuti langkah-langkah berikut secara berurutan.

1. Instal dan jalankan XAMPP
   Unduh XAMPP dari [situs resmi Apache Friends](<https://www.apachefriends.org/>). Jalankan modul Apache dan MySQL.
2. Buat folder proyek
   Simpan seluruh file pada:
   text
   ```
   C:\xampp\htdocs\sistem-ai\
   ```
   Pastikan file CSS berada di `assets`, file konfigurasi berada di `config`, dan helper autentikasi berada di `includes`.
3. Buat database
   Buka [phpMyAdmin](<http://localhost/phpmyadmin>), pilih tab SQL, lalu jalankan skrip database pada bagian 4.
4. Konfigurasi koneksi
   Periksa `config/config.php`. Sesuaikan host, nama database, username, dan password MySQL dengan konfigurasi XAMPP Anda.
5. Aktifkan ekstensi PHP cURL
   Buka `php.ini` dari panel XAMPP, pastikan ekstensi cURL diaktifkan, lalu restart Apache. Ekstensi ini dibutuhkan untuk menghubungi API AI.
6. Buka website
   Akses [Halaman registrasi](<http://localhost/sistem-ai/register.php>). Buat akun, kemudian masuk melalui [Halaman login](<http://localhost/sistem-ai/login.php>).
7. Jadikan akun pertama sebagai admin
   Setelah registrasi, jalankan SQL berikut di phpMyAdmin dengan mengganti email sesuai akun Anda:
   sql
   ```
   UPDATE users
   SET role = 'admin'
   WHERE email = 'admin@contoh.com';
   ```
   Logout, lalu login kembali agar sesi memuat peran admin yang baru.
8. Konfigurasi API AI
   Dapatkan API key dari penyedia AI yang digunakan. Atur environment variable `OPENAI_API_KEY` di lingkungan server Apache, kemudian restart Apache. Jangan pernah menaruh API key pada HTML, JavaScript, repositori publik, atau membagikannya kepada pengguna.
   Jika menggunakan layanan OpenAI, Anda dapat mempelajari pengaturan API di [dokumentasi API resmi](<https://platform.openai.com/docs/overview>). Penggunaan API dapat dikenakan biaya terpisah.

## 12. Pengujian fitur

Setelah website berjalan, lakukan pengujian berikut.

## Checklist pengujian

0 dari 8

[ ]Registrasi akun baru dan login

[ ]Tambah data baru melalui dashboard

[ ]Edit dan hapus data

[ ]Cari judul dan filter kategori

[ ]Ekspor data CSV dan buka di Excel

[ ]Cetak laporan dan simpan sebagai PDF

[ ]Kirim pertanyaan melalui asisten AI

[ ]Pastikan akun biasa tidak dapat mengedit data pengguna lain

## 13. Hal penting sebelum website dipublikasikan

Kode di atas merupakan fondasi MVP yang dapat Anda kembangkan. Sebelum dipakai untuk data penting atau dibuka ke internet, beberapa hal masih perlu ditambahkan atau diperkuat:

- Keamanan: HTTPS, pembatasan percobaan login, kebijakan password, konfigurasi cookie sesi, serta perlindungan CSRF yang konsisten pada semua endpoint.
- Hak akses: buat pengelolaan peran admin yang aman, bukan memberi hak admin melalui halaman registrasi publik.
- AI: batasi jumlah permintaan dan biaya, tangani kegagalan API, serta berikan konteks database hanya melalui query backend yang diizinkan.
- Laporan: implementasikan pagination untuk dataset besar dan gunakan library PDF jika perlu membuat PDF otomatis di server.
- Deployment: simpan kredensial di environment server dan jangan menampilkan pesan kesalahan internal kepada pengunjung.

Hasil akhirnya: Anda memiliki rancangan aplikasi PHP + MySQL dengan registrasi dan login, dashboard, CRUD, pencarian, filter, ekspor CSV untuk Excel, cetak PDF melalui browser, dan asisten AI melalui API. Semua komponen perlu disimpan sesuai nama file dan diuji di lingkungan PHP/MySQL Anda; kode ini belum merupakan paket ZIP yang sudah terpasang atau teruji di server Anda.
