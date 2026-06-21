# 海外仓订单状态机系统 - 部署文档

## 1. 系统概述

海外仓订单状态机系统是跨境电商履约平台的核心模块，负责管理订单全生命周期的状态流转、异常处理、回滚保护和多系统回写。

### 核心功能
- **订单状态机**：待处理 → 已路由 → 已推送仓库 → 仓库已接单 → 已出库 → 已发货 → 已签收 / 已取消 的状态流转管理
- **履约状态机**：未开始 → 拣货中 → 打包中 → 已发货 → 已签收 / 异常 的履约流程管理
- **异常状态管理**：异常标记、异常类型分类、异常等级、异常解决流程
- **回滚保护机制**：金额阈值保护、时间窗口保护、终态保护、人工保护、审核保护
- **多系统回写**：ERP/WMS/CRM/财务系统的状态同步与重试机制
- **审计日志**：全链路操作追踪、状态变更审计、权限校验

---

## 2. 系统要求

| 组件 | 最低版本 | 说明 |
|------|----------|------|
| PHP | >= 7.4 | 需要 bcmath 扩展支持精确计算 |
| MySQL | >= 5.7 | 需要 InnoDB 引擎支持事务 |
| PHP 扩展 | pdo, pdo_mysql, json, bcmath | 必须启用 |

---

## 3. 环境变量配置

所有环境变量均在 `config/config.php` 中通过 `$_ENV` 读取，未配置时使用默认值。

### 3.1 数据库配置

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `DB_HOST` | 127.0.0.1 | 数据库主机地址 |
| `DB_PORT` | 3306 | 数据库端口 |
| `DB_NAME` | overseas_warehouse | 数据库名 |
| `DB_USER` | root | 数据库用户名 |
| `DB_PASS` | (空) | 数据库密码 |

### 3.2 回调配置 (callback)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `CALLBACK_TOKEN` | wh_callback_token_2024 | 仓库回调认证Token |
| `CALLBACK_IP_WHITELIST` | 127.0.0.1,10.0.0.0/8,192.168.0.0/16 | 回调IP白名单，逗号分隔 |

### 3.3 订单基础配置 (order)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `ORDER_NO_PREFIX` | WH | 订单号前缀 |
| `ORDER_MAX_QUANTITY_PER_ITEM` | 999 | 单个商品最大数量限制 |

### 3.4 仓库配置 (warehouse)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WAREHOUSE_DEFAULT_PRIORITY` | 0 | 仓库默认优先级 |

### 3.5 状态机核心配置 (state_machine)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `STATE_MACHINE_STRICT_VALIDATION` | true | 是否启用严格模式（终态校验等） |
| `STATE_MACHINE_ALLOW_FORCE_TRANSITION` | false | 是否允许强制状态变更（绕过校验） |
| `STATE_MACHINE_TRANSITION_LOG_ENABLED` | true | 是否记录状态流转日志 |
| `STATE_MACHINE_ROLLBACK_ENABLED` | true | 是否启用回滚功能 |
| `STATE_MACHINE_MAX_ROLLBACK_DEPTH` | 3 | 最大回滚深度（允许回滚的步数） |

### 3.6 订单审核配置 (order_audit)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `ORDER_AUDIT_ENABLED` | true | 是否启用订单审核功能 |
| `ORDER_AUDIT_ROLLBACK_REQUIRED` | true | 回滚操作是否需要审核 |
| `ORDER_AUDIT_ROLLBACK_THRESHOLD` | 10000.00 | 回滚审核金额阈值（元），超过此金额必须审核 |
| `ORDER_AUDIT_EXCEPTION_MARK_REQUIRED` | false | 标记异常是否需要审核 |
| `ORDER_AUDIT_EXCEPTION_MARK_THRESHOLD` | 0 | 标记异常审核金额阈值（元） |
| `ORDER_AUDIT_EXCEPTION_RESOLVE_REQUIRED` | true | 异常解决是否需要审核 |
| `ORDER_AUDIT_EXCEPTION_RESOLVE_THRESHOLD` | 0 | 异常解决审核金额阈值（元） |
| `ORDER_AUDIT_STATUS_CHANGE_REQUIRED` | false | 状态变更是否需要审核 |
| `ORDER_AUDIT_STATUS_CHANGE_THRESHOLD` | 0 | 状态变更审核金额阈值（元） |
| `ORDER_AUDIT_WRITEBACK_REQUIRED` | false | 回写操作是否需要审核 |
| `ORDER_AUDIT_WRITEBACK_THRESHOLD` | 0 | 回写审核金额阈值（元） |

