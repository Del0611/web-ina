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
