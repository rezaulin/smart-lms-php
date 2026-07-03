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

// Simple router
$routes = require __DIR__ . '/../app/routes.php';

// Match route
$matched = false;
foreach ($routes as $route) {
    $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_-]+)', $route['path']);
    $pattern = "#^{$pattern}$#";

    if ($route['method'] === $requestMethod && preg_match($pattern, $requestUri, $matches)) {
        array_shift($matches); // Remove full match

        // Extract params
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $route['path'], $paramNames);
        $params = array_combine($paramNames[1] ?? [], $matches);

        // Call controller
        [$controller, $method] = explode('@', $route['handler']);
        $controllerClass = "App\\Controllers\\{$controller}";

        if (!class_exists($controllerClass)) {
            Response::error("Controller not found: {$controller}", 500);
        }

        $controllerInstance = new $controllerClass();
        if (!method_exists($controllerInstance, $method)) {
            Response::error("Method not found: {$method}", 500);
        }

        // Execute
        try {
            $controllerInstance->$method($params);
        } catch (Exception $e) {
            error_log("Controller error: " . $e->getMessage());
            Response::error('Internal server error', 500);
        }

        $matched = true;
        break;
    }
}

if (!$matched) {
    Response::notFound('Endpoint tidak ditemukan');
}
