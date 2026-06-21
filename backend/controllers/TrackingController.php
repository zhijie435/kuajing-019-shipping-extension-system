<?php

class TrackingController
{
    private TrackingService $trackingService;

    public function __construct()
    {
        $this->trackingService = new TrackingService();
    }

    public function handle(string $action, ?string $param, array $data, array $query): void
    {
        $method = getRequestMethod();

        switch (true) {
            case ($action === '' || $action === 'index' || $action === 'events'):
                if ($method === 'GET') {
                    $this->listEvents($query);
                }
                break;

            case $action === 'callback' || $action === 'webhook':
                $carrierCode = $param ?? '';
                $this->handleCallback($carrierCode);
                break;

            case $action === 'detail':
                $trackingNo = $param ?? ($query['tracking_no'] ?? '');
                $this->getDetail($trackingNo);
                break;

            case $action === 'logs':
                if ($method === 'GET') {
                    $this->listCallbackLogs($query);
                }
                break;

            case $action === 'retry':
                $logId = (int)($param ?? 0);
                $this->retryCallback($logId);
                break;

            case $action === 'stats':
            case $action === 'statistics':
                $this->getStatistics($query);
                break;

            default:
                JsonResponse::error('未知的操作: ' . $action, 404);
        }
    }

    private function listEvents(array $query): void
    {
        $result = $this->trackingService->listTrackingEvents($query);
        JsonResponse::paginate($result['items'], $result['total'], $result['page'], $result['page_size']);
    }

    private function getDetail(string $trackingNo): void
    {
        if (empty($trackingNo)) {
            JsonResponse::error('缺少参数 tracking_no', 400);
            return;
        }

        $detail = $this->trackingService->getTrackingByNo($trackingNo);
        JsonResponse::success($detail);
    }

    private function handleCallback(string $carrierCode): void
    {
        if (empty($carrierCode)) {
            JsonResponse::error('缺少承运商编码', 400);
            return;
        }

        $body = file_get_contents('php://input');
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $ip = getClientIp();

        $result = $this->trackingService->handleCallback($carrierCode, $body, $headers, $ip);

        if ($result['success']) {
            JsonResponse::success($result, '回调处理成功');
        } else {
            JsonResponse::error('回调处理失败: ' . ($result['error'] ?? '未知错误'), 500);
        }
    }

    private function listCallbackLogs(array $query): void
    {
        $result = $this->trackingService->listCallbackLogs($query);
        JsonResponse::paginate($result['items'], $result['total'], $result['page'], $result['page_size']);
    }

    private function retryCallback(int $logId): void
    {
        if (empty($logId)) {
            JsonResponse::error('缺少参数 log_id', 400);
            return;
        }

        try {
            $result = $this->trackingService->retryFailedCallback($logId);
            JsonResponse::success(['retry_success' => $result]);
        } catch (Throwable $e) {
            JsonResponse::error('重试失败: ' . $e->getMessage(), 500);
        }
    }

    private function getStatistics(array $query): void
    {
        $stats = $this->trackingService->getTrackingStatistics($query);
        JsonResponse::success($stats);
    }
}
