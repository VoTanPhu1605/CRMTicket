<?php
// AI config — OpenRouter (free models)
$_ai_key = getenv('OPENROUTER_API_KEY') ?: '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';

define('OPENROUTER_API_KEY', $_ai_key);
define('GEMINI_API_KEY', $_ai_key);          // alias dùng trong ai.php
define('GEMINI_MODEL', 'deepseek/deepseek-chat-v3-0324:free'); // free, rất mạnh
define('AI_ENABLED', !empty($_ai_key));
