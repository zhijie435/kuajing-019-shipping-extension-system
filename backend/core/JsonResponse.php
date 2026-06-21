<?php

class JsonResponse
{
    public static function success($data = null, string $message = '操作成功', int $code = 200): void
    {
        self::output([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error(string $message = '操作失败', int $code = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        self::output($response, $code);
    }

    public static function paginate(array $items, int $total, int $page, int $pageSize): void
    {
        self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => (int)ceil($total / $pageSize),
            ],
        ]);
    }

    private static function output(array $data, int $httpCode): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
