<?php
// AI config — key set via Railway environment variable OPENROUTER_API_KEY
$_ai_key = getenv('OPENROUTER_API_KEY') ?: '';

define('GEMINI_API_KEY', $_ai_key);
define('GEMINI_MODEL', 'deepseek/deepseek-chat-v3-0324:free');
define('AI_ENABLED', !empty($_ai_key));
