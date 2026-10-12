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
