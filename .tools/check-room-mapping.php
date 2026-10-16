<?php

declare(strict_types=1);

// Isolated fixtures only. This script does not connect to an IP-Symcon kernel.
const KR_READY = 10103;
const IPS_KERNELSTARTED = 10001;
const VM_UPDATE = 10603;
const KL_WARNING = 1;
$runlevel = KR_READY;
$reads = [];
$semaphores = [];
$instances = [
    100 => ['name' => 'Control', 'module' => 'control'],
    200 => ['name' => 'Native Zone', 'module' => '{9496FF42-B793-02E3-8271-541651A9085F}'],
    300 => ['name' => 'Native Panel', 'module' => '{82991C5F-F554-4ED2-B2C7-2B2999B32C46}'],
    400 => ['name' => 'Central Living Room', 'module' => '{1D1DC6F5-A07B-FC2B-84E4-D68E6B71D401}']
];
$variables = [];
function fixture(int $id, int $parent, string $ident, mixed $value, int $updated = 1): void
{
    $GLOBALS['variables'][$id] = compact('parent', 'ident', 'value', 'updated');
}
fixture(101, 100, 'CO2Sensor1Value', 710);
fixture(102, 100, 'CO2Sensor1RelativeHumidity', 43);
fixture(201, 200, 'CurrentTemperature', 21.4);
fixture(202, 200, 'TargetTemperature', 22);
fixture(203, 200, 'PTCRelease', true);
fixture(301, 300, 'CurrentTemperature', 20.2);
fixture(302, 300, 'TargetTemperature', 20);
fixture(303, 300, 'PTCStatus', false);
fixture(401, 400, 'CurrentTemperature', 22.1);
fixture(402, 400, 'TargetTemperature', 22.5);
fixture(403, 400, 'PTCRelease', true);
fixture(501, 0, 'ExternalCO2', 850);
fixture(502, 0, 'ExternalHumidity', 57.2);
fixture(503, 0, 'ExternalPTC', true);
fixture(504, 0, 'InvalidCO2', 'unavailable');
fixture(505, 0, 'UnselectedVariable', 9999);
fixture(506, 0, 'Uninitialized', 0, 0);
fixture(507, 0, 'ZeroHumidity', 0.0);
function IPS_GetKernelRunlevel(): int { return $GLOBALS['runlevel']; }
function IPS_SemaphoreEnter(string $name, int $waitingTime): bool
{
    if (isset($GLOBALS['semaphores'][$name])) return false;
    $GLOBALS['semaphores'][$name] = true;
    return true;
}
function IPS_SemaphoreLeave(string $name): void { unset($GLOBALS['semaphores'][$name]); }
function IPS_InstanceExists(int $id): bool { return isset($GLOBALS['instances'][$id]); }
function IPS_GetInstance(int $id): array { return ['ModuleInfo' => ['ModuleID' => $GLOBALS['instances'][$id]['module']]]; }
function IPS_GetProperty(int $id, string $name): mixed { return $name === 'Interval' ? 60 : null; }
function IPS_GetName(int $id): string { return $GLOBALS['instances'][$id]['name']; }
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['variables'][$id]); }
function IPS_GetChildrenIDs(int $parent): array { return array_keys(array_filter($GLOBALS['variables'], fn (array $v): bool => $v['parent'] === $parent)); }
function IPS_GetObject(int $id): array { return ['ObjectIdent' => $GLOBALS['variables'][$id]['ident']]; }
function IPS_GetVariable(int $id): array { return ['VariableUpdated' => $GLOBALS['variables'][$id]['updated']]; }
function GetValue(int $id): mixed { $GLOBALS['reads'][] = $id; return $GLOBALS['variables'][$id]['value']; }
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTVisualizationRooms.php';

