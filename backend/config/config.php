<?php

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'logistics_extension',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
    ],

    'tracking' => [
        'callback_enabled' => filter_var($_ENV['TRACKING_CALLBACK_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'callback_auth_enabled' => filter_var($_ENV['TRACKING_CALLBACK_AUTH_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'callback_ip_whitelist' => $_ENV['TRACKING_CALLBACK_IP_WHITELIST'] ?? '127.0.0.1,10.0.0.0/8,192.168.0.0/16',
        'callback_max_retry' => (int)($_ENV['TRACKING_CALLBACK_MAX_RETRY'] ?? 3),
        'callback_retry_interval' => (int)($_ENV['TRACKING_CALLBACK_RETRY_INTERVAL'] ?? 60),
        'auto_sync_enabled' => filter_var($_ENV['TRACKING_AUTO_SYNC_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'event_dedup_enabled' => filter_var($_ENV['TRACKING_EVENT_DEDUP_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'log_retention_days' => (int)($_ENV['TRACKING_LOG_RETENTION_DAYS'] ?? 90),
    ],

    'carrier' => [
        'default_timeout' => (int)($_ENV['CARRIER_DEFAULT_TIMEOUT'] ?? 30),
        'default_retry' => (int)($_ENV['CARRIER_DEFAULT_RETRY'] ?? 3),
        'default_rate_limit' => (int)($_ENV['CARRIER_DEFAULT_RATE_LIMIT'] ?? 100),
        'health_check_enabled' => filter_var($_ENV['CARRIER_HEALTH_CHECK_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'health_check_interval' => (int)($_ENV['CARRIER_HEALTH_CHECK_INTERVAL'] ?? 300),
    ],

    'notification' => [
        'tracking_exception_enabled' => filter_var($_ENV['NOTIFICATION_TRACKING_EXCEPTION_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'tracking_exception_email' => $_ENV['NOTIFICATION_TRACKING_EXCEPTION_EMAIL'] ?? 'admin@example.com',
        'carrier_offline_enabled' => filter_var($_ENV['NOTIFICATION_CARRIER_OFFLINE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'carrier_offline_email' => $_ENV['NOTIFICATION_CARRIER_OFFLINE_EMAIL'] ?? 'admin@example.com',
    ],

    'global' => [
        'log_level' => $_ENV['GLOBAL_LOG_LEVEL'] ?? 'info',
        'api_enabled' => filter_var($_ENV['GLOBAL_API_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'maintenance_mode' => filter_var($_ENV['GLOBAL_MAINTENANCE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    'callback' => [
        'token' => $_ENV['CALLBACK_TOKEN'] ?? 'wh_callback_token_2024',
        'ip_whitelist' => $_ENV['CALLBACK_IP_WHITELIST'] ?? '127.0.0.1,10.0.0.0/8,192.168.0.0/16',
    ],

    'order' => [
        'no_prefix' => $_ENV['ORDER_NO_PREFIX'] ?? 'WH',
        'max_quantity_per_item' => (int)($_ENV['ORDER_MAX_QUANTITY_PER_ITEM'] ?? 999),
    ],

    'warehouse' => [
        'default_priority' => (int)($_ENV['WAREHOUSE_DEFAULT_PRIORITY'] ?? 0),
    ],

    'state_machine' => [
        'strict_validation' => (bool)($_ENV['STATE_MACHINE_STRICT_VALIDATION'] ?? true),
        'allow_force_transition' => (bool)($_ENV['STATE_MACHINE_ALLOW_FORCE_TRANSITION'] ?? false),
        'transition_log_enabled' => (bool)($_ENV['STATE_MACHINE_TRANSITION_LOG_ENABLED'] ?? true),
        'rollback_enabled' => (bool)($_ENV['STATE_MACHINE_ROLLBACK_ENABLED'] ?? true),
        'max_rollback_depth' => (int)($_ENV['STATE_MACHINE_MAX_ROLLBACK_DEPTH'] ?? 3),
    ],

    'order_audit' => [
        'enabled' => (bool)($_ENV['ORDER_AUDIT_ENABLED'] ?? true),
        'rollback_required' => (bool)($_ENV['ORDER_AUDIT_ROLLBACK_REQUIRED'] ?? true),
        'rollback_threshold' => (float)($_ENV['ORDER_AUDIT_ROLLBACK_THRESHOLD'] ?? 10000.00),
        'exception_mark_required' => (bool)($_ENV['ORDER_AUDIT_EXCEPTION_MARK_REQUIRED'] ?? false),
        'exception_mark_threshold' => (float)($_ENV['ORDER_AUDIT_EXCEPTION_MARK_THRESHOLD'] ?? 0),
        'exception_resolve_required' => (bool)($_ENV['ORDER_AUDIT_EXCEPTION_RESOLVE_REQUIRED'] ?? true),
        'exception_resolve_threshold' => (float)($_ENV['ORDER_AUDIT_EXCEPTION_RESOLVE_THRESHOLD'] ?? 0),
        'status_change_required' => (bool)($_ENV['ORDER_AUDIT_STATUS_CHANGE_REQUIRED'] ?? false),
        'status_change_threshold' => (float)($_ENV['ORDER_AUDIT_STATUS_CHANGE_THRESHOLD'] ?? 0),
        'writeback_required' => (bool)($_ENV['ORDER_AUDIT_WRITEBACK_REQUIRED'] ?? false),
        'writeback_threshold' => (float)($_ENV['ORDER_AUDIT_WRITEBACK_THRESHOLD'] ?? 0),
    ],

    'order_rollback_protection' => [
        'enabled' => (bool)($_ENV['ORDER_ROLLBACK_PROTECTION_ENABLED'] ?? true),
        'default_amount_threshold' => (float)($_ENV['ORDER_ROLLBACK_PROTECTION_AMOUNT_THRESHOLD'] ?? 50000.00),
        'time_window_hours' => (int)($_ENV['ORDER_ROLLBACK_PROTECTION_TIME_WINDOW_HOURS'] ?? 24),
        'terminal_status_protected' => (bool)($_ENV['ORDER_ROLLBACK_PROTECTION_TERMINAL_STATUS'] ?? true),
        'max_rollback_count' => (int)($_ENV['ORDER_ROLLBACK_PROTECTION_MAX_ROLLBACK_COUNT'] ?? 3),
    ],

    'order_exception' => [
        'enabled' => (bool)($_ENV['ORDER_EXCEPTION_ENABLED'] ?? true),
        'auto_detect_enabled' => (bool)($_ENV['ORDER_EXCEPTION_AUTO_DETECT_ENABLED'] ?? true),
        'notify_enabled' => (bool)($_ENV['ORDER_EXCEPTION_NOTIFY_ENABLED'] ?? true),
        'notify_email' => $_ENV['ORDER_EXCEPTION_NOTIFY_EMAIL'] ?? 'admin@example.com',
        'high_level_auto_escalate' => (bool)($_ENV['ORDER_EXCEPTION_HIGH_LEVEL_AUTO_ESCALATE'] ?? true),
        'escalate_hours' => (int)($_ENV['ORDER_EXCEPTION_ESCALATE_HOURS'] ?? 24),
    ],

    'order_writeback' => [
        'enabled' => (bool)($_ENV['ORDER_WRITEBACK_ENABLED'] ?? true),
        'max_retry_count' => (int)($_ENV['ORDER_WRITEBACK_MAX_RETRY_COUNT'] ?? 3),
        'retry_interval' => (int)($_ENV['ORDER_WRITEBACK_RETRY_INTERVAL'] ?? 60),
        'erp_enabled' => (bool)($_ENV['ORDER_WRITEBACK_ERP_ENABLED'] ?? true),
        'wms_enabled' => (bool)($_ENV['ORDER_WRITEBACK_WMS_ENABLED'] ?? true),
        'crm_enabled' => (bool)($_ENV['ORDER_WRITEBACK_CRM_ENABLED'] ?? false),
        'finance_enabled' => (bool)($_ENV['ORDER_WRITEBACK_FINANCE_ENABLED'] ?? true),
    ],
];
