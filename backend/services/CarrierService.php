<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';

class CarrierService
{
    private PDO $db;
    private Logger $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }

    public function listCarriers(array $params = []): array
    {
        $where = ['1=1'];
        $binds = [];

        if (!empty($params['status'])) {
            $where[] = 'c.status = :status';
            $binds[':status'] = (int)$params['status'];
        }

        if (!empty($params['carrier_type'])) {
            $where[] = 'c.carrier_type = :carrier_type';
            $binds[':carrier_type'] = (int)$params['carrier_type'];
        }

        if (!empty($params['keyword'])) {
            $where[] = '(c.carrier_code LIKE :kw OR c.carrier_name LIKE :kw2)';
            $binds[':kw'] = '%' . $params['keyword'] . '%';
            $binds[':kw2'] = '%' . $params['keyword'] . '%';
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM carriers c WHERE {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($binds);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT c.*, 
                       cc.protocol_type, cc.api_base_url, cc.auth_type, cc.callback_url, cc.status as config_status,
                       (SELECT COUNT(*) FROM carrier_products cp WHERE cp.carrier_id = c.id AND cp.status = 1) as product_count
                FROM carriers c 
                LEFT JOIN carrier_configs cc ON c.id = cc.carrier_id
                WHERE {$whereSql} 
                ORDER BY c.priority DESC, c.created_at DESC 
                LIMIT {$offset}, {$pageSize}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $items = $stmt->fetchAll();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function getCarrier(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, cc.* 
             FROM carriers c 
             LEFT JOIN carrier_configs cc ON c.id = cc.carrier_id 
             WHERE c.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $carrier = $stmt->fetch();

        if (!$carrier) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM carrier_products WHERE carrier_id = :id ORDER BY created_at DESC");
        $stmt->execute([':id' => $id]);
        $carrier['products'] = $stmt->fetchAll();

        return $carrier;
    }

    public function createCarrier(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO carriers (carrier_code, carrier_name, carrier_type, logo_url, contact_name, contact_phone, contact_email, country, supported_regions, status, priority, remark) 
                 VALUES (:carrier_code, :carrier_name, :carrier_type, :logo_url, :contact_name, :contact_phone, :contact_email, :country, :supported_regions, :status, :priority, :remark)"
            );
            $stmt->execute([
                ':carrier_code' => $data['carrier_code'],
                ':carrier_name' => $data['carrier_name'],
                ':carrier_type' => $data['carrier_type'] ?? CarrierType::EXPRESS,
                ':logo_url' => $data['logo_url'] ?? '',
                ':contact_name' => $data['contact_name'] ?? '',
                ':contact_phone' => $data['contact_phone'] ?? '',
                ':contact_email' => $data['contact_email'] ?? '',
                ':country' => $data['country'] ?? '',
                ':supported_regions' => isset($data['supported_regions']) ? json_encode($data['supported_regions']) : null,
                ':status' => $data['status'] ?? CarrierStatus::DISABLED,
                ':priority' => $data['priority'] ?? 0,
                ':remark' => $data['remark'] ?? '',
            ]);

            $carrierId = (int)$this->db->lastInsertId();

            if (!empty($data['config'])) {
                $this->upsertCarrierConfig($carrierId, $data['config']);
            }

            $this->db->commit();

            try {
                $this->saveConfigHistory($carrierId, 'create', $data['operator'] ?? '', '创建承运商');
            } catch (Exception $e) {
                $this->logger->warning('保存配置历史失败', ['carrier_id' => $carrierId, 'error' => $e->getMessage()]);
            }

            $this->logger->info('承运商创建成功', ['carrier_id' => $carrierId, 'carrier_code' => $data['carrier_code']]);
            return $carrierId;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('承运商创建失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateCarrier(int $id, array $data): bool
    {
        $carrier = $this->getCarrier($id);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $this->db->beginTransaction();
        try {
            $fields = [];
            $binds = [':id' => $id];

            $allowFields = ['carrier_name', 'carrier_type', 'logo_url', 'contact_name', 'contact_phone', 'contact_email', 'country', 'supported_regions', 'priority', 'remark'];

            foreach ($allowFields as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = :{$field}";
                    $binds[":{$field}"] = $field === 'supported_regions' && is_array($data[$field])
                        ? json_encode($data[$field])
                        : $data[$field];
                }
            }

            if (!empty($fields)) {
                $sql = "UPDATE carriers SET " . implode(', ', $fields) . " WHERE id = :id";
                $this->db->prepare($sql)->execute($binds);
            }

            if (!empty($data['config'])) {
                $this->upsertCarrierConfig($id, $data['config']);
            }

            $this->db->commit();

            try {
                $this->saveConfigHistory($id, 'update', $data['operator'] ?? '', $data['change_remark'] ?? '更新承运商配置');
            } catch (Exception $e) {
                $this->logger->warning('保存配置历史失败', ['carrier_id' => $id, 'error' => $e->getMessage()]);
            }

            $this->logger->info('承运商更新成功', ['carrier_id' => $id]);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('承运商更新失败', ['carrier_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function updateCarrierStatus(int $id, int $status): bool
    {
        $carrier = $this->getCarrier($id);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $stmt = $this->db->prepare("UPDATE carriers SET status = :status WHERE id = :id");
        $result = $stmt->execute([':status' => $status, ':id' => $id]);

        $this->logger->info('承运商状态变更', ['carrier_id' => $id, 'status' => $status]);
        return $result;
    }

    public function deleteCarrier(int $id): bool
    {
        $carrier = $this->getCarrier($id);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM carrier_products WHERE carrier_id = :id")->execute([':id' => $id]);
            $this->db->prepare("DELETE FROM carrier_configs WHERE carrier_id = :id")->execute([':id' => $id]);
            $this->db->prepare("DELETE FROM carriers WHERE id = :id")->execute([':id' => $id]);

            $this->db->commit();
            $this->logger->info('承运商删除成功', ['carrier_id' => $id]);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('承运商删除失败', ['carrier_id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getCarrierProducts(int $carrierId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM carrier_products WHERE carrier_id = :carrier_id ORDER BY created_at DESC");
        $stmt->execute([':carrier_id' => $carrierId]);
        return $stmt->fetchAll();
    }

    public function createCarrierProduct(int $carrierId, array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO carrier_products (carrier_id, product_code, product_name, service_type, delivery_days_min, delivery_days_max, weight_limit_kg, supported_countries, price_config, status) 
             VALUES (:carrier_id, :product_code, :product_name, :service_type, :delivery_days_min, :delivery_days_max, :weight_limit_kg, :supported_countries, :price_config, :status)"
        );
        $stmt->execute([
            ':carrier_id' => $carrierId,
            ':product_code' => $data['product_code'],
            ':product_name' => $data['product_name'],
            ':service_type' => $data['service_type'] ?? ServiceType::STANDARD,
            ':delivery_days_min' => $data['delivery_days_min'] ?? null,
            ':delivery_days_max' => $data['delivery_days_max'] ?? null,
            ':weight_limit_kg' => $data['weight_limit_kg'] ?? null,
            ':supported_countries' => isset($data['supported_countries']) ? json_encode($data['supported_countries']) : null,
            ':price_config' => isset($data['price_config']) ? json_encode($data['price_config']) : null,
            ':status' => $data['status'] ?? 1,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function deleteCarrierProduct(int $productId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM carrier_products WHERE id = :id");
        return $stmt->execute([':id' => $productId]);
    }

    private function upsertCarrierConfig(int $carrierId, array $config): void
    {
        $existing = $this->db->prepare("SELECT id FROM carrier_configs WHERE carrier_id = :carrier_id");
        $existing->execute([':carrier_id' => $carrierId]);
        $existingRow = $existing->fetch();

        $data = [
            ':carrier_id' => $carrierId,
            ':protocol_type' => $config['protocol_type'] ?? ProtocolType::HTTP,
            ':api_base_url' => $config['api_base_url'] ?? '',
            ':auth_type' => $config['auth_type'] ?? AuthType::API_KEY,
            ':api_key' => $config['api_key'] ?? '',
            ':api_secret' => $config['api_secret'] ?? '',
            ':auth_token' => $config['auth_token'] ?? '',
            ':callback_url' => $config['callback_url'] ?? '',
            ':callback_secret' => $config['callback_secret'] ?? '',
            ':timeout_seconds' => $config['timeout_seconds'] ?? 30,
            ':retry_times' => $config['retry_times'] ?? 3,
            ':rate_limit' => $config['rate_limit'] ?? 100,
            ':extra_config' => isset($config['extra_config']) ? json_encode($config['extra_config']) : null,
            ':status' => $config['status'] ?? 1,
        ];

        if ($existingRow) {
            $data[':id'] = $existingRow['id'];
            $sql = "UPDATE carrier_configs SET 
                    protocol_type = :protocol_type, api_base_url = :api_base_url, auth_type = :auth_type,
                    api_key = :api_key, api_secret = :api_secret, auth_token = :auth_token,
                    callback_url = :callback_url, callback_secret = :callback_secret,
                    timeout_seconds = :timeout_seconds, retry_times = :retry_times, rate_limit = :rate_limit,
                    extra_config = :extra_config, status = :status 
                    WHERE id = :id";
        } else {
            $sql = "INSERT INTO carrier_configs (carrier_id, protocol_type, api_base_url, auth_type, api_key, api_secret, auth_token, callback_url, callback_secret, timeout_seconds, retry_times, rate_limit, extra_config, status) 
                    VALUES (:carrier_id, :protocol_type, :api_base_url, :auth_type, :api_key, :api_secret, :auth_token, :callback_url, :callback_secret, :timeout_seconds, :retry_times, :rate_limit, :extra_config, :status)";
        }

        $this->db->prepare($sql)->execute($data);
    }

    public function healthCheck(int $carrierId): array
    {
        $carrier = $this->getCarrier($carrierId);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $startTime = microtime(true);
        $healthy = false;
        $errorMessage = '';

        try {
            if (empty($carrier['api_base_url'])) {
                throw new RuntimeException('未配置API地址');
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => rtrim($carrier['api_base_url'], '/') . '/health',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $carrier['timeout_seconds'] ?? 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new RuntimeException("CURL错误: {$curlError}");
            }

            $healthy = $httpCode >= 200 && $httpCode < 300;
            if (!$healthy) {
                $errorMessage = "HTTP状态码: {$httpCode}";
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }

        $latency = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'carrier_id' => $carrierId,
            'carrier_code' => $carrier['carrier_code'],
            'healthy' => $healthy,
            'latency_ms' => $latency,
            'error_message' => $errorMessage,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function linkageCheck(int $carrierId): array
    {
        $carrier = $this->getCarrier($carrierId);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $checks = [];
        $allPassed = true;

        $checks[] = $this->checkBasicConfig($carrier);
        $checks[] = $this->checkApiConfig($carrier);
        $checks[] = $this->checkCallbackConfig($carrier);
        $checks[] = $this->checkStatusMapping($carrier);
        $checks[] = $this->checkAdapter($carrier);
        $checks[] = $this->checkProducts($carrier);

        foreach ($checks as $check) {
            if (!$check['passed']) {
                $allPassed = false;
                break;
            }
        }

        $passedCount = count(array_filter($checks, fn($c) => $c['passed']));

        return [
            'carrier_id' => $carrierId,
            'carrier_code' => $carrier['carrier_code'],
            'carrier_name' => $carrier['carrier_name'],
            'all_passed' => $allPassed,
            'passed_count' => $passedCount,
            'total_count' => count($checks),
            'checks' => $checks,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function checkBasicConfig(array $carrier): array
    {
        $errors = [];

        if (empty($carrier['carrier_code'])) {
            $errors[] = '承运商编码为空';
        }
        if (empty($carrier['carrier_name'])) {
            $errors[] = '承运商名称为空';
        }
        if (empty($carrier['carrier_type'])) {
            $errors[] = '承运商类型未设置';
        }
        if ($carrier['status'] != CarrierStatus::ENABLED) {
            $errors[] = '承运商未启用';
        }

        return [
            'name' => '基础配置',
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function checkApiConfig(array $carrier): array
    {
        $errors = [];

        if (empty($carrier['api_base_url'])) {
            $errors[] = 'API地址未配置';
        } elseif (!filter_var($carrier['api_base_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'API地址格式不正确';
        }

        if (empty($carrier['auth_type'])) {
            $errors[] = '认证方式未配置';
        } else {
            switch ($carrier['auth_type']) {
                case AuthType::API_KEY:
                    if (empty($carrier['api_key'])) {
                        $errors[] = 'API Key未配置';
                    }
                    break;
                case AuthType::BASIC:
                    if (empty($carrier['api_key']) || empty($carrier['api_secret'])) {
                        $errors[] = 'Basic Auth用户名或密码未配置';
                    }
                    break;
                case AuthType::OAUTH2:
                    if (empty($carrier['api_key']) || empty($carrier['api_secret'])) {
                        $errors[] = 'OAuth2客户端ID或密钥未配置';
                    }
                    break;
                case AuthType::TOKEN:
                    if (empty($carrier['auth_token'])) {
                        $errors[] = 'Token未配置';
                    }
                    break;
            }
        }

        if (empty($carrier['protocol_type'])) {
            $errors[] = '协议类型未配置';
        }

        if (empty($carrier['timeout_seconds'])) {
            $errors[] = '超时时间未配置';
        }

        if ($carrier['config_status'] != 1) {
            $errors[] = '接入配置未启用';
        }

        return [
            'name' => 'API接入配置',
            'passed' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function checkCallbackConfig(array $carrier): array
    {
        $errors = [];

        if (empty($carrier['callback_url'])) {
            $errors[] = '回调地址未配置';
        } elseif (!filter_var($carrier['callback_url'], FILTER_VALIDATE_URL)) {
            $errors[] = '回调地址格式不正确';
        }

        if (empty($carrier['callback_secret'])) {
            $errors[] = '回调签名密钥未配置（建议配置以提高安全性）';
        }

        return [
            'name' => '轨迹回传配置',
            'passed' => !empty($carrier['callback_url']) && filter_var($carrier['callback_url'], FILTER_VALIDATE_URL),
            'errors' => $errors,
            'warnings' => empty($carrier['callback_secret']) ? ['建议配置回调签名密钥'] : [],
        ];
    }

    private function checkStatusMapping(array $carrier): array
    {
        $errors = [];
        $warnings = [];

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tracking_status_mappings WHERE carrier_code = :code AND status = 1"
        );
        $stmt->execute([':code' => $carrier['carrier_code']]);
        $mappingCount = (int)$stmt->fetchColumn();

        if ($mappingCount === 0) {
            $errors[] = '未配置任何状态映射规则';
        }

        $standardStatuses = [
            TrackingStandardStatus::PICKED_UP,
            TrackingStandardStatus::IN_TRANSIT,
            TrackingStandardStatus::OUT_FOR_DELIVERY,
            TrackingStandardStatus::DELIVERED,
            TrackingStandardStatus::EXCEPTION,
        ];

        foreach ($standardStatuses as $status) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM tracking_status_mappings 
                 WHERE carrier_code = :code AND standard_status = :status AND status = 1"
            );
            $stmt->execute([':code' => $carrier['carrier_code'], ':status' => $status]);
            $count = (int)$stmt->fetchColumn();
            if ($count === 0 && $status !== TrackingStandardStatus::EXCEPTION) {
                $warnings[] = "标准状态「{$status}」未配置映射规则";
            }
        }

        return [
            'name' => '状态映射配置',
            'passed' => $mappingCount > 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'mapping_count' => $mappingCount,
        ];
    }

    private function checkAdapter(array $carrier): array
    {
        $errors = [];
        $warnings = [];

        try {
            require_once __DIR__ . '/CarrierAdapter.php';
            $adapter = CarrierAdapterFactory::create($carrier['carrier_code']);

            if (!$adapter instanceof CarrierAdapterInterface) {
                $errors[] = '适配器未实现CarrierAdapterInterface接口';
            }

            $reflection = new ReflectionClass($adapter);
            if ($reflection->getName() === HttpCarrierAdapter::class) {
                $warnings[] = '使用通用HTTP适配器，建议实现专用适配器以获得更好的兼容性';
            }
        } catch (Exception $e) {
            $errors[] = '适配器加载失败: ' . $e->getMessage();
        }

        return [
            'name' => '承运商适配器',
            'passed' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function checkProducts(array $carrier): array
    {
        $errors = [];
        $warnings = [];

        $productCount = is_array($carrier['products']) ? count($carrier['products']) : 0;

        if ($productCount === 0) {
            $warnings[] = '未配置任何服务产品';
        }

        $enabledCount = 0;
        if (!empty($carrier['products'])) {
            foreach ($carrier['products'] as $product) {
                if (($product['status'] ?? 0) == 1) {
                    $enabledCount++;
                }
            }
        }

        if ($productCount > 0 && $enabledCount === 0) {
            $errors[] = '无启用状态的服务产品';
        }

        return [
            'name' => '服务产品配置',
            'passed' => $enabledCount > 0 || $productCount === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'product_count' => $productCount,
            'enabled_count' => $enabledCount,
        ];
    }

    public function listConfigHistory(int $carrierId, array $params = []): array
    {
        $carrier = $this->getCarrier($carrierId);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $countStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM carrier_config_history WHERE carrier_id = :carrier_id"
        );
        $countStmt->execute([':carrier_id' => $carrierId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT id, carrier_id, change_type, operator, change_remark, created_at 
             FROM carrier_config_history 
             WHERE carrier_id = :carrier_id 
             ORDER BY created_at DESC 
             LIMIT {$offset}, {$pageSize}"
        );
        $stmt->execute([':carrier_id' => $carrierId]);
        $items = $stmt->fetchAll();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function getConfigHistoryDetail(int $historyId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM carrier_config_history WHERE id = :id"
        );
        $stmt->execute([':id' => $historyId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if (!empty($row['config_snapshot'])) {
            $row['config_snapshot'] = json_decode($row['config_snapshot'], true);
        }

        return $row;
    }

    public function rollbackConfig(int $historyId, string $operator = '', string $remark = ''): bool
    {
        $history = $this->getConfigHistoryDetail($historyId);
        if (!$history) {
            throw new RuntimeException('历史记录不存在');
        }

        $carrierId = (int)$history['carrier_id'];
        $carrier = $this->getCarrier($carrierId);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $snapshot = $history['config_snapshot'] ?? [];
        if (empty($snapshot)) {
            throw new RuntimeException('配置快照为空，无法回滚');
        }

        $this->db->beginTransaction();
        try {
            $this->saveConfigHistory($carrierId, 'rollback', $operator, $remark ?: '回滚到历史版本');

            if (!empty($snapshot['carrier'])) {
                $this->updateCarrierFromSnapshot($carrierId, $snapshot['carrier']);
            }

            if (isset($snapshot['config'])) {
                $this->upsertCarrierConfig($carrierId, $snapshot['config']);
            }

            $this->db->commit();
            $this->logger->info('承运商配置回滚成功', [
                'carrier_id' => $carrierId,
                'history_id' => $historyId,
                'operator' => $operator,
            ]);
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('承运商配置回滚失败', [
                'carrier_id' => $carrierId,
                'history_id' => $historyId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function updateCarrierFromSnapshot(int $carrierId, array $carrierData): void
    {
        $fields = [];
        $binds = [':id' => $carrierId];

        $allowFields = ['carrier_name', 'carrier_type', 'logo_url', 'contact_name', 'contact_phone', 'contact_email', 'country', 'supported_regions', 'status', 'priority', 'remark'];

        foreach ($allowFields as $field) {
            if (array_key_exists($field, $carrierData)) {
                $fields[] = "{$field} = :{$field}";
                $binds[":{$field}"] = $field === 'supported_regions' && is_array($carrierData[$field])
                    ? json_encode($carrierData[$field])
                    : $carrierData[$field];
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE carriers SET " . implode(', ', $fields) . " WHERE id = :id";
            $this->db->prepare($sql)->execute($binds);
        }
    }

    public function saveConfigHistory(int $carrierId, string $changeType, string $operator = '', string $remark = ''): int
    {
        $carrier = $this->getCarrier($carrierId);
        if (!$carrier) {
            throw new RuntimeException('承运商不存在');
        }

        $snapshot = [
            'carrier' => [
                'carrier_code' => $carrier['carrier_code'],
                'carrier_name' => $carrier['carrier_name'],
                'carrier_type' => $carrier['carrier_type'],
                'logo_url' => $carrier['logo_url'],
                'contact_name' => $carrier['contact_name'],
                'contact_phone' => $carrier['contact_phone'],
                'contact_email' => $carrier['contact_email'],
                'country' => $carrier['country'],
                'supported_regions' => $carrier['supported_regions'],
                'status' => $carrier['status'],
                'priority' => $carrier['priority'],
                'remark' => $carrier['remark'],
            ],
            'config' => [
                'protocol_type' => $carrier['protocol_type'],
                'api_base_url' => $carrier['api_base_url'],
                'auth_type' => $carrier['auth_type'],
                'api_key' => $carrier['api_key'],
                'api_secret' => $carrier['api_secret'],
                'auth_token' => $carrier['auth_token'],
                'callback_url' => $carrier['callback_url'],
                'callback_secret' => $carrier['callback_secret'],
                'timeout_seconds' => $carrier['timeout_seconds'],
                'retry_times' => $carrier['retry_times'],
                'rate_limit' => $carrier['rate_limit'],
                'extra_config' => $carrier['extra_config'],
                'status' => $carrier['config_status'],
            ],
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO carrier_config_history (carrier_id, config_snapshot, change_type, operator, change_remark) 
             VALUES (:carrier_id, :snapshot, :change_type, :operator, :remark)"
        );
        $stmt->execute([
            ':carrier_id' => $carrierId,
            ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ':change_type' => $changeType,
            ':operator' => $operator,
            ':remark' => $remark,
        ]);

        return (int)$this->db->lastInsertId();
    }
}
