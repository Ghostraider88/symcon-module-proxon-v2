<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTStatusPolling.php';

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTVisualizationRooms.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTControlInput.php';

class ProxonFWTSystemControl extends IPSModuleStrict
{
    use FWTStatusPolling;

    use FWTVisualizationRooms;
    use FWTControlInput;

    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
    private const ADVANCED_MODULE_ID = '{96EBFF26-A28F-4C3F-BB0B-166BDEE7B182}';
    private const MONITORING_MODULE_ID = '{C48CB61D-8500-41F3-AC35-82DAF84EC870}';

    private const SENSOR_ROOM_REGISTERS = [
        1 => 394,
        2 => 395,
        3 => 396,
        4 => 397,
        5 => 466
    ];

    private const SENSOR_CO2_REGISTERS = [
        1 => 21,
        2 => 43,
        3 => 45,
        4 => 6,
        5 => 2
    ];

    private const SENSOR_HUMIDITY_REGISTERS = [
        1 => 22,
        2 => 44,
        3 => 46,
        4 => 7,
        5 => 9
    ];

    private array $pollCache = [];
    private bool $statusPollInProgress = false;
    private bool $statusPollHadSuccessfulRead = false;

    public function Create(): void
    {
        parent::Create();

        $this->CreateStatusReadQuality();

        $this->RegisterPropertyInteger('Interval', 30);
        $this->RegisterPropertyInteger('CO2RFSensorCount', 1);
        $this->RegisterAttributeInteger('AppliedSensorCount', 0);
        $this->RegisterAttributeString('SensorActivationTimestamps', '{}');
        $this->RegisterPropertyBoolean('AdvancedControlsEnabled', false);
        $this->RegisterPropertyInteger('MonitoringInstanceID', 0);
        $this->RegisterAttributeInteger('RegisteredMonitoringInstanceID', 0);
        $this->RegisterAttributeInteger('RawBypassStatus', -1);
        $this->RegisterAttributeInteger('RawDamperPosition', -1);
        $this->RegisterAttributeInteger('RawHeatPumpMode', -1);
        $this->RegisterAttributeInteger('LastVisualizationUpdate', 0);
        $this->CreateVisualizationRooms();
        $this->RegisterTimer('Poller', 0, 'PROXON_RequestStatus($_IPS[\'TARGET\']);');
        $this->RegisterTimer('StatusQuality', 0, 'PROXON_RefreshReadQuality($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegisterStatusReadQualityVariable(500);
        $this->UpdateSensorActivationState();

        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML);
        $this->UpdateMonitoringReference();

        $this->RegisterVariableInteger('OperatingMode', $this->Translate('Operating Mode'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'DISPLAY' => 0,
            'LAYOUT' => 1,
            'OPTIONS' => json_encode($this->GetOperatingModeOptions(), JSON_THROW_ON_ERROR)
        ], 1);
        $this->EnableAction('OperatingMode');

        $this->RegisterVariableInteger('FanStage', $this->Translate('Fan Stage'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'DISPLAY' => 0,
            'LAYOUT' => 1,
            'OPTIONS' => json_encode($this->GetFanStageOptions(), JSON_THROW_ON_ERROR)
        ], 2);
        $this->EnableAction('FanStage');

        $this->RegisterVariableBoolean('CoolingEnabled', $this->Translate('Cooling Enabled'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
        ], 3);
        $this->EnableAction('CoolingEnabled');

        $this->RegisterVariableInteger('IntensiveVentilationDuration', $this->Translate('Intensive Ventilation Duration'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
            'SUFFIX' => ' ' . $this->Translate('Minutes')
        ], 4);
        $this->EnableAction('IntensiveVentilationDuration');

        $this->RegisterPlantControlVariables();
        $this->RegisterStatusVariables();
        $this->RegisterSensorVariables();
        $this->UpdateVisualizationRoomBindings();
        $this->HideLegacyAdvancedVariables();

        $this->ConfigureStatusPolling($this->ReadPropertyInteger('Interval'));
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode((string) file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        if (!$this->ReadPropertyBoolean('AdvancedControlsEnabled')) {
            $form['actions'] = array_values(array_filter(
                $form['actions'],
                static fn (array $action): bool => ($action['caption'] ?? '') !== 'Create Advanced Instance'
            ));
        }

        return json_encode($form, JSON_THROW_ON_ERROR);
    }

    public function GetVisualizationTile(): string
    {
        $html = file_get_contents(__DIR__ . '/module.html');
        $svg = file_get_contents(__DIR__ . '/visualization.svg');
        if ($html === false || $svg === false) {
            throw new RuntimeException('The FWT visualization files could not be loaded.');
        }

        $initialData = json_encode(
            $this->GetVisualizationData(),
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );

        $roomLabels = [];
        foreach (['Rooms', 'Room', 'Ground Floor', 'Upper Floor', 'Actual / Target', 'CO2', 'Relative Humidity', 'PTC Active', 'No rooms assigned', 'On', 'Off', 'Running', 'Heating (inferred)', 'Cooling (inferred)', 'Mode Inferred', 'Current', 'Stale', 'Failed', 'Unknown', 'Plant', 'Monitoring', 'Modbus', 'Disconnected', 'Select a Monitoring instance', 'Fault status incomplete'] as $caption) {
            $roomLabels[$caption] = $this->Translate($caption);
        }

        return strtr($html, [
            '<!-- FWT_SVG -->' => $svg,
            '__INITIAL_DATA__' => $initialData,
            '__ROOM_LABELS__' => json_encode($roomLabels, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        ]);
    }

    public function CreateAdvancedInstance(): void
    {
        if (!$this->ReadPropertyBoolean('AdvancedControlsEnabled')) {
            throw new RuntimeException($this->Translate('Enable advanced controls before creating the Advanced instance.'));
        }

        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            throw new RuntimeException($this->Translate('The Modbus parent must be active before creating the Advanced instance.'));
        }

        $connectionID = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($connectionID <= 0) {
            throw new RuntimeException($this->Translate('No active Modbus parent connection was found.'));
        }

        foreach (IPS_GetInstanceListByModuleID(self::ADVANCED_MODULE_ID) as $advancedID) {
            if ((int) IPS_GetProperty($advancedID, 'SystemControlInstanceID') === $this->InstanceID) {
                $this->LogMessage($this->Translate('The Advanced instance already exists.'), KL_MESSAGE);
                return;
            }
        }

        $advancedID = IPS_CreateInstance(self::ADVANCED_MODULE_ID);
        try {
            if (!IPS_SetName($advancedID, $this->Translate('FWT Advanced System Control'))
                || !IPS_SetParent($advancedID, IPS_GetParent($this->InstanceID))
                || !IPS_SetProperty($advancedID, 'SystemControlInstanceID', $this->InstanceID)
                || !IPS_ConnectInstance($advancedID, $connectionID)
                || !IPS_ApplyChanges($advancedID)) {
                throw new RuntimeException($this->Translate('The Advanced instance could not be configured.'));
            }
        } catch (Throwable $exception) {
            if (IPS_InstanceExists($advancedID) && IPS_GetChildrenIDs($advancedID) === []) {
                IPS_DeleteInstance($advancedID);
            }
            throw $exception;
        }

        $this->LogMessage($this->Translate('The Advanced instance was created.'), KL_MESSAGE);
    }

    public function CreateMonitoringInstance(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            throw new RuntimeException($this->Translate('The Modbus parent must be active before creating the Monitoring instance.'));
        }

        $connectionID = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($connectionID <= 0) {
            throw new RuntimeException($this->Translate('No active Modbus parent connection was found.'));
        }

        foreach (IPS_GetInstanceListByModuleID(self::MONITORING_MODULE_ID) as $monitoringID) {
            $instance = IPS_GetInstance($monitoringID);
            if ((int) $instance['ConnectionID'] === $connectionID
                && IPS_GetParent($monitoringID) === IPS_GetParent($this->InstanceID)) {
                $this->LogMessage($this->Translate('The Monitoring instance already exists.'), KL_MESSAGE);
                return;
            }
        }

        $monitoringID = IPS_CreateInstance(self::MONITORING_MODULE_ID);
        try {
            if (!IPS_SetName($monitoringID, $this->Translate('System Monitoring'))
                || !IPS_SetParent($monitoringID, IPS_GetParent($this->InstanceID))
                || !IPS_ConnectInstance($monitoringID, $connectionID)
                || !IPS_ApplyChanges($monitoringID)) {
                throw new RuntimeException($this->Translate('The Monitoring instance could not be configured.'));
            }
        } catch (Throwable $exception) {
            if (IPS_InstanceExists($monitoringID) && IPS_GetChildrenIDs($monitoringID) === []) {
                IPS_DeleteInstance($monitoringID);
            }
            throw $exception;
        }

        $this->LogMessage($this->Translate('The Monitoring instance was created.'), KL_MESSAGE);
    }

