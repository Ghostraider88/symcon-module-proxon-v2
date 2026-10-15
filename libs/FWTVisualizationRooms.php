<?php

declare(strict_types=1);

trait FWTVisualizationRooms
{
    private function CreateVisualizationRooms(): void
    {
        $this->RegisterPropertyString('VisualizationRooms', '[]');
        $this->RegisterAttributeString('VisualizationRoomReferences', '[]');
        $this->RegisterAttributeString('VisualizationRoomMessages', '[]');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        $this->RegisterTimer('RoomVisualizationRefresh', 0, 'PROXON_RefreshVisualizationRooms($_IPS[\'TARGET\']);');
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($SenderID === 0 && $Message === IPS_KERNELSTARTED) {
            $this->UpdateVisualizationRoomBindings();
            $this->SetTimerInterval('RoomVisualizationRefresh', 250);
        } elseif ($Message === VM_UPDATE
            && in_array($SenderID, json_decode($this->ReadAttributeString('VisualizationRoomMessages'), true) ?? [], true)) {
            $this->SetTimerInterval('RoomVisualizationRefresh', 250);
        }
    }

    public function RefreshVisualizationRooms(): void
    {
        $this->SetTimerInterval('RoomVisualizationRefresh', 0);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->UpdateVisualizationValue($this->GetVisualizationData());
        }
    }

    private function UpdateVisualizationRoomBindings(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        $references = [];
        $messages = [];
        foreach ($this->GetVisualizationRoomDefinitions(true) as $room) {
            if ($room['instanceID'] > 0) {
                $references[] = $room['instanceID'];
            }
            foreach ($room['variables'] as $variableID) {
                if ($variableID > 0 && IPS_VariableExists($variableID)) {
                    $references[] = $variableID;
                    $messages[] = $variableID;
                }
            }
        }
        $references = array_values(array_unique($references));
        $messages = array_values(array_unique($messages));
        sort($references);
        sort($messages);
        $oldReferences = json_decode($this->ReadAttributeString('VisualizationRoomReferences'), true) ?? [];
        $oldMessages = json_decode($this->ReadAttributeString('VisualizationRoomMessages'), true) ?? [];
        foreach (array_diff($oldReferences, $references) as $objectID) {
            $this->UnregisterReference($objectID);
        }
        foreach ($references as $objectID) {
            $this->RegisterReference($objectID);
        }
        foreach (array_diff($oldMessages, $messages) as $variableID) {
            $this->UnregisterMessage($variableID, VM_UPDATE);
        }
        foreach ($messages as $variableID) {
            $this->RegisterMessage($variableID, VM_UPDATE);
        }
        $this->WriteAttributeString('VisualizationRoomReferences', json_encode($references, JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('VisualizationRoomMessages', json_encode($messages, JSON_THROW_ON_ERROR));
        $this->SetTimerInterval('RoomVisualizationRefresh', 250);
    }

    private function GetVisualizationRoomDefinitions(bool $reportErrors = false): array
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return [];
        }
        try {
            $rows = json_decode($this->ReadPropertyString('VisualizationRooms'), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || !array_is_list($rows)) {
                throw new JsonException('Expected a list');
            }
        } catch (JsonException $exception) {
            if ($reportErrors) {
                $this->LogMessage($this->Translate('The visualization room mapping must be a JSON list.'), KL_WARNING);
            }
            return [];
        }

        $ownVariables = $this->GetVisualizationChildVariables($this->InstanceID);
        $rooms = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $instanceID = $this->GetVisualizationMappingID($row['RoomInstanceID'] ?? 0);
            $native = [];
            if ($instanceID > 0 && IPS_InstanceExists($instanceID)
                && in_array(IPS_GetInstance($instanceID)['ModuleInfo']['ModuleID'], [
                    '{82991C5F-F554-4ED2-B2C7-2B2999B32C46}',
                    '{9496FF42-B793-02E3-8271-541651A9085F}',
                    '{1D1DC6F5-A07B-FC2B-84E4-D68E6B71D401}'
                ], true)) {
                $native = $this->GetVisualizationChildVariables($instanceID);
            } else {
                $instanceID = 0;
            }
            $name = is_string($row['Name'] ?? null) ? trim($row['Name']) : '';
            if ($name === '' && $instanceID > 0) {
                $name = IPS_GetName($instanceID);
            }
            if ($name === '' && $instanceID === 0) {
                continue;
            }
            $sensor = $this->GetVisualizationMappingID($row['AirQualitySensor'] ?? 0);
            $sensorAvailable = $sensor >= 1 && $sensor <= $this->GetSensorCount();
            $externalCO2 = $this->GetVisualizationMappingID($row['CO2VariableID'] ?? 0);
            $externalHumidity = $this->GetVisualizationMappingID($row['HumidityVariableID'] ?? 0);
            $externalPTC = $this->GetVisualizationMappingID($row['PTCStatusVariableID'] ?? 0);
            $rooms[] = [
                'name' => $name,
                'floor' => ($row['Floor'] ?? '') === 'OG' ? 'OG' : 'EG',
                'instanceID' => $instanceID,
                'variables' => [
                    'temperature' => $native['CurrentTemperature'] ?? 0,
                    'setpoint' => $native['TargetTemperature'] ?? 0,
                    'co2' => $externalCO2 > 0 ? $externalCO2
                        : ($sensorAvailable ? ($ownVariables['CO2Sensor' . $sensor . 'Value'] ?? 0) : 0),
                    'humidity' => $externalHumidity > 0 ? $externalHumidity
                        : ($sensorAvailable ? ($ownVariables['CO2Sensor' . $sensor . 'RelativeHumidity'] ?? 0) : 0),
                    // PTCRelease is permission to heat, not evidence of an active heater.
                    'ptc' => $externalPTC > 0 ? $externalPTC : ($native['PTCStatus'] ?? 0)
                ]
            ];
        }
        return $rooms;
    }

    private function GetVisualizationMappingID(mixed $value): int
    {
        return is_int($value) || (is_string($value) && preg_match('/^\d+$/D', $value) === 1)
            ? max(0, (int) $value) : 0;
    }

    private function GetVisualizationChildVariables(int $parentID): array
    {
        $variables = [];
        foreach (IPS_GetChildrenIDs($parentID) as $childID) {
            if (IPS_VariableExists($childID)) {
                $ident = IPS_GetObject($childID)['ObjectIdent'];
                if ($ident !== '') {
                    $variables[$ident] = $childID;
                }
            }
        }
        return $variables;
    }

    private function GetVisualizationRooms(): array
    {
        $rooms = [];
        foreach ($this->GetVisualizationRoomDefinitions() as $definition) {
            $room = ['name' => $definition['name'], 'floor' => $definition['floor']];
            foreach ($definition['variables'] as $field => $variableID) {
                $room[$field] = $this->GetVisualizationRoomValue($variableID, $field);
            }
            $rooms[] = $room;
        }
        return $rooms;
    }

    private function GetVisualizationRoomValue(int $variableID, string $field): int|float|bool|null
    {
        if ($variableID <= 0 || !IPS_VariableExists($variableID)
            || (int) IPS_GetVariable($variableID)['VariableUpdated'] <= 0) {
            return null;
        }
        $value = GetValue($variableID);
        if ($field === 'ptc') {
            return is_bool($value) ? $value : null;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            return null;
        }
        if (($field === 'humidity' && ($value < 0 || $value > 100)) || ($field === 'co2' && $value < 0)) {
            return null;
        }
        return $value;
    }
}
