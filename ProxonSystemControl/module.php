<?php

declare(strict_types=1);

class ProxonSystemControl extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Interval', 30);
        $this->RegisterTimer('Poller', 0, 'PROXON_RequestStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RegisterVariableInteger('OperatingMode', $this->Translate('Operating Mode'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'DISPLAY' => 0,
            'LAYOUT' => 1,
            'OPTIONS' => json_encode([
                ['Value' => 0, 'Caption' => $this->Translate('Off'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 1, 'Caption' => $this->Translate('Summer Operation'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 2, 'Caption' => $this->Translate('Winter Operation'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 3, 'Caption' => $this->Translate('Eco Comfort'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 4, 'Caption' => $this->Translate('Stove Mode'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 6, 'Caption' => $this->Translate('Emergency Operation'), 'IconActive' => false, 'IconValue' => '']
            ], JSON_THROW_ON_ERROR)
        ], 1);
        $this->EnableAction('OperatingMode');

        $this->RegisterVariableInteger('FanStage', $this->Translate('Fan Stage'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'DISPLAY' => 0,
            'LAYOUT' => 1,
            'OPTIONS' => json_encode([
                ['Value' => 1, 'Caption' => $this->Translate('Fan Stage 1'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 2, 'Caption' => $this->Translate('Fan Stage 2'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 3, 'Caption' => $this->Translate('Fan Stage 3'), 'IconActive' => false, 'IconValue' => ''],
                ['Value' => 4, 'Caption' => $this->Translate('Fan Stage 4'), 'IconActive' => false, 'IconValue' => '']
            ], JSON_THROW_ON_ERROR)
        ], 2);
        $this->EnableAction('FanStage');

        $this->RegisterVariableBoolean('CoolingEnabled', $this->Translate('Cooling Enabled'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
        ], 3);
        $this->EnableAction('CoolingEnabled');

        $this->RegisterVariableInteger('IntensiveVentilationDuration', $this->Translate('Intensive Ventilation Duration'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
            'MIN' => 0,
            'MAX' => 1440,
            'STEP_SIZE' => 1,
            'DIGITS' => 0,
            'SUFFIX' => ' ' . $this->Translate('Minutes')
        ], 4);
        $this->EnableAction('IntensiveVentilationDuration');

        $this->SetTimerInterval('Poller', $this->ReadPropertyInteger('Interval') * 1000);
    }

    public function RequestStatus(): void
    {
        if (!$this->HasActiveParent()) {
            return;
        }

        $registers = [
            'OperatingMode' => 16,
            'FanStage' => 22,
            'CoolingEnabled' => 62,
            'IntensiveVentilationDuration' => 133
        ];

        foreach ($registers as $ident => $address) {
            $value = $this->ReadHoldingRegister($address);
            if ($value === null) {
                continue;
            }

            if ($ident === 'CoolingEnabled') {
                $this->SetValue($ident, $value > 0);
                continue;
            }

            $this->SetValue($ident, $value);
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'OperatingMode':
                $this->SetOperatingMode((int) $Value);
                break;
            case 'FanStage':
                $this->SetFanStage((int) $Value);
                break;
            case 'CoolingEnabled':
                $this->SetCoolingEnabled((bool) $Value);
                break;
            case 'IntensiveVentilationDuration':
                $this->SetIntensiveVentilationDuration((int) $Value);
                break;
            default:
                throw new InvalidArgumentException('Unknown action ident: ' . $Ident);
        }
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

    private function ReadHoldingRegister(int $address): ?int
    {
        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => 3,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => ''
        ], JSON_THROW_ON_ERROR));

        if (!is_string($response) || strlen($response) < 4) {
            return null;
        }

        $value = unpack('nvalue', substr($response, 2, 2));
        return $value === false ? null : $value['value'];
    }

    private function WriteHoldingRegister(int $address, int $value): bool
    {
        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => 6,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => bin2hex(pack('n', $value))
        ], JSON_THROW_ON_ERROR));

        return $response !== false;
    }
}