### 3.7 回滚保护配置 (order_rollback_protection)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `ORDER_ROLLBACK_PROTECTION_ENABLED` | true | 是否启用回滚保护功能 |
| `ORDER_ROLLBACK_PROTECTION_AMOUNT_THRESHOLD` | 50000.00 | 金额阈值保护默认值（元），超过此金额自动启用保护 |
| `ORDER_ROLLBACK_PROTECTION_TIME_WINDOW_HOURS` | 24 | 时间窗口保护时长（小时），订单创建后此时长内启用保护 |
| `ORDER_ROLLBACK_PROTECTION_TERMINAL_STATUS` | true | 是否启用终态保护（终态订单禁止回滚） |
| `ORDER_ROLLBACK_PROTECTION_MAX_ROLLBACK_COUNT` | 3 | 单订单最大允许回滚次数 |

### 3.8 异常状态配置 (order_exception)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `ORDER_EXCEPTION_ENABLED` | true | 是否启用异常状态功能 |
| `ORDER_EXCEPTION_AUTO_DETECT_ENABLED` | true | 是否启用异常自动检测 |
| `ORDER_EXCEPTION_NOTIFY_ENABLED` | true | 是否启用异常通知 |
| `ORDER_EXCEPTION_NOTIFY_EMAIL` | admin@example.com | 异常通知接收邮箱 |
| `ORDER_EXCEPTION_HIGH_LEVEL_AUTO_ESCALATE` | true | 高等级异常是否自动升级 |
| `ORDER_EXCEPTION_ESCALATE_HOURS` | 24 | 异常升级时间阈值（小时），超过此时长未处理自动升级 |

### 3.9 订单回写配置 (order_writeback)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `ORDER_WRITEBACK_ENABLED` | true | 是否启用订单回写功能 |
| `ORDER_WRITEBACK_MAX_RETRY_COUNT` | 3 | 回写失败最大重试次数 |
| `ORDER_WRITEBACK_RETRY_INTERVAL` | 60 | 回写重试间隔（秒） |
| `ORDER_WRITEBACK_ERP_ENABLED` | true | 是否启用 ERP 系统回写 |
| `ORDER_WRITEBACK_WMS_ENABLED` | true | 是否启用 WMS 系统回写 |
| `ORDER_WRITEBACK_CRM_ENABLED` | false | 是否启用 CRM 系统回写 |
| `ORDER_WRITEBACK_FINANCE_ENABLED` | true | 是否启用财务系统回写 |

### 3.10 钱包冻结配置 (wallet.freeze)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_FREEZE_MAX_SINGLE_AMOUNT` | 100000.00 | 单笔冻结最大金额 (元) |
| `WALLET_FREEZE_MAX_DAILY_AMOUNT` | 500000.00 | 单经销商每日冻结累计上限 (元) |
| `WALLET_FREEZE_MAX_COUNT_PER_DEALER` | 50 | 单经销商同时存在的冻结单数量上限 |
| `WALLET_FREEZE_DEFAULT_EXPIRE_HOURS` | 72 | 冻结单默认有效期 (小时) |
| `WALLET_FREEZE_AUTO_UNFREEZE_ENABLED` | true | 是否启用自动解冻 |
| `WALLET_FREEZE_AUTO_UNFREEZE_THRESHOLD_HOURS` | 168 | 自动解冻时间阈值，超过此时长的冻结单自动解冻 (小时，默认7天) |
| `WALLET_FREEZE_ALLOW_PARTIAL_UNFREEZE` | true | 是否允许部分解冻 |
| `WALLET_FREEZE_UNFREEZE_REQUIRES_AUDIT` | false | 解冻操作是否需要审核 |
| `WALLET_FREEZE_DEDUCT_REQUIRES_AUDIT` | false | 冻结资金扣除是否需要审核 |
| `WALLET_FREEZE_NO_PREFIX` | FRZ | 冻结单号前缀 |

### 3.11 余额变更配置 (wallet.balance)