class RoomHarness
{
    use FWTVisualizationRooms;
    public int $InstanceID = 100;
    public array $attributes = ['VisualizationRoomReferences' => '[]', 'VisualizationRoomMessages' => '[]', 'SensorActivationTimestamps' => '{}'];
    public string $mapping = '[]';
    public array $references = [];
    public array $messages = [];
    public array $timers = [];
    public array $logs = [];
    public array $updates = [];
    public function rooms(): array { return $this->GetVisualizationRooms(); }
    public function bind(): void { $this->UpdateVisualizationRoomBindings(); }
    public function ReadPropertyString(string $name): string { return $this->mapping; }
    public function ReadPropertyInteger(string $name): int { return 60; }
    public function ReadAttributeString(string $name): string { return $this->attributes[$name]; }
    public function WriteAttributeString(string $name, string $value): void { $this->attributes[$name] = $value; }
    public function RegisterReference(int $id): void { $this->references[$id] = true; }
    public function UnregisterReference(int $id): void { unset($this->references[$id]); }
    public function RegisterMessage(int $id, int $message): void { $this->messages[$id] = $message; }
    public function UnregisterMessage(int $id, int $message): void { unset($this->messages[$id]); }
    public function SetTimerInterval(string $name, int $interval): void { $this->timers[$name] = $interval; }
    public function UpdateVisualizationValue(mixed $value): void
    {
        if (!is_scalar($value)) {
            throw new TypeError('Cannot auto-convert value for parameter Value (Type is not supported)');
        }
        $this->updates[] = $value;
    }
    public function Translate(string $text): string { return $text; }
    public function LogMessage(string $text, int $severity): void { $this->logs[] = $text; }
    private function GetSensorCount(): int { return 1; }
    private function GetVisualizationData(): array { return ['rooms' => $this->GetVisualizationRooms()]; }
}
function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$module = new RoomHarness();
$rows = [
    ['Name' => 'Wohnen <img src=x>', 'Floor' => 'EG', 'RoomInstanceID' => 200, 'AirQualitySensor' => 1, 'CO2VariableID' => 501],
    ['Name' => '', 'Floor' => 'OG', 'RoomInstanceID' => 300, 'HumidityVariableID' => 502, 'PTCStatusVariableID' => 503],
    ['Name' => '', 'Floor' => 'EG', 'RoomInstanceID' => 400],
    ['Name' => 'Büro', 'Floor' => 'EG', 'CO2VariableID' => 504]
];
$module->mapping = json_encode($rows, JSON_THROW_ON_ERROR);
$rooms = $module->rooms();
check($rooms[0]['name'] === 'Wohnen <img src=x>' && $rooms[1]['name'] === 'Native Panel', 'Custom and fallback names');
check($rooms[0]['co2'] === 850 && $rooms[0]['humidity'] === 43, 'External CO2 must preserve native humidity');
check($rooms[1]['co2'] === null && $rooms[1]['humidity'] === 57.2, 'External humidity must work independently');
check($rooms[0]['temperature'] === 21.4 && $rooms[0]['setpoint'] === 22, 'Selected room temperatures');
check($rooms[0]['quality']['temperature']['status'] === 'stale', 'Room quality must reflect the selected panel update interval');
check($rooms[0]['ptc'] === null && $rooms[1]['ptc'] === true, 'PTC permission must never become actual state');
check($rooms[2]['name'] === 'Central Living Room', 'Central room name fallback');
check($rooms[2]['temperature'] === 22.1 && $rooms[2]['setpoint'] === 22.5, 'Central room temperatures');
check($rooms[2]['ptc'] === null, 'Central PTC release must not become actual state');
check($rooms[3]['co2'] === null && $rooms[3]['temperature'] === null, 'Bad or missing readings remain unavailable');
check(array_column($rooms, 'name') === ['Wohnen <img src=x>', 'Native Panel', 'Central Living Room', 'Büro'], 'Keep configured room order');
check(!in_array(505, $reads, true), 'Do not read unselected variables');
$module->bind();
check(isset($module->messages[501], $module->messages[102]) && !isset($module->messages[203]), 'Subscribe to actual selected data only');
$module->messages = [];
$module->bind();
check(isset($module->messages[501]), 'Restore subscriptions when runtime registrations were reset');
$module->timers = [];
$module->MessageSink(1, 505, VM_UPDATE, []);
check($module->timers === [], 'Ignore unrelated updates');
$module->MessageSink(2, 501, VM_UPDATE, []);
check($module->timers['RoomVisualizationRefresh'] === 250, 'Coalesce selected updates');
$module->RefreshVisualizationRooms();
$published = count($module->updates) === 1 ? json_decode($module->updates[0], true, 512, JSON_THROW_ON_ERROR) : null;
check($published['rooms'] === $rooms && $module->timers['RoomVisualizationRefresh'] === 0, 'Publish JSON data without a Modbus poll');
$module->mapping = json_encode([['Name' => 'Extra', 'HumidityVariableID' => 507, 'CO2VariableID' => 506]], JSON_THROW_ON_ERROR);
$zero = $module->rooms()[0];
check($zero['humidity'] === 0.0 && $zero['co2'] === null, 'Keep valid zero humidity distinct from an uninitialized variable');
$readingMethod = new ReflectionMethod(RoomHarness::class, 'GetVisualizationRoomReading');
$blockedReading = $readingMethod->invoke($module, 101, 'co2', 300, time());
check($blockedReading['value'] === null && $blockedReading['quality']['status'] === 'unknown', 'Do not treat a pre-activation sensor value as current.');
$variables[101]['updated'] = time();
$variables[101]['value'] = 825;
$currentReading = $readingMethod->invoke($module, 101, 'co2', 300, time() - 1);
check($currentReading['value'] === 825 && $currentReading['quality']['status'] === 'current', 'A post-activation sensor reading becomes valid.');
$module->mapping = json_encode([['Name' => 'Unavailable override', 'RoomInstanceID' => 300, 'AirQualitySensor' => 1, 'CO2VariableID' => 999]], JSON_THROW_ON_ERROR);
$unavailable = $module->rooms()[0];
check($unavailable['co2'] === null && $unavailable['humidity'] === 43, 'A missing selected override must not silently fall back');
check($unavailable['ptc'] === false, 'Native actual PTC false remains a valid state');
$module->mapping = json_encode([['Name' => 'Extra', 'HumidityVariableID' => 507, 'CO2VariableID' => 506]], JSON_THROW_ON_ERROR);
$module->bind();
check(!isset($module->references[200], $module->messages[501]) && isset($module->messages[507]), 'Remove obsolete references/subscriptions');
$runlevel = 0;
check($module->rooms() === [], 'Do not read instances before kernel readiness');
$runlevel = KR_READY;
$module->mapping = '{invalid';
$module->bind();
check($module->rooms() === [] && count($module->logs) === 1, 'Invalid mapping is reported, without a fatal error');

