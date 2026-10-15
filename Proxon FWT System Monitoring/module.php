<?php

declare(strict_types=1);

class ProxonFWTSystemMonitoring extends IPSModuleStrict
{
    private const MODBUS_DATA_ID = '{E310B701-4AE7-458E-B618-EC13A1A6F6A8}';
    private const ARCHIVE_MODULE_ID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

    private array $pollCache = [];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Interval', 60);
        $this->RegisterPropertyInteger('PowerVariableID', 0);
        $this->RegisterPropertyInteger('DeviceFilterRemainingHoursAtReference', 0);
        $this->RegisterAttributeFloat('AccumulatedEnergyKWh', 0.0);
        $this->RegisterAttributeFloat('LastPowerW', 0.0);
        $this->RegisterAttributeInteger('LastPowerTimestamp', 0);
        $this->RegisterAttributeInteger('RegisteredPowerVariableID', 0);
        $this->RegisterAttributeInteger('DeviceFilterRuntimeAtReference', -1);
        $this->RegisterAttributeInteger('DeviceFilterRemainingHoursAtStoredReference', 0);
        $this->RegisterAttributeInteger('DeviceFilterRuntimeReferenceTimestamp', 0);
        $this->RegisterTimer('Poller', 0, 'PROXONMON_RequestStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->RemoveLegacyMonitoringVariables();
        $this->UpdatePowerVariableReference();
        $this->RegisterMonitoringVariables();
        $this->UpdateDeviceFilterRuntimeEstimate();
        $this->SetTimerInterval('Poller', max(5, $this->ReadPropertyInteger('Interval')) * 1000);
    }

    public function RequestStatus(): void
    {
        $this->pollCache = [];
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        $this->UpdateArchivedEnergy();
        if (!$this->HasActiveParent()) {
            $this->SetValue('FaultStatusComplete', false);
            $this->SetValue('FaultSummary', $this->Translate('Fault status unavailable: no active Modbus parent connection.'));
            return;
        }

        $successfulReads = 0;
        $successfulIdentifiers = [];
        foreach ($this->GetMonitoringDefinitions() as $ident => $definition) {
            $rawValue = $this->ReadRegister($definition['function'], $definition['address']);
            if ($rawValue === null) {
                continue;
            }
            $value = $this->DecodeValue($rawValue, $definition);
            $this->SetValue($ident, $value);
            $successfulReads++;
            $successfulIdentifiers[] = $ident;

            if ($ident === 'TotalElectricalPower') {
                $this->UpdateEnergyEstimate((float) $value);
            }

        }

        $this->UpdateFaultStatus($successfulIdentifiers);
        if (in_array('DeviceFilterRuntime', $successfulIdentifiers, true)) {
            $this->UpdateDeviceFilterRuntimeEstimate();
        }

        if ($successfulReads > 0) {
            $this->SetValue('LastSuccessfulRead', date('Y-m-d H:i:s'));
        }
    }

    public function ResetPendingFaults(): void
    {
        $this->SendServiceCommand(464, $this->Translate('Fault reset command sent.'));
    }

    public function RestartController(): void
    {
        $this->SendServiceCommand(465, $this->Translate('Controller restart command sent.'));
    }

    public function CaptureDeviceFilterRuntimeReference(): void
    {
        $remainingHours = $this->ReadPropertyInteger('DeviceFilterRemainingHoursAtReference');
        if ($remainingHours <= 0) {
            throw new InvalidArgumentException($this->Translate('Enter a positive app remaining time in hours first.'));
        }
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            throw new RuntimeException($this->Translate('An active Modbus parent connection is required.'));
        }

        $this->pollCache = [];
        $rawValue = $this->ReadRegister(3, 469);
        if ($rawValue === null) {
            throw new RuntimeException($this->Translate('Device filter runtime could not be read; no reference was saved.'));
        }

