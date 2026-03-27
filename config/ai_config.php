<?php
// AI config — Groq API (key set in Railway env var GROQ_API_KEY)
$_ai_key = getenv('GROQ_API_KEY')
        ?: ($_ENV['GROQ_API_KEY'] ?? '')
        ?: ($_SERVER['GROQ_API_KEY'] ?? '');

define('GROQ_API_KEY', $_ai_key);
define('GEMINI_API_KEY', $_ai_key);   // alias dùng trong ai.php
define('GEMINI_MODEL',   'llama-3.1-8b-instant');
define('AI_ENABLED',     !empty($_ai_key));
