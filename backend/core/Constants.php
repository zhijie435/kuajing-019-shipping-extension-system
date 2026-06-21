<?php

class CarrierStatus
{
    const DISABLED = 0;
    const ENABLED = 1;
    const STOPPED = 2;
    const TESTING = 3;

    public static function getLabels(): array
    {
        return [
            self::DISABLED => '未启用',
            self::ENABLED => '已启用',
            self::STOPPED => '已停用',
            self::TESTING => '测试中',
        ];
    }

    public static function getLabel(int $status): string
    {
        return self::getLabels()[$status] ?? '未知';
    }
}

class CarrierType
{
    const EXPRESS = 1;
    const DEDICATED_LINE = 2;
    const POST = 3;
    const SEA = 4;
    const AIR = 5;
    const RAIL = 6;

    public static function getLabels(): array
    {
        return [
            self::EXPRESS => '快递',
            self::DEDICATED_LINE => '专线',
            self::POST => '邮政',
            self::SEA => '海运',
            self::AIR => '空运',
            self::RAIL => '铁路',
        ];
    }

    public static function getLabel(int $type): string
    {
        return self::getLabels()[$type] ?? '未知';
    }
}

class TrackingStandardStatus
{
    const PICKED_UP = 'PICKED_UP';
    const IN_TRANSIT = 'IN_TRANSIT';
    const OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    const DELIVERED = 'DELIVERED';
    const EXCEPTION = 'EXCEPTION';
    const UNKNOWN = 'UNKNOWN';

    public static function getLabels(): array
    {
        return [
            self::PICKED_UP => '已揽收',
            self::IN_TRANSIT => '运输中',
            self::OUT_FOR_DELIVERY => '派送中',
            self::DELIVERED => '已签收',
            self::EXCEPTION => '异常',
            self::UNKNOWN => '未知',
        ];
    }

    public static function getLabel(string $status): string
    {
        return self::getLabels()[$status] ?? '未知';
    }
}

class ProtocolType
{
    const HTTP = 'http';
    const FTP = 'ftp';
    const EDI = 'edi';
    const MQ = 'mq';

    public static function getLabels(): array
    {
        return [
            self::HTTP => 'HTTP',
            self::FTP => 'FTP',
            self::EDI => 'EDI',
            self::MQ => 'MQ',
        ];
    }
}

class AuthType
{
    const API_KEY = 'api_key';
    const OAUTH2 = 'oauth2';
    const BASIC = 'basic';
    const TOKEN = 'token';

    public static function getLabels(): array
    {
        return [
            self::API_KEY => 'API Key',
            self::OAUTH2 => 'OAuth2',
            self::BASIC => 'Basic Auth',
            self::TOKEN => 'Token',
        ];
    }
}

class ServiceType
{
    const STANDARD = 'standard';
    const EXPRESS = 'express';
    const ECONOMY = 'economy';

    public static function getLabels(): array
    {
        return [
            self::STANDARD => '标准',
            self::EXPRESS => '加急',
            self::ECONOMY => '经济',
        ];
    }
}
