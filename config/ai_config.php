<?php
// =====================================================
// AI Assistant - Groq API Configuration (FREE)
// Lấy API key miễn phí tại: https://console.groq.com
// Free tier: 14,400 req/ngày, không hết hạn
// =====================================================

// ↓↓↓ PASTE GROQ API KEY VÀO ĐÂY ↓↓↓
$_ai_key = ''; // VD: 'gsk_...'
// ↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑

// Fallback: environment variable
if (empty($_ai_key)) {
    $_ai_key = getenv('GROQ_API_KEY') ?: '';
}

define('GROQ_API_KEY', $_ai_key);
define('GROQ_MODEL',   'llama-3.1-8b-instant'); // free, nhanh
define('AI_ENABLED', !empty(GROQ_API_KEY));