// Exercise the real tile assembly with the isolated object fixtures as well.
class IPSModuleStrict
{
    public int $InstanceID = 100;
    public function ReadAttributeInteger(string $name): int { return 0; }
    public function ReadAttributeString(string $name): string { return '{}'; }
    public function WriteAttributeString(string $name, string $value): void {}
    public function ReadPropertyInteger(string $name): int { return $name === 'CO2RFSensorCount' ? 1 : 0; }
    public function ReadPropertyString(string $name): string { return $GLOBALS['tileMapping']; }
    public function Translate(string $text): string { return $text; }
    public function SetValue(string $ident, mixed $value): void {}
    public function HasActiveParent(): bool { return false; }
}
$tileMapping = json_encode($rows, JSON_THROW_ON_ERROR);
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Proxon FWT System Control' . DIRECTORY_SEPARATOR . 'module.php';
$tile = (new ProxonFWTSystemControl())->GetVisualizationTile();
check(!str_contains($tile, '__INITIAL_DATA__') && !str_contains($tile, '__ROOM_LABELS__'), 'Tile placeholders must be substituted');
preg_match('/const initialData = (.*);/', $tile, $match);
check(json_decode($match[1], true, 512, JSON_THROW_ON_ERROR)['rooms'] === $rooms, 'Real tile payload contains configured room readings');
check(!str_contains($tile, 'Wohnen <img'), 'JSON payload must escape markup before embedding in a script');
$tileMapping = json_encode([['Name' => '__ROOM_LABELS__', 'Floor' => 'EG']], JSON_THROW_ON_ERROR);
$tokenTile = (new ProxonFWTSystemControl())->GetVisualizationTile();
preg_match('/const initialData = (.*);/', $tokenTile, $tokenMatch);
check(json_decode($tokenMatch[1], true, 512, JSON_THROW_ON_ERROR)['rooms'][0]['name'] === '__ROOM_LABELS__', 'Names must not be interpreted as template placeholders');

if (in_array('--json', $argv, true)) {
    echo json_encode(['rooms' => $rooms], JSON_THROW_ON_ERROR);
} elseif (in_array('--tile', $argv, true)) {
    echo $tile;
} else {
    echo "Room mapping: precedence, missing values, PTC distinction, references, subscriptions and startup checks passed.\n";
}