#### 3.11.1 充值配置 (wallet.balance.recharge)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_RECHARGE_MIN_SINGLE_AMOUNT` | 0.01 | 单笔充值最小金额 |
| `WALLET_RECHARGE_MAX_SINGLE_AMOUNT` | 500000.00 | 单笔充值最大金额 |
| `WALLET_RECHARGE_MAX_DAILY_AMOUNT` | 2000000.00 | 每日充值累计上限 |
| `WALLET_RECHARGE_REQUIRES_AUDIT` | false | 充值是否需要审核 |
| `WALLET_RECHARGE_AUDIT_THRESHOLD` | 100000.00 | 充值审核阈值，超过此金额需审核 |

#### 3.11.2 提现配置 (wallet.balance.withdraw)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_WITHDRAW_MIN_SINGLE_AMOUNT` | 1.00 | 单笔提现最小金额 |
| `WALLET_WITHDRAW_MAX_SINGLE_AMOUNT` | 200000.00 | 单笔提现最大金额 |
| `WALLET_WITHDRAW_MAX_DAILY_AMOUNT` | 500000.00 | 每日提现累计上限 |
| `WALLET_WITHDRAW_DAILY_COUNT_LIMIT` | 10 | 每日提现次数上限 |
| `WALLET_WITHDRAW_REQUIRES_AUDIT` | true | 提现是否需要审核 |
| `WALLET_WITHDRAW_AUDIT_THRESHOLD` | 50000.00 | 提现审核阈值 |

#### 3.11.3 消费配置 (wallet.balance.consume)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_CONSUME_MIN_SINGLE_AMOUNT` | 0.01 | 单笔消费最小金额 |
| `WALLET_CONSUME_MAX_SINGLE_AMOUNT` | 100000.00 | 单笔消费最大金额 |
| `WALLET_CONSUME_MAX_DAILY_AMOUNT` | 1000000.00 | 每日消费累计上限 |
| `WALLET_CONSUME_ALLOW_NEGATIVE_BALANCE` | false | 是否允许余额为负（透支） |

#### 3.11.4 退款配置 (wallet.balance.refund)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_REFUND_MAX_SINGLE_AMOUNT` | 100000.00 | 单笔退款最大金额 |
| `WALLET_REFUND_REQUIRES_AUDIT` | true | 退款是否需要审核 |
| `WALLET_REFUND_AUDIT_THRESHOLD` | 10000.00 | 退款审核阈值 |
| `WALLET_REFUND_WITHIN_DAYS` | 90 | 允许退款的时间范围 (天) |

### 3.12 对账配置 (wallet.reconciliation)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_RECONCILIATION_ENABLED` | true | 是否启用对账功能 |
| `WALLET_RECONCILIATION_AUTO_HOUR` | 3 | 自动对账执行时间 (小时，0-23，默认凌晨3点) |
| `WALLET_RECONCILIATION_ALERT_ON_ERROR` | true | 对账异常时是否发送告警 |
| `WALLET_RECONCILIATION_ALERT_EMAIL` | admin@example.com | 异常告警接收邮箱 |
| `WALLET_RECONCILIATION_EXPORT_ENCODING` | UTF-8 | CSV导出编码 |
| `WALLET_RECONCILIATION_MAX_EXPORT_ROWS` | 100000 | 单次导出最大行数 |

### 3.13 钱包状态机配置 (wallet.state_machine)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `WALLET_STATE_MACHINE_STRICT_VALIDATION` | true | 是否启用严格模式（金额负数校验等） |
| `WALLET_STATE_MACHINE_ALLOW_FORCE_TRANSITION` | false | 是否允许强制状态流转（绕过校验） |
| `WALLET_STATE_MACHINE_TRANSITION_LOG_ENABLED` | true | 是否记录状态流转日志 |

### 3.14 安全配置 (wallet.security)

| 环境变量 | 默认值 | 说明 |
|----------|--------|------|
| `OPERATION_PASSWORD_REQUIRED` | true | 大额操作是否需要验证支付密码 |
| `OPERATION_PASSWORD_THRESHOLD` | 10000.00 | 支付密码验证阈值 (元) |
| `TWO_FACTOR_REQUIRED` | false | 是否启用双因素认证 |
| `TWO_FACTOR_THRESHOLD` | 50000.00 | 双因素认证阈值 (元) |
| `IP_WHITELIST_ENABLED` | false | 是否启用IP白名单限制 |

---

## 4. 部署步骤

### 4.1 代码部署

