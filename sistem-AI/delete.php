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
