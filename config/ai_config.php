<?php
// =====================================================
// AI Assistant - Google Gemini API Configuration
// Lấy API key miễn phí tại: https://aistudio.google.com/apikey
// =====================================================

// ↓↓↓ PASTE GEMINI API KEY VÀO ĐÂY ↓↓↓
$_ai_key = 'AIzaSyDe0boqG8ZRp3FedPV_Ut8uPZEkIAWmKWU'; // VD: 'AIzaSy...'
// ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

// Fallback: environment variable
if (empty($_ai_key)) {
    $_ai_key = getenv('GEMINI_API_KEY') ?: '';
}

define('GEMINI_API_KEY', $_ai_key);
define('GEMINI_MODEL',   'gemini-2.5-flash');
define('AI_ENABLED', !empty(GEMINI_API_KEY));