```bash
# 进入项目目录
cd /path/to/project/backend

# 安装依赖 (如果使用 composer)
composer install --no-dev --optimize-autoloader

# 设置目录权限
chmod -R 755 .
chmod -R 777 storage logs  # 如有日志和缓存目录
```

### 4.2 数据库初始化

```bash
# 1. 创建数据库
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS overseas_warehouse DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. 导入数据表结构
mysql -u root -p overseas_warehouse < sql/database.sql

# 3. 如有需要，执行数据初始化
php install.php
```

### 4.3 环境变量设置

#### 方式一：通过 Shell 环境变量

```bash
# 临时设置（当前会话有效）
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=overseas_warehouse
export DB_USER=root
export DB_PASS=your_password

# 回调配置
export CALLBACK_TOKEN=your_callback_token
export CALLBACK_IP_WHITELIST=127.0.0.1,10.0.0.0/8

# 订单状态机配置
export STATE_MACHINE_STRICT_VALIDATION=true
export STATE_MACHINE_ROLLBACK_ENABLED=true
export STATE_MACHINE_MAX_ROLLBACK_DEPTH=3

# 回滚保护配置
export ORDER_ROLLBACK_PROTECTION_AMOUNT_THRESHOLD=50000.00
export ORDER_ROLLBACK_PROTECTION_TIME_WINDOW_HOURS=24
export ORDER_ROLLBACK_PROTECTION_TERMINAL_STATUS=true

# 异常状态配置
export ORDER_EXCEPTION_NOTIFY_EMAIL=admin@company.com
export ORDER_EXCEPTION_HIGH_LEVEL_AUTO_ESCALATE=true

# 回写配置
export ORDER_WRITEBACK_MAX_RETRY_COUNT=3
export ORDER_WRITEBACK_ERP_ENABLED=true
export ORDER_WRITEBACK_WMS_ENABLED=true
export ORDER_WRITEBACK_FINANCE_ENABLED=true
```

#### 方式二：通过 .env 文件（需配合 vlucas/phpdotenv）

```bash
# 创建 .env 文件
cat > .env << 'EOF'
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=overseas_warehouse
DB_USER=root
DB_PASS=your_password

# Callback
CALLBACK_TOKEN=your_callback_token
CALLBACK_IP_WHITELIST=127.0.0.1,10.0.0.0/8

# State Machine
STATE_MACHINE_STRICT_VALIDATION=true
STATE_MACHINE_ROLLBACK_ENABLED=true
STATE_MACHINE_MAX_ROLLBACK_DEPTH=3

# Order Audit
ORDER_AUDIT_ROLLBACK_REQUIRED=true
ORDER_AUDIT_ROLLBACK_THRESHOLD=10000.00
ORDER_AUDIT_EXCEPTION_RESOLVE_REQUIRED=true

# Rollback Protection
ORDER_ROLLBACK_PROTECTION_ENABLED=true
ORDER_ROLLBACK_PROTECTION_AMOUNT_THRESHOLD=50000.00
ORDER_ROLLBACK_PROTECTION_TIME_WINDOW_HOURS=24
ORDER_ROLLBACK_PROTECTION_TERMINAL_STATUS=true
ORDER_ROLLBACK_PROTECTION_MAX_ROLLBACK_COUNT=3

# Exception
ORDER_EXCEPTION_ENABLED=true
ORDER_EXCEPTION_NOTIFY_EMAIL=admin@company.com
ORDER_EXCEPTION_HIGH_LEVEL_AUTO_ESCALATE=true

# Writeback
ORDER_WRITEBACK_ENABLED=true
ORDER_WRITEBACK_MAX_RETRY_COUNT=3
ORDER_WRITEBACK_ERP_ENABLED=true
ORDER_WRITEBACK_WMS_ENABLED=true
ORDER_WRITEBACK_FINANCE_ENABLED=true
EOF
```

---

## 5. 验收命令

### 5.1 执行完整验收

```bash
cd /path/to/project/backend
chmod +x acceptance.sh
./acceptance.sh
```

### 5.2 验收项说明

验收脚本包含以下 **16 项** 检查：

