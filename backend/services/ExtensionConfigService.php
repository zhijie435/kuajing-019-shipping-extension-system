<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';

class ExtensionConfigService
{
    private PDO $db;
    private Logger $logger;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }

    public function listConfigs(array $params = []): array
    {
        $where = ['1=1'];
        $binds = [];

        if (!empty($params['config_group'])) {
            $where[] = 'config_group = :config_group';
            $binds[':config_group'] = $params['config_group'];
        }

        if (!empty($params['keyword'])) {
            $where[] = '(config_key LIKE :kw OR description LIKE :kw2)';
            $binds[':kw'] = '%' . $params['keyword'] . '%';
            $binds[':kw2'] = '%' . $params['keyword'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT * FROM logistics_extension_configs WHERE {$whereSql} ORDER BY config_group, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);

        $configs = $stmt->fetchAll();

        foreach ($configs as &$config) {
            $config['config_value'] = $this->castValue($config['config_value'], $config['value_type']);
        }

        return $configs;
    }

    public function getConfigGroups(): array
    {
        $stmt = $this->db->query("SELECT DISTINCT config_group FROM logistics_extension_configs ORDER BY config_group");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getConfig(string $key)
    {
        $stmt = $this->db->prepare("SELECT * FROM logistics_extension_configs WHERE config_key = :key");
        $stmt->execute([':key' => $key]);
        $config = $stmt->fetch();

        if (!$config) {
            return null;
        }

        return $this->castValue($config['config_value'], $config['value_type']);
    }

    public function updateConfig(string $key, $value): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM logistics_extension_configs WHERE config_key = :key");
        $stmt->execute([':key' => $key]);
        $config = $stmt->fetch();

        if (!$config) {
            throw new RuntimeException("配置项不存在: {$key}");
        }

        if ($config['is_readonly']) {
            throw new RuntimeException("配置项为只读: {$key}");
        }

        $stringValue = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;

        $stmt = $this->db->prepare(
            "UPDATE logistics_extension_configs SET config_value = :value WHERE config_key = :key"
        );
        $result = $stmt->execute([':value' => $stringValue, ':key' => $key]);

        $this->logger->info('配置项更新', ['key' => $key, 'value' => $stringValue]);
        return $result;
    }

    public function batchUpdateConfigs(array $configs): bool
    {
        $this->db->beginTransaction();
        try {
            foreach ($configs as $key => $value) {
                $this->updateConfig($key, $value);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('批量更新配置失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function createConfig(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO logistics_extension_configs (config_key, config_value, config_group, value_type, description, is_readonly) 
             VALUES (:config_key, :config_value, :config_group, :value_type, :description, :is_readonly)"
        );
        $stmt->execute([
            ':config_key' => $data['config_key'],
            ':config_value' => $data['config_value'] ?? '',
            ':config_group' => $data['config_group'] ?? 'default',
            ':value_type' => $data['value_type'] ?? 'string',
            ':description' => $data['description'] ?? '',
            ':is_readonly' => $data['is_readonly'] ?? 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function deleteConfig(string $key): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM logistics_extension_configs WHERE config_key = :key");
        $stmt->execute([':key' => $key]);
        $config = $stmt->fetch();

        if (!$config) {
            throw new RuntimeException("配置项不存在: {$key}");
        }

        if ($config['is_readonly']) {
            throw new RuntimeException("只读配置项不可删除: {$key}");
        }

        $stmt = $this->db->prepare("DELETE FROM logistics_extension_configs WHERE config_key = :key");
        return $stmt->execute([':key' => $key]);
    }

    public function getStatusMappings(string $carrierCode = ''): array
    {
        $sql = "SELECT tsm.*, c.carrier_name 
                FROM tracking_status_mappings tsm 
                LEFT JOIN carriers c ON tsm.carrier_code = c.carrier_code";
        $binds = [];

        if (!empty($carrierCode)) {
            $sql .= " WHERE tsm.carrier_code = :carrier_code";
            $binds[':carrier_code'] = $carrierCode;
        }

        $sql .= " ORDER BY tsm.carrier_code, tsm.priority DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        return $stmt->fetchAll();
    }

    public function createStatusMapping(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tracking_status_mappings (carrier_code, carrier_event_code, carrier_event_desc, standard_status, is_exception, exception_type, priority, status) 
             VALUES (:carrier_code, :carrier_event_code, :carrier_event_desc, :standard_status, :is_exception, :exception_type, :priority, :status)"
        );
        $stmt->execute([
            ':carrier_code' => $data['carrier_code'],
            ':carrier_event_code' => $data['carrier_event_code'],
            ':carrier_event_desc' => $data['carrier_event_desc'] ?? '',
            ':standard_status' => $data['standard_status'],
            ':is_exception' => $data['is_exception'] ?? 0,
            ':exception_type' => $data['exception_type'] ?? '',
            ':priority' => $data['priority'] ?? 0,
            ':status' => $data['status'] ?? 1,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateStatusMapping(int $id, array $data): bool
    {
        $fields = [];
        $binds = [':id' => $id];

        $allowFields = ['carrier_event_desc', 'standard_status', 'is_exception', 'exception_type', 'priority', 'status'];

        foreach ($allowFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $binds[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE tracking_status_mappings SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->prepare($sql)->execute($binds);
    }

    public function deleteStatusMapping(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tracking_status_mappings WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    private function castValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
}
