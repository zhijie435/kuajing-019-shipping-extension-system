<?php

class ExtensionConfigController
{
    private ExtensionConfigService $configService;

    public function __construct()
    {
        $this->configService = new ExtensionConfigService();
    }

    public function handle(string $action, ?string $param, array $data, array $query): void
    {
        $method = getRequestMethod();

        switch (true) {
            case ($action === '' || $action === 'index' || $action === 'configs'):
                if ($method === 'GET') {
                    $this->listConfigs($query);
                } elseif ($method === 'POST') {
                    $this->createConfig($data);
                }
                break;

            case $action === 'groups':
                $this->listConfigGroups();
                break;

            case $action === 'batch':
                $this->batchUpdateConfigs($data);
                break;

            case $action === 'mapping' || $action === 'mappings':
                if ($method === 'GET') {
                    $this->listStatusMappings($query);
                } elseif ($method === 'POST') {
                    $this->createStatusMapping($data);
                }
                break;

            case $action === 'mapping_id' || $action === 'mapping_item':
                $id = (int)($param ?? 0);
                if ($method === 'PUT' || $method === 'POST') {
                    $this->updateStatusMapping($id, $data);
                } elseif ($method === 'DELETE') {
                    $this->deleteStatusMapping($id);
                }
                break;

            default:
                $key = $action;
                if (!empty($key)) {
                    if ($method === 'GET') {
                        $this->getConfig($key);
                    } elseif ($method === 'PUT' || $method === 'POST') {
                        $this->updateConfig($key, $data);
                    } elseif ($method === 'DELETE') {
                        $this->deleteConfig($key);
                    }
                } else {
                    JsonResponse::error('未知的操作', 404);
                }
        }
    }

    private function listConfigs(array $query): void
    {
        $configs = $this->configService->listConfigs($query);
        JsonResponse::success($configs);
    }

    private function listConfigGroups(): void
    {
        $groups = $this->configService->getConfigGroups();
        JsonResponse::success($groups);
    }

    private function getConfig(string $key): void
    {
        $value = $this->configService->getConfig($key);
        JsonResponse::success(['key' => $key, 'value' => $value]);
    }

    private function createConfig(array $data): void
    {
        $required = ['config_key'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                JsonResponse::error("缺少必填字段: {$field}", 400);
                return;
            }
        }

        try {
            $id = $this->configService->createConfig($data);
            JsonResponse::success(['id' => $id], '配置创建成功', 201);
        } catch (Throwable $e) {
            JsonResponse::error('配置创建失败: ' . $e->getMessage(), 500);
        }
    }

    private function updateConfig(string $key, array $data): void
    {
        if (!array_key_exists('config_value', $data) && !array_key_exists('value', $data)) {
            JsonResponse::error('缺少参数 config_value', 400);
            return;
        }

        $value = $data['config_value'] ?? $data['value'] ?? '';

        try {
            $this->configService->updateConfig($key, $value);
            JsonResponse::success(['key' => $key, 'value' => $this->configService->getConfig($key)], '配置更新成功');
        } catch (Throwable $e) {
            JsonResponse::error('配置更新失败: ' . $e->getMessage(), 500);
        }
    }

    private function deleteConfig(string $key): void
    {
        try {
            $this->configService->deleteConfig($key);
            JsonResponse::success(null, '配置删除成功');
        } catch (Throwable $e) {
            JsonResponse::error('配置删除失败: ' . $e->getMessage(), 500);
        }
    }

    private function batchUpdateConfigs(array $data): void
    {
        $configs = $data['configs'] ?? [];
        if (empty($configs) || !is_array($configs)) {
            JsonResponse::error('缺少参数 configs', 400);
            return;
        }

        try {
            $this->configService->batchUpdateConfigs($configs);
            JsonResponse::success($this->configService->listConfigs(), '批量更新成功');
        } catch (Throwable $e) {
            JsonResponse::error('批量更新失败: ' . $e->getMessage(), 500);
        }
    }

    private function listStatusMappings(array $query): void
    {
        $carrierCode = $query['carrier_code'] ?? '';
        $mappings = $this->configService->getStatusMappings($carrierCode);
        JsonResponse::success($mappings);
    }

    private function createStatusMapping(array $data): void
    {
        $required = ['carrier_code', 'carrier_event_code', 'standard_status'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                JsonResponse::error("缺少必填字段: {$field}", 400);
                return;
            }
        }

        try {
            $id = $this->configService->createStatusMapping($data);
            JsonResponse::success(['id' => $id], '状态映射创建成功', 201);
        } catch (Throwable $e) {
            JsonResponse::error('状态映射创建失败: ' . $e->getMessage(), 500);
        }
    }

    private function updateStatusMapping(int $id, array $data): void
    {
        if (empty($id)) {
            JsonResponse::error('缺少参数 id', 400);
            return;
        }

        try {
            $this->configService->updateStatusMapping($id, $data);
            JsonResponse::success(null, '状态映射更新成功');
        } catch (Throwable $e) {
            JsonResponse::error('状态映射更新失败: ' . $e->getMessage(), 500);
        }
    }

    private function deleteStatusMapping(int $id): void
    {
        if (empty($id)) {
            JsonResponse::error('缺少参数 id', 400);
            return;
        }

        try {
            $this->configService->deleteStatusMapping($id);
            JsonResponse::success(null, '状态映射删除成功');
        } catch (Throwable $e) {
            JsonResponse::error('状态映射删除失败: ' . $e->getMessage(), 500);
        }
    }
}
