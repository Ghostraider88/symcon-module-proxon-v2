<?php

declare(strict_types=1);

class ProxonT300Control extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
    private const READ_FUNCTION = 3;
    private const INPUT_READ_FUNCTION = 4;
    private const WRITE_FUNCTION = 6;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Interval', 60);
        $this->RegisterTimer('Poller', 0, 'PROXONT300_RequestStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegisterControlVariables();
        $this->RegisterInputVariables();
        $this->SetTimerInterval('Poller', max(1, $this->ReadPropertyInteger('Interval')) * 1000);
    }

    public function RequestStatus(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            return;
        }

        $definitions = $this->GetControlDefinitions();
        foreach ($this->GetRegisterGroups() as $group) {
            $values = $this->ReadHoldingRegisters($group['address'], $group['quantity']);
            if ($values === null) {
                continue;
            }

            foreach ($definitions as $ident => $definition) {
                $offset = $definition['address'] - $group['address'];
                if ($offset < 0 || $offset >= count($values)) {
                    continue;
                }

                $this->SetValue($ident, $this->DecodeValue($values[$offset], $definition));
            }
        }

        $inputDefinitions = $this->GetInputDefinitions();
        foreach ($this->GetInputRegisterGroups() as $group) {
            $values = $this->ReadInputRegisters($group['address'], $group['quantity']);
            if ($values === null) {
                continue;
            }

            foreach ($inputDefinitions as $ident => $definition) {
                $offset = $definition['address'] - $group['address'];
                if ($offset < 0 || $offset >= count($values)) {
                    continue;
                }

                $this->SetValue($ident, $this->DecodeInputValue($values[$offset], $definition));
            }
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        $definitions = $this->GetControlDefinitions();
        if (!isset($definitions[$Ident])) {
            throw new InvalidArgumentException('Unknown action ident: ' . $Ident);
        }

        $definition = $definitions[$Ident];
        [$normalizedValue, $rawValue] = $this->NormalizeValue($Value, $definition);
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            throw new RuntimeException($this->Translate('The T300 Modbus parent is not available.'));
        }

        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => self::WRITE_FUNCTION,
            'Address' => $definition['address'],
            'Quantity' => 1,
            'Data' => bin2hex(pack('n', $rawValue))
        ], JSON_THROW_ON_ERROR));

        if ($response === false) {
            $this->LogMessage(sprintf(
                $this->Translate('Writing T300 register %d failed.'),
                $definition['address']
            ), KL_WARNING);
            return;
        }

        // Keep the UI responsive after a successful command; the next poll confirms it.
        $this->SetValue($Ident, $normalizedValue);
    }

    private function RegisterControlVariables(): void
    {
        foreach ($this->GetControlDefinitions() as $ident => $definition) {
            $caption = $this->Translate($definition['caption']);
            switch ($definition['type']) {
                case 'boolean':
                    $this->RegisterVariableBoolean($ident, $caption, [
                        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                    ], $definition['position']);
                    break;
                case 'enumeration':
                    $this->RegisterVariableInteger($ident, $caption, $this->GetEnumerationPresentation($definition['options']), $definition['position']);
                    break;
                case 'float':
                    $this->RegisterVariableFloat($ident, $caption, [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                        'SUFFIX' => $definition['suffix']
                    ], $definition['position']);
                    break;
                default:
                    $this->RegisterVariableInteger($ident, $caption, [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT
                    ], $definition['position']);
                    break;
            }

            $this->MaintainAction($ident, true);
        }
    }

    private function GetControlDefinitions(): array
    {
        return [
            'OperatingMode' => [
                'caption' => 'Operating Mode',
                'address' => 2002,
                'type' => 'enumeration',
                'minimum' => 0,
                'maximum' => 3,
                'scale' => 1,
                'options' => [0 => 'Off', 1 => 'Demand', 2 => 'LF1', 3 => 'LF2'],
                'position' => 1
            ],
            'NormalWaterTemperature' => [
                'caption' => 'Normal Water Temperature',
                'address' => 2000,
                'type' => 'float',
                'minimum' => 20,
                'maximum' => 55,
                'scale' => 10,
                'suffix' => " \u{00B0}C",
                'position' => 2
            ],
            'ElectricHeaterEnabled' => [
                'caption' => 'Electric Heater Enabled',
                'address' => 2001,
                'type' => 'boolean',
                'minimum' => 0,
                'maximum' => 1,
                'scale' => 1,
                'position' => 3
            ],
            'Hour' => [
                'caption' => 'Hour',
                'address' => 2150,
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 23,
                'scale' => 1,
                'position' => 10
            ],
            'Minute' => [
                'caption' => 'Minute',
                'address' => 2151,
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 59,
                'scale' => 1,
                'position' => 11
            ],
            'Weekday' => [
                'caption' => 'Weekday (1-7)',
                'address' => 2152,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 7,
                'scale' => 1,
                'position' => 12
            ],
            'Day' => [
                'caption' => 'Day',
                'address' => 2153,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 31,
                'scale' => 1,
                'position' => 13
            ],
            'Month' => [
                'caption' => 'Month',
                'address' => 2154,
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 12,
                'scale' => 1,
                'position' => 14
            ],
            'Year' => [
                'caption' => 'Year (0-99)',
                'address' => 2155,
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 99,
                'scale' => 1,
                'position' => 15
            ],
            'DaylightSavingTime' => [
                'caption' => 'Daylight Saving Time',
                'address' => 2008,
                'type' => 'boolean',
                'minimum' => 0,
                'maximum' => 1,
                'scale' => 1,
                'position' => 16
            ],
            'ElectricHeaterTemperature' => [
                'caption' => 'Electric Heater Temperature',
                'address' => 2003,
                'type' => 'float',
                'minimum' => 20,
                'maximum' => 70,
                'scale' => 10,
                'suffix' => " \u{00B0}C",
                'position' => 20
            ],
            'ScreenTimeout' => [
                'caption' => 'Screen Timeout',
                'address' => 2009,
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 240,
                'scale' => 1,
                'position' => 21
            ],
            'LegionellaFunction' => [
                'caption' => 'Legionella Function',
                'address' => 2025,
                'type' => 'boolean',
                'minimum' => 0,
                'maximum' => 1,
                'scale' => 1,
                'position' => 22
            ],
            'PVFunction' => [
                'caption' => 'PV Function',
                'address' => 2010,
                'type' => 'boolean',
                'minimum' => 0,
                'maximum' => 1,
                'scale' => 1,
                'position' => 23
            ]
        ];
    }

    private function RegisterInputVariables(): void
    {
        foreach ($this->GetInputDefinitions() as $ident => $definition) {
            $caption = $this->Translate($definition['caption']);
            if ($definition['type'] === 'boolean') {
                $this->RegisterVariableBoolean($ident, $caption, [], $definition['position']);
            } elseif ($definition['type'] === 'integer') {
                $this->RegisterVariableInteger($ident, $caption, $this->GetInputPresentation($definition), $definition['position']);
            } else {
                $this->RegisterVariableFloat($ident, $caption, $this->GetInputPresentation($definition), $definition['position']);
            }

            $this->MaintainAction($ident, false);
        }
    }

    private function GetInputDefinitions(): array
    {
        return [
            'EvaporatorInletTemperature' => ['caption' => 'Evaporator Inlet Temperature', 'address' => 811, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 101],
            'EvaporatorTemperature' => ['caption' => 'Evaporator Temperature', 'address' => 812, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 102],
            'TankBottomTemperature' => ['caption' => 'Tank Bottom Temperature', 'address' => 813, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 103],
            'TankCenterTemperature' => ['caption' => 'Tank Center Temperature', 'address' => 814, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 104],
            'CompressorTemperature' => ['caption' => 'Compressor Temperature', 'address' => 815, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 105],
            'SuctionGasTemperature' => ['caption' => 'Suction Gas Temperature', 'address' => 816, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 106],
            'ExternalSensorTemperature' => ['caption' => 'External Sensor Temperature', 'address' => 817, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 107],
            'EvaporatorLowSidePressure' => ['caption' => 'Evaporator Low-Side Pressure', 'address' => 818, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => ' bar', 'digits' => 2, 'position' => 108],
            'DefrostDifferentialPressure' => ['caption' => 'Defrost Differential Pressure', 'address' => 819, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => ' Pa', 'digits' => 2, 'position' => 109],
            'ExpansionValvePosition' => ['caption' => 'Expansion Valve Position', 'address' => 820, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'position' => 110],
            'CompressorRelay' => ['caption' => 'Compressor Relay', 'address' => 824, 'type' => 'boolean', 'scale' => 1, 'position' => 111],
            'SolarRelay' => ['caption' => 'Solar Relay', 'address' => 825, 'type' => 'boolean', 'scale' => 1, 'position' => 112],
            'ElectricHeaterRelay' => ['caption' => 'Electric Heater Relay', 'address' => 826, 'type' => 'boolean', 'scale' => 1, 'position' => 113],
            'FanRelay' => ['caption' => 'Fan Relay', 'address' => 827, 'type' => 'boolean', 'scale' => 1, 'position' => 114],
            'DefrostRelay' => ['caption' => 'Defrost Relay', 'address' => 828, 'type' => 'boolean', 'scale' => 1, 'position' => 115],
            'PressureDerivedEvaporatorTemperature' => ['caption' => 'Pressure-Derived Evaporator Temperature', 'address' => 829, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 116],
            'SuctionEvaporatorTemperatureDifference' => ['caption' => 'Suction-to-Evaporator Temperature Difference', 'address' => 830, 'type' => 'float', 'scale' => 10, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 117],
            'HotAirRestrictionDuration' => ['caption' => 'Hot-Air Restriction Duration', 'address' => 832, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 118],
            'DefrostRestrictionDuration' => ['caption' => 'Defrost Restriction Duration', 'address' => 835, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 119],
            'HeatPumpStateDuration' => ['caption' => 'Heat Pump State Duration', 'address' => 836, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 120],
            'FanSpeed' => ['caption' => 'Fan Speed', 'address' => 838, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' %', 'position' => 121],
            'MaximumDefrostTimeRemaining' => ['caption' => 'Maximum Defrost Time Remaining', 'address' => 839, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 122],
            'DefrostWaitTime' => ['caption' => 'Defrost Wait Time', 'address' => 840, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 123],
            'ColdOperationDuration' => ['caption' => 'Cold Operation Duration', 'address' => 841, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 124],
            'FanRPM' => ['caption' => 'Fan Speed RPM', 'address' => 862, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' rpm', 'position' => 125],
            'EmergencyHeaterPermission' => ['caption' => 'Emergency Heater Permission', 'address' => 868, 'type' => 'boolean', 'scale' => 1, 'position' => 126],
            'CurrentSetpoint' => ['caption' => 'Current Setpoint', 'address' => 879, 'type' => 'float', 'scale' => 10, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 127],
            'FirmwareVersion' => ['caption' => 'Firmware Version', 'address' => 881, 'type' => 'float', 'scale' => 10, 'offset' => 0, 'digits' => 1, 'position' => 128],
            'AverageTankTemperature' => ['caption' => 'Average Tank Temperature', 'address' => 882, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 2, 'position' => 129],
            'SuperheatTemperature' => ['caption' => 'Superheat Temperature', 'address' => 883, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 2, 'position' => 130],
            'ActiveMaximumSuperheat' => ['caption' => 'Active Maximum Superheat', 'address' => 884, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 2, 'position' => 131],
            'ActiveMinimumSuperheat' => ['caption' => 'Active Minimum Superheat', 'address' => 885, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 2, 'position' => 132],
            'SuperheatReference' => ['caption' => 'Superheat Reference', 'address' => 886, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 2, 'position' => 133],
            'ExpansionValveStartTimeRemaining' => ['caption' => 'Expansion Valve Start Time Remaining', 'address' => 887, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 134],
            'RegulationReleaseAfterDefrostTime' => ['caption' => 'Regulation Release After Defrost Time', 'address' => 888, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' s', 'position' => 135],
            'MaximumSuperheatAfterDefrost' => ['caption' => 'Maximum Superheat After Defrost', 'address' => 890, 'type' => 'float', 'scale' => 100, 'offset' => 0, 'suffix' => " \u{00B0}C", 'digits' => 2, 'position' => 136],
            'DefrostStartTemperature' => ['caption' => 'Defrost Start Temperature', 'address' => 892, 'type' => 'float', 'scale' => 10, 'offset' => -100, 'suffix' => " \u{00B0}C", 'digits' => 1, 'position' => 137],
            'PVTankLevel' => ['caption' => 'PV Tank Level', 'address' => 898, 'type' => 'integer', 'scale' => 1, 'offset' => 0, 'suffix' => ' %', 'position' => 138],
            'PVElectricHeaterActive' => ['caption' => 'PV Electric Heater Active', 'address' => 899, 'type' => 'boolean', 'scale' => 1, 'position' => 139],
            'PVHeatPumpActive' => ['caption' => 'PV Heat Pump Active', 'address' => 900, 'type' => 'boolean', 'scale' => 1, 'position' => 140]
        ];
    }

    private function GetInputPresentation(array $definition): array
    {
        $presentation = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION];
        if (isset($definition['suffix'])) {
            $presentation['SUFFIX'] = $definition['suffix'];
        }
        if (isset($definition['digits'])) {
            $presentation['DIGITS'] = $definition['digits'];
        }

        return $presentation;
    }

    private function GetRegisterGroups(): array
    {
        return [
            ['address' => 2000, 'quantity' => 4],
            ['address' => 2008, 'quantity' => 3],
            ['address' => 2025, 'quantity' => 1],
            ['address' => 2150, 'quantity' => 6]
        ];
    }

    private function GetInputRegisterGroups(): array
    {
        return [
            ['address' => 811, 'quantity' => 20],
            ['address' => 832, 'quantity' => 10],
            ['address' => 862, 'quantity' => 1],
            ['address' => 868, 'quantity' => 25],
            ['address' => 898, 'quantity' => 3]
        ];
    }

    private function ReadHoldingRegisters(int $address, int $quantity): ?array
    {
        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => self::READ_FUNCTION,
            'Address' => $address,
            'Quantity' => $quantity,
            'Data' => ''
        ], JSON_THROW_ON_ERROR));

        if (!is_string($response) || strlen($response) !== 2 + ($quantity * 2)) {
            return null;
        }

        $values = unpack('n*', substr($response, 2));
        return $values === false ? null : array_values($values);
    }

    private function ReadInputRegisters(int $address, int $quantity): ?array
    {
        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => self::INPUT_READ_FUNCTION,
            'Address' => $address,
            'Quantity' => $quantity,
            'Data' => ''
        ], JSON_THROW_ON_ERROR));

        if (!is_string($response) || strlen($response) !== 2 + ($quantity * 2)) {
            return null;
        }

        $values = unpack('n*', substr($response, 2));
        return $values === false ? null : array_values($values);
    }

    private function DecodeValue(int $rawValue, array $definition): bool|int|float
    {
        if ($definition['type'] === 'boolean') {
            return $rawValue > 0;
        }

        if ($definition['type'] === 'float') {
            return $rawValue / $definition['scale'];
        }

        return $rawValue;
    }

    private function DecodeInputValue(int $rawValue, array $definition): bool|int|float
    {
        if ($definition['type'] === 'boolean') {
            return $rawValue > 0;
        }

        $value = ($rawValue / $definition['scale']) + $definition['offset'];
        return $definition['type'] === 'integer' ? (int) round($value) : (float) $value;
    }

    private function NormalizeValue(mixed $value, array $definition): array
    {
        if ($definition['type'] === 'boolean') {
            if (!is_bool($value) && $value !== 0 && $value !== 1) {
                throw new InvalidArgumentException($this->Translate('Boolean controls accept only true or false.'));
            }

            $normalizedValue = (bool) $value;
            return [$normalizedValue, $normalizedValue ? 1 : 0];
        }

        if (!is_numeric($value) || !is_finite((float) $value)) {
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

        if ($definition['type'] === 'integer' || $definition['type'] === 'enumeration') {
            if (floor($numericValue) !== $numericValue) {
                throw new InvalidArgumentException($this->Translate('The value must be a whole number.'));
            }

            $normalizedValue = (int) $numericValue;
            if ($definition['type'] === 'enumeration' && !array_key_exists($normalizedValue, $definition['options'])) {
                throw new InvalidArgumentException($this->Translate('Invalid selection value.'));
            }

            return [$normalizedValue, $normalizedValue];
        }

        $rawValue = (int) round($numericValue * $definition['scale']);
        return [$rawValue / $definition['scale'], $rawValue];
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
}
