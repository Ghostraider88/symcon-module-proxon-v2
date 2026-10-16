<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTStatusPolling.php';

define('KR_READY', 10103);
$GLOBALS['testSemaphores'] = [];

function IPS_GetKernelRunlevel(): int
{
    return KR_READY;
}

function IPS_SemaphoreEnter(string $name, int $waitingTime): bool
{
    if (isset($GLOBALS['testSemaphores'][$name])) {
        return false;
    }

    $GLOBALS['testSemaphores'][$name] = true;
    return true;
}

function IPS_SemaphoreLeave(string $name): void
{
    unset($GLOBALS['testSemaphores'][$name]);
}

final class FWTStatusPollingProbe
{
    use FWTStatusPolling;

    public int $InstanceID = 1001;
    public int $interval = 30;
    public int $timerInterval = -1;
    public int $status = -1;
    public mixed $parentResponse = false;
    public bool $throwOnRead = false;
    public array $debugMessages = [];
    public array $attributes = [];
    public array $values = [];

    private function SetTimerInterval(string $name, int $interval): void
    {
        $this->timerInterval = $interval;
    }

    private function RegisterAttributeString(string $name, string $defaultValue): void
    {
        $this->attributes[$name] = $defaultValue;
    }

    private function RegisterVariableString(string $ident, string $name, string $profile, int $position): void
    {
        $this->values[$ident] ??= '';
    }

    private function Translate(string $text): string
    {
        return $text;
    }

    public function HasActiveParent(): bool
    {
        return true;
    }

    private function ReadAttributeString(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    private function WriteAttributeString(string $name, string $value): void
    {
        $this->attributes[$name] = $value;
    }

    private function SetValue(string $ident, mixed $value): void
    {
        $this->values[$ident] = $value;
    }

    private function SetStatus(int $status): void
    {
        $this->status = $status;
    }

    private function ReadPropertyInteger(string $name): int
    {
        return $this->interval;
    }

    private function SendDataToParent(string $request): mixed
    {
        if ($this->throwOnRead) {
            throw new RuntimeException('simulated transport exception');
        }

        return $this->parentResponse;
    }

    private function SendDebug(string $message, string $data, int $format): void
    {
        $this->debugMessages[] = [$message, $data];
    }

    public function Configure(): void
    {
        $this->ConfigureStatusPolling($this->interval);
    }

    public function InitializeQuality(): void
    {
        $this->CreateStatusReadQuality();
        $this->RegisterStatusReadQualityVariable(500);
    }

    public function RunPoll(callable $callback): bool
    {
        $startedAt = $this->BeginStatusPoll();
        if ($startedAt === null) {
            return false;
        }

        try {
            $callback();
            return true;
        } finally {
            $this->EndStatusPoll($startedAt, 'test');
        }
    }

    public function Read(): mixed
    {
        return $this->SendMeasuredStatusRead('FC3 block 10x1', 'request', 4);
    }
}

function assertStatusPolling(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function getLatestReadDebug(array $messages): array
{
    $readMessages = array_values(array_filter($messages, static fn (array $message): bool => $message[0] === 'Modbus read'));
    return $readMessages[array_key_last($readMessages)];
}

$probe = new FWTStatusPollingProbe();
$probe->InitializeQuality();
$probe->interval = 0;
$probe->Configure();
assertStatusPolling($probe->timerInterval === 0 && $probe->status === 104, 'Interval 0 must disable only the timer.');
$pollRuns = 0;
assertStatusPolling($probe->RunPoll(static function () use (&$pollRuns): void { $pollRuns++; }), 'A manual poll must work when automatic polling is disabled.');
assertStatusPolling($pollRuns === 1, 'The manual poll should run exactly once.');

foreach ([-1, 1, 4, 86401] as $invalidInterval) {
    $probe->interval = $invalidInterval;
    $probe->Configure();
    assertStatusPolling($probe->timerInterval === 0 && $probe->status === 203, 'Invalid intervals must stop polling and set error status.');
    assertStatusPolling(!$probe->RunPoll(static function (): void { throw new RuntimeException('invalid interval must not poll'); }), 'Invalid intervals must reject manual poll requests.');
}

foreach ([5, 30, 86400] as $validInterval) {
    $probe->interval = $validInterval;
    $probe->Configure();
    assertStatusPolling($probe->timerInterval === $validInterval * 1000 && $probe->status === 102, 'Valid intervals must be applied in milliseconds.');
}

$probe->interval = 30;
$nestedPollRan = false;
$probe->RunPoll(function () use ($probe, &$nestedPollRan): void {
    assertStatusPolling(!$probe->RunPoll(static function () use (&$nestedPollRan): void { $nestedPollRan = true; }), 'Reentrant polls must be skipped.');
    $parallelInvocation = new FWTStatusPollingProbe();
    assertStatusPolling(!$parallelInvocation->RunPoll(static function () use (&$nestedPollRan): void { $nestedPollRan = true; }), 'Concurrent calls for the same instance must be rejected by the semaphore.');
});
assertStatusPolling(!$nestedPollRan, 'The reentrant callback must not execute.');
assertStatusPolling($GLOBALS['testSemaphores'] === [], 'The semaphore must be released after the outer poll.');

try {
    $probe->RunPoll(static function (): void { throw new RuntimeException('simulated poll failure'); });
} catch (RuntimeException $exception) {
    assertStatusPolling($exception->getMessage() === 'simulated poll failure', 'The original poll exception must propagate.');
}
assertStatusPolling($probe->RunPoll(static function (): void {}), 'The poll guard must release after an exception.');

$probe->parentResponse = "\x01\x00\x2A";
$probe->RunPoll(static fn () => $probe->Read());
assertStatusPolling(str_contains(getLatestReadDebug($probe->debugMessages)[1], 'failed'), 'Short responses must be recorded as failures.');
assertStatusPolling($probe->status === 202, 'Failed reads must set an error status.');
$quality = json_decode($probe->values['ReadQuality'], true);
assertStatusPolling(($quality['FC3 block 10x1']['status'] ?? '') === 'failed', 'A short read must be machine-readable as failed.');
$probe->parentResponse = "\x01\x02\x00\x2A";
$probe->RunPoll(static fn () => $probe->Read());
assertStatusPolling(str_contains(getLatestReadDebug($probe->debugMessages)[1], 'ok (4 bytes)'), 'Complete responses must be recorded as successful.');
assertStatusPolling($probe->status === 102, 'Successful reads must restore the active status.');
$quality = json_decode($probe->values['ReadQuality'], true);
assertStatusPolling(($quality['FC3 block 10x1']['status'] ?? '') === 'current', 'A complete read must restore current quality.');
assertStatusPolling(($quality['FC3 block 10x1']['lastSuccessTimestamp'] ?? 0) > 0, 'Read quality must store a machine-readable success timestamp.');
$probe->throwOnRead = true;
try {
    $probe->Read();
} catch (RuntimeException $exception) {
    assertStatusPolling($exception->getMessage() === 'simulated transport exception', 'The transport exception must propagate.');
}
assertStatusPolling(str_contains(getLatestReadDebug($probe->debugMessages)[1], 'failed'), 'Transport exceptions must still produce timing evidence.');

echo "Status polling and transport checks passed.\n";
