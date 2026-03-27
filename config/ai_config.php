<?php
// AI config — Google Gemini API
// Set GEMINI_API_KEY in Railway environment variables
$_ai_key = getenv('GEMINI_API_KEY')
        ?: ($_ENV['GEMINI_API_KEY'] ?? '')
        ?: ($_SERVER['GEMINI_API_KEY'] ?? '');

define('GEMINI_API_KEY', $_ai_key);
define('GEMINI_MODEL',   'gemini-2.0-flash-lite'); // 30 RPM, 1M TPM free
define('AI_ENABLED',     !empty($_ai_key));
