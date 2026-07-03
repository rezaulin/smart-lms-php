<?php

// Autoload composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
use App\Config\Env;
use App\Config\Database;
use App\Middleware\CORS;
use App\Middleware\RateLimiter;
use App\Utils\Response;

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] ?? '1');

// Load .env
try {
    Env::load(__DIR__ . '/../.env');
} catch (Exception $e) {
    Response::error('Configuration error: ' . $e->getMessage(), 500);
}

// Set timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Asia/Jakarta');

// CORS
CORS::handle();

// Global rate limiter
RateLimiter::handleGlobal();

// Parse request
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');

// Load routes - routes.php handles everything internally
require_once __DIR__ . '/../routes.php';
