<?php

declare(strict_types=1);

const KR_READY = 10103;
const KL_WARNING = 1;
const VARIABLETYPE_INTEGER = 1;
const VARIABLE_PRESENTATION_ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
const VARIABLE_PRESENTATION_VALUE_PRESENTATION = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';

$GLOBALS['instances'] = [
    100 => ['ModuleInfo' => ['ModuleID' => '{014DD20E-4090-4399-9C1F-D850E3DF4591}'], 'ConnectionID' => 10],
    200 => ['ModuleInfo' => ['ModuleID' => '{014DD20E-4090-4399-9C1F-D850E3DF4591}'], 'ConnectionID' => 20],
    300 => ['ModuleInfo' => ['ModuleID' => '{11111111-1111-1111-1111-111111111111}'], 'ConnectionID' => 10],
    500 => ['ModuleInfo' => ['ModuleID' => '{96EBFF26-A28F-4C3F-BB0B-166BDEE7B182}'], 'ConnectionID' => 10]
];
$GLOBALS['enabled'] = [100 => true];
$GLOBALS['activeModule'] = null;
$GLOBALS['nextVariableID'] = 1000;

function IPS_GetKernelRunlevel(): int
{
    return KR_READY;
}

function IPS_InstanceExists(int $instanceID): bool
{
    return isset($GLOBALS['instances'][$instanceID]);
}

function IPS_GetInstance(int $instanceID): array
{
    return $GLOBALS['instances'][$instanceID] ?? [];
}

function IPS_GetProperty(int $instanceID, string $property): mixed
{
    return $property === 'AdvancedControlsEnabled' && ($GLOBALS['enabled'][$instanceID] ?? false);
}

function IPS_GetObjectIDByIdent(string $ident, int $parentID): int
{
    $module = $GLOBALS['activeModule'];
    return $parentID === $module->InstanceID ? ($module->variables[$ident] ?? 0) : 0;
}

function IPS_SetVariableCustomPresentation(int $variableID, array $presentation): bool
{
    return true;
}

function IPS_SetHidden(int $variableID, bool $hidden): bool
{
    $GLOBALS['activeModule']->hidden[$variableID] = $hidden;
    return true;
}

class IPSModuleStrict
{
    public int $InstanceID = 0;
    public array $properties = [];
    public array $attributes = [];
    public array $references = [];
    public array $variables = [];
    public array $hidden = [];
    public array $actions = [];
    public array $values = [];
    public array $logs = [];

    public function ReadPropertyInteger(string $name): int
    {
        return (int) ($this->properties[$name] ?? 0);
    }

    public function ReadAttributeInteger(string $name): int
    {
        return (int) ($this->attributes[$name] ?? 0);
    }

