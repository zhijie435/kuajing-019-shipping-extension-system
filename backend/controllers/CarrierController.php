<?php

class CarrierController
{
    private CarrierService $carrierService;

    public function __construct()
    {
        $this->carrierService = new CarrierService();
    }

    public function handle(string $action, ?string $param, array $data, array $query): void
    {
        $method = getRequestMethod();

        switch (true) {
            case ($action === '' || $action === 'index'):
                if ($method === 'GET') {
                    $this->listCarriers($query);
                } elseif ($method === 'POST') {
                    $this->createCarrier($data);
                }
                break;

            case $action === 'list':
                $this->listCarriers($query);
                break;

            case $action === 'select':
                $this->selectCarriers();
                break;

            case ctype_digit($action):
                $id = (int)$action;
                if ($method === 'GET') {
                    $this->getCarrier($id);
                } elseif ($method === 'PUT' || $method === 'POST') {
                    $this->updateCarrier($id, $data);
                } elseif ($method === 'DELETE') {
                    $this->deleteCarrier($id);
                }
                break;

            case $action === 'status':
                $this->updateStatus($data);
                break;

            case $action === 'health':
                $id = (int)($param ?? 0);
                $this->healthCheck($id);
                break;

            case $action === 'products':
                $id = (int)($param ?? 0);
                if ($method === 'GET') {
                    $this->listProducts($id);
                } elseif ($method === 'POST') {
                    $this->createProduct($id, $data);
                }
                break;

            case $action === 'product':
                $productId = (int)($param ?? 0);
                if ($method === 'DELETE') {
                    $this->deleteProduct($productId);
                }
                break;

            default:
                JsonResponse::error('未知的操作: ' . $action, 404);
        }
    }

    private function listCarriers(array $query): void
    {
        $result = $this->carrierService->listCarriers($query);
        JsonResponse::paginate($result['items'], $result['total'], $result['page'], $result['page_size']);
    }

    private function selectCarriers(): void
    {
        $result = $this->carrierService->listCarriers(['page_size' => 1000]);
        $options = array_map(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['carrier_name'],
                'code' => $item['carrier_code'],
                'status' => $item['status'],
            ];
        }, $result['items']);
        JsonResponse::success($options);
    }

    private function getCarrier(int $id): void
    {
        $carrier = $this->carrierService->getCarrier($id);
        if (!$carrier) {
            JsonResponse::error('承运商不存在', 404);
            return;
        }
        JsonResponse::success($carrier);
    }

    private function createCarrier(array $data): void
    {
        $required = ['carrier_code', 'carrier_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                JsonResponse::error("缺少必填字段: {$field}", 400);
                return;
            }
        }

        try {
            $id = $this->carrierService->createCarrier($data);
            $carrier = $this->carrierService->getCarrier($id);
            JsonResponse::success($carrier, '承运商创建成功', 201);
        } catch (Throwable $e) {
            JsonResponse::error('承运商创建失败: ' . $e->getMessage(), 500);
        }
    }

    private function updateCarrier(int $id, array $data): void
    {
        try {
            $this->carrierService->updateCarrier($id, $data);
            $carrier = $this->carrierService->getCarrier($id);
            JsonResponse::success($carrier, '承运商更新成功');
        } catch (Throwable $e) {
            JsonResponse::error('承运商更新失败: ' . $e->getMessage(), 500);
        }
    }

    private function deleteCarrier(int $id): void
    {
        try {
            $this->carrierService->deleteCarrier($id);
            JsonResponse::success(null, '承运商删除成功');
        } catch (Throwable $e) {
            JsonResponse::error('承运商删除失败: ' . $e->getMessage(), 500);
        }
    }

    private function updateStatus(array $data): void
    {
        $id = $data['id'] ?? 0;
        $status = $data['status'] ?? null;

        if (empty($id) || $status === null) {
            JsonResponse::error('缺少参数 id 或 status', 400);
            return;
        }

        try {
            $this->carrierService->updateCarrierStatus((int)$id, (int)$status);
            $carrier = $this->carrierService->getCarrier((int)$id);
            JsonResponse::success($carrier, '状态更新成功');
        } catch (Throwable $e) {
            JsonResponse::error('状态更新失败: ' . $e->getMessage(), 500);
        }
    }

    private function healthCheck(int $id): void
    {
        if (empty($id)) {
            JsonResponse::error('缺少参数 id', 400);
            return;
        }

        try {
            $result = $this->carrierService->healthCheck($id);
            JsonResponse::success($result);
        } catch (Throwable $e) {
            JsonResponse::error('健康检查失败: ' . $e->getMessage(), 500);
        }
    }

    private function listProducts(int $carrierId): void
    {
        if (empty($carrierId)) {
            JsonResponse::error('缺少参数 carrier_id', 400);
            return;
        }

        $products = $this->carrierService->getCarrierProducts($carrierId);
        JsonResponse::success($products);
    }

    private function createProduct(int $carrierId, array $data): void
    {
        if (empty($carrierId)) {
            JsonResponse::error('缺少参数 carrier_id', 400);
            return;
        }

        $required = ['product_code', 'product_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                JsonResponse::error("缺少必填字段: {$field}", 400);
                return;
            }
        }

        try {
            $productId = $this->carrierService->createCarrierProduct($carrierId, $data);
            JsonResponse::success(['id' => $productId], '产品创建成功', 201);
        } catch (Throwable $e) {
            JsonResponse::error('产品创建失败: ' . $e->getMessage(), 500);
        }
    }

    private function deleteProduct(int $productId): void
    {
        if (empty($productId)) {
            JsonResponse::error('缺少参数 product_id', 400);
            return;
        }

        $this->carrierService->deleteCarrierProduct($productId);
        JsonResponse::success(null, '产品删除成功');
    }
}