| 序号 | 验收项 | 说明 |
|------|--------|------|
| 1 | PHP 版本检查 | 验证 PHP >= 7.4 版本要求 |
| 2 | PHP 扩展检查 | 验证 pdo, pdo_mysql, json, bcmath 扩展已安装 |
| 3 | 配置文件加载检查 | 验证 config.php 配置节点完整性 |
| 4 | 订单状态机配置检查 | 验证 state_machine 核心配置（回滚开关、最大回滚深度等） |
| 5 | 订单审核配置检查 | 验证 order_audit 审核配置（回滚审核、异常审核等） |
| 6 | 回滚保护配置检查 | 验证 order_rollback_protection 保护配置完整性 |
| 7 | 异常状态配置检查 | 验证 order_exception 异常配置完整性 |
| 8 | 订单回写配置检查 | 验证 order_writeback 回写配置完整性 |
| 9 | 回调配置检查 | 验证 callback Token 和 IP 白名单配置 |
| 10 | 订单基础配置检查 | 验证 order 基础配置（订单号前缀、数量限制等） |
| 11 | 订单状态枚举检查 | 验证订单状态和履约状态枚举完整性 |
| 12 | 回调类型检查 | 验证回调类型常量完整性 |
| 13 | 核心服务类检查 | 验证 OrderService/FulfillmentCallbackService 类完整性 |
| 14 | 数据库表结构检查 | 验证 orders/order_items/order_status_tracks 表存在 |
| 15 | 状态流转规则检查 | 验证订单状态流转规则合法性 |
| 16 | 异常与回滚功能测试 | 验证异常回调处理、回滚保护逻辑正确性 |

### 5.3 单项验收（仅运行单元测试）

```bash
# 运行全部单元测试
php tests/run.php

# 运行指定测试类
php tests/run.php WarehouseRouterTest
php tests/run.php WalletFreezeTest
php tests/run.php WalletBalanceTest
php tests/run.php WalletStateMachineTest
```

### 5.4 验收输出示例

```
========================================
  海外仓订单状态机系统 - 部署验收
========================================
[1/16] 检查 PHP 版本... ✓ PASS (PHP 7.4.33)
[2/16] 检查 PHP 扩展... ✓ PASS (pdo, pdo_mysql, json, bcmath)
[3/16] 检查配置文件加载... ✓ PASS (db/state_machine/order_audit 配置完整)
[4/16] 检查订单状态机配置... ✓ PASS (严格模式/回滚开关/最大回滚深度配置完整)
[5/16] 检查订单审核配置... ✓ PASS (回滚审核/异常标记/异常解决审核配置完整)
[6/16] 检查回滚保护配置... ✓ PASS (金额阈值/时间窗口/终态保护/最大回滚次数配置完整)
[7/16] 检查异常状态配置... ✓ PASS (异常检测/通知/自动升级配置完整)
[8/16] 检查订单回写配置... ✓ PASS (重试次数/重试间隔/多系统回写开关配置完整)
[9/16] 检查回调配置... ✓ PASS (Token/IP白名单配置完整)
[10/16] 检查订单基础配置... ✓ PASS (订单号前缀/数量限制配置完整)
[11/16] 检查订单状态枚举... ✓ PASS (订单状态/履约状态枚举完整)
[12/16] 检查回调类型... ✓ PASS (6种回调类型常量完整)
[13/16] 检查核心服务类... ✓ PASS (OrderService/FulfillmentCallbackService 类完整)
[14/16] 检查数据库表结构... ✓ PASS (orders/order_items/order_status_tracks 表存在)
[15/16] 检查状态流转规则... ✓ PASS (订单状态流转规则合法)
[16/16] 测试异常与回滚功能... ✓ PASS (异常回调处理/回滚保护逻辑正确)
========================================
  验收结果: 16 通过, 0 失败 (共 16 项)
========================================
  全部验收通过，部署成功！
========================================
```

### 5.5 验收脚本说明

```
验收说明：
  [1] PHP >= 7.4 + bcmath 精确计算扩展
  [2] PDO/JSON 等必需扩展
  [3] config.php 核心配置节点完整性
  [4] 订单状态机：严格模式/回滚功能/最大回滚深度
  [5] 订单审核：回滚/异常标记/异常解决审核开关与阈值
  [6] 回滚保护：金额阈值/时间窗口/终态保护/最大回滚次数
  [7] 异常状态：异常检测/通知/自动升级配置
  [8] 订单回写：重试机制/多系统回写开关
  [9] 回调配置：Token认证/IP白名单
  [10] 订单基础配置：订单号前缀/商品数量限制
  [11] 订单状态枚举：8种订单状态 + 6种履约状态
  [12] 回调类型：接单/拣货/打包/发货/签收/异常 6种类型
  [13] 核心服务类：订单服务/回调服务类完整性
  [14] 数据库表结构：订单相关表完整性
  [15] 状态流转规则：订单状态流转合法性校验
  [16] 异常与回滚：异常处理/回滚保护逻辑测试
```

