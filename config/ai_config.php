<?php
// AI config — key set via Railway environment variable OPENROUTER_API_KEY
$_ai_key = getenv('OPENROUTER_API_KEY')
        ?: ($_ENV['OPENROUTER_API_KEY'] ?? '')
        ?: ($_SERVER['OPENROUTER_API_KEY'] ?? '');

define('GEMINI_API_KEY', $_ai_key);
define('GEMINI_MODEL', 'google/gemini-2.0-flash-exp:free');
define('AI_ENABLED', !empty($_ai_key));