    public function RequestStatus(): void
    {
        $startedAt = $this->BeginStatusPoll();
        if ($startedAt === null) {
            return;
        }
        try {
            $this->PollStatusValues();
        } finally {
            $this->statusPollInProgress = false;
            $this->EndStatusPoll($startedAt, 'FWT System Control');
        }
    }

    private function PollStatusValues(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            return;
        }

        $this->pollCache = [];
        $this->statusPollInProgress = true;
        $this->statusPollHadSuccessfulRead = false;

        foreach ([
            'OperatingMode' => 16,
            'FanStage' => 22,
            'CoolingEnabled' => 62,
            'IntensiveVentilationDuration' => 133
        ] as $ident => $address) {
            $value = $this->ReadRegister(3, $address);
            if ($value === null) {
                continue;
            }

            $this->SetValue($ident, $ident === 'CoolingEnabled' ? $value > 0 : $value);
        }

        foreach ($this->GetPlantControlDefinitions() as $ident => $definition) {
            $value = $this->ReadRegister(3, $definition['address']);
            if ($value === null) {
                continue;
            }

            $this->SetValue($ident, $this->DecodeControlValue($value, $definition));
        }

        $this->UpdatePlantStatus();
        $this->UpdateConnectedPanels();
        $this->UpdateSensorValues();
        $this->statusPollInProgress = false;
        if ($this->statusPollHadSuccessfulRead) {
            $this->WriteAttributeInteger('LastVisualizationUpdate', time());
        }
        $this->PublishVisualizationData($this->GetVisualizationData());
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'OperatingMode':
                $this->SetOperatingMode($Value);
                return;
            case 'FanStage':
                $this->SetFanStage($Value);
                return;
            case 'CoolingEnabled':
                $this->SetCoolingEnabled($Value);
                return;
            case 'IntensiveVentilationDuration':
                $this->SetIntensiveVentilationDuration($Value);
                return;
        }

        $definitions = $this->GetPlantControlDefinitions();
        if (isset($definitions[$Ident])) {
            $this->WritePlantControl($Ident, $Value, $definitions[$Ident]);
            return;
        }

        if (preg_match('/^CO2Sensor([1-5])Room$/', $Ident, $matches) === 1) {
            $sensor = (int) $matches[1];
            $definition = $this->GetSensorRoomDefinition($sensor);
            if ($sensor <= $this->GetSensorCount()) {
                $this->WritePlantControl($Ident, $Value, $definition);
                return;
            }
        }

        throw new InvalidArgumentException('Unknown action ident: ' . $Ident);
    }

    private function GetPlantControlDefinitions(): array
    {
        $definitions = [
            'DeviceFilterLifetime' => ['caption' => 'Device Filter Lifetime', 'address' => 460, 'type' => 'integer', 'scale' => 1, 'minimum' => 3, 'maximum' => 8, 'suffix' => ' ' . $this->Translate('Months'), 'position' => 10],
            'RecirculationFilterLifetime' => ['caption' => 'Recirculation Filter Lifetime', 'address' => 461, 'type' => 'integer', 'scale' => 1, 'minimum' => 50, 'maximum' => 900, 'suffix' => ' ' . $this->Translate('Hours'), 'position' => 11],
            'RecirculationFilterInstalled' => ['caption' => 'Recirculation Filter Installed', 'address' => 462, 'type' => 'boolean', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'position' => 12],
            'Co2Threshold' => ['caption' => 'CO2 Threshold', 'address' => 117, 'type' => 'integer', 'scale' => 1, 'minimum' => 400, 'maximum' => 1500, 'suffix' => ' ppm', 'position' => 13],
            'RelativeHumidityThreshold' => ['caption' => 'Relative Humidity Threshold', 'address' => 118, 'type' => 'integer', 'scale' => 1, 'minimum' => 0, 'maximum' => 100, 'suffix' => ' %', 'position' => 14]
        ];

        for ($sensor = 1; $sensor <= $this->GetSensorCount(); $sensor++) {
            $definitions['CO2Sensor' . $sensor . 'Room'] = $this->GetSensorRoomDefinition($sensor);
        }

        return $definitions;
    }

    private function GetSensorRoomDefinition(int $sensor): array
    {
        return [
            'caption' => sprintf($this->Translate('CO2/RF Sensor %d Room'), $sensor),
            'address' => self::SENSOR_ROOM_REGISTERS[$sensor],
            'type' => 'enumeration',
            'scale' => 1,
            'minimum' => 0,
            'maximum' => 21,
            'suffix' => '',
            'options' => $this->GetRoomAssignmentOptions(),
            'position' => 40 + $sensor
        ];
    }

    private function GetRoomAssignmentOptions(): array
    {
        $options = [0 => 'Not Assigned', 1 => 'Central Panel Room', 2 => 'Main Secondary Panel Room'];
        for ($panel = 1; $panel <= 19; $panel++) {
            $options[$panel + 2] = sprintf($this->Translate('Secondary Panel %d'), $panel);
        }

        return $options;
    }

    private function GetSensorCount(): int
    {
        return max(1, min(5, $this->ReadPropertyInteger('CO2RFSensorCount')));
    }

    private function HideLegacyAdvancedVariables(): void
    {
        $legacyIdents = [
            'PtcSwitchOnThreshold',
            'PtcSwitchOffThreshold',
            'GlobalPtcEnabled',
            'MinimumFreshAirTemperature',
            'HeatPumpCoolingThreshold',
            'CoolingMinimumOutdoorTemperature',
            'CompressorContinuousOperationOutdoorTemperature',
            'HeatPumpStartThreshold',
            'HeatPumpStopThreshold',
            'CompressorMinimumPower',
            'CompressorMaximumHeatingPower',
            'CompressorMaximumCoolingPower',
            'MaximumCompressorTemperature',
            'MaximumCondenserTemperatureCooling',
            'FanMinimumAirflow',
            'FanMaximumAirflow',
            'Zone1SensorSource',
            'Zone2SensorSource'
        ];
        for ($stage = 1; $stage <= 4; $stage++) {
            $legacyIdents[] = 'SupplyAirflowStage' . $stage;
            $legacyIdents[] = 'ExtractAirflowStage' . $stage;
        }

        foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (!IPS_VariableExists($childID)) {
                continue;
            }

            $ident = IPS_GetObject($childID)['ObjectIdent'];
            if (!in_array($ident, $legacyIdents, true)) {
                continue;
            }

            $this->MaintainAction($ident, false);
            if (!IPS_SetHidden($childID, true)) {
                $this->LogMessage(sprintf($this->Translate('Could not hide legacy advanced variable %s.'), $ident), KL_WARNING);
            }
        }
    }

    private function RegisterPlantControlVariables(): void
    {
        foreach ($this->GetPlantControlDefinitions() as $ident => $definition) {
            if (str_starts_with($ident, 'CO2Sensor')) {
                continue;
            }

            $caption = $this->Translate($definition['caption']);
            $suffix = $definition['suffix'];

            if ($definition['type'] === 'boolean') {
                $this->RegisterVariableBoolean($ident, $caption, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                ], $definition['position']);
            } elseif ($definition['type'] === 'enumeration') {
                $this->RegisterVariableInteger($ident, $caption, $this->GetEnumerationPresentation($definition['options']), $definition['position']);
            } elseif ($definition['type'] === 'integer') {
                $this->RegisterVariableInteger($ident, $caption, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'SUFFIX' => $suffix
                ], $definition['position']);
            } else {
                $this->RegisterVariableFloat($ident, $caption, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'SUFFIX' => $suffix
                ], $definition['position']);
            }

            $this->EnableAction($ident);
        }
    }

    private function RegisterStatusVariables(): void
    {
        $this->RegisterVariableString('ActualOperatingMode', $this->Translate('Actual Operating Mode'), '', 60);
        $this->RegisterVariableString('ActualHeatPumpMode', $this->Translate('Actual Heat Pump Mode'), '', 61);
        $this->RegisterStatusInteger('SupplyFanRPM', 'Supply Fan Speed', ' rpm', 62);
        $this->RegisterStatusInteger('ExtractFanRPM', 'Extract Fan Speed', ' rpm', 63);
        $this->RegisterStatusInteger('SupplyFanStageActual', 'Actual Supply Fan Stage', '', 64);
        $this->RegisterStatusInteger('ExtractFanStageActual', 'Actual Extract Fan Stage', '', 65);
        $this->RegisterStatusFloat('SupplyAirTemperature', 'Supply Air Temperature', ' °C', 66);
        $this->RegisterStatusFloat('ExtractAirTemperature', 'Extract Air Temperature', ' °C', 67);
        $this->RegisterStatusFloat('ExhaustAirTemperature', 'Exhaust Air Temperature', ' °C', 68);
        $this->RegisterStatusFloat('FreshAirTemperature', 'Fresh Air Temperature', ' °C', 69);
        $this->RegisterStatusInteger('DeviceFilterRuntime', 'Device Filter Runtime', ' ' . $this->Translate('Hours'), 70);
        $this->RegisterStatusInteger('RecirculationFilterRuntime', 'Recirculation Filter Runtime', ' ' . $this->Translate('Hours'), 71);
        $this->RegisterVariableString('ConnectedPanels', $this->Translate('Connected Panels'), '', 72);
        $this->RegisterStatusInteger('ConnectedPanelCount', 'Connected Panel Count', '', 73);
        $this->RegisterVariableString('BypassStatus', $this->Translate('Bypass Status'), '', 74);
        $this->RegisterVariableString('DamperPosition', $this->Translate('Damper Position'), '', 75);
    }

    private function RegisterStatusInteger(string $ident, string $caption, string $suffix, int $position): void
    {
        $presentation = [];
        if ($suffix !== '') {
            $presentation = [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'SUFFIX' => $suffix
            ];
        }

        $this->RegisterVariableInteger($ident, $this->Translate($caption), $presentation, $position);
    }

    private function RegisterStatusFloat(string $ident, string $caption, string $suffix, int $position): void
    {
        $this->RegisterVariableFloat($ident, $this->Translate($caption), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => $suffix,
            'DIGITS' => 1
        ], $position);
    }

    private function RegisterSensorVariables(): void
    {
        $sensorCount = $this->GetSensorCount();
        for ($sensor = 1; $sensor <= 5; $sensor++) {
            $active = $sensor <= $sensorCount;
            $roomIdent = 'CO2Sensor' . $sensor . 'Room';
            $co2Ident = 'CO2Sensor' . $sensor . 'Value';
            $humidityIdent = 'CO2Sensor' . $sensor . 'RelativeHumidity';
            $roomDefinition = $this->GetSensorRoomDefinition($sensor);
            $co2Position = 80 + (($sensor - 1) * 2);
            $humidityPosition = $co2Position + 1;

            $roomVariableId = $this->MaintainSensorVariable(
                $roomIdent,
                $this->Translate($roomDefinition['caption']),
                $roomDefinition['position'],
                $active
            );
            $co2VariableId = $this->MaintainSensorVariable(
                $co2Ident,
                sprintf($this->Translate('CO2 Sensor %d Value'), $sensor),
                $co2Position,
                $active
            );
            $humidityVariableId = $this->MaintainSensorVariable(
                $humidityIdent,
                sprintf($this->Translate('CO2 Sensor %d Relative Humidity'), $sensor),
                $humidityPosition,
                $active
            );

            if ($roomVariableId > 0) {
                $this->MaintainAction($roomIdent, $active);
            }

            if ($active) {
                IPS_SetVariableCustomPresentation($roomVariableId, $this->GetEnumerationPresentation($roomDefinition['options']));
            }

            if ($co2VariableId > 0) {
                IPS_SetVariableCustomPresentation($co2VariableId, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX' => ' ppm'
                ]);
            }

            if ($humidityVariableId > 0) {
                IPS_SetVariableCustomPresentation($humidityVariableId, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX' => ' %'
                ]);
            }
        }
    }

    private function MaintainSensorVariable(string $ident, string $name, int $position, bool $active): int
    {
        $existingID = 0;
        if (!$active) {
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $childID) {
                if (IPS_GetObject($childID)['ObjectIdent'] === $ident) {
                    $existingID = $childID;
                    break;
                }
            }

            if ($existingID === 0) {
                return 0;
            }
        }

        $this->MaintainVariable($ident, $name, VARIABLETYPE_INTEGER, '', $position, true);
        $variableID = $existingID > 0 ? $existingID : IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        if ($variableID > 0 && !IPS_SetHidden($variableID, !$active)) {
            $this->LogMessage($this->Translate('Could not update sensor variable visibility.'), KL_WARNING);
        }

        return $variableID;
    }

    private function UpdateSensorActivationState(): void
    {
        $previousCount = $this->ReadAttributeInteger('AppliedSensorCount');
        $currentCount = $this->GetSensorCount();
        if ($previousCount === $currentCount) {
            return;
        }

        $activationTimestamps = json_decode($this->ReadAttributeString('SensorActivationTimestamps'), true);
        if (!is_array($activationTimestamps)) {
            $activationTimestamps = [];
        }

        for ($sensor = min($previousCount, $currentCount) + 1; $sensor <= max($previousCount, $currentCount); $sensor++) {
            $this->InvalidateSensorReadQuality($sensor);
            if ($currentCount > $previousCount) {
                $activationTimestamps[$sensor] = time() + 1;
            }
        }

        $this->WriteAttributeString('SensorActivationTimestamps', json_encode($activationTimestamps, JSON_THROW_ON_ERROR));
        $this->WriteAttributeInteger('AppliedSensorCount', $currentCount);
    }

    private function InvalidateSensorReadQuality(int $sensor): void
    {
        $quality = $this->ReadStatusReadQualityMap();
        $groups = [
            'FC3 register ' . self::SENSOR_ROOM_REGISTERS[$sensor],
            'FC4 register ' . self::SENSOR_CO2_REGISTERS[$sensor],
            'FC4 register ' . self::SENSOR_HUMIDITY_REGISTERS[$sensor]
        ];
        foreach ($groups as $group) {
            unset($quality[$group]);
        }

        $this->WriteStatusReadQualityMap($quality);
    }

    private function GetEnumerationPresentation(array $options): array
    {
        $presentationOptions = [];
        foreach ($options as $value => $caption) {
            $presentationOptions[] = [
                'Value' => (int) $value,
                'Caption' => $this->Translate($caption),
                'IconActive' => false,
                'IconValue' => ''
            ];
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'DISPLAY' => 0,
            'LAYOUT' => 1,
            'OPTIONS' => json_encode($presentationOptions, JSON_THROW_ON_ERROR)
        ];
    }

    private function GetOperatingModeOptions(): array
    {
        return [
            ['Value' => 0, 'Caption' => $this->Translate('Off'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 1, 'Caption' => $this->Translate('Summer Operation'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 2, 'Caption' => $this->Translate('Winter Operation'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 3, 'Caption' => $this->Translate('Eco Comfort'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 4, 'Caption' => $this->Translate('Stove Mode'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 6, 'Caption' => $this->Translate('Emergency Operation'), 'IconActive' => false, 'IconValue' => '']
        ];
    }

    private function GetFanStageOptions(): array
    {
        return [
            ['Value' => 1, 'Caption' => $this->Translate('Fan Stage 1'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 2, 'Caption' => $this->Translate('Fan Stage 2'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 3, 'Caption' => $this->Translate('Fan Stage 3'), 'IconActive' => false, 'IconValue' => ''],
            ['Value' => 4, 'Caption' => $this->Translate('Fan Stage 4'), 'IconActive' => false, 'IconValue' => '']
        ];
    }

    private function UpdateMonitoringReference(): void
    {
        $monitoringInstanceID = $this->ReadPropertyInteger('MonitoringInstanceID');
        $registeredInstanceID = $this->ReadAttributeInteger('RegisteredMonitoringInstanceID');
        $isValid = $this->IsMonitoringInstance($monitoringInstanceID);

        if ($registeredInstanceID > 0 && (!$isValid || $registeredInstanceID !== $monitoringInstanceID)) {
            $this->UnregisterReference($registeredInstanceID);
        }

        if ($isValid) {
            $this->RegisterReference($monitoringInstanceID);
        }

        $this->WriteAttributeInteger('RegisteredMonitoringInstanceID', $isValid ? $monitoringInstanceID : 0);
    }

    private function IsMonitoringInstance(int $instanceID): bool
    {
        return $instanceID > 0
            && IPS_InstanceExists($instanceID)
            && in_array($instanceID, IPS_GetInstanceListByModuleID(self::MONITORING_MODULE_ID), true);
    }

    private function GetVisualizationData(): array
    {
        $this->RefreshReadQuality();
        $lastUpdate = $this->ReadAttributeInteger('LastVisualizationUpdate');
        $hasLiveData = $lastUpdate > 0;
        $monitoringInstanceID = $this->ReadPropertyInteger('MonitoringInstanceID');
        $monitoringSelected = $this->IsMonitoringInstance($monitoringInstanceID);
        $monitoring = $this->GetVisualizationMonitoringValues($monitoringInstanceID);
        $monitoringQuality = json_decode((string) ($monitoring['ReadQuality'] ?? '{}'), true) ?? [];

        $systemControl = [];
        $systemControlGroups = [
            'ActualOperatingMode' => 'FC4 register 23',
            'ActualHeatPumpMode' => 'FC4 register 241',
            'SupplyFanRPM' => 'FC4 register 0',
            'ExtractFanRPM' => 'FC4 register 1',
            'SupplyAirTemperature' => 'FC4 register 195',
            'ExtractAirTemperature' => 'FC4 register 196',
            'ExhaustAirTemperature' => 'FC4 register 197',
            'FreshAirTemperature' => 'FC4 register 198',
            'DeviceFilterRuntime' => 'FC3 register 469',
            'RecirculationFilterRuntime' => 'FC3 register 468',
            'BypassStatus' => 'FC4 register 222',
            'DamperPosition' => 'FC4 register 159',
            'CoolingEnabled' => 'FC3 register 62',
            'SupplyFanStageActual' => 'FC4 register 154',
            'ExtractFanStageActual' => 'FC4 register 211'
        ];
        foreach ($systemControlGroups as $ident => $group) {
            $systemControl[$ident] = $this->IsStatusReadCurrent($group) ? $this->GetValue($ident) : null;
        }

        $rawBypassStatus = $this->ReadAttributeInteger('RawBypassStatus');
        $rawDamperPosition = $this->ReadAttributeInteger('RawDamperPosition');
        $rawHeatPumpMode = $this->ReadAttributeInteger('RawHeatPumpMode');

        return [
            'connected' => $this->HasActiveParent(),
            'updatedAt' => $hasLiveData ? $lastUpdate : null,
            'systemControlInterval' => max(0, $this->ReadPropertyInteger('Interval')),
            'systemControlQuality' => $this->ReadStatusReadQualityMap(),
            'monitoringSelected' => $monitoringSelected,
            'monitoringInterval' => $monitoringSelected ? max(0, (int) IPS_GetProperty($monitoringInstanceID, 'Interval')) : null,
            'monitoringLastRead' => $monitoring['LastSuccessfulRead'] ?? null,
            'monitoringLastReadTimestamp' => $monitoring['LastSuccessfulReadTimestamp'] ?? null,
            'monitoringQuality' => $monitoringQuality,
            'systemControl' => $systemControl + [
                'BypassOpen' => $this->IsStatusReadCurrent('FC4 register 222') && in_array($rawBypassStatus, [0, 1], true) ? $rawBypassStatus === 1 : null,
                'DamperPositionCode' => $this->IsStatusReadCurrent('FC4 register 159') && $rawDamperPosition >= 0 && $rawDamperPosition <= 4 ? $rawDamperPosition : null,
                // Symcon archive values confirm 0 = off, 1 = heating, and 2 = cooling.
                'HeatPumpModeCode' => $this->IsStatusReadCurrent('FC4 register 241') && in_array($rawHeatPumpMode, [0, 1, 2], true) ? $rawHeatPumpMode : null
            ],
            'monitoring' => $monitoring,
            'rooms' => $this->GetVisualizationRooms()
        ];
    }

    private function GetVisualizationMonitoringValues(int $monitoringInstanceID): array
    {
        if (!$this->IsMonitoringInstance($monitoringInstanceID)) {
            return [];
        }

        $values = [];
        foreach (IPS_GetChildrenIDs($monitoringInstanceID) as $childID) {
            if (!IPS_VariableExists($childID)) {
                continue;
            }

            $ident = (string) IPS_GetObject($childID)['ObjectIdent'];
            if ($ident === '') {
                continue;
            }

            $values[$ident] = GetValue($childID);
        }

        return (int) ($values['LastSuccessfulReadTimestamp'] ?? 0) > 0 ? $values : [];
    }

    private function UpdatePlantStatus(): void
    {
        $mode = $this->ReadRegister(4, 23);
        if ($mode !== null) {
            $this->SetValue('ActualOperatingMode', $this->GetOperatingModeCaption($mode));
        }

        $heatPumpMode = $this->ReadRegister(4, 241);
        if ($heatPumpMode !== null) {
            $this->WriteAttributeInteger('RawHeatPumpMode', $heatPumpMode);
            $this->SetValue('ActualHeatPumpMode', $this->GetHeatPumpModeCaption($heatPumpMode));
        }

        $integerStatuses = [
            'SupplyFanRPM' => [4, 0],
            'ExtractFanRPM' => [4, 1],
            'SupplyFanStageActual' => [4, 154],
            'ExtractFanStageActual' => [4, 211],
            'DeviceFilterRuntime' => [3, 469],
            'RecirculationFilterRuntime' => [3, 468]
        ];
        foreach ($integerStatuses as $ident => [$function, $address]) {
            $value = $this->ReadRegister($function, $address);
            if ($value !== null) {
                $this->SetValue($ident, $value);
            }
        }

        $temperatureStatuses = [
            'SupplyAirTemperature' => 195,
            'ExtractAirTemperature' => 196,
            'ExhaustAirTemperature' => 197,
            'FreshAirTemperature' => 198
        ];
        foreach ($temperatureStatuses as $ident => $address) {
            $value = $this->ReadRegister(4, $address);
            if ($value !== null) {
                $this->SetValue($ident, $this->DecodeSignedValue($value) / 100);
            }
        }

        $bypassStatus = $this->ReadRegister(4, 222);
        if ($bypassStatus !== null) {
            $this->WriteAttributeInteger('RawBypassStatus', $bypassStatus);
            $this->SetValue('BypassStatus', $this->GetBypassStatusCaption($bypassStatus));
        }

        $damperPosition = $this->ReadRegister(4, 159);
        if ($damperPosition !== null) {
            $this->WriteAttributeInteger('RawDamperPosition', $damperPosition);
            $this->SetValue('DamperPosition', $this->GetDamperPositionCaption($damperPosition));
        }
    }

    private function GetBypassStatusCaption(int $value): string
    {
        return match ($value) {
            0 => $this->Translate('Closed'),
            1 => $this->Translate('Open'),
            default => sprintf($this->Translate('Unknown status (%d)'), $value)
        };
    }

    private function GetDamperPositionCaption(int $value): string
    {
        $captions = [
            0 => 'Ground Floor',
            1 => 'Ground Floor Preferred',
            2 => 'Balanced',
            3 => 'Upper Floor Preferred',
            4 => 'Upper Floor'
        ];

        return isset($captions[$value])
            ? $this->Translate($captions[$value])
            : sprintf($this->Translate('Unknown position (%d)'), $value);
    }

    private function UpdateConnectedPanels(): void
    {
        $lowWord = $this->ReadRegister(3, 210);
        $highWord = $this->ReadRegister(3, 211);
        if ($lowWord === null || $highWord === null) {
            return;
        }

        $mask = ($highWord << 16) | $lowWord;
        $panels = [];
        for ($panel = 1; $panel <= 19; $panel++) {
            if (($mask & (1 << ($panel - 1))) !== 0) {
                $panels[] = sprintf($this->Translate('Secondary Panel %d'), $panel);
            }
        }
        if (($mask & (1 << 19)) !== 0) {
            $panels[] = $this->Translate('Main Secondary Panel');
        }

        $this->SetValue('ConnectedPanels', $panels === [] ? $this->Translate('No Secondary Panels Found') : implode(', ', $panels));
        $this->SetValue('ConnectedPanelCount', count($panels));
    }

    private function UpdateSensorValues(): void
    {
        for ($sensor = 1; $sensor <= $this->GetSensorCount(); $sensor++) {
            $co2 = $this->ReadRegister(4, self::SENSOR_CO2_REGISTERS[$sensor]);
            if ($co2 !== null) {
                $this->SetValue('CO2Sensor' . $sensor . 'Value', $co2);
            }

            $humidity = $this->ReadRegister(4, self::SENSOR_HUMIDITY_REGISTERS[$sensor]);
            if ($humidity !== null) {
                $this->SetValue('CO2Sensor' . $sensor . 'RelativeHumidity', $humidity);
            }
        }
    }

    private function GetOperatingModeCaption(int $value): string
    {
        $captions = [
            0 => 'Off',
            1 => 'Summer Operation',
            2 => 'Winter Operation',
            3 => 'Eco Comfort',
            4 => 'Stove Mode',
            6 => 'Emergency Operation'
        ];

        return isset($captions[$value]) ? $this->Translate($captions[$value]) : (string) $value;
    }

    private function GetHeatPumpModeCaption(int $value): string
    {
        // 4x0241 is the actual mode register; these states are confirmed by the Symcon archive.
        $captions = [
            0 => 'Off',
            1 => 'Heating',
            2 => 'Cooling'
        ];

        return isset($captions[$value])
            ? $this->Translate($captions[$value])
            : sprintf($this->Translate('Unknown mode code %d'), $value);
    }

    private function DecodeControlValue(int $rawValue, array $definition): mixed
    {
        if ($definition['type'] === 'boolean') {
            return $rawValue > 0;
        }

        if ($definition['type'] === 'enumeration') {
            return $rawValue;
        }

        if (($definition['signed'] ?? false) === true) {
            $rawValue = $this->DecodeSignedValue($rawValue);
        }

        $value = $rawValue / $definition['scale'];
        return $definition['type'] === 'integer' ? (int) round($value) : (float) $value;
    }

    private function WritePlantControl(string $ident, mixed $value, array $definition): void
    {
        $normalized = $this->NormalizeFWTControlValue($value, $definition);

        if (!$this->WriteHoldingRegister($definition['address'], $normalized['raw'])) {
            return;
        }

        $this->SetValue($ident, $normalized['value']);
    }

    private function SetOperatingMode(mixed $value): void
    {
        $value = $this->NormalizeFWTSelection(
            $value,
            [0 => true, 1 => true, 2 => true, 3 => true, 4 => true, 6 => true],
            $this->Translate('Unsupported operating mode.')
        );

        $this->WriteAndUpdate('OperatingMode', 16, $value);
    }

    private function SetFanStage(mixed $value): void
    {
        $value = $this->NormalizeFWTInteger($value);
        if ($value < 1 || $value > 4) {
            throw new InvalidArgumentException($this->Translate('Fan stage must be between 1 and 4.'));
        }

        $this->WriteAndUpdate('FanStage', 22, $value);
    }

    private function SetCoolingEnabled(mixed $enabled): void
    {
        $enabled = $this->NormalizeFWTBoolean($enabled);
        $this->WriteAndUpdate('CoolingEnabled', 62, $enabled ? 1 : 0, true);
    }

    private function SetIntensiveVentilationDuration(mixed $minutes): void
    {
        $minutes = $this->NormalizeFWTInteger($minutes);
        if ($minutes < 0 || $minutes > 1440) {
            throw new InvalidArgumentException($this->Translate('Intensive ventilation duration must be between 0 and 1440 minutes.'));
        }

        $this->WriteAndUpdate('IntensiveVentilationDuration', 133, $minutes);
    }

    private function WriteAndUpdate(string $ident, int $address, int $value, bool $booleanValue = false): void
    {
        if (!$this->WriteHoldingRegister($address, $value)) {
            return;
        }

        $this->SetValue($ident, $booleanValue ? $value > 0 : $value);
    }

    private function ReadRegister(int $function, int $address): ?int
    {
        $key = $function . ':' . $address;
        if (array_key_exists($key, $this->pollCache)) {
            return $this->pollCache[$key];
        }

        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            $this->pollCache[$key] = null;
            return null;
        }

        $response = $this->SendMeasuredStatusRead('FC' . $function . ' register ' . $address, json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => $function,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => ''
        ], JSON_THROW_ON_ERROR), 4);

        if (!is_string($response) || strlen($response) < 4) {
            $this->pollCache[$key] = null;
            return null;
        }

        $value = unpack('nvalue', substr($response, 2, 2));
        $this->pollCache[$key] = $value === false ? null : $value['value'];
        if ($this->statusPollInProgress && $this->pollCache[$key] !== null) {
            $this->statusPollHadSuccessfulRead = true;
        }
        return $this->pollCache[$key];
    }

    private function WriteHoldingRegister(int $address, int $value): bool
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            return false;
        }

        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => 6,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => bin2hex(pack('n', $value & 0xFFFF))
        ], JSON_THROW_ON_ERROR));

        return $response !== false;
    }

    private function DecodeSignedValue(int $value): int
    {
        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }
}