---

## 6. 订单状态流转说明

### 6.1 订单状态定义

| 状态值 | 状态名称 | 说明 | 触发条件 |
|--------|----------|------|----------|
| 0 | 待处理 | 订单创建初始状态 | 订单刚创建，尚未分配仓库 |
| 1 | 已路由 | 已分配仓库 | 仓库路由成功，确定履约仓库 |
| 2 | 已推送仓库 | 已推送到WMS | 订单信息已推送到仓库系统 |
| 3 | 仓库已接单 | WMS已确认接单 | 仓库系统确认收到订单 |
| 4 | 已出库 | 商品已出库 | 商品从仓库出库完成 |
| 5 | 已发货 | 已交付物流 | 包裹已交付物流公司 |
| 6 | 已签收 | 已完成签收 | 客户已签收包裹 |
| 9 | 已取消 | 订单已取消 | 订单被取消 |

### 6.2 履约状态定义

| 状态值 | 状态名称 | 说明 | 触发条件 |
|--------|----------|------|----------|
| 0 | 未开始 | 履约未开始 | 订单刚创建 |
| 1 | 拣货中 | 仓库拣货中 | 仓库开始拣货作业 |
| 2 | 打包中 | 仓库打包中 | 仓库开始打包作业 |
| 3 | 已发货 | 已交付物流 | 包裹已交付物流公司 |
| 4 | 已签收 | 已完成签收 | 客户已签收包裹 |
| 9 | 异常 | 履约异常 | 履约过程出现异常 |

### 6.3 合法状态流转

#### 订单状态流转

```
待处理(0) → 已路由(1) → 已推送仓库(2) → 仓库已接单(3) → 已出库(4) → 已发货(5) → 已签收(6)
    ↓            ↓            ↓              ↓            ↓
  已取消(9)    已取消(9)    已取消(9)      已取消(9)    已取消(9)
```

| 当前状态 | 可流转到 | 说明 |
|----------|----------|------|
| 待处理(0) | 已路由(1), 已取消(9) | 路由分配或取消订单 |
| 已路由(1) | 已推送仓库(2), 已取消(9) | 推送WMS或取消订单 |
| 已推送仓库(2) | 仓库已接单(3), 已取消(9) | WMS确认接单或取消订单 |
| 仓库已接单(3) | 已出库(4), 已取消(9) | 商品出库或取消订单 |
| 已出库(4) | 已发货(5), 已取消(9) | 交付物流或取消订单 |
| 已发货(5) | 已签收(6) | 客户签收 |
| 已签收(6) | - | 终态，不可变更 |
| 已取消(9) | - | 终态，不可变更 |

#### 履约状态流转

```
未开始(0) → 拣货中(1) → 打包中(2) → 已发货(3) → 已签收(4)
                                  ↓
                                异常(9)
```

### 6.4 回调类型说明

| 回调类型 | 说明 | 触发操作 |
|----------|------|----------|
| ORDER_ACCEPT | 仓库接单 | WMS确认收到订单，更新订单状态为"仓库已接单" |
| PICKING_START | 开始拣货 | 仓库开始拣货，更新履约状态为"拣货中" |
| PACKING_START | 开始打包 | 仓库开始打包，更新履约状态为"打包中" |
| ORDER_SHIP | 订单发货 | 包裹交付物流，更新状态为"已发货"，释放锁定库存 |
| ORDER_DELIVER | 订单签收 | 客户签收，更新状态为"已签收" |
| ORDER_EXCEPTION | 订单异常 | 履约异常，更新履约状态为"异常"，记录异常原因 |

---

## 7. 异常状态管理

### 7.1 异常类型 (ExceptionType)

系统支持 7 种异常类型，覆盖订单全生命周期可能出现的异常场景：

