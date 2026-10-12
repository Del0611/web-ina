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
