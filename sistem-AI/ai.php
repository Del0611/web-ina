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
