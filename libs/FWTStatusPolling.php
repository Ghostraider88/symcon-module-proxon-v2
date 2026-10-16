<?php

declare(strict_types=1);

trait FWTStatusPolling
{
    private bool $fwtStatusPollActive = false;
    private int $fwtStatusPollReadCount = 0;
    private int $fwtStatusPollFailureCount = 0;

    public function RefreshReadQuality(): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY || !IPS_SemaphoreEnter($this->GetStatusPollingSemaphoreName(), 0)) {
            return;
        }

        try {
            $quality = $this->ReadStatusReadQualityMap();
            $now = (int) floor(microtime(true) * 1000);
            $maximumAgeMilliseconds = $this->GetStatusReadMaximumAge() * 1000;
            foreach ($quality as &$entry) {
                $lastSuccess = (int) ($entry['lastSuccessTimestamp'] ?? 0);
                $lastAttempt = (int) ($entry['lastAttemptTimestamp'] ?? 0);
                $lastAttemptSucceeded = (bool) ($entry['lastAttemptSucceeded'] ?? false);
                $entry['ageSeconds'] = $lastSuccess > 0 ? max(0, (int) floor(($now - $lastSuccess) / 1000)) : null;

                if ($lastSuccess === 0) {
                    $entry['status'] = $lastAttempt > 0 ? 'failed' : 'unknown';
                } elseif ($now - $lastSuccess > $maximumAgeMilliseconds) {
                    $entry['status'] = 'stale';
                } else {
                    $entry['status'] = $lastAttemptSucceeded ? 'current' : 'failed';
                }
            }
            unset($entry);

            $this->WriteStatusReadQualityMap($quality);
        } finally {
            IPS_SemaphoreLeave($this->GetStatusPollingSemaphoreName());
        }
    }

    private function CreateStatusReadQuality(): void
    {
        $this->RegisterAttributeString('StatusReadQuality', '{}');
    }

    private function RegisterStatusReadQualityVariable(int $position): void
    {
        $this->RegisterVariableString('ReadQuality', $this->Translate('Modbus Read Quality (JSON)'), '', $position);
        $this->SetTimerInterval('StatusQuality', 30000);
        $this->RefreshReadQuality();
    }

    private function RecordStatusReadQuality(string $group, bool $success, int $attemptTimestamp, array $identifiers = []): void
    {
        if ($this->fwtStatusPollActive) {
            $this->fwtStatusPollReadCount++;
            if (!$success) {
                $this->fwtStatusPollFailureCount++;
            }
        }

        $quality = $this->ReadStatusReadQualityMap();
        $entry = $quality[$group] ?? [];
        if ($identifiers !== []) {
            $entry['identifiers'] = array_values(array_unique($identifiers));
        }
        $entry['lastAttemptTimestamp'] = $attemptTimestamp;
        $entry['lastAttemptSucceeded'] = $success;
        $entry['lastSuccessTimestamp'] = $success
            ? $attemptTimestamp
            : (int) ($entry['lastSuccessTimestamp'] ?? 0);
        $entry['ageSeconds'] = $entry['lastSuccessTimestamp'] > 0
            ? max(0, (int) floor(((int) floor(microtime(true) * 1000) - $entry['lastSuccessTimestamp']) / 1000))
            : null;
        $entry['status'] = $success ? 'current' : 'failed';
        $quality[$group] = $entry;
        $this->WriteStatusReadQualityMap($quality);
    }

    private function ReadStatusReadQualityMap(): array
    {
        $quality = json_decode($this->ReadAttributeString('StatusReadQuality'), true);
        return is_array($quality) ? $quality : [];
    }

    private function WriteStatusReadQualityMap(array $quality): void
    {
        $serialized = json_encode($quality, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->WriteAttributeString('StatusReadQuality', $serialized);
        $this->SetValue('ReadQuality', $serialized);
    }

    private function GetStatusReadMaximumAge(): int
    {
        $interval = $this->ReadPropertyInteger('Interval');
        return $interval === 0 ? 300 : max(300, $interval * 3);
    }

    private function IsStatusReadCurrent(string $group): bool
    {
        $entry = $this->ReadStatusReadQualityMap()[$group] ?? null;
        if (!is_array($entry) || !(bool) ($entry['lastAttemptSucceeded'] ?? false)) {
            return false;
        }

        $lastSuccess = (int) ($entry['lastSuccessTimestamp'] ?? 0);
        $maximumAgeMilliseconds = $this->GetStatusReadMaximumAge() * 1000;
        return $lastSuccess > 0 && (int) floor(microtime(true) * 1000) - $lastSuccess <= $maximumAgeMilliseconds;
    }

    private function BuildContiguousStatusReadGroups(array $definitions): array
    {
        $entries = [];
        foreach ($definitions as $ident => $definition) {
            $entries[] = [
                'ident'      => $ident,
                'definition' => $definition
            ];
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => $left['definition']['address'] <=> $right['definition']['address']
        );

        $groups = [];
        foreach ($entries as $entry) {
            $address = $entry['definition']['address'];
            $lastGroupIndex = count($groups) - 1;
            if ($lastGroupIndex >= 0) {
                $lastGroup = &$groups[$lastGroupIndex];
                $lastAddress = $lastGroup['address'] + $lastGroup['quantity'] - 1;
                if ($address === $lastAddress + 1) {
                    $lastGroup['quantity']++;
                    $lastGroup['definitions'][] = $entry;
                    unset($lastGroup);
                    continue;
                }
                unset($lastGroup);
            }

            $groups[] = [
                'address'     => $address,
                'quantity'    => 1,
                'definitions' => [$entry]
            ];
        }

        return $groups;
    }

    private function ConfigureStatusPolling(int $intervalSeconds): void
    {
        if (!$this->IsStatusPollingIntervalValid($intervalSeconds)) {
            $this->SetTimerInterval('Poller', 0);
            $this->SetStatus(203);
            return;
        }

        if ($intervalSeconds === 0) {
            $this->SetTimerInterval('Poller', 0);
            $this->SetStatus(104);
            return;
        }

        $this->SetTimerInterval('Poller', $intervalSeconds * 1000);
        $this->SetStatus(102);
    }

    private function IsStatusPollingIntervalValid(int $intervalSeconds): bool
    {
        return $intervalSeconds === 0 || ($intervalSeconds >= 5 && $intervalSeconds <= 86400);
    }

    private function BeginStatusPoll(): ?int
    {
        if (
            $this->fwtStatusPollActive ||
            IPS_GetKernelRunlevel() !== KR_READY ||
            !$this->IsStatusPollingIntervalValid($this->ReadPropertyInteger('Interval')) ||
            !IPS_SemaphoreEnter($this->GetStatusPollingSemaphoreName(), 0)
        ) {
            return null;
        }

        $this->fwtStatusPollActive = true;
        $this->fwtStatusPollReadCount = 0;
        $this->fwtStatusPollFailureCount = 0;
        return hrtime(true);
    }

    private function EndStatusPoll(int $startedAt, string $group): void
    {
        $elapsedMicroseconds = intdiv(hrtime(true) - $startedAt, 1000);
        $this->fwtStatusPollActive = false;
        IPS_SemaphoreLeave($this->GetStatusPollingSemaphoreName());
        $this->SendDebug('Status poll duration', $group . ': ' . $elapsedMicroseconds . ' us', 0);

        if ($this->fwtStatusPollReadCount === 0) {
            if (!$this->HasActiveParent()) {
                $this->SetStatus(202);
            }
            return;
        }

        if ($this->fwtStatusPollFailureCount > 0) {
            $this->SetStatus(202);
            return;
        }

        $interval = $this->ReadPropertyInteger('Interval');
        $this->SetStatus($interval === 0 ? 104 : 102);
    }

    private function GetStatusPollingSemaphoreName(): string
    {
        return 'ProxonStatusPoll_' . $this->InstanceID;
    }

    private function SendMeasuredStatusRead(string $group, string $request, int $expectedBytes, array $identifiers = []): mixed
    {
        $startedAt = hrtime(true);
        $attemptTimestamp = (int) floor(microtime(true) * 1000);
        $response = false;
        try {
            $response = $this->SendDataToParent($request);
            return $response;
        } finally {
            $elapsedMicroseconds = intdiv(hrtime(true) - $startedAt, 1000);
            $responseValid = is_string($response) && strlen($response) === $expectedBytes;
            $detail = $responseValid ? 'ok (' . strlen($response) . ' bytes)' : 'failed (invalid or missing response)';
            $this->RecordStatusReadQuality($group, $responseValid, $attemptTimestamp, $identifiers);
            $this->SendDebug('Modbus read', $group . ': ' . $detail . ', ' . $elapsedMicroseconds . ' us', 0);
        }
    }
}
