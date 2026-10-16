<?php

declare(strict_types=1);

class IPSModuleStrict
{
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Proxon FWT System Monitoring' . DIRECTORY_SEPARATOR . 'module.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Proxon T300 Control' . DIRECTORY_SEPARATOR . 'module.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Proxon T300 Monitoring' . DIRECTORY_SEPARATOR . 'module.php';

function assertMonitoringGroups(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$reflection = new ReflectionClass(ProxonFWTSystemMonitoring::class);
$instance = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('GetMonitoringReadGroups');
$groups = $method->invoke($instance);

$expectedGroups = [
    '3:467x13', '4:19x2', '4:25x1', '4:47x5', '4:152x6', '4:162x1', '4:170x3', '4:174x1',
    '4:184x4', '4:190x1', '4:201x1', '4:213x1', '4:216x1', '4:233x1', '4:237x1',
    '4:267x2'
];
$actualGroups = [];
$definitionCount = 0;
foreach ($groups as $group) {
    $quantity = count($group['definitions']);
    $actualGroups[] = $group['function'] . ':' . $group['address'] . 'x' . $quantity;
    foreach ($group['definitions'] as $offset => $entry) {
        assertMonitoringGroups(
            $entry['definition']['function'] === $group['function'] &&
                $entry['definition']['address'] === $group['address'] + $offset,
            'A read group must contain only consecutive registers of one function code.'
        );
        $definitionCount++;
    }
}

assertMonitoringGroups(
    $actualGroups === $expectedGroups,
    'Only documented contiguous register runs may be batched; got ' . implode(', ', $actualGroups)
);
assertMonitoringGroups($definitionCount === 44, 'Every monitoring definition must appear in exactly one read group.');

$groupCounts = [];
$groupRanges = [];
foreach ([
    [ProxonT300Control::class, 'GetControlDefinitions', 'GetRegisterGroups'],
    [ProxonT300Monitoring::class, 'GetInputDefinitions', 'GetInputRegisterGroups']
] as [$className, $definitionMethodName, $groupMethodName]) {
    $reflection = new ReflectionClass($className);
    $instance = $reflection->newInstanceWithoutConstructor();
    $definitionMethod = $reflection->getMethod($definitionMethodName);
    $definitions = $definitionMethod->invoke($instance);
    $groupMethod = $reflection->getMethod($groupMethodName);
    $registerGroups = $groupMethod->invoke($instance);
    $groupCounts[$className] = count($registerGroups);
    $functionCode = $className === ProxonT300Control::class ? 3 : 4;
    $groupRanges[$className] = array_map(
        static fn (array $group): string => 'FC' . $functionCode . ':' . $group['address'] . 'x' . $group['quantity'],
        $registerGroups
    );
    $groupedAddresses = [];

    foreach ($registerGroups as $group) {
        assertMonitoringGroups(
            $group['quantity'] === count($group['definitions']),
            $className . ': each read block must contain exactly its defined registers.'
        );
        foreach ($group['definitions'] as $offset => $entry) {
            $address = $group['address'] + $offset;
            assertMonitoringGroups(
                $entry['definition']['address'] === $address,
                $className . ': read blocks must not cross unsupported address gaps.'
            );
            $groupedAddresses[] = $address;
        }
    }

    $definitionAddresses = array_map(static fn(array $definition): int => $definition['address'], array_values($definitions));
    sort($definitionAddresses);
    sort($groupedAddresses);
    assertMonitoringGroups(
        $groupedAddresses === $definitionAddresses,
        $className . ': every defined register must be read exactly once.'
    );
}

echo 'Contiguous-register grouping checks passed: FWT Monitoring=' . count($groups)
    . ', T300 Control=' . $groupCounts[ProxonT300Control::class]
    . ', T300 Monitoring=' . $groupCounts[ProxonT300Monitoring::class] . ' ['
    . implode(', ', $groupRanges[ProxonT300Monitoring::class]) . "].\n";
