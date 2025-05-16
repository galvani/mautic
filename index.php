<?php

use Mautic\CoreBundle\ErrorHandler\ErrorHandler;
use Mautic\Middleware\MiddlewareBuilder;
use Symfony\Component\HttpFoundation\Request;

// Define constants
if (!defined('MAUTIC_ROOT_DIR')) {
    define('MAUTIC_ROOT_DIR', __DIR__);
}
if (!defined('ELFINDER_IMG_PARENT_URL')) {
    define('ELFINDER_IMG_PARENT_URL', 'media/bundles/fmelfinder');
}

// Fix for hosts that do not have date.timezone set
date_default_timezone_set('UTC');

// Create .env.local.php if it doesn't exist
if (!file_exists(__DIR__ . '/.env.local.php')) {
    file_put_contents(__DIR__ . '/.env.local.php', '<?php return [];');
}

// Use your existing bootstrap path
require 'app/config/bootstrap.php';

// Set up error handler
ErrorHandler::register($_SERVER['APP_ENV']);

// Process the request
$kernel = (new MiddlewareBuilder(new AppKernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG'])))->resolve();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

// FrankenPHP worker mode
if (function_exists('frankenphp_handle_request')) {
    frankenphp_handle_request(function () {
        try {
            // Ensure .env.local.php exists
            if (!file_exists(__DIR__ . '/.env.local.php')) {
                file_put_contents(__DIR__ . '/.env.local.php', '<?php return [];');
            }

            // Load bootstrap with your existing path
            require 'app/config/bootstrap.php';

            // Set up the application with your existing structure
            $errorHandler = new \Mautic\CoreBundle\ErrorHandler\ErrorHandler();
            $errorHandler::register($_SERVER['APP_ENV']);

            $kernel = (new \Mautic\Middleware\MiddlewareBuilder(new AppKernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG'])))->resolve();
            $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
            $response = $kernel->handle($request);
            $response->send();
            $kernel->terminate($request, $response);

            return true;
        } catch (\Throwable $e) {
            error_log('Error in FrankenPHP worker: ' . $e->getMessage());
            header('Content-Type: text/plain', true, 500);
            echo 'Internal Server Error: ' . $e->getMessage();
            return false;
        }
    });
}