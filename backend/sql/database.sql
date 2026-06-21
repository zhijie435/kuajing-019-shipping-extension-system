-- =============================================
-- 物流扩展体系 - 数据库初始化脚本
-- =============================================

CREATE DATABASE IF NOT EXISTS logistics_extension DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE logistics_extension;

-- 1. 承运商表
CREATE TABLE IF NOT EXISTS `carriers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_code` VARCHAR(64) NOT NULL COMMENT '承运商编码(唯一标识)',
  `carrier_name` VARCHAR(128) NOT NULL COMMENT '承运商名称',
  `carrier_type` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '承运商类型: 1=快递 2=专线 3=邮政 4=海运 5=空运 6=铁路',
  `logo_url` VARCHAR(512) DEFAULT '' COMMENT '承运商Logo地址',
  `contact_name` VARCHAR(64) DEFAULT '' COMMENT '联系人',
  `contact_phone` VARCHAR(32) DEFAULT '' COMMENT '联系电话',
  `contact_email` VARCHAR(128) DEFAULT '' COMMENT '联系邮箱',
  `country` VARCHAR(64) DEFAULT '' COMMENT '所在国家',
  `supported_regions` JSON DEFAULT NULL COMMENT '支持配送的区域列表',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=未启用 1=已启用 2=已停用 3=测试中',
  `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级(越大越优先)',
  `remark` VARCHAR(512) DEFAULT '' COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carrier_code` (`carrier_code`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`carrier_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='承运商表';

-- 2. 承运商接入配置表
CREATE TABLE IF NOT EXISTS `carrier_configs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_id` INT UNSIGNED NOT NULL COMMENT '承运商ID',
  `protocol_type` VARCHAR(32) NOT NULL DEFAULT 'http' COMMENT '协议类型: http/ftp/edi/mq',
  `api_base_url` VARCHAR(512) DEFAULT '' COMMENT 'API基础地址',
  `auth_type` VARCHAR(32) NOT NULL DEFAULT 'api_key' COMMENT '认证方式: api_key/oauth2/basic/token',
  `api_key` VARCHAR(256) DEFAULT '' COMMENT 'API Key',
  `api_secret` VARCHAR(256) DEFAULT '' COMMENT 'API Secret',
  `auth_token` VARCHAR(512) DEFAULT '' COMMENT '认证Token',
  `callback_url` VARCHAR(512) DEFAULT '' COMMENT '轨迹回传回调地址',
  `callback_secret` VARCHAR(256) DEFAULT '' COMMENT '回调签名密钥',
  `timeout_seconds` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT '接口超时时间(秒)',
  `retry_times` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '失败重试次数',
  `rate_limit` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT '每分钟请求限频',
  `extra_config` JSON DEFAULT NULL COMMENT '扩展配置(JSON)',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carrier_id` (`carrier_id`),
  KEY `idx_protocol_type` (`protocol_type`),
  CONSTRAINT `fk_carrier_config_carrier` FOREIGN KEY (`carrier_id`) REFERENCES `carriers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='承运商接入配置表';

-- 3. 承运商服务产品表
CREATE TABLE IF NOT EXISTS `carrier_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_id` INT UNSIGNED NOT NULL COMMENT '承运商ID',
  `product_code` VARCHAR(64) NOT NULL COMMENT '产品编码',
  `product_name` VARCHAR(128) NOT NULL COMMENT '产品名称',
  `service_type` VARCHAR(32) NOT NULL DEFAULT 'standard' COMMENT '服务类型: standard=标准 express=加急 economy=经济',
  `delivery_days_min` INT UNSIGNED DEFAULT NULL COMMENT '最短配送天数',
  `delivery_days_max` INT UNSIGNED DEFAULT NULL COMMENT '最长配送天数',
  `weight_limit_kg` DECIMAL(10,2) DEFAULT NULL COMMENT '重量限制(kg)',
  `supported_countries` JSON DEFAULT NULL COMMENT '支持配送的国家列表',
  `price_config` JSON DEFAULT NULL COMMENT '价格配置(阶梯定价等)',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carrier_product` (`carrier_id`, `product_code`),
  KEY `idx_service_type` (`service_type`),
  CONSTRAINT `fk_carrier_product_carrier` FOREIGN KEY (`carrier_id`) REFERENCES `carriers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='承运商服务产品表';

-- 4. 轨迹回传记录表
CREATE TABLE IF NOT EXISTS `tracking_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tracking_no` VARCHAR(64) NOT NULL COMMENT '运单号',
  `carrier_code` VARCHAR(64) NOT NULL COMMENT '承运商编码',
  `carrier_id` INT UNSIGNED NOT NULL COMMENT '承运商ID',
  `event_code` VARCHAR(64) NOT NULL COMMENT '事件编码(承运商原始编码)',
  `event_desc` VARCHAR(256) DEFAULT '' COMMENT '事件描述',
  `event_time` DATETIME NOT NULL COMMENT '事件发生时间',
  `event_location` VARCHAR(256) DEFAULT '' COMMENT '事件发生地点',
  `event_country` VARCHAR(64) DEFAULT '' COMMENT '事件所在国家',
  `standard_status` VARCHAR(32) NOT NULL DEFAULT 'UNKNOWN' COMMENT '标准化状态: PICKED_UP/IN_TRANSIT/OUT_FOR_DELIVERY/DELIVERED/EXCEPTION/UNKNOWN',
  `order_no` VARCHAR(64) DEFAULT '' COMMENT '关联订单号',
  `raw_data` JSON DEFAULT NULL COMMENT '原始回传数据(JSON)',
  `is_synced` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否已同步到订单: 0=否 1=是',
  `sync_time` DATETIME DEFAULT NULL COMMENT '同步时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tracking_event` (`tracking_no`, `carrier_code`, `event_code`, `event_time`),
  KEY `idx_tracking_no` (`tracking_no`),
  KEY `idx_carrier_code` (`carrier_code`),
  KEY `idx_standard_status` (`standard_status`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_event_time` (`event_time`),
  KEY `idx_is_synced` (`is_synced`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轨迹回传记录表';

-- 5. 轨迹回传回调日志表
CREATE TABLE IF NOT EXISTS `tracking_callback_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_code` VARCHAR(64) NOT NULL COMMENT '承运商编码',
  `request_method` VARCHAR(16) NOT NULL DEFAULT 'POST' COMMENT '请求方法',
  `request_url` VARCHAR(512) DEFAULT '' COMMENT '请求URL',
  `request_headers` JSON DEFAULT NULL COMMENT '请求头',
  `request_body` JSON DEFAULT NULL COMMENT '请求体',
  `response_code` INT UNSIGNED DEFAULT NULL COMMENT 'HTTP响应码',
  `response_body` JSON DEFAULT NULL COMMENT '响应体',
  `process_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '处理状态: 0=待处理 1=处理成功 2=处理失败 3=重试中',
  `error_message` VARCHAR(512) DEFAULT '' COMMENT '错误信息',
  `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已重试次数',
  `ip_address` VARCHAR(64) DEFAULT '' COMMENT '请求来源IP',
  `processing_time_ms` INT UNSIGNED DEFAULT NULL COMMENT '处理耗时(毫秒)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carrier_code` (`carrier_code`),
  KEY `idx_process_status` (`process_status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轨迹回传回调日志表';

-- 6. 物流扩展配置表
CREATE TABLE IF NOT EXISTS `logistics_extension_configs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` VARCHAR(128) NOT NULL COMMENT '配置键',
  `config_value` TEXT DEFAULT NULL COMMENT '配置值',
  `config_group` VARCHAR(64) NOT NULL DEFAULT 'default' COMMENT '配置分组: tracking/carrier/notification/global',
  `value_type` VARCHAR(32) NOT NULL DEFAULT 'string' COMMENT '值类型: string/int/float/bool/json',
  `description` VARCHAR(256) DEFAULT '' COMMENT '配置说明',
  `is_readonly` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否只读: 0=否 1=是',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_key` (`config_key`),
  KEY `idx_config_group` (`config_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='物流扩展配置表';

-- 7. 轨迹状态映射规则表
CREATE TABLE IF NOT EXISTS `tracking_status_mappings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_code` VARCHAR(64) NOT NULL COMMENT '承运商编码',
  `carrier_event_code` VARCHAR(64) NOT NULL COMMENT '承运商原始事件编码',
  `carrier_event_desc` VARCHAR(256) DEFAULT '' COMMENT '承运商事件描述',
  `standard_status` VARCHAR(32) NOT NULL COMMENT '标准化状态',
  `is_exception` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否异常事件: 0=否 1=是',
  `exception_type` VARCHAR(64) DEFAULT '' COMMENT '异常类型',
  `priority` INT NOT NULL DEFAULT 0 COMMENT '映射优先级',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carrier_event` (`carrier_code`, `carrier_event_code`),
  KEY `idx_standard_status` (`standard_status`),
  CONSTRAINT `fk_mapping_carrier` FOREIGN KEY (`carrier_code`) REFERENCES `carriers`(`carrier_code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轨迹状态映射规则表';

-- 8. 初始化物流扩展配置数据
INSERT INTO `logistics_extension_configs` (`config_key`, `config_value`, `config_group`, `value_type`, `description`) VALUES
('tracking_callback_enabled', 'true', 'tracking', 'bool', '是否启用轨迹回传回调'),
('tracking_callback_auth_enabled', 'true', 'tracking', 'bool', '是否启用回调认证'),
('tracking_callback_ip_whitelist', '127.0.0.1,10.0.0.0/8,192.168.0.0/16', 'tracking', 'string', '回调IP白名单'),
('tracking_callback_max_retry', '3', 'tracking', 'int', '回调处理失败最大重试次数'),
('tracking_callback_retry_interval', '60', 'tracking', 'int', '回调重试间隔(秒)'),
('tracking_auto_sync_enabled', 'true', 'tracking', 'bool', '是否启用轨迹自动同步到订单'),
('tracking_event_dedup_enabled', 'true', 'tracking', 'bool', '是否启用轨迹事件去重'),
('tracking_log_retention_days', '90', 'tracking', 'int', '回调日志保留天数'),
('carrier_default_timeout', '30', 'carrier', 'int', '承运商接口默认超时(秒)'),
('carrier_default_retry', '3', 'carrier', 'int', '承运商接口默认重试次数'),
('carrier_default_rate_limit', '100', 'carrier', 'int', '承运商接口默认限频(次/分钟)'),
('carrier_health_check_enabled', 'true', 'carrier', 'bool', '是否启用承运商健康检查'),
('carrier_health_check_interval', '300', 'carrier', 'int', '承运商健康检查间隔(秒)'),
('notification_tracking_exception_enabled', 'true', 'notification', 'bool', '是否启用轨迹异常通知'),
('notification_tracking_exception_email', 'admin@example.com', 'notification', 'string', '轨迹异常通知邮箱'),
('notification_carrier_offline_enabled', 'true', 'notification', 'bool', '是否启用承运商下线通知'),
('notification_carrier_offline_email', 'admin@example.com', 'notification', 'string', '承运商下线通知邮箱'),
('global_log_level', 'info', 'global', 'string', '系统日志级别: debug/info/warning/error'),
('global_api_enabled', 'true', 'global', 'bool', '是否启用API接口'),
('global_maintenance_mode', 'false', 'global', 'bool', '是否启用维护模式');

-- 9. 初始化示例承运商
INSERT INTO `carriers` (`carrier_code`, `carrier_name`, `carrier_type`, `contact_name`, `contact_phone`, `country`, `status`, `priority`, `remark`) VALUES
('SF_EXPRESS', '顺丰速运', 1, '张经理', '400-811-1111', 'CN', 1, 100, '国内快递龙头'),
('FEDEX', 'FedEx联邦快递', 1, 'John Smith', '+1-800-463-3339', 'US', 1, 90, '国际快递巨头'),
('DHL', 'DHL国际快递', 1, 'Hans Mueller', '+49-228-182-0', 'DE', 1, 85, '欧洲快递巨头'),
('UPS', 'UPS联合包裹', 1, 'Mike Johnson', '+1-800-742-5877', 'US', 1, 80, '北美快递巨头'),
('YANWEN', '燕文物流', 2, '李总', '400-108-5656', 'CN', 3, 60, '跨境电商专线');

-- 10. 初始化承运商接入配置
INSERT INTO `carrier_configs` (`carrier_id`, `protocol_type`, `api_base_url`, `auth_type`, `callback_url`, `callback_secret`, `timeout_seconds`, `retry_times`, `rate_limit`) VALUES
(1, 'http', 'https://api.sf-express.com/v1', 'api_key', 'https://your-domain.com/api/tracking/callback/SF_EXPRESS', 'sf_callback_secret_2024', 30, 3, 100),
(2, 'http', 'https://apis.fedex.com/track/v1', 'oauth2', 'https://your-domain.com/api/tracking/callback/FEDEX', 'fedex_callback_secret_2024', 30, 3, 200),
(3, 'http', 'https://api.dhl.com/track/shipments', 'api_key', 'https://your-domain.com/api/tracking/callback/DHL', 'dhl_callback_secret_2024', 30, 3, 150),
(4, 'http', 'https://onlinetools.ups.com/track/v1', 'oauth2', 'https://your-domain.com/api/tracking/callback/UPS', 'ups_callback_secret_2024', 30, 3, 200),
(5, 'http', 'https://api.yanwen.com/v2', 'api_key', 'https://your-domain.com/api/tracking/callback/YANWEN', 'yanwen_callback_secret_2024', 30, 3, 100);

-- 11. 承运商配置历史表
CREATE TABLE IF NOT EXISTS `carrier_config_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_id` INT UNSIGNED NOT NULL COMMENT '承运商ID',
  `config_snapshot` JSON NOT NULL COMMENT '配置快照(JSON)',
  `change_type` VARCHAR(32) NOT NULL DEFAULT 'update' COMMENT '变更类型: create=创建 update=更新 rollback=回滚',
  `operator` VARCHAR(64) DEFAULT '' COMMENT '操作人',
  `change_remark` VARCHAR(256) DEFAULT '' COMMENT '变更说明',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carrier_id` (`carrier_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_config_history_carrier` FOREIGN KEY (`carrier_id`) REFERENCES `carriers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='承运商配置历史表';

-- 12. 轨迹回滚记录表
CREATE TABLE IF NOT EXISTS `tracking_rollback_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `carrier_code` VARCHAR(64) NOT NULL COMMENT '承运商编码',
  `carrier_id` INT UNSIGNED NOT NULL COMMENT '承运商ID',
  `rollback_type` VARCHAR(32) NOT NULL DEFAULT 'time_range' COMMENT '回滚类型: time_range=按时间范围 tracking_no=按运单号 batch=按批次',
  `rollback_scope` JSON DEFAULT NULL COMMENT '回滚范围参数',
  `rollback_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '回滚事件数量',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=待执行 1=执行中 2=成功 3=失败',
  `operator` VARCHAR(64) DEFAULT '' COMMENT '操作人',
  `remark` VARCHAR(256) DEFAULT '' COMMENT '备注',
  `error_message` VARCHAR(512) DEFAULT '' COMMENT '错误信息',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `idx_carrier_code` (`carrier_code`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轨迹回滚记录表';

-- 13. 初始化轨迹状态映射规则
INSERT INTO `tracking_status_mappings` (`carrier_code`, `carrier_event_code`, `carrier_event_desc`, `standard_status`, `is_exception`, `exception_type`, `priority`) VALUES
('SF_EXPRESS', 'COLLECTED', '已揽收', 'PICKED_UP', 0, '', 10),
('SF_EXPRESS', 'IN_TRANSIT', '运输中', 'IN_TRANSIT', 0, '', 10),
('SF_EXPRESS', 'DELIVERING', '派送中', 'OUT_FOR_DELIVERY', 0, '', 10),
('SF_EXPRESS', 'SIGNED', '已签收', 'DELIVERED', 0, '', 10),
('SF_EXPRESS', 'EXCEPTION', '异常', 'EXCEPTION', 1, 'shipping_abnormal', 10),
('FEDEX', 'PU', 'Picked Up', 'PICKED_UP', 0, '', 10),
('FEDEX', 'IT', 'In Transit', 'IN_TRANSIT', 0, '', 10),
('FEDEX', 'OD', 'Out for Delivery', 'OUT_FOR_DELIVERY', 0, '', 10),
('FEDEX', 'DL', 'Delivered', 'DELIVERED', 0, '', 10),
('FEDEX', 'DE', 'Delivery Exception', 'EXCEPTION', 1, 'shipping_abnormal', 10),
('DHL', 'PICKUP', 'Pickup', 'PICKED_UP', 0, '', 10),
('DHL', 'IN_TRANSIT', 'In Transit', 'IN_TRANSIT', 0, '', 10),
('DHL', 'WITH_DELIVERY_COURIER', 'With Delivery Courier', 'OUT_FOR_DELIVERY', 0, '', 10),
('DHL', 'DELIVERED', 'Delivered', 'DELIVERED', 0, '', 10),
('DHL', 'UNABLE_TO_DELIVER', 'Unable to Deliver', 'EXCEPTION', 1, 'shipping_abnormal', 10),
('UPS', 'PICKUP', 'Pickup', 'PICKED_UP', 0, '', 10),
('UPS', 'IN_TRANSIT', 'In Transit', 'IN_TRANSIT', 0, '', 10),
('UPS', 'OUT_FOR_DELIVERY', 'Out for Delivery', 'OUT_FOR_DELIVERY', 0, '', 10),
('UPS', 'DELIVERED', 'Delivered', 'DELIVERED', 0, '', 10),
('UPS', 'EXCEPTION', 'Exception', 'EXCEPTION', 1, 'shipping_abnormal', 10),
('YANWEN', 'PICKED_UP', '已揽收', 'PICKED_UP', 0, '', 10),
('YANWEN', 'IN_TRANSIT', '运输中', 'IN_TRANSIT', 0, '', 10),
('YANWEN', 'OUT_FOR_DELIVERY', '派送中', 'OUT_FOR_DELIVERY', 0, '', 10),
('YANWEN', 'DELIVERED', '已签收', 'DELIVERED', 0, '', 10),
('YANWEN', 'EXCEPTION', '异常', 'EXCEPTION', 1, 'shipping_abnormal', 10);
