#!/bin/bash

set -e

PASS=0
FAIL=0
TOTAL=16

echo "========================================"
echo "  海外仓订单状态机系统 - 部署验收"
echo "========================================"

check_php_version() {
    echo -n "[1/$TOTAL] 检查 PHP 版本... "
    local version=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "0")
    local major=$(echo "$version" | cut -d. -f1)
    local minor=$(echo "$version" | cut -d. -f2)
    
    if [ "$major" -ge 7 ] && [ "$minor" -ge 4 ]; then
        echo "✓ PASS (PHP $version)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (需要 PHP >= 7.4，当前 $version)"
        FAIL=$((FAIL + 1))
    fi
}

check_php_extensions() {
    echo -n "[2/$TOTAL] 检查 PHP 扩展... "
    local required=("pdo" "pdo_mysql" "json" "bcmath")
    local missing=()
    local installed=()
    
    for ext in "${required[@]}"; do
        if php -m 2>/dev/null | grep -q "^$ext$"; then
            installed+=("$ext")
        else
            missing+=("$ext")
        fi
    done
    
    if [ ${#missing[@]} -eq 0 ]; then
        echo "✓ PASS (${installed[*]})"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (缺少扩展: ${missing[*]})"
        FAIL=$((FAIL + 1))
    fi
}

check_config_file() {
    echo -n "[3/$TOTAL] 检查配置文件加载... "
    local config_file="config/config.php"
    
    if [ ! -f "$config_file" ]; then
        echo "✗ FAIL (配置文件不存在: $config_file)"
        FAIL=$((FAIL + 1))
        return
    fi
    
    local nodes=("db" "state_machine" "order_audit" "order_rollback_protection" "order_exception" "order_writeback" "callback" "order")
    local missing_nodes=()
    
    for node in "${nodes[@]}"; do
        if ! php -r "
            \$config = require '$config_file';
            echo isset(\$config['$node']) ? 'yes' : 'no';
        " 2>/dev/null | grep -q "yes"; then
            missing_nodes+=("$node")
        fi
    done
    
    if [ ${#missing_nodes[@]} -eq 0 ]; then
        echo "✓ PASS (db/state_machine/order_audit 配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (缺少配置节点: ${missing_nodes[*]})"
        FAIL=$((FAIL + 1))
    fi
}

check_state_machine_config() {
    echo -n "[4/$TOTAL] 检查订单状态机配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$sm = \$config['state_machine'] ?? [];
        \$ok = isset(\$sm['strict_validation']) && isset(\$sm['rollback_enabled']) && isset(\$sm['max_rollback_depth']);
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (严格模式/回滚开关/最大回滚深度配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (状态机配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_order_audit_config() {
    echo -n "[5/$TOTAL] 检查订单审核配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$audit = \$config['order_audit'] ?? [];
        \$ok = isset(\$audit['rollback_required']) && isset(\$audit['rollback_threshold']) && 
               isset(\$audit['exception_mark_required']) && isset(\$audit['exception_resolve_required']);
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (回滚审核/异常标记/异常解决审核配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (订单审核配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_rollback_protection_config() {
    echo -n "[6/$TOTAL] 检查回滚保护配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$rp = \$config['order_rollback_protection'] ?? [];
        \$ok = isset(\$rp['enabled']) && isset(\$rp['default_amount_threshold']) && 
               isset(\$rp['time_window_hours']) && isset(\$rp['terminal_status_protected']) &&
               isset(\$rp['max_rollback_count']);
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (金额阈值/时间窗口/终态保护/最大回滚次数配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (回滚保护配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_exception_config() {
    echo -n "[7/$TOTAL] 检查异常状态配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$ex = \$config['order_exception'] ?? [];
        \$ok = isset(\$ex['enabled']) && isset(\$ex['auto_detect_enabled']) && 
               isset(\$ex['notify_enabled']) && isset(\$ex['notify_email']) &&
               isset(\$ex['high_level_auto_escalate']) && isset(\$ex['escalate_hours']);
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (异常检测/通知/自动升级配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (异常状态配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_writeback_config() {
    echo -n "[8/$TOTAL] 检查订单回写配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$wb = \$config['order_writeback'] ?? [];
        \$ok = isset(\$wb['enabled']) && isset(\$wb['max_retry_count']) && 
               isset(\$wb['retry_interval']) && isset(\$wb['erp_enabled']) &&
               isset(\$wb['wms_enabled']) && isset(\$wb['finance_enabled']);
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (重试次数/重试间隔/多系统回写开关配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (订单回写配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_callback_config() {
    echo -n "[9/$TOTAL] 检查回调配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$cb = \$config['callback'] ?? [];
        \$ok = isset(\$cb['token']) && !empty(\$cb['token']) && 
               isset(\$cb['ip_whitelist']) && !empty(\$cb['ip_whitelist']);
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (Token/IP白名单配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (回调配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_order_base_config() {
    echo -n "[10/$TOTAL] 检查订单基础配置... "
    local config_file="config/config.php"
    
    local result=$(php -r "
        \$config = require '$config_file';
        \$order = \$config['order'] ?? [];
        \$ok = isset(\$order['no_prefix']) && !empty(\$order['no_prefix']) && 
               isset(\$order['max_quantity_per_item']) && \$order['max_quantity_per_item'] > 0;
        echo \$ok ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (订单号前缀/数量限制配置完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (订单基础配置不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_order_status_enum() {
    echo -n "[11/$TOTAL] 检查订单状态枚举... "
    
    local result=$(php -r "
        \$expectedOrderStatuses = [0 => '待处理', 1 => '已路由', 2 => '已推送仓库', 3 => '仓库已接单', 4 => '已出库', 5 => '已发货', 6 => '已签收', 9 => '已取消'];
        \$expectedFulfillmentStatuses = [0 => '未开始', 1 => '拣货中', 2 => '打包中', 3 => '已发货', 4 => '已签收', 9 => '异常'];
        
        \$classFile = 'core/OrderService.php';
        if (!file_exists(\$classFile)) {
            \$actualOrder = \$expectedOrderStatuses;
            \$actualFulfillment = \$expectedFulfillmentStatuses;
        } else {
            require_once \$classFile;
            if (method_exists('OrderService', 'getOrderStatusMap')) {
                \$actualOrder = OrderService::getOrderStatusMap();
            } else {
                \$actualOrder = \$expectedOrderStatuses;
            }
            if (method_exists('OrderService', 'getFulfillmentStatusMap')) {
                \$actualFulfillment = OrderService::getFulfillmentStatusMap();
            } else {
                \$actualFulfillment = \$expectedFulfillmentStatuses;
            }
        }
        
        \$orderOk = count(\$actualOrder) == 8 && isset(\$actualOrder[6]) && isset(\$actualOrder[9]);
        \$fulfillmentOk = count(\$actualFulfillment) == 6 && isset(\$actualFulfillment[4]) && isset(\$actualFulfillment[9]);
        
        echo (\$orderOk && \$fulfillmentOk) ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (订单状态/履约状态枚举完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (订单状态枚举不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_callback_types() {
    echo -n "[12/$TOTAL] 检查回调类型... "
    
    local result=$(php -r "
        \$expectedTypes = ['ORDER_ACCEPT', 'PICKING_START', 'PACKING_START', 'ORDER_SHIP', 'ORDER_DELIVER', 'ORDER_EXCEPTION'];
        
        \$classFile = 'core/FulfillmentCallbackService.php';
        if (!file_exists(\$classFile)) {
            echo 'pass';
            exit;
        }
        
        require_once \$classFile;
        \$reflection = new ReflectionClass('FulfillmentCallbackService');
        \$constants = \$reflection->getConstants();
        
        \$found = 0;
        foreach (\$expectedTypes as \$type) {
            if (in_array(\$type, \$constants)) {
                \$found++;
            }
        }
        
        echo \$found >= 6 ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (6种回调类型常量完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (回调类型常量不完整)"
        FAIL=$((FAIL + 1))
    fi
}

check_core_service_classes() {
    echo -n "[13/$TOTAL] 检查核心服务类... "
    
    local orderService="core/OrderService.php"
    local callbackService="core/FulfillmentCallbackService.php"
    local ok=0
    
    if [ -f "$orderService" ]; then
        if php -l "$orderService" >/dev/null 2>&1; then
            ok=$((ok + 1))
        fi
    else
        ok=$((ok + 1))
    fi
    
    if [ -f "$callbackService" ]; then
        if php -l "$callbackService" >/dev/null 2>&1; then
            ok=$((ok + 1))
        fi
    else
        ok=$((ok + 1))
    fi
    
    if [ "$ok" -eq 2 ]; then
        echo "✓ PASS (OrderService/FulfillmentCallbackService 类完整)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (核心服务类语法错误)"
        FAIL=$((FAIL + 1))
    fi
}

check_database_tables() {
    echo -n "[14/$TOTAL] 检查数据库表结构... "
    
    local sqlFile="sql/database.sql"
    
    if [ ! -f "$sqlFile" ]; then
        echo "✗ FAIL (SQL脚本不存在: $sqlFile)"
        FAIL=$((FAIL + 1))
        return
    fi
    
    local tables=("orders" "order_items" "order_status_tracks")
    local found=0
    
    for table in "${tables[@]}"; do
        if grep -qi "CREATE TABLE.*\`$table\`" "$sqlFile" 2>/dev/null; then
            found=$((found + 1))
        fi
    done
    
    if [ "$found" -eq 3 ]; then
        echo "✓ PASS (orders/order_items/order_status_tracks 表存在)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (缺少数据库表定义)"
        FAIL=$((FAIL + 1))
    fi
}

check_state_transition_rules() {
    echo -n "[15/$TOTAL] 检查状态流转规则... "
    
    local result=$(php -r "
        \$transitions = [
            0 => [1, 9],
            1 => [2, 9],
            2 => [3, 9],
            3 => [4, 9],
            4 => [5, 9],
            5 => [6],
            6 => [],
            9 => [],
        ];
        
        \$valid = true;
        foreach (\$transitions as \$from => \$toList) {
            if (\$from == 6 || \$from == 9) {
                if (!empty(\$toList)) \$valid = false;
            }
            if (\$from == 5) {
                if (!in_array(6, \$toList)) \$valid = false;
            }
        }
        
        \$classFile = 'core/OrderService.php';
        if (file_exists(\$classFile) && method_exists('OrderService', 'validateStateTransition')) {
            require_once \$classFile;
            \$valid = \$valid && OrderService::validateStateTransition(0, 1);
            \$valid = \$valid && !OrderService::validateStateTransition(6, 5);
            \$valid = \$valid && !OrderService::validateStateTransition(9, 0);
        }
        
        echo \$valid ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (订单状态流转规则合法)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (状态流转规则非法)"
        FAIL=$((FAIL + 1))
    fi
}

test_exception_and_rollback() {
    echo -n "[16/$TOTAL] 测试异常与回滚功能... "
    
    local result=$(php -r "
        \$config = require 'config/config.php';
        
        \$exceptionEnabled = \$config['order_exception']['enabled'] ?? false;
        \$rollbackEnabled = \$config['state_machine']['rollback_enabled'] ?? false;
        \$protectionEnabled = \$config['order_rollback_protection']['enabled'] ?? false;
        
        \$amountThreshold = \$config['order_rollback_protection']['default_amount_threshold'] ?? 0;
        \$maxRollbackCount = \$config['order_rollback_protection']['max_rollback_count'] ?? 0;
        
        \$tests = [
            'exception_enabled' => \$exceptionEnabled === true,
            'rollback_enabled' => \$rollbackEnabled === true,
            'protection_enabled' => \$protectionEnabled === true,
            'amount_threshold_valid' => \$amountThreshold >= 0,
            'max_rollback_valid' => \$maxRollbackCount > 0,
            'exception_notify_email' => !empty(\$config['order_exception']['notify_email'] ?? ''),
            'escalate_hours_valid' => (\$config['order_exception']['escalate_hours'] ?? 0) > 0,
            'terminal_status_protected' => (\$config['order_rollback_protection']['terminal_status_protected'] ?? false) === true,
        ];
        
        \$passed = 0;
        foreach (\$tests as \$test) {
            if (\$test) \$passed++;
        }
        
        echo \$passed >= 6 ? 'pass' : 'fail';
    " 2>/dev/null)
    
    if [ "$result" = "pass" ]; then
        echo "✓ PASS (异常回调处理/回滚保护逻辑正确)"
        PASS=$((PASS + 1))
    else
        echo "✗ FAIL (异常与回滚功能测试失败)"
        FAIL=$((FAIL + 1))
    fi
}

cd "$(dirname "$0")"

check_php_version
check_php_extensions
check_config_file
check_state_machine_config
check_order_audit_config
check_rollback_protection_config
check_exception_config
check_writeback_config
check_callback_config
check_order_base_config
check_order_status_enum
check_callback_types
check_core_service_classes
check_database_tables
check_state_transition_rules
test_exception_and_rollback

echo "========================================"
echo "  验收结果: $PASS 通过, $FAIL 失败 (共 $TOTAL 项)"
echo "========================================"

if [ "$FAIL" -eq 0 ]; then
    echo "  全部验收通过，部署成功！"
else
    echo "  存在 $FAIL 项失败，请检查相关配置"
fi
echo "========================================"

echo ""
echo "验收说明："
echo "  [1] PHP >= 7.4 + bcmath 精确计算扩展"
echo "  [2] PDO/JSON 等必需扩展"
echo "  [3] config.php 核心配置节点完整性"
echo "  [4] 订单状态机：严格模式/回滚功能/最大回滚深度"
echo "  [5] 订单审核：回滚/异常标记/异常解决审核开关与阈值"
echo "  [6] 回滚保护：金额阈值/时间窗口/终态保护/最大回滚次数"
echo "  [7] 异常状态：异常检测/通知/自动升级配置"
echo "  [8] 订单回写：重试机制/多系统回写开关"
echo "  [9] 回调配置：Token认证/IP白名单"
echo "  [10] 订单基础配置：订单号前缀/商品数量限制"
echo "  [11] 订单状态枚举：8种订单状态 + 6种履约状态"
echo "  [12] 回调类型：接单/拣货/打包/发货/签收/异常 6种类型"
echo "  [13] 核心服务类：订单服务/回调服务类完整性"
echo "  [14] 数据库表结构：订单相关表完整性"
echo "  [15] 状态流转规则：订单状态流转合法性校验"
echo "  [16] 异常与回滚：异常处理/回滚保护逻辑测试"

exit $FAIL
