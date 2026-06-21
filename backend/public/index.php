<?php

spl_autoload_register(function ($class) {
    $coreDir = __DIR__ . '/core/';
    $serviceDir = __DIR__ . '/services/';
    $controllerDir = __DIR__ . '/controllers/';

    $coreFile = $coreDir . $class . '.php';
    $serviceFile = $serviceDir . $class . '.php';
    $controllerFile = $controllerDir . $class . '.php';

    if (file_exists($coreFile)) {
        require_once $coreFile;
    } elseif (file_exists($serviceFile)) {
        require_once $serviceFile;
    } elseif (file_exists($controllerFile)) {
        require_once $controllerFile;
    }
});

require_once __DIR__ . '/core/Constants.php';

function getRequestMethod(): string
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function getRequestData(): array
{
    $input = file_get_contents('php://input');
    $json = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }
    return array_merge($_GET, $_POST);
}

function getQueryParams(): array
{
    return $_GET ?? [];
}

function getClientIp(): string
{
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

function route(string $uri): void
{
    if (getRequestMethod() === 'OPTIONS') {
        JsonResponse::success();
    }

    $globalConfig = require __DIR__ . '/config/config.php';
    if (!empty($globalConfig['global']['maintenance_mode'])) {
        JsonResponse::error('系统维护中', 503);
    }

    if (empty($globalConfig['global']['api_enabled'])) {
        JsonResponse::error('API服务已关闭', 503);
    }

    $uri = parse_url($uri, PHP_URL_PATH) ?? '';
    $uri = preg_replace('#^/api/?#', '', $uri);
    $uri = trim($uri, '/');
    $parts = explode('/', $uri);

    try {
        if (empty($parts[0])) {
            JsonResponse::success(['version' => '1.0.0', 'name' => '物流扩展体系 API']);
            return;
        }

        $module = $parts[0];
        $action = $parts[1] ?? 'index';
        $param = $parts[2] ?? null;

        switch ($module) {
            case 'carriers':
                $controller = new CarrierController();
                $controller->handle($action, $param, getRequestData(), getQueryParams());
                break;

            case 'tracking':
                $controller = new TrackingController();
                $controller->handle($action, $param, getRequestData(), getQueryParams());
                break;

            case 'extension':
                $controller = new ExtensionConfigController();
                $controller->handle($action, $param, getRequestData(), getQueryParams());
                break;

            case 'meta':
                $controller = new MetaController();
                $controller->handle($action, $param, getRequestData(), getQueryParams());
                break;

            default:
                JsonResponse::error('接口不存在: ' . $module, 404);
        }
    } catch (Throwable $e) {
        $logger = new Logger();
        $logger->error('请求异常', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        JsonResponse::error('服务器内部错误: ' . $e->getMessage(), 500);
    }
}

route($_SERVER['REQUEST_URI'] ?? '/');
