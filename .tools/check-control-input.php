<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTControlInput.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'FWTAdvancedControlValidation.php';

class FWTControlInputHarness
{
    use FWTControlInput;

    public function Translate(string $text): string
    {
        return $text;
    }

    public function Normalize(mixed $value, array $definition): array
    {
        return $this->NormalizeFWTControlValue($value, $definition);
    }

    public function IsPanelIDValid(mixed $value, int $maximum = 20): bool
    {
        return $this->IsValidFWTPanelID($value, $maximum);
    }

    public function WholeDegreeTemperature(mixed $value): int
    {
        return $this->NormalizeFWTWholeDegreeTemperature($value);
    }

    public function OperatingMode(mixed $value): int
    {
        return $this->NormalizeFWTSelection($value, [0 => true, 1 => true, 2 => true, 3 => true, 4 => true, 6 => true]);
    }
}

class FWTAdvancedValidationHarness
{
    use FWTAdvancedControlValidation;

    public array $cache = [];
    public array $liveValues = [];
    public array $freshReads = [];

    public function Translate(string $text): string
    {
        return $text;
    }

    public function ReadFreshRegister(int $address): ?int
    {
        $this->freshReads[] = $address;
        unset($this->cache[$address]);
        return $this->liveValues[$address] ?? null;
    }

    public function DecodeControlValue(int $rawValue, array $definition): float
    {
        return $rawValue / $definition['scale'];
    }

    public function Validate(string $ident, int|float $requestedValue, array $opposingDefinition): void
    {
        $this->ValidateFWTFanAirflowControl($ident, $requestedValue, $opposingDefinition);
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function checkRejected(callable $operation, string $message, string $exceptionClass = InvalidArgumentException::class): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return;
        }
        throw $exception;
    }

    throw new RuntimeException($message);
}

$input = new FWTControlInputHarness();
$floatDefinition = ['type' => 'float', 'minimum' => 25, 'maximum' => 50, 'scale' => 100];
$atMinimum = $input->Normalize(25, $floatDefinition);
check($atMinimum['raw'] === 2500 && $atMinimum['value'] === 25.0, 'Float lower boundary and raw encoding');
$rounded = $input->Normalize(33.337, $floatDefinition);
check($rounded['raw'] === 3334 && $rounded['value'] === 33.34, 'Displayed value matches encoded resolution');
foreach ([NAN, INF, -INF, '25', true, 51] as $invalid) {
    checkRejected(static fn () => $input->Normalize($invalid, $floatDefinition), 'Invalid float input or range was accepted');
}

$integerDefinition = ['type' => 'integer', 'minimum' => 0, 'maximum' => 1440, 'scale' => 1];
check($input->Normalize(120, $integerDefinition)['raw'] === 120, 'Integer control encoding');
checkRejected(static fn () => $input->Normalize(120.5, $integerDefinition), 'Fractional integer was accepted');

$enumerationDefinition = ['type' => 'enumeration', 'options' => [0 => 'Off', 1 => 'On']];
check($input->Normalize(0, $enumerationDefinition)['value'] === 0, 'Enumeration zero is valid');
foreach (['invalid', 2] as $invalid) {
    checkRejected(static fn () => $input->Normalize($invalid, $enumerationDefinition), 'Invalid enumeration was accepted');
}
check($input->OperatingMode(0) === 0 && $input->OperatingMode(6) === 6, 'Valid operating mode boundaries');
foreach ([5, 'invalid', NAN] as $invalid) {
    checkRejected(static fn () => $input->OperatingMode($invalid), 'Invalid operating mode was accepted');
}

$booleanDefinition = ['type' => 'boolean'];
check($input->Normalize(false, $booleanDefinition)['raw'] === 0, 'Boolean false encoding');
check($input->Normalize(1, $booleanDefinition)['value'] === true, 'Integer one boolean encoding');
foreach (['false', 2, 1.0] as $invalid) {
    checkRejected(static fn () => $input->Normalize($invalid, $booleanDefinition), 'Invalid boolean was accepted');
}

check($input->IsPanelIDValid(1) && $input->IsPanelIDValid(20), 'Panel IDs 1 and 20 are valid');
foreach ([0, 21, -1, 1.5, '1'] as $invalid) {
    check(!$input->IsPanelIDValid($invalid), 'Invalid panel ID was accepted');
}
check($input->WholeDegreeTemperature(21.4) === 21 && $input->WholeDegreeTemperature(21.5) === 22, 'FWT room temperatures round to whole degrees');
checkRejected(static fn () => $input->WholeDegreeTemperature(NAN), 'Non-finite room temperature was accepted');

$opposingDefinition = ['address' => 12, 'minimum' => 0, 'maximum' => 100, 'scale' => 100];
$fanLimits = new FWTAdvancedValidationHarness();
$fanLimits->liveValues[12] = 7500;
$fanLimits->Validate('FanMinimumAirflow', 50.0, $opposingDefinition);
check($fanLimits->freshReads === [12], 'Read the opposing airflow limit freshly');

$fanLimits = new FWTAdvancedValidationHarness();
$fanLimits->cache[12] = 10000;
$fanLimits->liveValues[12] = 4000;
checkRejected(
    static fn () => $fanLimits->Validate('FanMinimumAirflow', 50.0, $opposingDefinition),
    'A stale cached airflow value was used'
);
check($fanLimits->freshReads === [12] && !isset($fanLimits->cache[12]), 'Stale cache was bypassed');

$fanLimits = new FWTAdvancedValidationHarness();
$fanLimits->liveValues[11] = 10000;
checkRejected(
    static fn () => $fanLimits->Validate('FanMaximumAirflow', 0.0, ['address' => 11, 'minimum' => 0, 'maximum' => 100, 'scale' => 100]),
    'Maximum below minimum was accepted'
);

$fanLimits = new FWTAdvancedValidationHarness();
checkRejected(
    static fn () => $fanLimits->Validate('FanMinimumAirflow', 10.0, $opposingDefinition),
    'Missing opposing airflow value was accepted',
    RuntimeException::class
);

echo "FWT control input and Advanced fan-limit regression checks passed.\n";
