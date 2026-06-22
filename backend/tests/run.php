<?php

$checks = [];
$passed = 0;
$failed = 0;

function check(string $name, bool $result, string $detail = ''): void
{
    global $checks, $passed, $failed;
    $checks[] = ['name' => $name, 'result' => $result, 'detail' => $detail];
    if ($result) $passed++; else $failed++;
}

check('PHP版本 >= 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), '当前: ' . PHP_VERSION);

$requiredExts = ['pdo', 'pdo_mysql', 'json', 'bcmath', 'curl'];
$missing = array_filter($requiredExts, fn($e) => !extension_loaded($e));
check('PHP扩展检查', empty($missing), $missing ? '缺少: ' . implode(', ', $missing) : '全部已安装');

$config = require __DIR__ . '/../config/config.php';
check('配置文件加载', !empty($config) && isset($config['db']), $config ? '配置节点完整' : '加载失败');

check('轨迹回调配置', isset($config['tracking']['callback_enabled']), 'callback_enabled: ' . var_export($config['tracking']['callback_enabled'] ?? null, true));
check('承运商配置', isset($config['carrier']['default_timeout']), 'default_timeout: ' . ($config['carrier']['default_timeout'] ?? ''));
check('通知配置', isset($config['notification']['tracking_exception_enabled']), 'tracking_exception_enabled: ' . var_export($config['notification']['tracking_exception_enabled'] ?? null, true));
check('全局配置', isset($config['global']['api_enabled']), 'api_enabled: ' . var_export($config['global']['api_enabled'] ?? null, true));

$constants = [
    'CarrierStatus::ENABLED' => CarrierStatus::ENABLED,
    'CarrierType::EXPRESS' => CarrierType::EXPRESS,
    'TrackingStandardStatus::DELIVERED' => TrackingStandardStatus::DELIVERED,
    'ProtocolType::HTTP' => ProtocolType::HTTP,
    'AuthType::API_KEY' => AuthType::API_KEY,
    'ServiceType::STANDARD' => ServiceType::STANDARD,
];
check('常量定义检查', count(array_filter($constants, fn($v) => $v !== null)) === count($constants), count($constants) . '项常量');

$serviceFiles = [
    __DIR__ . '/../services/CarrierService.php',
    __DIR__ . '/../services/TrackingService.php',
    __DIR__ . '/../services/ExtensionConfigService.php',
    __DIR__ . '/../services/CarrierAdapter.php',
];
$allServicesExist = array_filter($serviceFiles, 'file_exists');
check('核心服务类文件', count($allServicesExist) === count($serviceFiles), count($allServicesExist) . '/' . count($serviceFiles) . ' 文件存在');

$controllerFiles = [
    __DIR__ . '/../controllers/CarrierController.php',
    __DIR__ . '/../controllers/TrackingController.php',
    __DIR__ . '/../controllers/ExtensionConfigController.php',
    __DIR__ . '/../controllers/MetaController.php',
];
$allControllersExist = array_filter($controllerFiles, 'file_exists');
check('控制器文件', count($allControllersExist) === count($controllerFiles), count($allControllersExist) . '/' . count($controllerFiles) . ' 文件存在');

check('承运商状态枚举', count(CarrierStatus::getLabels()) === 4, count(CarrierStatus::getLabels()) . '种状态');
check('承运商类型枚举', count(CarrierType::getLabels()) === 6, count(CarrierType::getLabels()) . '种类型');
check('轨迹标准状态枚举', count(TrackingStandardStatus::getLabels()) === 6, count(TrackingStandardStatus::getLabels()) . '种状态');

$sqlFile = __DIR__ . '/../sql/database.sql';
check('数据库Schema文件', file_exists($sqlFile), $sqlFile);

echo "========================================\n";
echo "  物流扩展体系 - 部署验收\n";
echo "========================================\n";

foreach ($checks as $i => $c) {
    $num = $i + 1;
    $total = count($checks);
    $mark = $c['result'] ? '✓ PASS' : '✗ FAIL';
    echo "[{$num}/{$total}] {$c['name']}... {$mark}";
    if ($c['detail']) echo " ({$c['detail']})";
    echo "\n";
}

echo "========================================\n";
echo "  验收结果: {$passed} 通过, {$failed} 失败 (共 " . count($checks) . " 项)\n";
echo "========================================\n";

if ($failed === 0) {
    echo "  全部验收通过，部署成功！\n";
} else {
    echo "  存在失败项，请检查后重新验收。\n";
}
echo "========================================\n";

exit($failed > 0 ? 1 : 0);