    public function WriteAttributeInteger(string $name, int $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function ReadAttributeString(string $name): string
    {
        return (string) ($this->attributes[$name] ?? '{}');
    }

    public function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function SetValue(string $ident, mixed $value): void
    {
        $this->values[$ident] = $value;
    }

    public function RegisterReference(int $id): void
    {
        $this->references[$id] = true;
    }

    public function UnregisterReference(int $id): void
    {
        unset($this->references[$id]);
    }

    public function MaintainVariable(string $ident, string $name, int $type, string $profile, int $position, bool $keep): void
    {
        if (!$keep) {
            unset($this->variables[$ident]);
            return;
        }
        $this->variables[$ident] ??= $GLOBALS['nextVariableID']++;
    }

    public function MaintainAction(string $ident, bool $active): void
    {
        $this->actions[$ident] = $active;
    }

    public function Translate(string $text): string
    {
        return $text;
    }

    public function LogMessage(string $message, int $severity): void
    {
        $this->logs[] = $message;
    }
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Proxon FWT System Control' . DIRECTORY_SEPARATOR . 'module.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Proxon FWT System Control Advanced' . DIRECTORY_SEPARATOR . 'module.php';

function checkConfigurationRetention(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$advanced = new ProxonFWTSystemControlAdvanced();
$advanced->InstanceID = 500;
$advanced->properties['SystemControlInstanceID'] = 100;
$updateReference = new ReflectionMethod(ProxonFWTSystemControlAdvanced::class, 'UpdateSystemControlReference');
$updateReference->invoke($advanced);
checkConfigurationRetention(isset($advanced->references[100]), 'The selected compatible System Control instance is referenced.');
checkConfigurationRetention($advanced->attributes['RegisteredSystemControlInstanceID'] === 100, 'The current reference is tracked.');

$advanced->properties['SystemControlInstanceID'] = 200;
$updateReference->invoke($advanced);
checkConfigurationRetention($advanced->references === [], 'Changing to a System Control on another Modbus connection removes the old reference and rejects the new one.');
checkConfigurationRetention($advanced->attributes['RegisteredSystemControlInstanceID'] === 0, 'An invalid selection is not tracked as a reference.');

$advanced->properties['SystemControlInstanceID'] = 300;
$updateReference->invoke($advanced);
checkConfigurationRetention($advanced->references === [], 'A different module type cannot be selected as the System Control reference.');

$advanced->properties['SystemControlInstanceID'] = 100;
$updateReference->invoke($advanced);
$isAdvancedEnabled = new ReflectionMethod(ProxonFWTSystemControlAdvanced::class, 'IsAdvancedEnabled');
checkConfigurationRetention($isAdvancedEnabled->invoke($advanced) === true, 'Writes may use a matching enabled System Control instance.');
$advanced->properties['SystemControlInstanceID'] = 200;
checkConfigurationRetention($isAdvancedEnabled->invoke($advanced) === false, 'Writes reject a target on another Modbus connection.');

$systemControl = new ProxonFWTSystemControl();
$systemControl->InstanceID = 100;
$systemControl->properties['CO2RFSensorCount'] = 3;
$GLOBALS['activeModule'] = $systemControl;
$registerSensors = new ReflectionMethod(ProxonFWTSystemControl::class, 'RegisterSensorVariables');
$registerSensors->invoke($systemControl);
$sensorThreeIDs = [
    $systemControl->variables['CO2Sensor3Room'],
    $systemControl->variables['CO2Sensor3Value'],
    $systemControl->variables['CO2Sensor3RelativeHumidity']
];
checkConfigurationRetention($systemControl->actions['CO2Sensor3Room'] === true, 'An active sensor room assignment remains actionable.');

$systemControl->properties['CO2RFSensorCount'] = 1;
$registerSensors->invoke($systemControl);
checkConfigurationRetention(
    $sensorThreeIDs === [
        $systemControl->variables['CO2Sensor3Room'],
        $systemControl->variables['CO2Sensor3Value'],
        $systemControl->variables['CO2Sensor3RelativeHumidity']
    ],
    'Reducing the count preserves existing sensor variable IDs.'
);
checkConfigurationRetention($systemControl->hidden[$sensorThreeIDs[0]] && $systemControl->hidden[$sensorThreeIDs[1]] && $systemControl->hidden[$sensorThreeIDs[2]], 'Inactive sensor variables are hidden without deletion.');
checkConfigurationRetention($systemControl->actions['CO2Sensor3Room'] === false, 'An inactive sensor room assignment cannot be changed.');

$systemControl->properties['CO2RFSensorCount'] = 3;
$registerSensors->invoke($systemControl);
checkConfigurationRetention(
    $sensorThreeIDs === [
        $systemControl->variables['CO2Sensor3Room'],
        $systemControl->variables['CO2Sensor3Value'],
        $systemControl->variables['CO2Sensor3RelativeHumidity']
    ],
    'Re-enabling sensors restores the same variable IDs.'
);
checkConfigurationRetention(!$systemControl->hidden[$sensorThreeIDs[0]] && !$systemControl->hidden[$sensorThreeIDs[1]] && !$systemControl->hidden[$sensorThreeIDs[2]], 'Re-enabled sensor variables become visible again.');
checkConfigurationRetention($systemControl->actions['CO2Sensor3Room'] === true, 'Re-enabled sensor room assignment becomes actionable again.');

$systemControl->attributes['AppliedSensorCount'] = 1;
$systemControl->attributes['StatusReadQuality'] = json_encode([
    'FC3 register 396' => ['status' => 'current'],
    'FC4 register 45' => ['status' => 'current'],
    'FC4 register 46' => ['status' => 'current'],
    'FC4 register 21' => ['status' => 'current']
], JSON_THROW_ON_ERROR);
$updateSensorActivationState = new ReflectionMethod(ProxonFWTSystemControl::class, 'UpdateSensorActivationState');
$updateSensorActivationState->invoke($systemControl);
$quality = json_decode($systemControl->attributes['StatusReadQuality'], true, 512, JSON_THROW_ON_ERROR);
checkConfigurationRetention(!isset($quality['FC3 register 396'], $quality['FC4 register 45'], $quality['FC4 register 46']), 'Re-enabled sensor groups require a new successful read.');
checkConfigurationRetention(isset($quality['FC4 register 21']), 'Unchanged active sensor groups retain their quality.');
$activationTimestamps = json_decode($systemControl->attributes['SensorActivationTimestamps'], true, 512, JSON_THROW_ON_ERROR);
checkConfigurationRetention(($activationTimestamps[2] ?? 0) > 0 && ($activationTimestamps[3] ?? 0) > 0, 'Newly enabled sensors record their activation time.');

echo "Configuration references and sensor variable retention checks passed.\n";
