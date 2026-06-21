<?php

interface CarrierAdapterInterface
{
    public function createShipment(array $orderData): array;
    public function cancelShipment(string $trackingNo): array;
    public function queryTracking(string $trackingNo): array;
    public function getTrackingCallbackUrl(): string;
    public function validateCallbackSignature(array $headers, string $body): bool;
    public function parseCallbackData(string $body): array;
}

class CarrierAdapterFactory
{
    public static function create(string $carrierCode): CarrierAdapterInterface
    {
        $adapterClass = self::getAdapterClass($carrierCode);

        if (!class_exists($adapterClass)) {
            $adapterClass = HttpCarrierAdapter::class;
        }

        return new $adapterClass($carrierCode);
    }

    private static function getAdapterClass(string $carrierCode): string
    {
        $adapters = [
            'SF_EXPRESS' => SFExpressAdapter::class,
            'FEDEX' => FedExAdapter::class,
            'DHL' => DHLAdapter::class,
            'UPS' => UPSAdapter::class,
            'YANWEN' => YanwenAdapter::class,
        ];

        return $adapters[$carrierCode] ?? '';
    }
}

class HttpCarrierAdapter implements CarrierAdapterInterface
{
    protected PDO $db;
    protected string $carrierCode;
    protected array $config;

    public function __construct(string $carrierCode)
    {
        $this->db = Database::getInstance();
        $this->carrierCode = $carrierCode;
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $stmt = $this->db->prepare(
            "SELECT cc.* FROM carrier_configs cc 
             JOIN carriers c ON cc.carrier_id = c.id 
             WHERE c.carrier_code = :code AND cc.status = 1"
        );
        $stmt->execute([':code' => $this->carrierCode]);
        $this->config = $stmt->fetch() ?: [];
    }

    public function createShipment(array $orderData): array
    {
        $url = rtrim($this->config['api_base_url'] ?? '', '/') . '/shipments';
        $response = $this->httpRequest('POST', $url, $orderData);

        return [
            'success' => $response['http_code'] === 200,
            'tracking_no' => $response['data']['tracking_no'] ?? '',
            'label_url' => $response['data']['label_url'] ?? '',
            'raw_response' => $response,
        ];
    }

    public function cancelShipment(string $trackingNo): array
    {
        $url = rtrim($this->config['api_base_url'] ?? '', '/') . "/shipments/{$trackingNo}/cancel";
        $response = $this->httpRequest('POST', $url, ['tracking_no' => $trackingNo]);

        return [
            'success' => $response['http_code'] === 200,
            'raw_response' => $response,
        ];
    }

    public function queryTracking(string $trackingNo): array
    {
        $url = rtrim($this->config['api_base_url'] ?? '', '/') . "/tracking/{$trackingNo}";
        $response = $this->httpRequest('GET', $url);

        return [
            'success' => $response['http_code'] === 200,
            'events' => $response['data']['events'] ?? [],
            'raw_response' => $response,
        ];
    }

    public function getTrackingCallbackUrl(): string
    {
        return $this->config['callback_url'] ?? '';
    }

    public function validateCallbackSignature(array $headers, string $body): bool
    {
        if (empty($this->config['callback_secret'])) {
            return true;
        }

        $signature = $headers['X-Signature'] ?? $headers['x-signature'] ?? '';
        if (empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $body, $this->config['callback_secret']);
        return hash_equals($expectedSignature, $signature);
    }

    public function parseCallbackData(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('回调数据JSON解析失败');
        }

        return [
            'tracking_no' => $data['tracking_no'] ?? $data['trackingNumber'] ?? '',
            'carrier_code' => $this->carrierCode,
            'events' => $data['events'] ?? $data['tracking_events'] ?? [],
        ];
    }

    protected function httpRequest(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();

        $headers = ['Content-Type: application/json'];

        if ($this->config['auth_type'] === AuthType::API_KEY) {
            $headers[] = "X-API-Key: {$this->config['api_key']}";
        } elseif ($this->config['auth_type'] === AuthType::BASIC) {
            $headers[] = 'Authorization: Basic ' . base64_encode("{$this->config['api_key']}:{$this->config['api_secret']}");
        } elseif ($this->config['auth_type'] === AuthType::TOKEN) {
            $headers[] = "Authorization: Bearer {$this->config['auth_token']}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout_seconds'] ?? 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'data' => json_decode($response, true) ?: [],
            'error' => $error,
        ];
    }
}

