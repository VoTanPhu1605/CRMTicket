<?php
// AI config - key loaded from environment variable GROQ_API_KEY
$_ai_key = getenv('GROQ_API_KEY') ?: '';

define('GROQ_API_KEY', $_ai_key);
// llama3-8b-8192: 30,000 TPM (5x cao hơn llama-3.1-8b-instant), hỗ trợ tool calling
define('GROQ_MODEL',   'llama3-8b-8192');
define('AI_ENABLED', !empty(GROQ_API_KEY));
