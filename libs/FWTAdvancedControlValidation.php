<?php

declare(strict_types=1);

trait FWTAdvancedControlValidation
{
    private function ValidateFWTFanAirflowControl(string $ident, int|float $requestedValue, array $opposingDefinition): void
    {
        $opposingRawValue = $this->ReadFreshRegister($opposingDefinition['address']);
        if ($opposingRawValue === null) {
            throw new RuntimeException($this->Translate('The current opposing fan airflow limit could not be read.'));
        }

        $opposingValue = $this->DecodeControlValue($opposingRawValue, $opposingDefinition);
        if ((!is_int($opposingValue) && !is_float($opposingValue))
            || !is_finite((float) $opposingValue)
            || $opposingValue < $opposingDefinition['minimum']
            || $opposingValue > $opposingDefinition['maximum']) {
            throw new RuntimeException($this->Translate('The current opposing fan airflow limit is outside its valid range.'));
        }

        $minimum = $ident === 'FanMinimumAirflow' ? $requestedValue : $opposingValue;
        $maximum = $ident === 'FanMaximumAirflow' ? $requestedValue : $opposingValue;
        if ($minimum > $maximum) {
            throw new InvalidArgumentException($this->Translate('The minimum airflow must not exceed the maximum airflow.'));
        }
    }
}
