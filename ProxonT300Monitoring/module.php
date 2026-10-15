<?php

declare(strict_types=1);

class ProxonT300Monitoring extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
    private const READ_FUNCTION = 4;
    private const EXTERNAL_SENSOR_UNAVAILABLE_RAW_VALUE = 2569;
    private const FAULT_CODE_DESCRIPTIONS = [
        0 => 'No Fault',
        1 => 'Modbus',
        2 => 'Filter',
        3 => 'Compressor',
        4 => 'T9',
        5 => 'T11',
        6 => 'T13',
        7 => 'T21',
        8 => 'T20',
        9 => 'T6',
        10 => 'T5',
        11 => 'P19',
        12 => 'P14',
        13 => 'Compressor Temperature',
        14 => 'Fan',
        15 => 'Low Pressure',
        16 => 'Pressure Switch'
    ];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Interval', 60);
        $this->RegisterTimer('Poller', 0, 'PROXONT300MON_RequestStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegisterInputVariables();
        $this->RegisterVariableString(
            'FaultDescription',
            $this->Translate('Fault Description'),
            [],
            158
        );
        $this->MaintainAction('FaultDescription', false);
        $this->RegisterVariableBoolean(
            'ExternalSensorTemperatureAvailable',
            $this->Translate('External Sensor Temperature Available'),
            [],
            159
        );
        $this->MaintainAction('ExternalSensorTemperatureAvailable', false);
        $this->SetTimerInterval('Poller', max(1, $this->ReadPropertyInteger('Interval')) * 1000);
    }

    public function RequestStatus(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            return;
        }

        $definitions = $this->GetInputDefinitions();
        foreach ($this->GetInputRegisterGroups() as $group) {
            $values = $this->ReadInputRegisters($group['address'], $group['quantity']);
            if ($values === null) {
                continue;
            }

            foreach ($definitions as $ident => $definition) {
                $offset = $definition['address'] - $group['address'];
                if ($offset < 0 || $offset >= count($values)) {
                    continue;
                }

                $rawValue = $values[$offset];
                if ($ident === 'ExternalSensorTemperature') {
                    $available = $rawValue !== 0 && $rawValue !== self::EXTERNAL_SENSOR_UNAVAILABLE_RAW_VALUE;
                    $this->SetValue('ExternalSensorTemperatureAvailable', $available);
                }

                $decodedValue = $this->DecodeInputValue($rawValue, $definition);
                $this->SetValue($ident, $decodedValue);
                if ($ident === 'FaultCode') {
                    $this->SetValue('FaultDescription', $this->GetFaultDescription((int) $decodedValue));
                }
            }
        }
    }

    private function GetFaultDescription(int $code): string
    {
        if (!array_key_exists($code, self::FAULT_CODE_DESCRIPTIONS)) {
            return sprintf($this->Translate('Unknown fault code: %d'), $code);
        }

        return $this->Translate(self::FAULT_CODE_DESCRIPTIONS[$code]);
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
            'PVHeatPumpActive' => ['caption' => 'PV Heat Pump Active', 'address' => 900, 'type' => 'boolean', 'scale' => 1, 'position' => 140],
            'EmergencyOperation' => [
                'caption' => 'Emergency Operation',
                'address' => 834,
                'type' => 'integer',
                'scale' => 1,
                'position' => 141
            ],
            'CompressorStatusErrorDuration' => ['caption' => 'Compressor Status Error Duration', 'address' => 842, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 142],
            'FanErrorDuration' => ['caption' => 'Fan Error Duration', 'address' => 844, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 143],
            'CompressorTemperatureErrorDuration' => ['caption' => 'Compressor Temperature Error Duration', 'address' => 845, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 144],
            'P14PressureErrorDuration' => ['caption' => 'P14 Pressure Error Duration', 'address' => 846, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 145],
            'FaultCode' => [
                'caption' => 'Fault Code',
                'address' => 861,
                'type' => 'integer',
                'scale' => 1,
                'position' => 146
            ],
            'P19PressureErrorDuration' => ['caption' => 'P19 Pressure Error Duration', 'address' => 869, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 147],
            'P14ErrorDuration' => ['caption' => 'P14 Error Duration', 'address' => 870, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 148],
            'T5ErrorDuration' => ['caption' => 'T5 Error Duration', 'address' => 871, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 149],
            'T6ErrorDuration' => ['caption' => 'T6 Error Duration', 'address' => 872, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 150],
            'T20ErrorDuration' => ['caption' => 'T20 Error Duration', 'address' => 873, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 151],
            'T21ErrorDuration' => ['caption' => 'T21 Error Duration', 'address' => 874, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 152],
            'T13ErrorDuration' => ['caption' => 'T13 Error Duration', 'address' => 875, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 153],
            'T11ErrorDuration' => ['caption' => 'T11 Error Duration', 'address' => 876, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 154],
            'T9ErrorDuration' => ['caption' => 'T9 Error Duration', 'address' => 877, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 155],
            'CompressorMaximumTemperatureErrorDuration' => ['caption' => 'Compressor Maximum Temperature Error Duration', 'address' => 878, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 156],
            'RestartCount' => ['caption' => 'Restart Count', 'address' => 880, 'type' => 'integer', 'scale' => 1, 'position' => 157]
        ];
    }

    private function GetInputPresentation(array $definition): array
    {
        $presentation = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION];
        if (isset($definition['suffix'])) {
            $presentation['SUFFIX'] = $definition['suffix'];
        }
        return $presentation;
    }

    private function GetInputRegisterGroups(): array
    {
        return [
            ['address' => 811, 'quantity' => 20],
            ['address' => 832, 'quantity' => 10],
            ['address' => 842, 'quantity' => 5],
            ['address' => 861, 'quantity' => 1],
            ['address' => 862, 'quantity' => 1],
            ['address' => 868, 'quantity' => 25],
            ['address' => 898, 'quantity' => 3]
        ];
    }

    private function ReadInputRegisters(int $address, int $quantity): ?array
    {
        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => self::READ_FUNCTION,
            'Address' => $address,
            'Quantity' => $quantity,
            'Data' => ''
        ], JSON_THROW_ON_ERROR));

        if (!is_string($response) || strlen($response) !== 2 + ($quantity * 2)) {
            $this->SendDebug('FC4 read failed', sprintf('Address %d, quantity %d', $address, $quantity), 0);
            return null;
        }

        $values = unpack('n*', substr($response, 2));
        return $values === false ? null : array_values($values);
    }

    private function DecodeInputValue(int $rawValue, array $definition): bool|int|float
    {
        if ($definition['type'] === 'boolean') {
            return $rawValue > 0;
        }

        $value = ($rawValue / $definition['scale']) + ($definition['offset'] ?? 0);
        return $definition['type'] === 'integer' ? (int) round($value) : (float) $value;
    }
}