class SFExpressAdapter extends HttpCarrierAdapter
{
    public function parseCallbackData(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('顺丰回调数据解析失败');
        }

        $result = $data['result'] ?? $data;

        return [
            'tracking_no' => $result['mailNo'] ?? $result['tracking_no'] ?? '',
            'carrier_code' => $this->carrierCode,
            'events' => array_map(function ($event) {
                return [
                    'event_code' => $event['opCode'] ?? $event['event_code'] ?? '',
                    'event_desc' => $event['remark'] ?? $event['event_desc'] ?? '',
                    'event_time' => $event['opTime'] ?? $event['event_time'] ?? '',
                    'event_location' => $event['location'] ?? $event['event_location'] ?? '',
                ];
            }, $result['routeInfos'] ?? $result['events'] ?? []),
        ];
    }
}

class FedExAdapter extends HttpCarrierAdapter
{
    public function parseCallbackData(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('FedEx回调数据解析失败');
        }

        $trackResult = $data['output']['completeTrackResults'][0]['trackResults'][0] ?? $data;

        return [
            'tracking_no' => $trackResult['trackingNumberInfo']['trackingNumber'] ?? $data['tracking_no'] ?? '',
            'carrier_code' => $this->carrierCode,
            'events' => array_map(function ($event) {
                return [
                    'event_code' => $event['eventDefinition']['key'] ?? $event['event_code'] ?? '',
                    'event_desc' => $event['eventDefinition']['description'] ?? $event['event_desc'] ?? '',
                    'event_time' => $event['date'] ?? $event['event_time'] ?? '',
                    'event_location' => ($event['scanLocation']['city'] ?? '') . ', ' . ($event['scanLocation']['countryName'] ?? ''),
                ];
            }, $trackResult['scanEvents'] ?? $data['events'] ?? []),
        ];
    }
}

class DHLAdapter extends HttpCarrierAdapter
{
    public function parseCallbackData(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('DHL回调数据解析失败');
        }

        $shipments = $data['shipments'][0] ?? $data;

        return [
            'tracking_no' => $shipments['id'] ?? $data['tracking_no'] ?? '',
            'carrier_code' => $this->carrierCode,
            'events' => array_map(function ($event) {
                return [
                    'event_code' => $event['statusCode'] ?? $event['event_code'] ?? '',
                    'event_desc' => $event['description'] ?? $event['event_desc'] ?? '',
                    'event_time' => $event['timestamp'] ?? $event['event_time'] ?? '',
                    'event_location' => ($event['location']['address']['addressLocality'] ?? '') . ', ' . ($event['location']['address']['countryCode'] ?? ''),
                ];
            }, $shipments['events'] ?? []),
        ];
    }
}

class UPSAdapter extends HttpCarrierAdapter
{
    public function parseCallbackData(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('UPS回调数据解析失败');
        }

        $trackResponse = $data['trackResponse']['shipment'][0] ?? $data;

        return [
            'tracking_no' => $trackResponse['inquiryNumber'] ?? $data['tracking_no'] ?? '',
            'carrier_code' => $this->carrierCode,
            'events' => array_map(function ($event) {
                return [
                    'event_code' => $event['status']['type'] ?? $event['event_code'] ?? '',
                    'event_desc' => $event['status']['description'] ?? $event['event_desc'] ?? '',
                    'event_time' => ($event['date'] ?? '') . ' ' . ($event['time'] ?? ''),
                    'event_location' => ($event['location']['address']['city'] ?? '') . ', ' . ($event['location']['address']['country'] ?? ''),
                ];
            }, $trackResponse['package'][0]['activity'] ?? $data['events'] ?? []),
        ];
    }
}

class YanwenAdapter extends HttpCarrierAdapter
{
    public function parseCallbackData(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('燕文回调数据解析失败');
        }

        return [
            'tracking_no' => $data['tracking_no'] ?? $data['trackingNumber'] ?? '',
            'carrier_code' => $this->carrierCode,
            'events' => array_map(function ($event) {
                return [
                    'event_code' => $event['status_code'] ?? $event['event_code'] ?? '',
                    'event_desc' => $event['status_desc'] ?? $event['event_desc'] ?? '',
                    'event_time' => $event['event_time'] ?? '',
                    'event_location' => $event['location'] ?? $event['event_location'] ?? '',
                ];
            }, $data['traces'] ?? $data['events'] ?? []),
        ];
    }
}
