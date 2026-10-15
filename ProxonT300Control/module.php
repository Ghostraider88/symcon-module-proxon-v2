<?php

declare(strict_types=1);

class ProxonT300Control extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
    private const READ_FUNCTION = 3;
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

    private function GetRegisterGroups(): array
    {
        return [
            ['address' => 2000, 'quantity' => 4],
            ['address' => 2008, 'quantity' => 3],
            ['address' => 2025, 'quantity' => 1],
            ['address' => 2150, 'quantity' => 6]
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
