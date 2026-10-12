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
