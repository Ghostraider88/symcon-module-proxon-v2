<?php

declare(strict_types=1);

class ProxonFWTSystemControlAdvanced extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
    private const SYSTEM_CONTROL_MODULE_ID = '{014DD20E-4090-4399-9C1F-D850E3DF4591}';

    private array $pollCache = [];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('SystemControlInstanceID', 0);
        $this->RegisterPropertyInteger('Interval', 30);
        $this->RegisterTimer('Poller', 0, 'PROXONADV_RequestStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $systemControlID = $this->ReadPropertyInteger('SystemControlInstanceID');
        if ($systemControlID > 0 && IPS_InstanceExists($systemControlID)) {
            $this->RegisterReference($systemControlID);
        }

        $this->RegisterAdvancedVariables();
        $this->UpdateActionAvailability();
        $this->SetTimerInterval('Poller', max(1, $this->ReadPropertyInteger('Interval')) * 1000);
    }

    public function RequestStatus(): void
    {
        $panelMask = $this->UpdateActionAvailability();
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            return;
        }

        foreach ($this->GetAdvancedPlantControlDefinitions() as $ident => $definition) {
            $panel = $this->GetPanelFromIdent($ident);
            if ($panel !== null && !$this->IsPanelConnected($panel, $panelMask)) {
                continue;
            }

            $value = $this->ReadRegister($definition['address']);
            if ($value === null) {
                continue;
            }

            $this->SetValue($ident, $this->DecodeControlValue($value, $definition));
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->EnsureAdvancedEnabled();

        $definitions = $this->GetAdvancedPlantControlDefinitions();
        if (!isset($definitions[$Ident])) {
            throw new InvalidArgumentException('Unknown action ident: ' . $Ident);
        }

        $this->WriteControl($Ident, $Value, $definitions[$Ident]);
    }

    private function RegisterAdvancedVariables(): void
    {
        foreach ($this->GetAdvancedPlantControlDefinitions() as $ident => $definition) {
            $caption = isset($definition['captionIndex'])
                ? sprintf($this->Translate($definition['caption']), $definition['captionIndex'])
                : $this->Translate($definition['caption']);
            if ($definition['type'] === 'boolean') {
                $this->RegisterVariableBoolean($ident, $caption, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                ], $definition['position']);
            } elseif ($definition['type'] === 'enumeration') {
                $this->RegisterVariableInteger($ident, $caption, $this->GetEnumerationPresentation($definition['options']), $definition['position']);
            } elseif ($definition['type'] === 'integer') {
                $this->RegisterVariableInteger($ident, $caption, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'SUFFIX' => $definition['suffix']
                ], $definition['position']);
            } else {
                $this->RegisterVariableFloat($ident, $caption, [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                    'SUFFIX' => $definition['suffix']
                ], $definition['position']);
            }

            if ($this->GetPanelFromIdent($ident) !== null) {
                $variableID = IPS_GetObjectIDByIdent($ident, $this->InstanceID);
                if (!IPS_SetHidden($variableID, true)) {
                    $this->LogMessage($this->Translate('Could not hide an unavailable panel temperature correction.'), KL_WARNING);
                }
                $this->MaintainAction($ident, false);
            }
        }
    }

    private function GetAdvancedPlantControlDefinitions(): array
    {
        $definitions = [
            'PtcSwitchOnThreshold' => ['caption' => 'PTC Switch-On Threshold', 'address' => 317, 'type' => 'float', 'scale' => 10, 'minimum' => 0, 'maximum' => 50, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 1],
            'PtcSwitchOffThreshold' => ['caption' => 'PTC Switch-Off Threshold', 'address' => 318, 'type' => 'float', 'scale' => 10, 'minimum' => 0, 'maximum' => 50, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 2],
            'GlobalPtcEnabled' => ['caption' => 'Global PTC Enabled', 'address' => 325, 'type' => 'boolean', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'position' => 3],
            'BypassTemperatureThreshold' => ['caption' => 'Bypass Temperature Threshold', 'address' => 19, 'type' => 'float', 'scale' => 100, 'minimum' => 8, 'maximum' => 20, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 4],
            'HeatPumpCoolingThreshold' => ['caption' => 'Heat Pump Cooling Threshold', 'address' => 41, 'type' => 'float', 'scale' => 100, 'minimum' => 3, 'maximum' => 6, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 5],
            'CoolingMinimumOutdoorTemperature' => ['caption' => 'Cooling Minimum Outdoor Temperature', 'address' => 46, 'type' => 'float', 'scale' => 100, 'minimum' => 5, 'maximum' => 20, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 6],
            'CompressorContinuousOperationOutdoorTemperature' => ['caption' => 'Compressor Continuous Operation Outdoor Temperature', 'address' => 48, 'type' => 'float', 'scale' => 100, 'minimum' => -15, 'maximum' => 5, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 7],
            'HeatPumpStartThreshold' => ['caption' => 'Heat Pump Start Threshold', 'address' => 42, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 3, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 8],
            'HeatPumpStopThreshold' => ['caption' => 'Heat Pump Stop Threshold', 'address' => 134, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 3, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 9],
            'CompressorRestartDelay' => ['caption' => 'Compressor Restart Delay', 'address' => 30, 'type' => 'integer', 'scale' => 1, 'minimum' => 0, 'maximum' => 3600, 'suffix' => ' s', 'position' => 10],
            'MinimumCompressorRuntime' => ['caption' => 'Minimum Compressor Runtime', 'address' => 31, 'type' => 'integer', 'scale' => 1, 'minimum' => 0, 'maximum' => 3600, 'suffix' => ' s', 'position' => 11],
            'CompressorPressureEqualizationTime' => ['caption' => 'Compressor Pressure Equalization Time', 'address' => 40, 'type' => 'integer', 'scale' => 1, 'minimum' => 0, 'maximum' => 180, 'suffix' => ' s', 'position' => 12],
            'CompressorMinimumPowerRuntimeWithoutDemand' => ['caption' => 'Compressor Minimum-Power Runtime Without Demand', 'address' => 94, 'type' => 'integer', 'scale' => 1, 'minimum' => 0, 'maximum' => 1200, 'suffix' => ' s', 'position' => 13],
            'MaximumCondenserTemperatureFanStage4' => ['caption' => 'Maximum Condenser Temperature Fan Stage 4', 'address' => 33, 'type' => 'float', 'scale' => 100, 'minimum' => 45, 'maximum' => 60, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 14],
            'CompressorMinimumPower' => ['caption' => 'Compressor Minimum Power', 'address' => 44, 'type' => 'float', 'scale' => 100, 'minimum' => 25, 'maximum' => 50, 'suffix' => ' %', 'position' => 15],
            'CompressorMaximumHeatingPower' => ['caption' => 'Compressor Maximum Heating Power', 'address' => 43, 'type' => 'float', 'scale' => 100, 'minimum' => 50, 'maximum' => 100, 'suffix' => ' %', 'position' => 16],
            'CompressorMaximumCoolingPower' => ['caption' => 'Compressor Maximum Cooling Power', 'address' => 52, 'type' => 'float', 'scale' => 100, 'minimum' => 50, 'maximum' => 100, 'suffix' => ' %', 'position' => 17],
            'MaximumCompressorTemperature' => ['caption' => 'Maximum Compressor Temperature', 'address' => 34, 'type' => 'float', 'scale' => 100, 'minimum' => 80, 'maximum' => 140, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 18],
            'MaximumCondenserTemperatureCooling' => ['caption' => 'Maximum Condenser Temperature Cooling', 'address' => 45, 'type' => 'float', 'scale' => 100, 'minimum' => 45, 'maximum' => 55, 'suffix' => " \u{00B0}C", 'signed' => true, 'position' => 19],
            'FanMinimumAirflow' => ['caption' => 'Fan Minimum Airflow', 'address' => 11, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 100, 'suffix' => ' %', 'position' => 20],
            'FanMaximumAirflow' => ['caption' => 'Fan Maximum Airflow', 'address' => 12, 'type' => 'float', 'scale' => 100, 'minimum' => 0, 'maximum' => 100, 'suffix' => ' %', 'position' => 21],
            'Zone1SensorSource' => ['caption' => 'Zone 1 Temperature Source', 'address' => 115, 'type' => 'enumeration', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'options' => [0 => 'Central Panel Sensor', 1 => 'Internal Sensor T41'], 'position' => 22],
            'Zone2SensorSource' => ['caption' => 'Zone 2 Temperature Source', 'address' => 116, 'type' => 'enumeration', 'scale' => 1, 'minimum' => 0, 'maximum' => 1, 'suffix' => '', 'options' => [0 => 'Main Secondary Panel Sensor', 1 => 'Internal Sensor T42'], 'position' => 23]
        ];

        for ($stage = 1; $stage <= 4; $stage++) {
            $definitions['SupplyAirflowStage' . $stage] = [
                'caption' => 'Supply Airflow Stage %d',
                'captionIndex' => $stage,
                'address' => $stage + 1,
                'type' => 'float',
                'scale' => 100,
                'minimum' => 0,
                'maximum' => 100,
                'suffix' => ' %',
                'position' => 30 + $stage
            ];
            $definitions['ExtractAirflowStage' . $stage] = [
                'caption' => 'Extract Airflow Stage %d',
                'captionIndex' => $stage,
                'address' => $stage + 5,
                'type' => 'float',
                'scale' => 100,
                'minimum' => 0,
                'maximum' => 100,
                'suffix' => ' %',
                'position' => 34 + $stage
            ];
        }

        for ($panel = 1; $panel <= 20; $panel++) {
            $isMainSecondaryPanel = $panel === 20;
            $register = 190 + ($panel % 20);
            $ident = 'Panel' . $panel . 'TemperatureOffset';
            $definitions[$ident] = [
                'caption' => $isMainSecondaryPanel
                    ? 'Main Secondary Panel Temperature Offset'
                    : 'Secondary Panel %d Temperature Offset',
                'captionIndex' => $isMainSecondaryPanel ? null : $panel,
                'address' => $register,
                'type' => 'float',
                'scale' => 10,
                'minimum' => -10,
                'maximum' => 10,
                'suffix' => " \u{00B0}C",
                'signed' => true,
                'position' => 40 + $panel
            ];
        }

        return $definitions;
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

    private function UpdateActionAvailability(): ?int
    {
        $enabled = $this->IsAdvancedEnabled();
        $this->pollCache = [];
        $panelMask = null;
        if (IPS_GetKernelRunlevel() === KR_READY && $this->HasActiveParent()) {
            $panelMask = $this->GetConnectedPanelMask();
        }

        foreach (array_keys($this->GetAdvancedPlantControlDefinitions()) as $ident) {
            $panel = $this->GetPanelFromIdent($ident);
            $panelConnected = $panel === null || $this->IsPanelConnected($panel, $panelMask);
            $this->MaintainAction($ident, $enabled && $panelConnected);

            if ($panel !== null) {
                $variableID = IPS_GetObjectIDByIdent($ident, $this->InstanceID);
                if (!IPS_SetHidden($variableID, !$panelConnected)) {
                    $this->LogMessage($this->Translate('Could not update panel temperature correction visibility.'), KL_WARNING);
                }
            }
        }

        return $panelMask;
    }

    private function GetPanelFromIdent(string $ident): ?int
    {
        if (preg_match('/^Panel([1-9]|1[0-9]|20)TemperatureOffset$/', $ident, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function GetConnectedPanelMask(): ?int
    {
        $lowWord = $this->ReadRegister(210);
        $highWord = $this->ReadRegister(211);
        if ($lowWord === null || $highWord === null) {
            return null;
        }

        return ($highWord << 16) | $lowWord;
    }

    private function IsPanelConnected(int $panel, ?int $panelMask): bool
    {
        if ($panelMask === null) {
            return false;
        }

        return ($panelMask & (1 << ($panel - 1))) !== 0;
    }

    private function IsAdvancedEnabled(): bool
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return false;
        }

        $systemControlID = $this->ReadPropertyInteger('SystemControlInstanceID');
        if ($systemControlID <= 0 || !IPS_InstanceExists($systemControlID)) {
            return false;
        }

        $systemControl = IPS_GetInstance($systemControlID);
        if (($systemControl['ModuleInfo']['ModuleID'] ?? '') !== self::SYSTEM_CONTROL_MODULE_ID) {
            return false;
        }

        $systemControlConnectionID = (int) ($systemControl['ConnectionID'] ?? 0);
        $advancedConnectionID = (int) IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($systemControlConnectionID <= 0 || $systemControlConnectionID !== $advancedConnectionID) {
            return false;
        }

        return (bool) IPS_GetProperty($systemControlID, 'AdvancedControlsEnabled');
    }

    private function EnsureAdvancedEnabled(): void
    {
        if (!$this->IsAdvancedEnabled()) {
            throw new RuntimeException($this->Translate('Enable Advanced Controls in the associated System Control instance before writing values.'));
        }
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
        return $definition['type'] === 'integer' ? (int) round($value) : $value;
    }

    private function WriteControl(string $ident, mixed $value, array $definition): void
    {
        $panel = $this->GetPanelFromIdent($ident);
        if ($panel !== null) {
            $this->pollCache = [];
            if (!$this->IsPanelConnected($panel, $this->GetConnectedPanelMask())) {
                throw new RuntimeException($this->Translate('The selected panel is not currently connected.'));
            }
        }

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

        $this->EnsureAdvancedEnabled();
        if (!$this->WriteHoldingRegister($definition['address'], $rawValue)) {
            return;
        }

        $this->SetValue($ident, $normalizedValue);
    }

    private function ReadRegister(int $address): ?int
    {
        if (array_key_exists($address, $this->pollCache)) {
            return $this->pollCache[$address];
        }

        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            $this->pollCache[$address] = null;
            return null;
        }

        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => 3,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => ''
        ], JSON_THROW_ON_ERROR));

        if (!is_string($response) || strlen($response) < 4) {
            $this->pollCache[$address] = null;
            return null;
        }

        $value = unpack('nvalue', substr($response, 2, 2));
        $this->pollCache[$address] = $value === false ? null : $value['value'];
        return $this->pollCache[$address];
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
