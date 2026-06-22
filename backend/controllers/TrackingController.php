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

            case $action === 'rollback-time':
                $this->rollbackTrackingByTime($data);
                break;

            case $action === 'rollback-no':
                $this->rollbackTrackingByNo($data);
                break;

            case $action === 'rollback-logs':
                if ($method === 'GET') {
                    $this->listRollbackLogs($query);
                }
                break;

            case $action === 'simulate-callback':
                $this->simulateCallback($data);
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

    private function rollbackTrackingByTime(array $data): void
    {
        $carrierCode = $data['carrier_code'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime = $data['end_time'] ?? '';
        $operator = $data['operator'] ?? '';
        $remark = $data['remark'] ?? '';

        if (empty($carrierCode) || empty($startTime) || empty($endTime)) {
            JsonResponse::error('缺少必要参数: carrier_code, start_time, end_time', 400);
            return;
        }

        try {
            $count = $this->trackingService->rollbackTrackingByTimeRange($carrierCode, $startTime, $endTime, $operator, $remark);
            JsonResponse::success(['rollback_count' => $count], "轨迹回滚成功，共回滚 {$count} 条记录");
        } catch (Throwable $e) {
            JsonResponse::error('轨迹回滚失败: ' . $e->getMessage(), 500);
        }
    }

    private function rollbackTrackingByNo(array $data): void
    {
        $trackingNo = $data['tracking_no'] ?? '';
        $carrierCode = $data['carrier_code'] ?? '';
        $operator = $data['operator'] ?? '';
        $remark = $data['remark'] ?? '';

        if (empty($trackingNo)) {
            JsonResponse::error('缺少参数 tracking_no', 400);
            return;
        }

        try {
            $count = $this->trackingService->rollbackTrackingByNo($trackingNo, $carrierCode, $operator, $remark);
            JsonResponse::success(['rollback_count' => $count], "轨迹回滚成功，共回滚 {$count} 条记录");
        } catch (Throwable $e) {
            JsonResponse::error('轨迹回滚失败: ' . $e->getMessage(), 500);
        }
    }

    private function listRollbackLogs(array $query): void
    {
        try {
            $result = $this->trackingService->listRollbackLogs($query);
            JsonResponse::paginate($result['items'], $result['total'], $result['page'], $result['page_size']);
        } catch (Throwable $e) {
            JsonResponse::error('获取回滚日志失败: ' . $e->getMessage(), 500);
        }
    }

    private function simulateCallback(array $data): void
    {
        $carrierCode = $data['carrier_code'] ?? '';
        $testData = $data['test_data'] ?? [];

        if (empty($carrierCode) || empty($testData)) {
            JsonResponse::error('缺少参数 carrier_code 或 test_data', 400);
            return;
        }

        try {
            $result = $this->trackingService->simulateCallback($carrierCode, $testData);
            JsonResponse::success($result, $result['success'] ? '模拟回调成功' : '模拟回调失败');
        } catch (Throwable $e) {
            JsonResponse::error('模拟回调失败: ' . $e->getMessage(), 500);
        }
    }
}
