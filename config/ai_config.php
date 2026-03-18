<?php
// AI config - key loaded from environment variable GROQ_API_KEY
$_ai_key = getenv('GROQ_API_KEY') ?: '';

define('GROQ_API_KEY', $_ai_key);
define('GROQ_MODEL',   'llama-3.1-8b-instant');
define('AI_ENABLED', !empty(GROQ_API_KEY));