| 类型值 | 类型名称 | 说明 |
|--------|----------|------|
| `payment_abnormal` | 支付异常 | 支付过程出现异常，如重复支付、金额不符、支付超时等 |
| `shipping_abnormal` | 物流异常 | 物流配送异常，如地址错误、包裹丢失、配送延迟等 |
| `system_abnormal` | 系统异常 | 系统处理异常，如接口调用失败、数据不一致等 |
| `manual_handling` | 需人工处理 | 需要人工介入处理的特殊情况 |
| `inventory_abnormal` | 库存异常 | 库存异常，如超卖、库存不足等 |
| `refund_abnormal` | 退款异常 | 退款流程异常，如退款失败、金额错误等 |
| `other` | 其他异常 | 其他未分类的异常情况 |

### 7.2 异常等级 (ExceptionLevel)

异常等级用于标识异常的严重程度，决定是否需要审核以及处理优先级：

| 等级值 | 等级名称 | 颜色标识 | 是否需审核 | 说明 |
|--------|----------|----------|------------|------|
| 0 | 无异常 | `#52c41a` | 否 | 正常状态 |
| 1 | 低 | `#faad14` | 否 | 轻微异常，不影响主流程 |
| 2 | 中 | `#fa8c16` | 是 | 中等异常，需要审核处理 |
| 3 | 高 | `#f5222d` | 是 | 严重异常，优先处理 |
| 4 | 严重 | `#722ed1` | 是 | 致命异常，需立即处理 |

### 7.3 异常回调处理流程

当仓库系统回调 `ORDER_EXCEPTION` 时：

1. **参数校验**：验证回调Token、IP白名单、订单与仓库匹配性
2. **状态更新**：将订单履约状态更新为 `9（异常）`
3. **记录信息**：保存异常原因、异常类型到订单表
4. **状态追踪**：在 `order_status_tracks` 表记录异常操作
5. **审计日志**：记录状态变更审计日志
6. **异常通知**：根据配置发送邮件通知相关人员
7. **自动升级**：高等级异常超过 `ORDER_EXCEPTION_ESCALATE_HOURS` 未处理自动升级

### 7.4 异常状态下的操作限制

订单履约状态为 `异常(9)` 时：
- ✅ 允许执行：异常解决、人工干预
- ❌ 禁止执行：正常状态流转操作（发货、签收等）

---

## 8. 回滚保护机制

### 8.1 回滚保护类型 (RollbackProtectionType)

系统支持 5 种回滚保护类型，可组合使用：

| 类型值 | 类型名称 | 说明 |
|--------|----------|------|
| `amount_threshold` | 金额阈值保护 | 订单金额超过设定阈值时启用回滚保护 |
| `time_window` | 时间窗口保护 | 在特定时间窗口内启用回滚保护 |
| `terminal_status` | 终态保护 | 订单到达终态时启用回滚保护 |
| `manual_protect` | 人工保护 | 人工手动设置的回滚保护 |
| `audit_required` | 需审核保护 | 需要审核通过才能回滚 |

### 8.2 保护规则说明

#### 8.2.1 金额阈值保护
- 当订单金额 >= `ORDER_ROLLBACK_PROTECTION_AMOUNT_THRESHOLD` 时，自动启用保护
- 启用后，回滚操作必须经过审核流程

#### 8.2.2 时间窗口保护
- 订单创建后 `ORDER_ROLLBACK_PROTECTION_TIME_WINDOW_HOURS` 小时内启用保护
- 超过时间窗口后保护自动失效

#### 8.2.3 终态保护
- 当 `ORDER_ROLLBACK_PROTECTION_TERMINAL_STATUS = true` 时，终态订单（已签收/已取消）禁止回滚
- 终态保护优先级最高，不受其他保护类型影响

#### 8.2.4 最大回滚次数保护
- 单订单最多允许回滚 `ORDER_ROLLBACK_PROTECTION_MAX_ROLLBACK_COUNT` 次
- 超过次数后禁止继续回滚

### 8.3 回滚审核流程

当订单需要审核才能回滚时，流程如下：

```
申请回滚 → 提交审核申请 → 审核通过 → 执行回滚
                    ↓
                  审核拒绝 → 终止流程
```

审核状态说明：

| 状态值 | 状态名称 | 说明 |
|--------|----------|------|
| `pending` | 待审核 | 审核申请已提交，等待处理 |
| `approved` | 已通过 | 审核通过，可执行回滚 |
| `rejected` | 已拒绝 | 审核拒绝，不可执行回滚 |
| `cancelled` | 已取消 | 申请人取消审核申请 |

