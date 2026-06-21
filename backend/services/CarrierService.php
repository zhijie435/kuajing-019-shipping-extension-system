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
}
