<?php

declare(strict_types=1);

class ProxonSystemControl extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';

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

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Interval', 30);
        $this->RegisterPropertyInteger('CO2RFSensorCount', 1);
        $this->RegisterTimer('Poller', 0, 'PROXON_RequestStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

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

        $this->SetTimerInterval('Poller', max(1, $this->ReadPropertyInteger('Interval')) * 1000);
    }

    public function RequestStatus(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            return;
        }

        $this->pollCache = [];

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
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'OperatingMode':
                $this->SetOperatingMode((int) $Value);
                return;
            case 'FanStage':
                $this->SetFanStage((int) $Value);
                return;
            case 'CoolingEnabled':
                $this->SetCoolingEnabled((bool) $Value);
                return;
            case 'IntensiveVentilationDuration':
                $this->SetIntensiveVentilationDuration((int) $Value);
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
            'PtcSwitchOnThreshold' => ['caption' => 'PTC Switch-On Threshold', 'address' => 317, 'type' => 'float', 'scale' => 10, 'minimum' => 0, 'maximum' => 50, 'suffix' => ' °C', 'signed' => true, 'position' => 10],
            'PtcSwitchOffThreshold' => ['caption' => 'PTC Switch-Off Threshold', 'address' => 318, 'type' => 'float', 'scale' => 10, 'minimum' => 0, 'maximum' => 50, 'suffix' => ' °C', 'signed' => true, 'position' => 11],
            'GlobalPtcEnabled' => ['caption' => 'Global PTC Enabled', 'address' => 325, 'type' => 'boolean', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'position' => 12],
            'MinimumFreshAirTemperature' => ['caption' => 'Minimum Fresh Air Temperature', 'address' => 19, 'type' => 'float', 'scale' => 100, 'minimum' => 8, 'maximum' => 20, 'suffix' => ' °C', 'signed' => true, 'position' => 13],
            'HeatPumpCoolingThreshold' => ['caption' => 'Heat Pump Cooling Threshold', 'address' => 41, 'type' => 'float', 'scale' => 100, 'minimum' => 3, 'maximum' => 6, 'suffix' => ' °C', 'signed' => true, 'position' => 14],
            'CoolingMinimumOutdoorTemperature' => ['caption' => 'Cooling Minimum Outdoor Temperature', 'address' => 46, 'type' => 'float', 'scale' => 100, 'minimum' => 5, 'maximum' => 20, 'suffix' => ' °C', 'signed' => true, 'position' => 15],
            'CompressorContinuousOperationOutdoorTemperature' => ['caption' => 'Compressor Continuous Operation Outdoor Temperature', 'address' => 48, 'type' => 'float', 'scale' => 100, 'minimum' => -15, 'maximum' => 5, 'suffix' => ' °C', 'signed' => true, 'position' => 16],
            'HeatPumpStartThreshold' => ['caption' => 'Heat Pump Start Threshold', 'address' => 42, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 3, 'suffix' => ' °C', 'signed' => true, 'position' => 17],
            'HeatPumpStopThreshold' => ['caption' => 'Heat Pump Stop Threshold', 'address' => 134, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 3, 'suffix' => ' °C', 'signed' => true, 'position' => 18],
            'CompressorMinimumPower' => ['caption' => 'Compressor Minimum Power', 'address' => 44, 'type' => 'float', 'scale' => 100, 'minimum' => 25, 'maximum' => 50, 'suffix' => ' %', 'position' => 19],
            'CompressorMaximumHeatingPower' => ['caption' => 'Compressor Maximum Heating Power', 'address' => 43, 'type' => 'float', 'scale' => 100, 'minimum' => 50, 'maximum' => 100, 'suffix' => ' %', 'position' => 20],
            'CompressorMaximumCoolingPower' => ['caption' => 'Compressor Maximum Cooling Power', 'address' => 52, 'type' => 'float', 'scale' => 100, 'minimum' => 50, 'maximum' => 100, 'suffix' => ' %', 'position' => 21],
            'MaximumCompressorTemperature' => ['caption' => 'Maximum Compressor Temperature', 'address' => 34, 'type' => 'float', 'scale' => 100, 'minimum' => 80, 'maximum' => 140, 'suffix' => ' °C', 'signed' => true, 'position' => 22],
            'MaximumCondenserTemperatureCooling' => ['caption' => 'Maximum Condenser Temperature Cooling', 'address' => 45, 'type' => 'float', 'scale' => 100, 'minimum' => 45, 'maximum' => 55, 'suffix' => ' °C', 'signed' => true, 'position' => 23],
            'FanMinimumAirflow' => ['caption' => 'Fan Minimum Airflow', 'address' => 11, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 100, 'suffix' => ' %', 'position' => 24],
            'FanMaximumAirflow' => ['caption' => 'Fan Maximum Airflow', 'address' => 12, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 100, 'suffix' => ' %', 'position' => 25],
            'DeviceFilterLifetime' => ['caption' => 'Device Filter Lifetime', 'address' => 460, 'type' => 'integer', 'scale' => 1, 'minimum' => 3, 'maximum' => 8, 'suffix' => ' ' . $this->Translate('Months'), 'position' => 26],
            'RecirculationFilterLifetime' => ['caption' => 'Recirculation Filter Lifetime', 'address' => 461, 'type' => 'integer', 'scale' => 1, 'minimum' => 50, 'maximum' => 900, 'suffix' => ' ' . $this->Translate('Hours'), 'position' => 27],
            'RecirculationFilterInstalled' => ['caption' => 'Recirculation Filter Installed', 'address' => 462, 'type' => 'boolean', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'position' => 28],
            'Co2Threshold' => ['caption' => 'CO2 Threshold', 'address' => 117, 'type' => 'integer', 'scale' => 1, 'minimum' => 400, 'maximum' => 1500, 'suffix' => ' ppm', 'position' => 29],
            'RelativeHumidityThreshold' => ['caption' => 'Relative Humidity Threshold', 'address' => 118, 'type' => 'integer', 'scale' => 1, 'minimum' => 0, 'maximum' => 100, 'suffix' => ' %', 'position' => 30],
            'Zone1SensorSource' => ['caption' => 'Zone 1 Temperature Source', 'address' => 115, 'type' => 'enumeration', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'options' => [0 => 'Central Panel Sensor', 1 => 'Internal Sensor T41'], 'position' => 31],
            'Zone2SensorSource' => ['caption' => 'Zone 2 Temperature Source', 'address' => 116, 'type' => 'enumeration', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'options' => [0 => 'Main Secondary Panel Sensor', 1 => 'Internal Sensor T42'], 'position' => 32]
        ];

        for ($stage = 1; $stage <= 4; $stage++) {
            $definitions['SupplyAirflowStage' . $stage] = [
                'caption' => 'Supply Airflow Stage ' . $stage,
                'address' => $stage + 1,
                'type' => 'float',
                'scale' => 100,
                'minimum' => 0,
                'maximum' => 100,
                'suffix' => ' %',
                'position' => 32 + $stage
            ];
        }

        for ($stage = 1; $stage <= 4; $stage++) {
            $definitions['ExtractAirflowStage' . $stage] = [
                'caption' => 'Extract Airflow Stage ' . $stage,
                'address' => $stage + 5,
                'type' => 'float',
                'scale' => 100,
                'minimum' => 0,
                'maximum' => 100,
                'suffix' => ' %',
                'position' => 36 + $stage
            ];
        }

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

            $this->MaintainVariable($roomIdent, $this->Translate($roomDefinition['caption']), VARIABLETYPE_INTEGER, '', $roomDefinition['position'], $active);
            $this->MaintainVariable($co2Ident, sprintf($this->Translate('CO2 Sensor %d Value'), $sensor), VARIABLETYPE_INTEGER, '', $co2Position, $active);
            $this->MaintainVariable($humidityIdent, sprintf($this->Translate('CO2 Sensor %d Relative Humidity'), $sensor), VARIABLETYPE_INTEGER, '', $humidityPosition, $active);

            if ($active) {
                $roomVariableId = IPS_GetObjectIDByIdent($roomIdent, $this->InstanceID);
                IPS_SetVariableCustomPresentation($roomVariableId, $this->GetEnumerationPresentation($roomDefinition['options']));
                $this->EnableAction($roomIdent);
                IPS_SetVariableCustomPresentation(IPS_GetObjectIDByIdent($co2Ident, $this->InstanceID), [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX' => ' ppm'
                ]);
                IPS_SetVariableCustomPresentation(IPS_GetObjectIDByIdent($humidityIdent, $this->InstanceID), [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX' => ' %'
                ]);
            }
        }
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

    private function UpdatePlantStatus(): void
    {
        $mode = $this->ReadRegister(4, 23);
        if ($mode !== null) {
            $this->SetValue('ActualOperatingMode', $this->GetOperatingModeCaption($mode));
        }

        $heatPumpMode = $this->ReadRegister(3, 69);
        if ($heatPumpMode !== null) {
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
        $captions = [
            0 => 'Off',
            1 => 'Heating Demand',
            2 => 'Cooling Demand',
            3 => 'Ventilation'
        ];

        return isset($captions[$value]) ? $this->Translate($captions[$value]) : (string) $value;
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
        if ($definition['type'] === 'boolean') {
            $normalizedValue = (bool) $value;
            $rawValue = $normalizedValue ? 1 : 0;
        } elseif ($definition['type'] === 'enumeration') {
            $normalizedValue = (int) $value;
            if (!array_key_exists($normalizedValue, $definition['options'])) {
                throw new InvalidArgumentException($this->Translate('Invalid selection value.'));
            }
            $rawValue = $normalizedValue;
        } else {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException($this->Translate('The value must be numeric.'));
            }

            $numericValue = (float) $value;
            if ($numericValue < $definition['minimum'] || $numericValue > $definition['maximum']) {
                throw new InvalidArgumentException(sprintf(
                    $this->Translate('The value must be between %s and %s.'),
                    (string) $definition['minimum'],
                    (string) $definition['maximum']
                ));
            }

            $normalizedValue = $definition['type'] === 'integer' ? (int) round($numericValue) : $numericValue;
            $rawValue = (int) round($numericValue * $definition['scale']);
        }

        if (!$this->WriteHoldingRegister($definition['address'], $rawValue)) {
            return;
        }

        $this->SetValue($ident, $normalizedValue);
    }

    private function SetOperatingMode(int $value): void
    {
        if (!in_array($value, [0, 1, 2, 3, 4, 6], true)) {
            throw new InvalidArgumentException($this->Translate('Unsupported operating mode.'));
        }

        $this->WriteAndUpdate('OperatingMode', 16, $value);
    }

    private function SetFanStage(int $value): void
    {
        if ($value < 1 || $value > 4) {
            throw new InvalidArgumentException($this->Translate('Fan stage must be between 1 and 4.'));
        }

        $this->WriteAndUpdate('FanStage', 22, $value);
    }

    private function SetCoolingEnabled(bool $enabled): void
    {
        $this->WriteAndUpdate('CoolingEnabled', 62, $enabled ? 1 : 0, true);
    }

    private function SetIntensiveVentilationDuration(int $minutes): void
    {
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

        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => $function,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => ''
        ], JSON_THROW_ON_ERROR));

        if (!is_string($response) || strlen($response) < 4) {
            $this->pollCache[$key] = null;
            return null;
        }

        $value = unpack('nvalue', substr($response, 2, 2));
        $this->pollCache[$key] = $value === false ? null : $value['value'];
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
