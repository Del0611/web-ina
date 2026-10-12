<?php
declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'sistem_ai');
define('DB_USER', 'root');
define('DB_PASS', '');

// Isi API key melalui environment variable.
define('AI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('AI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o-mini');