        $runtime = (int) $this->DecodeValue($rawValue, $this->GetMonitoringDefinitions()['DeviceFilterRuntime']);
        $now = time();
        $this->SetValue('DeviceFilterRuntime', $runtime);
        $this->WriteAttributeInteger('DeviceFilterRuntimeAtReference', $runtime);
        $this->WriteAttributeInteger('DeviceFilterRemainingHoursAtStoredReference', $remainingHours);
        $this->WriteAttributeInteger('DeviceFilterRuntimeReferenceTimestamp', $now);
        $this->UpdateDeviceFilterRuntimeEstimate();
    }

    private function RemoveLegacyMonitoringVariables(): void
    {
        foreach ([
            'CompressorCOPJAZ1Minute' => VARIABLETYPE_FLOAT,
            'CompressorCOPJAZ1Hour' => VARIABLETYPE_FLOAT,
            'CompressorCOPJAZ24Hours' => VARIABLETYPE_FLOAT,
            'CompressorCOPJAZ365Days' => VARIABLETYPE_FLOAT,
            'CompressorCOPJAZDays' => VARIABLETYPE_INTEGER,
            'TotalCOPJAZ1Minute' => VARIABLETYPE_FLOAT,
            'TotalCOPJAZ1Hour' => VARIABLETYPE_FLOAT,
            'TotalCOPJAZ24Hours' => VARIABLETYPE_FLOAT,
            'Airflow' => VARIABLETYPE_FLOAT
        ] as $ident => $type) {
            $this->MaintainVariable($ident, '', $type, '', 0, false);
        }
    }

    private function RegisterMonitoringVariables(): void
    {
        $this->RegisterVariableBoolean('FaultPresent', $this->Translate('Fault Present'), [], 19);
        $this->RegisterVariableString('FaultSummary', $this->Translate('Fault Summary'), '', 18);
        $this->RegisterVariableString('FaultDetails', $this->Translate('Fault Details'), '', 48);
        $this->RegisterVariableBoolean('FaultStatusComplete', $this->Translate('Fault Status Complete'), [], 49);

        $estimatePresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => ' h'
        ];
        $this->RegisterVariableString('DeviceFilterRuntimeEstimateStatus', $this->Translate('Device Filter Runtime Estimate Status'), '', 74);
        $this->RegisterVariableInteger('DeviceFilterEstimatedTotalRuntime', $this->Translate('Estimated Total Device Filter Runtime'), $estimatePresentation, 75);
        $this->RegisterVariableInteger('DeviceFilterRemainingRuntimeEstimate', $this->Translate('Estimated Remaining Device Filter Runtime'), $estimatePresentation, 76);

        foreach ($this->GetMonitoringDefinitions() as $ident => $definition) {
            $presentation = [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ];
            if (($definition['suffix'] ?? '') !== '') {
                $presentation['SUFFIX'] = $definition['suffix'];
            }
            if (isset($definition['digits'])) {
                $presentation['DIGITS'] = $definition['digits'];
            }

            $caption = $this->Translate($definition['caption']);
            if ($definition['type'] === 'boolean') {
                $this->RegisterVariableBoolean($ident, $caption, [], $definition['position']);
            } elseif ($definition['type'] === 'integer') {
                $this->RegisterVariableInteger($ident, $caption, $presentation, $definition['position']);
            } else {
                $this->RegisterVariableFloat($ident, $caption, $presentation, $definition['position']);
            }
        }

        $this->RegisterVariableFloat('EnergySinceFirstSample', $this->Translate('Estimated Energy Since First Sample'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX' => ' kWh',
            'DIGITS' => 3
        ], 4);
        $this->SetValue('EnergySinceFirstSample', $this->ReadAttributeFloat('AccumulatedEnergyKWh'));
        foreach ([
            'EnergyToday' => ['caption' => 'Electricity Consumption Today', 'position' => 70],
            'EnergyThisMonth' => ['caption' => 'Electricity Consumption This Month', 'position' => 71],
            'EnergyThisYear' => ['caption' => 'Electricity Consumption This Year', 'position' => 72]
        ] as $ident => $definition) {
            $this->RegisterVariableFloat($ident, $this->Translate($definition['caption']), [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'SUFFIX' => ' kWh',
                'DIGITS' => 2
            ], $definition['position']);
        }
        $this->RegisterVariableString('EnergyArchiveStatus', $this->Translate('Energy Archive Status'), '', 73);
        $this->RegisterVariableString('LastSuccessfulRead', $this->Translate('Last Successful Read'), '', 90);

        foreach (array_merge($this->GetTechnicalFaultIdents(), ['FaultDetails']) as $ident) {
            $variableID = IPS_GetObjectIDByIdent($ident, $this->InstanceID);
            if ($variableID > 0 && IPS_ObjectExists($variableID)) {
                IPS_SetHidden($variableID, true);
            }
        }
    }

    private function UpdateDeviceFilterRuntimeEstimate(): void
    {
        $runtimeAtReference = $this->ReadAttributeInteger('DeviceFilterRuntimeAtReference');
        $remainingAtReference = $this->ReadAttributeInteger('DeviceFilterRemainingHoursAtStoredReference');
        $referenceTimestamp = $this->ReadAttributeInteger('DeviceFilterRuntimeReferenceTimestamp');

        if ($runtimeAtReference < 0 || $remainingAtReference <= 0 || $referenceTimestamp <= 0) {
            $this->SetValue('DeviceFilterRuntimeEstimateStatus', $this->Translate('No app reference captured.'));
            $this->SetValue('DeviceFilterEstimatedTotalRuntime', 0);
            $this->SetValue('DeviceFilterRemainingRuntimeEstimate', 0);
            return;
        }

        $currentRuntime = (int) $this->GetValue('DeviceFilterRuntime');
        if ($currentRuntime < $runtimeAtReference) {
            $this->SetValue('DeviceFilterRuntimeEstimateStatus', $this->Translate('The filter runtime counter moved below its reference; capture a new app reference.'));
            $this->SetValue('DeviceFilterEstimatedTotalRuntime', 0);
            $this->SetValue('DeviceFilterRemainingRuntimeEstimate', 0);
            return;
        }

        $estimatedTotalRuntime = $runtimeAtReference + $remainingAtReference;
        $remainingRuntime = max(0, $estimatedTotalRuntime - $currentRuntime);
        $this->SetValue('DeviceFilterRuntimeEstimateStatus', sprintf(
            $this->Translate('Estimate based on app reference captured %s; assumes the app and 4x0469 use the same filter operating hours.'),
            date('Y-m-d H:i', $referenceTimestamp)
        ));
        $this->SetValue('DeviceFilterEstimatedTotalRuntime', $estimatedTotalRuntime);
        $this->SetValue('DeviceFilterRemainingRuntimeEstimate', $remainingRuntime);
    }

    private function UpdateFaultStatus(array $successfulIdentifiers): void
    {
        $faultRegisters = $this->GetFaultRegisterDefinitions();
        $successfulCount = 0;
        $activeRegisters = [];
        foreach ($faultRegisters as $ident => $definition) {
            if (!in_array($ident, $successfulIdentifiers, true)) {
                continue;
            }

            $successfulCount++;
            $value = (int) $this->GetValue($ident);
            if ($value > 0) {
                $activeRegisters[$ident] = $value;
            }
        }

        $this->SetValue('FaultPresent', count($activeRegisters) > 0);

        $messages = [];
        if (isset($activeRegisters['FaultRegister'])) {
            $messages[] = $this->Translate('The FWT controller reports a plant fault.');
        }
        if (isset($activeRegisters['InverterFatalError'])) {
            $messages[] = $this->Translate('The inverter reports a fatal fault.');
        }
        $internalFaultIdents = ['FaultStatus1', 'FaultStatus2', 'FaultStatus3', 'FaultStatus4', 'SecondaryBoardFaultFlags', 'InverterErrorCode', 'InverterErrorCode2', 'InverterErrorInput'];
        if (array_intersect($internalFaultIdents, array_keys($activeRegisters)) !== []) {
            $messages[] = $this->Translate('An internal board or inverter fault marker is active.');
        }

        $missingCount = count($faultRegisters) - $successfulCount;
        $this->SetValue('FaultStatusComplete', $missingCount === 0);
        if ($messages === []) {
            $summary = $missingCount === 0
                ? $this->Translate('No fault marker is active.')
                : $this->Translate('No fault marker was found in the read registers.');
        } else {
            $summary = implode(' ', array_unique($messages));
        }

        if ($missingCount > 0) {
            $summary .= ' ' . sprintf(
                $this->Translate('Fault status is incomplete (%d of %d registers read).'),
                $successfulCount,
                count($faultRegisters)
            );
        }
        $this->SetValue('FaultSummary', $summary);

        $detailLines = [];
        foreach ($faultRegisters as $ident => $definition) {
            if (!in_array($ident, $successfulIdentifiers, true)) {
                $detailLines[] = sprintf('%s: %s', $definition['register'], $this->Translate('not read'));
                continue;
            }

            $value = (int) $this->GetValue($ident);
            $detailLine = sprintf('%s: %d', $definition['register'], $value);
            if (in_array($ident, ['FaultStatus1', 'FaultStatus2', 'FaultStatus3', 'FaultStatus4', 'SecondaryBoardFaultFlags'], true)) {
                $activeBits = $this->GetActiveBitPositions($value);
                if ($activeBits !== []) {
                    $detailLine .= ' (' . sprintf(
                        $this->Translate('Active bit positions: %s; meanings unresolved.'),
                        implode(', ', $activeBits)
                    ) . ')';
                }
            }
            $detailLines[] = $detailLine;
        }
        $detailLines[] = $this->Translate('Fault code and bit meanings are not decoded by the register sheet.');
        $this->SetValue('FaultDetails', implode("\n", $detailLines));
    }

    private function GetActiveBitPositions(int $rawValue): array
    {
        $value = $rawValue & 0xFFFF;
        $positions = [];
        for ($bit = 0; $bit < 16; $bit++) {
            if (($value & (1 << $bit)) !== 0) {
                $positions[] = $bit;
            }
        }

        return $positions;
    }

    private function GetFaultRegisterDefinitions(): array
    {
        return [
            'FaultRegister' => ['register' => 'FC04 / 4x0047'],
            'FaultStatus1' => ['register' => 'FC04 / 4x0048'],
            'FaultStatus2' => ['register' => 'FC04 / 4x0049'],
            'FaultStatus3' => ['register' => 'FC04 / 4x0050'],
            'FaultStatus4' => ['register' => 'FC04 / 4x0051'],
            'SecondaryBoardFaultFlags' => ['register' => 'FC04 / 4x0157'],
            'InverterErrorCode' => ['register' => 'FC04 / 4x0170'],
            'InverterErrorCode2' => ['register' => 'FC04 / 4x0172'],
            'InverterFatalError' => ['register' => 'FC04 / 4x0174'],
            'InverterErrorInput' => ['register' => 'FC04 / 4x0184']
        ];
    }

    private function GetTechnicalFaultIdents(): array
    {
        return array_keys($this->GetFaultRegisterDefinitions());
    }

    private function GetMonitoringDefinitions(): array
    {
        return [
            'ElectricalPowerPCB' => ['caption' => 'Electrical Power PCB', 'function' => 4, 'address' => 19, 'type' => 'float', 'scale' => 10, 'suffix' => ' W', 'digits' => 1, 'position' => 1],
            'ElectricalPowerInverter' => ['caption' => 'Electrical Power Inverter', 'function' => 4, 'address' => 20, 'type' => 'float', 'scale' => 10, 'suffix' => ' W', 'digits' => 1, 'position' => 2],
            'TotalElectricalPower' => ['caption' => 'Total Electrical Power', 'function' => 4, 'address' => 25, 'type' => 'float', 'scale' => 10, 'suffix' => ' W', 'digits' => 1, 'position' => 3],
            'FaultRegister' => ['caption' => 'Fault Register', 'function' => 4, 'address' => 47, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 20],
            'FaultStatus1' => ['caption' => 'Fault Status 1 (raw)', 'function' => 4, 'address' => 48, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 21],
            'FaultStatus2' => ['caption' => 'Fault Status 2 (raw)', 'function' => 4, 'address' => 49, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 22],
            'FaultStatus3' => ['caption' => 'Fault Status 3 (raw)', 'function' => 4, 'address' => 50, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 23],
            'FaultStatus4' => ['caption' => 'Fault Status 4 (raw)', 'function' => 4, 'address' => 51, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 24],
            'FanStatus' => ['caption' => 'Fan Status (raw)', 'function' => 4, 'address' => 152, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 25],
            'FanErrorRemainingTime' => ['caption' => 'Fan Error Remaining Time', 'function' => 4, 'address' => 153, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 26],
            'ActualSupplyFanStage' => ['caption' => 'Actual Supply Fan Stage', 'function' => 4, 'address' => 154, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 27],
            'SupplyFanVoltage' => ['caption' => 'Supply Fan Voltage', 'function' => 4, 'address' => 155, 'type' => 'float', 'scale' => 100, 'suffix' => ' V', 'digits' => 2, 'position' => 28],
            'ExtractFanVoltage' => ['caption' => 'Extract Fan Voltage', 'function' => 4, 'address' => 156, 'type' => 'float', 'scale' => 100, 'suffix' => ' V', 'digits' => 2, 'position' => 29],
            'SecondaryBoardFaultFlags' => ['caption' => 'Secondary Board Fault Flags (raw)', 'function' => 4, 'address' => 157, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 30],
            'CompressorStatus' => ['caption' => 'Compressor Status (raw)', 'function' => 4, 'address' => 162, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 31],
            'InverterErrorCode' => ['caption' => 'Inverter Error Code', 'function' => 4, 'address' => 170, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 32],
            'CurrentCompressorPower' => ['caption' => 'Current Compressor Power', 'function' => 4, 'address' => 171, 'type' => 'float', 'scale' => 100, 'suffix' => ' %', 'digits' => 2, 'position' => 33],
            'InverterErrorCode2' => ['caption' => 'Inverter Error Code 2', 'function' => 4, 'address' => 172, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 34],
            'InverterFatalError' => ['caption' => 'Inverter Fatal Error', 'function' => 4, 'address' => 174, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 35],
            'InverterErrorInput' => ['caption' => 'Inverter Error Input', 'function' => 4, 'address' => 184, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 36],
            'InverterResetCounterTime' => ['caption' => 'Inverter Reset Counter Time', 'function' => 4, 'address' => 185, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 37],
            'InverterErrorDelay' => ['caption' => 'Inverter Error Delay', 'function' => 4, 'address' => 186, 'type' => 'integer', 'scale' => 1, 'suffix' => ' s', 'position' => 38],
            'InverterErrorCounter' => ['caption' => 'Inverter Error Counter', 'function' => 4, 'address' => 187, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 39],
            'CompressorSpeed' => ['caption' => 'Compressor Speed', 'function' => 4, 'address' => 190, 'type' => 'integer', 'scale' => 1, 'suffix' => ' rpm', 'position' => 40],
            'MaximumCompressorPower' => ['caption' => 'Maximum Compressor Power', 'function' => 4, 'address' => 201, 'type' => 'float', 'scale' => 100, 'suffix' => ' %', 'digits' => 2, 'position' => 41],
            'InverterModbusErrorCounter' => ['caption' => 'Inverter Modbus Error Counter', 'function' => 4, 'address' => 213, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 42],
            'EvaporatorTemperatureFromPressure' => ['caption' => 'Evaporator Temperature from Pressure', 'function' => 4, 'address' => 216, 'type' => 'float', 'scale' => 100, 'suffix' => ' °C', 'digits' => 2, 'signed' => true, 'position' => 43],
            'InverterPowerModuleTemperature' => ['caption' => 'Inverter Power Module Temperature', 'function' => 4, 'address' => 233, 'type' => 'integer', 'scale' => 1, 'suffix' => ' °C', 'position' => 44],
            'PacketErrorCounter' => ['caption' => 'Packet Error Counter', 'function' => 4, 'address' => 237, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 45],
            'InverterModbusRestartCounter' => ['caption' => 'Inverter Modbus Restart Counter', 'function' => 4, 'address' => 267, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 46],
            'InverterOneSecondErrorCounter' => ['caption' => 'Inverter 1-Second Error Counter', 'function' => 4, 'address' => 268, 'type' => 'integer', 'scale' => 1, 'suffix' => '', 'position' => 47],
            'PlantRuntime' => ['caption' => 'Plant Runtime', 'function' => 3, 'address' => 467, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 60],
            'RecirculationFilterRuntime' => ['caption' => 'Recirculation Filter Runtime', 'function' => 3, 'address' => 468, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 61],
            'DeviceFilterRuntime' => ['caption' => 'Device Filter Runtime', 'function' => 3, 'address' => 469, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 62],
            'PreheaterRuntime' => ['caption' => 'Preheater Runtime', 'function' => 3, 'address' => 470, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 63],
            'RecirculationRuntime' => ['caption' => 'Recirculation Runtime', 'function' => 3, 'address' => 471, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 64],
            'InverterRuntime' => ['caption' => 'Inverter Runtime', 'function' => 3, 'address' => 472, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 65],
            'CompressorCoolingRuntime' => ['caption' => 'Compressor Cooling Runtime', 'function' => 3, 'address' => 473, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 66],
            'CompressorHeatingRuntime' => ['caption' => 'Compressor Heating Runtime', 'function' => 3, 'address' => 474, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 67],
            'DefrostRuntime' => ['caption' => 'Defrost Runtime', 'function' => 3, 'address' => 475, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 68],
            'FanRuntimeStage1' => ['caption' => 'Fan Runtime Stage 1', 'function' => 3, 'address' => 476, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 69],
            'FanRuntimeStage2' => ['caption' => 'Fan Runtime Stage 2', 'function' => 3, 'address' => 477, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 70],
            'FanRuntimeStage3' => ['caption' => 'Fan Runtime Stage 3', 'function' => 3, 'address' => 478, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 71],
            'FanRuntimeStage4' => ['caption' => 'Fan Runtime Stage 4', 'function' => 3, 'address' => 479, 'type' => 'integer', 'scale' => 1, 'suffix' => ' h', 'position' => 72]
        ];

        return $definitions;
    }

    private function DecodeValue(int $rawValue, array $definition): mixed
    {
        if (($definition['signed'] ?? false) === true) {
            $rawValue = $this->DecodeSignedValue($rawValue);
        }

        if ($definition['type'] === 'boolean') {
            return $rawValue > 0;
        }

        $value = $rawValue / $definition['scale'];
        return $definition['type'] === 'integer' ? (int) round($value) : (float) $value;
    }

    private function UpdateEnergyEstimate(float $powerW): void
    {
        $now = time();
        $lastTimestamp = $this->ReadAttributeInteger('LastPowerTimestamp');
        $lastPowerW = $this->ReadAttributeFloat('LastPowerW');
        $energyKWh = $this->ReadAttributeFloat('AccumulatedEnergyKWh');
        $maximumGap = max(300, max(5, $this->ReadPropertyInteger('Interval')) * 3);

        if ($powerW >= 0 && $lastPowerW >= 0 && $lastTimestamp > 0 && $now > $lastTimestamp) {
            $elapsed = $now - $lastTimestamp;
            if ($elapsed <= $maximumGap) {
                $energyKWh += (($lastPowerW + $powerW) / 2) * $elapsed / 3600000;
            }
        }

        $this->WriteAttributeFloat('AccumulatedEnergyKWh', $energyKWh);
        $this->WriteAttributeFloat('LastPowerW', max(0.0, $powerW));
        $this->WriteAttributeInteger('LastPowerTimestamp', $now);
        $this->SetValue('EnergySinceFirstSample', $energyKWh);
    }

    private function UpdatePowerVariableReference(): void
    {
        $variableID = $this->ReadPropertyInteger('PowerVariableID');
        $registeredVariableID = $this->ReadAttributeInteger('RegisteredPowerVariableID');
        $isValid = $variableID > 0 && IPS_VariableExists($variableID);

        if ($registeredVariableID > 0 && (!$isValid || $registeredVariableID !== $variableID)) {
            $this->UnregisterReference($registeredVariableID);
        }

        if ($isValid) {
            $this->RegisterReference($variableID);
        }

        $this->WriteAttributeInteger('RegisteredPowerVariableID', $isValid ? $variableID : 0);
    }

    private function UpdateArchivedEnergy(): void
    {
        $variableID = $this->ReadPropertyInteger('PowerVariableID');
        if ($variableID <= 0) {
            $this->SetEnergyArchiveUnavailable('Select a logged total electrical power variable in watts.');
            return;
        }
        if (!IPS_VariableExists($variableID)) {
            $this->SetEnergyArchiveUnavailable('The selected power variable does not exist.');
            return;
        }

        $variable = IPS_GetVariable($variableID);
        if (!in_array($variable['VariableType'], [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT], true)) {
            $this->SetEnergyArchiveUnavailable('The selected power variable must be an integer or float in watts.');
            return;
        }

        $archiveInstances = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE_ID);
        if ($archiveInstances === []) {
            $this->SetEnergyArchiveUnavailable('No Archive Control instance is available.');
            return;
        }
        $archiveID = (int) $archiveInstances[0];
        if (!AC_GetLoggingStatus($archiveID, $variableID)) {
            $this->SetEnergyArchiveUnavailable('Archive logging is not enabled for the selected power variable.');
            return;
        }
        if (AC_GetAggregationType($archiveID, $variableID) !== 0) {
            $this->SetEnergyArchiveUnavailable('The selected variable must use standard archive aggregation, not counter aggregation.');
            return;
        }

        $now = time();
        $periods = [
            'EnergyToday' => [1, mktime(0, 0, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now))],
            'EnergyThisMonth' => [3, mktime(0, 0, 0, (int) date('n', $now), 1, (int) date('Y', $now))],
            'EnergyThisYear' => [4, mktime(0, 0, 0, 1, 1, (int) date('Y', $now))]
        ];

        $availablePeriods = 0;
        foreach ($periods as $ident => [$aggregationLevel, $periodStart]) {
            $energyKWh = $this->ReadArchivedEnergyKWh($archiveID, $variableID, $aggregationLevel, $periodStart, $now);
            if ($energyKWh === null) {
                $this->SetValue($ident, 0.0);
                continue;
            }

            $this->SetValue($ident, $energyKWh);
            $availablePeriods++;
        }

        if ($availablePeriods === 0) {
            $this->SetValue('EnergyArchiveStatus', $this->Translate('No aggregated archive data is available for the current day, month or year.'));
        } elseif ($availablePeriods < count($periods)) {
            $this->SetValue('EnergyArchiveStatus', $this->Translate('Energy values are calculated from available archive aggregates; one or more periods have no data yet.'));
        } else {
            $this->SetValue('EnergyArchiveStatus', $this->Translate('Energy values are calculated from archived average power and aggregation duration.'));
        }
    }

    private function ReadArchivedEnergyKWh(int $archiveID, int $variableID, int $aggregationLevel, int $periodStart, int $now): ?float
    {
        $values = AC_GetAggregatedValues($archiveID, $variableID, $aggregationLevel, $periodStart, $now, 0);
        $energyKWh = 0.0;
        $hasData = false;

        foreach ($values as $value) {
            if (!isset($value['TimeStamp'], $value['Avg'], $value['Duration'])
                || (int) $value['TimeStamp'] < $periodStart
                || (int) $value['TimeStamp'] > $now
                || (int) $value['Duration'] <= 0
                || (float) $value['Avg'] < 0) {
                continue;
            }

            $energyKWh += (float) $value['Avg'] * (int) $value['Duration'] / 3600000;
            $hasData = true;
        }

        return $hasData ? $energyKWh : null;
    }

    private function SetEnergyArchiveUnavailable(string $message): void
    {
        foreach (['EnergyToday', 'EnergyThisMonth', 'EnergyThisYear'] as $ident) {
            $this->SetValue($ident, 0.0);
        }
        $this->SetValue('EnergyArchiveStatus', $this->Translate($message));
    }

    private function ReadRegister(int $function, int $address): ?int
    {
        $key = $function . ':' . $address;
        if (array_key_exists($key, $this->pollCache)) {
            return $this->pollCache[$key];
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

    private function SendServiceCommand(int $address, string $message): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !$this->HasActiveParent()) {
            throw new RuntimeException($this->Translate('An active Modbus parent connection is required.'));
        }

        $response = $this->SendDataToParent(json_encode([
            'DataID' => self::MODBUS_DATA_ID,
            'Function' => 6,
            'Address' => $address,
            'Quantity' => 1,
            'Data' => bin2hex(pack('n', 1))
        ], JSON_THROW_ON_ERROR));

        if ($response === false) {
            throw new RuntimeException($this->Translate('The FWT service command could not be sent.'));
        }

        $this->LogMessage($message, KL_MESSAGE);
    }

    private function DecodeSignedValue(int $value): int
    {
        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }
}
