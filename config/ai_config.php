<?php
// AI config — Gemini API (Google AI Studio free tier)
$_ai_key = getenv('GEMINI_API_KEY') ?: 'AIzaSyBm4-uyvi6j1s-67qRfIakGYaLeHJUfY10';

define('GEMINI_API_KEY', $_ai_key);
define('GEMINI_MODEL', 'gemini-2.0-flash');
define('AI_ENABLED', !empty(GEMINI_API_KEY));
