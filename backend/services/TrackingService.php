<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../services/CarrierAdapter.php';

class TrackingService
{
    private PDO $db;
    private Logger $logger;
    private array $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        $this->config = require __DIR__ . '/../config/config.php';
    }

    public function handleCallback(string $carrierCode, string $requestBody, array $headers, string $ipAddress = ''): array
    {
        $startTime = microtime(true);
        $logId = $this->logCallback($carrierCode, $requestBody, $headers, $ipAddress);

        try {
            if (!$this->config['tracking']['callback_enabled']) {
                throw new RuntimeException('轨迹回调功能未启用');
            }

            if ($this->config['tracking']['callback_auth_enabled'] && !$this->validateIp($ipAddress)) {
                throw new RuntimeException('IP地址未授权: ' . $ipAddress);
            }

            $adapter = CarrierAdapterFactory::create($carrierCode);

            if ($this->config['tracking']['callback_auth_enabled'] && !$adapter->validateCallbackSignature($headers, $requestBody)) {
                throw new RuntimeException('回调签名验证失败');
            }

            $parsedData = $adapter->parseCallbackData($requestBody);

            if (empty($parsedData['tracking_no'])) {
                throw new RuntimeException('缺少运单号');
            }

            $carrierId = $this->getCarrierId($carrierCode);
            if (!$carrierId) {
                throw new RuntimeException('承运商不存在: ' . $carrierCode);
            }

            $this->db->beginTransaction();

            try {
                $savedCount = 0;
                $skippedCount = 0;

                foreach ($parsedData['events'] as $event) {
                    $result = $this->saveTrackingEvent($carrierCode, $carrierId, $parsedData['tracking_no'], $event);
                    if ($result === 'saved') {
                        $savedCount++;
                    } else {
                        $skippedCount++;
                    }
                }

                $this->db->commit();
            } catch (Exception $innerEx) {
                $this->db->rollBack();
                throw $innerEx;
            }

            if ($this->config['tracking']['auto_sync_enabled']) {
                $this->syncToOrder($parsedData['tracking_no'], $carrierCode);
            }

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->updateCallbackLog($logId, 1, null, $processingTime);

            $this->logger->info('轨迹回调处理成功', [
                'carrier_code' => $carrierCode,
                'tracking_no' => $parsedData['tracking_no'],
                'saved' => $savedCount,
                'skipped' => $skippedCount,
            ]);

            return [
                'success' => true,
                'tracking_no' => $parsedData['tracking_no'],
                'saved_count' => $savedCount,
                'skipped_count' => $skippedCount,
            ];
        } catch (Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->updateCallbackLog($logId, 2, $e->getMessage(), $processingTime);

            $this->logger->error('轨迹回调处理失败', [
                'carrier_code' => $carrierCode,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function listTrackingEvents(array $params = []): array
    {
        $where = ['1=1'];
        $binds = [];

        if (!empty($params['tracking_no'])) {
            $where[] = 'te.tracking_no = :tracking_no';
            $binds[':tracking_no'] = $params['tracking_no'];
        }

        if (!empty($params['carrier_code'])) {
            $where[] = 'te.carrier_code = :carrier_code';
            $binds[':carrier_code'] = $params['carrier_code'];
        }

        if (!empty($params['standard_status'])) {
            $where[] = 'te.standard_status = :standard_status';
            $binds[':standard_status'] = $params['standard_status'];
        }

        if (!empty($params['order_no'])) {
            $where[] = 'te.order_no = :order_no';
            $binds[':order_no'] = $params['order_no'];
        }

        if (isset($params['is_synced']) && $params['is_synced'] !== '') {
            $where[] = 'te.is_synced = :is_synced';
            $binds[':is_synced'] = (int)$params['is_synced'];
        }

        if (!empty($params['event_time_start'])) {
            $where[] = 'te.event_time >= :event_time_start';
            $binds[':event_time_start'] = $params['event_time_start'];
        }

        if (!empty($params['event_time_end'])) {
            $where[] = 'te.event_time <= :event_time_end';
            $binds[':event_time_end'] = $params['event_time_end'];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM tracking_events te WHERE {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($binds);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT te.*, c.carrier_name 
                FROM tracking_events te 
                LEFT JOIN carriers c ON te.carrier_id = c.id 
                WHERE {$whereSql} 
                ORDER BY te.event_time DESC 
                LIMIT {$offset}, {$pageSize}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $items = $stmt->fetchAll();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function getTrackingByNo(string $trackingNo): array
    {
        $stmt = $this->db->prepare(
            "SELECT te.*, c.carrier_name, c.carrier_type 
             FROM tracking_events te 
             LEFT JOIN carriers c ON te.carrier_id = c.id 
             WHERE te.tracking_no = :tracking_no 
             ORDER BY te.event_time ASC"
        );
        $stmt->execute([':tracking_no' => $trackingNo]);
        $events = $stmt->fetchAll();

        $latestEvent = !empty($events) ? $events[count($events) - 1] : null;

        return [
            'tracking_no' => $trackingNo,
            'events' => $events,
            'event_count' => count($events),
            'latest_status' => $latestEvent ? TrackingStandardStatus::getLabel($latestEvent['standard_status']) : '',
            'latest_event' => $latestEvent,
        ];
    }

    public function listCallbackLogs(array $params = []): array
    {
        $where = ['1=1'];
        $binds = [];

        if (!empty($params['carrier_code'])) {
            $where[] = 'carrier_code = :carrier_code';
            $binds[':carrier_code'] = $params['carrier_code'];
        }

        if (isset($params['process_status']) && $params['process_status'] !== '') {
            $where[] = 'process_status = :process_status';
            $binds[':process_status'] = (int)$params['process_status'];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM tracking_callback_logs WHERE {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($binds);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT id, carrier_code, request_method, request_url, process_status, error_message, retry_count, ip_address, processing_time_ms, created_at 
                FROM tracking_callback_logs 
                WHERE {$whereSql} 
                ORDER BY created_at DESC 
                LIMIT {$offset}, {$pageSize}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $items = $stmt->fetchAll();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function retryFailedCallback(int $logId): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM tracking_callback_logs WHERE id = :id AND process_status IN (2, 3)");
        $stmt->execute([':id' => $logId]);
        $log = $stmt->fetch();

        if (!$log) {
            throw new RuntimeException('回调日志不存在或状态不允许重试');
        }

        $maxRetry = $this->config['tracking']['callback_max_retry'];
        if ($log['retry_count'] >= $maxRetry) {
            throw new RuntimeException('已达到最大重试次数');
        }

        $this->db->prepare(
            "UPDATE tracking_callback_logs SET process_status = 3, retry_count = retry_count + 1 WHERE id = :id"
        )->execute([':id' => $logId]);

        $result = $this->handleCallback(
            $log['carrier_code'],
            $log['request_body'] ?? '',
            (array)($log['request_headers'] ?? []),
            $log['ip_address']
        );

        return $result['success'];
    }

    public function getTrackingStatistics(array $params = []): array
    {
        $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo = $params['date_to'] ?? date('Y-m-d');

        $stmt = $this->db->prepare(
            "SELECT standard_status, COUNT(*) as cnt 
             FROM tracking_events 
             WHERE DATE(event_time) BETWEEN :date_from AND :date_to 
             GROUP BY standard_status"
        );
        $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $statusDist = [];
        foreach ($stmt->fetchAll() as $row) {
            $statusDist[$row['standard_status']] = (int)$row['cnt'];
        }

        $stmt = $this->db->prepare(
            "SELECT carrier_code, COUNT(*) as cnt 
             FROM tracking_events 
             WHERE DATE(event_time) BETWEEN :date_from AND :date_to 
             GROUP BY carrier_code 
             ORDER BY cnt DESC"
        );
        $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $carrierDist = $stmt->fetchAll();

        $stmt = $this->db->prepare(
            "SELECT process_status, COUNT(*) as cnt 
             FROM tracking_callback_logs 
             WHERE DATE(created_at) BETWEEN :date_from AND :date_to 
             GROUP BY process_status"
        );
        $stmt->execute([':date_from' => $dateFrom, ':date_to' => $dateTo]);
        $callbackStats = [];
        foreach ($stmt->fetchAll() as $row) {
            $callbackStats[$row['process_status']] = (int)$row['cnt'];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM tracking_events WHERE is_synced = 0"
        );
        $stmt->execute();
        $unsyncedCount = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM tracking_callback_logs WHERE process_status = 2"
        );
        $stmt->execute();
        $failedCallbackCount = (int)$stmt->fetchColumn();

        return [
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'status_distribution' => $statusDist,
            'carrier_distribution' => $carrierDist,
            'callback_stats' => $callbackStats,
            'unsynced_count' => $unsyncedCount,
            'failed_callback_count' => $failedCallbackCount,
        ];
    }

    private function saveTrackingEvent(string $carrierCode, int $carrierId, string $trackingNo, array $event): string
    {
        $eventTime = $event['event_time'] ?? date('Y-m-d H:i:s');
        $eventCode = $event['event_code'] ?? '';

        if ($this->config['tracking']['event_dedup_enabled']) {
            $stmt = $this->db->prepare(
                "SELECT id FROM tracking_events 
                 WHERE tracking_no = :tracking_no AND carrier_code = :carrier_code 
                 AND event_code = :event_code AND event_time = :event_time"
            );
            $stmt->execute([
                ':tracking_no' => $trackingNo,
                ':carrier_code' => $carrierCode,
                ':event_code' => $eventCode,
                ':event_time' => $eventTime,
            ]);
            if ($stmt->fetch()) {
                return 'skipped';
            }
        }

        $standardStatus = $this->mapToStandardStatus($carrierCode, $eventCode);

        $stmt = $this->db->prepare(
            "INSERT INTO tracking_events (tracking_no, carrier_code, carrier_id, event_code, event_desc, event_time, event_location, event_country, standard_status, raw_data, is_synced) 
             VALUES (:tracking_no, :carrier_code, :carrier_id, :event_code, :event_desc, :event_time, :event_location, :event_country, :standard_status, :raw_data, 0)"
        );
        $stmt->execute([
            ':tracking_no' => $trackingNo,
            ':carrier_code' => $carrierCode,
            ':carrier_id' => $carrierId,
            ':event_code' => $eventCode,
            ':event_desc' => $event['event_desc'] ?? '',
            ':event_time' => $eventTime,
            ':event_location' => $event['event_location'] ?? '',
            ':event_country' => $event['event_country'] ?? '',
            ':standard_status' => $standardStatus,
            ':raw_data' => json_encode($event, JSON_UNESCAPED_UNICODE),
        ]);

        if ($standardStatus === TrackingStandardStatus::EXCEPTION) {
            $this->notifyException($trackingNo, $carrierCode, $event);
        }

        return 'saved';
    }

    private function mapToStandardStatus(string $carrierCode, string $eventCode): string
    {
        $stmt = $this->db->prepare(
            "SELECT standard_status FROM tracking_status_mappings 
             WHERE carrier_code = :carrier_code AND carrier_event_code = :event_code AND status = 1 
             ORDER BY priority DESC LIMIT 1"
        );
        $stmt->execute([':carrier_code' => $carrierCode, ':event_code' => $eventCode]);
        $mapping = $stmt->fetchColumn();

        return $mapping ?: TrackingStandardStatus::UNKNOWN;
    }

    private function syncToOrder(string $trackingNo, string $carrierCode = ''): void
    {
        $stmt = $this->db->prepare(
            "UPDATE tracking_events SET is_synced = 1, sync_time = NOW() 
             WHERE tracking_no = :tracking_no AND is_synced = 0"
        );
        $stmt->execute([':tracking_no' => $trackingNo]);

        $this->updateLatestStatusToOrder($trackingNo, $carrierCode);
    }

    private function updateLatestStatusToOrder(string $trackingNo, string $carrierCode = ''): void
    {
        $sql = "SELECT standard_status, event_time FROM tracking_events 
                WHERE tracking_no = :tracking_no";
        $binds = [':tracking_no' => $trackingNo];

        if (!empty($carrierCode)) {
            $sql .= " AND carrier_code = :carrier_code";
            $binds[':carrier_code'] = $carrierCode;
        }

        $sql .= " ORDER BY event_time DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $latestEvent = $stmt->fetch();

        if (!$latestEvent) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE tracking_events SET order_status = :status 
             WHERE tracking_no = :tracking_no AND is_synced = 1"
        );
        $stmt->execute([
            ':status' => $latestEvent['standard_status'],
            ':tracking_no' => $trackingNo,
        ]);

        $this->logger->info('轨迹状态回写订单成功', [
            'tracking_no' => $trackingNo,
            'latest_status' => $latestEvent['standard_status'],
        ]);
    }

    private function logCallback(string $carrierCode, string $body, array $headers, string $ip): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tracking_callback_logs (carrier_code, request_method, request_url, request_headers, request_body, process_status, ip_address) 
             VALUES (:carrier_code, :method, :url, :headers, :body, 0, :ip)"
        );
        $stmt->execute([
            ':carrier_code' => $carrierCode,
            ':method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            ':url' => $_SERVER['REQUEST_URI'] ?? '',
            ':headers' => json_encode($headers),
            ':body' => $body,
            ':ip' => $ip,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function updateCallbackLog(int $logId, int $status, ?string $error = null, ?float $processingTime = null): void
    {
        $sql = "UPDATE tracking_callback_logs SET process_status = :status";
        $binds = [':id' => $logId, ':status' => $status];

        if ($error !== null) {
            $sql .= ", error_message = :error";
            $binds[':error'] = $error;
        }

        if ($processingTime !== null) {
            $sql .= ", processing_time_ms = :time";
            $binds[':time'] = (int)$processingTime;
        }

        $sql .= " WHERE id = :id";
        $this->db->prepare($sql)->execute($binds);
    }

    private function validateIp(string $ip): bool
    {
        if (empty($ip)) {
            return true;
        }

        $whitelist = explode(',', $this->config['tracking']['callback_ip_whitelist']);
        $whitelist = array_map('trim', $whitelist);

        foreach ($whitelist as $allowed) {
            if ($allowed === '*' || $allowed === $ip) {
                return true;
            }

            if (strpos($allowed, '/') !== false) {
                [$subnet, $bits] = explode('/', $allowed);
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $mask = -1 << (32 - (int)$bits);
                if (($ipLong & $mask) === ($subnetLong & $mask)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getCarrierId(string $carrierCode): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM carriers WHERE carrier_code = :code");
        $stmt->execute([':code' => $carrierCode]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function notifyException(string $trackingNo, string $carrierCode, array $event): void
    {
        if (!$this->config['notification']['tracking_exception_enabled']) {
            return;
        }

        $this->logger->warning('轨迹异常通知', [
            'tracking_no' => $trackingNo,
            'carrier_code' => $carrierCode,
            'event' => $event,
            'notify_email' => $this->config['notification']['tracking_exception_email'],
        ]);
    }

    public function rollbackTrackingByTimeRange(string $carrierCode, string $startTime, string $endTime, string $operator = '', string $remark = ''): int
    {
        if (empty($carrierCode)) {
            throw new RuntimeException('承运商编码不能为空');
        }
        if (empty($startTime) || empty($endTime)) {
            throw new RuntimeException('时间范围不能为空');
        }

        $carrierId = $this->getCarrierId($carrierCode);
        if (!$carrierId) {
            throw new RuntimeException('承运商不存在: ' . $carrierCode);
        }

        $this->db->beginTransaction();
        try {
            $logId = $this->createRollbackLog($carrierCode, $carrierId, 'time_range', [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ], $operator, $remark);

            $this->updateRollbackLogStatus($logId, 1);

            $countStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM tracking_events 
                 WHERE carrier_code = :carrier_code 
                 AND event_time BETWEEN :start_time AND :end_time"
            );
            $countStmt->execute([
                ':carrier_code' => $carrierCode,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
            ]);
            $rollbackCount = (int)$countStmt->fetchColumn();

            $deleteStmt = $this->db->prepare(
                "DELETE FROM tracking_events 
                 WHERE carrier_code = :carrier_code 
                 AND event_time BETWEEN :start_time AND :end_time"
            );
            $deleteStmt->execute([
                ':carrier_code' => $carrierCode,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
            ]);

            $this->updateRollbackLogStatus($logId, 2, null, $rollbackCount);

            $this->db->commit();
            $this->logger->info('轨迹数据回滚成功', [
                'carrier_code' => $carrierCode,
                'rollback_count' => $rollbackCount,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'operator' => $operator,
            ]);

            return $rollbackCount;
        } catch (Exception $e) {
            $this->db->rollBack();
            if (isset($logId)) {
                $this->updateRollbackLogStatus($logId, 3, $e->getMessage());
            }
            $this->logger->error('轨迹数据回滚失败', [
                'carrier_code' => $carrierCode,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function rollbackTrackingByNo(string $trackingNo, string $carrierCode = '', string $operator = '', string $remark = ''): int
    {
        if (empty($trackingNo)) {
            throw new RuntimeException('运单号不能为空');
        }

        $this->db->beginTransaction();
        try {
            $where = 'tracking_no = :tracking_no';
            $binds = [':tracking_no' => $trackingNo];

            if (!empty($carrierCode)) {
                $where .= ' AND carrier_code = :carrier_code';
                $binds[':carrier_code'] = $carrierCode;
            }

            $carrierId = null;
            if (!empty($carrierCode)) {
                $carrierId = $this->getCarrierId($carrierCode);
            }

            $logId = $this->createRollbackLog($carrierCode, $carrierId ?: 0, 'tracking_no', [
                'tracking_no' => $trackingNo,
            ], $operator, $remark);

            $this->updateRollbackLogStatus($logId, 1);

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM tracking_events WHERE {$where}");
            $countStmt->execute($binds);
            $rollbackCount = (int)$countStmt->fetchColumn();

            $deleteStmt = $this->db->prepare("DELETE FROM tracking_events WHERE {$where}");
            $deleteStmt->execute($binds);

            $this->updateRollbackLogStatus($logId, 2, null, $rollbackCount);

            $this->db->commit();
            $this->logger->info('轨迹数据回滚成功', [
                'tracking_no' => $trackingNo,
                'carrier_code' => $carrierCode,
                'rollback_count' => $rollbackCount,
                'operator' => $operator,
            ]);

            return $rollbackCount;
        } catch (Exception $e) {
            $this->db->rollBack();
            if (isset($logId)) {
                $this->updateRollbackLogStatus($logId, 3, $e->getMessage());
            }
            $this->logger->error('轨迹数据回滚失败', [
                'tracking_no' => $trackingNo,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function listRollbackLogs(array $params = []): array
    {
        $where = ['1=1'];
        $binds = [];

        if (!empty($params['carrier_code'])) {
            $where[] = 'carrier_code = :carrier_code';
            $binds[':carrier_code'] = $params['carrier_code'];
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = 'status = :status';
            $binds[':status'] = (int)$params['status'];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM tracking_rollback_logs WHERE {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($binds);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT id, carrier_code, rollback_type, rollback_count, status, operator, remark, error_message, created_at, finished_at 
                FROM tracking_rollback_logs 
                WHERE {$whereSql} 
                ORDER BY created_at DESC 
                LIMIT {$offset}, {$pageSize}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            if (!empty($item['rollback_scope'])) {
                $item['rollback_scope'] = json_decode($item['rollback_scope'], true);
            }
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function simulateCallback(string $carrierCode, array $testData): array
    {
        if (empty($carrierCode)) {
            throw new RuntimeException('承运商编码不能为空');
        }

        $carrierId = $this->getCarrierId($carrierCode);
        if (!$carrierId) {
            throw new RuntimeException('承运商不存在: ' . $carrierCode);
        }

        $adapter = CarrierAdapterFactory::create($carrierCode);

        $body = json_encode($testData, JSON_UNESCAPED_UNICODE);

        try {
            $parsedData = $adapter->parseCallbackData($body);

            $isValid = !empty($parsedData['tracking_no']) && !empty($parsedData['events']);

            return [
                'success' => $isValid,
                'carrier_code' => $carrierCode,
                'parsed_data' => $parsedData,
                'raw_body' => $body,
                'event_count' => count($parsedData['events'] ?? []),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'carrier_code' => $carrierCode,
                'error' => $e->getMessage(),
                'raw_body' => $body,
            ];
        }
    }

    private function createRollbackLog(string $carrierCode, int $carrierId, string $rollbackType, array $scope, string $operator = '', string $remark = ''): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tracking_rollback_logs (carrier_code, carrier_id, rollback_type, rollback_scope, status, operator, remark) 
             VALUES (:carrier_code, :carrier_id, :rollback_type, :scope, 0, :operator, :remark)"
        );
        $stmt->execute([
            ':carrier_code' => $carrierCode,
            ':carrier_id' => $carrierId,
            ':rollback_type' => $rollbackType,
            ':scope' => json_encode($scope, JSON_UNESCAPED_UNICODE),
            ':operator' => $operator,
            ':remark' => $remark,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function updateRollbackLogStatus(int $logId, int $status, ?string $error = null, ?int $rollbackCount = null): void
    {
        $sql = "UPDATE tracking_rollback_logs SET status = :status";
        $binds = [':id' => $logId, ':status' => $status];

        if ($error !== null) {
            $sql .= ", error_message = :error";
            $binds[':error'] = $error;
        }

        if ($rollbackCount !== null) {
            $sql .= ", rollback_count = :count";
            $binds[':count'] = $rollbackCount;
        }

        if ($status == 2 || $status == 3) {
            $sql .= ", finished_at = NOW()";
        }

        $sql .= " WHERE id = :id";
        $this->db->prepare($sql)->execute($binds);
    }
}