### 8.4 回滚限制条件

执行回滚操作前必须满足：
- 回滚功能已启用（`STATE_MACHINE_ROLLBACK_ENABLED = true`）
- 回滚栈非空（有可回滚的历史记录）
- 未达到最大回滚深度限制（`STATE_MACHINE_MAX_ROLLBACK_DEPTH`）
- 订单无有效的回滚保护
- 操作用户拥有回滚权限

---

## 9. 常用运维命令

### 9.1 订单状态查询

```bash
# 查询订单详情
php -r "
require 'core/OrderService.php';
\$service = new OrderService();
\$order = \$service->getOrderDetail('WH2024010100001');
print_r(\$order);
"
```

### 9.2 异常订单处理

```bash
# 查询异常订单列表
php -r "
require 'core/OrderService.php';
\$service = new OrderService();
\$result = \$service->listOrders(['order_status' => 9], 1, 50);
echo '异常订单数量: ' . \$result['total'] . PHP_EOL;
foreach (\$result['list'] as \$order) {
    echo '订单号: ' . \$order['order_no'] . ', 异常原因: ' . \$order['exception_reason'] . PHP_EOL;
}
"
```

### 9.3 回调日志查询

```bash
# 查询订单回调日志
php -r "
require 'core/FulfillmentCallbackService.php';
\$service = new FulfillmentCallbackService();
\$logs = \$service->getCallbackLogs('WH2024010100001');
print_r(\$logs);
"
```

### 9.4 手动触发回写

```bash
# 手动触发订单回写（需封装CLI脚本实现）
php -r "
require 'core/OrderService.php';
// 自定义回写逻辑
"
```

---

## 10. 故障排查

### 10.1 验收失败常见原因

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| 配置加载失败 | 缺少 state_machine/order_audit 配置节点 | 检查 `config/config.php` 是否已更新为最新版本 |
| 状态机配置检查失败 | STATE_MACHINE_ROLLBACK_ENABLED 未设置 | 确认环境变量或配置文件中已设置回滚开关 |
| 回滚保护配置检查失败 | ORDER_ROLLBACK_PROTECTION_* 配置缺失 | 检查回滚保护相关环境变量是否正确配置 |
| 异常状态配置检查失败 | ORDER_EXCEPTION_NOTIFY_EMAIL 未设置 | 配置异常通知邮箱 |
| 订单状态枚举检查失败 | OrderService 中缺少状态常量 | 确认 OrderService::getOrderStatusMap() 包含所有状态 |
| 回调类型检查失败 | FulfillmentCallbackService 缺少回调类型常量 | 确认所有 6 种回调类型常量已定义 |
| 数据库表结构检查失败 | orders/order_items 表不存在 | 执行 `sql/database.sql` 初始化数据库 |

### 10.2 异常回调处理失败

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| Token验证失败 | CALLBACK_TOKEN 配置不匹配 | 确认仓库系统使用的Token与配置一致 |
| IP白名单验证失败 | 回调IP不在白名单内 | 将仓库系统公网IP添加到 `CALLBACK_IP_WHITELIST` |
| 订单不存在 | 订单号错误或订单未创建 | 检查订单是否已正确创建 |
| 仓库不匹配 | 回调仓库与订单所属仓库不一致 | 确认订单路由分配的仓库是否正确 |

### 10.3 回滚操作失败

| 问题 | 可能原因 | 解决方案 |
|------|----------|----------|
| 回滚功能未启用 | STATE_MACHINE_ROLLBACK_ENABLED = false | 修改配置启用回滚功能 |
| 达到最大回滚深度 | 已回滚次数达到 STATE_MACHINE_MAX_ROLLBACK_DEPTH | 联系管理员进行特殊处理 |
| 订单受回滚保护 | 订单金额超过阈值或处于时间窗口内 | 提交审核申请，审核通过后执行回滚 |
| 终态订单禁止回滚 | 订单已到达终态（已签收/已取消） | 终态订单不支持回滚操作 |
| 无回滚历史 | 回滚栈为空 | 订单状态尚未变更，无需回滚 |

### 10.4 联系支持

如遇无法解决的问题，请提供以下信息：
- `php -v` 输出
- `php -m` 输出（已安装扩展列表）
- `./acceptance.sh` 完整输出
- 相关日志文件
- 具体的错误信息和复现步骤
