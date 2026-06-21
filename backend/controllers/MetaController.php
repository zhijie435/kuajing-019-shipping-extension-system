<?php

class MetaController
{
    public function handle(string $action, ?string $param, array $data, array $query): void
    {
        switch ($action) {
            case 'carrier_types':
                $this->carrierTypes();
                break;
            case 'carrier_statuses':
                $this->carrierStatuses();
                break;
            case 'standard_statuses':
                $this->standardStatuses();
                break;
            case 'protocol_types':
                $this->protocolTypes();
                break;
            case 'auth_types':
                $this->authTypes();
                break;
            case 'service_types':
                $this->serviceTypes();
                break;
            case 'all':
                $this->all();
                break;
            default:
                JsonResponse::error('未知的元数据类型', 404);
        }
    }

    private function carrierTypes(): void
    {
        $items = [];
        foreach (CarrierType::getLabels() as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        JsonResponse::success($items);
    }

    private function carrierStatuses(): void
    {
        $items = [];
        foreach (CarrierStatus::getLabels() as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        JsonResponse::success($items);
    }

    private function standardStatuses(): void
    {
        $items = [];
        foreach (TrackingStandardStatus::getLabels() as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        JsonResponse::success($items);
    }

    private function protocolTypes(): void
    {
        $items = [];
        foreach (ProtocolType::getLabels() as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        JsonResponse::success($items);
    }

    private function authTypes(): void
    {
        $items = [];
        foreach (AuthType::getLabels() as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        JsonResponse::success($items);
    }

    private function serviceTypes(): void
    {
        $items = [];
        foreach (ServiceType::getLabels() as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        JsonResponse::success($items);
    }

    private function all(): void
    {
        JsonResponse::success([
            'carrier_types' => $this->formatLabels(CarrierType::getLabels()),
            'carrier_statuses' => $this->formatLabels(CarrierStatus::getLabels()),
            'standard_statuses' => $this->formatLabels(TrackingStandardStatus::getLabels()),
            'protocol_types' => $this->formatLabels(ProtocolType::getLabels()),
            'auth_types' => $this->formatLabels(AuthType::getLabels()),
            'service_types' => $this->formatLabels(ServiceType::getLabels()),
        ]);
    }

    private function formatLabels(array $labels): array
    {
        $items = [];
        foreach ($labels as $value => $label) {
            $items[] = ['value' => $value, 'label' => $label];
        }
        return $items;
    }
}
