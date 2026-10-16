<?php

declare(strict_types=1);

trait FWTControlInput
{
    private function NormalizeFWTNumber(mixed $value): float
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidArgumentException($this->Translate('The value must be a finite number.'));
        }

        return (float) $value;
    }

    private function NormalizeFWTInteger(mixed $value): int
    {
        $number = $this->NormalizeFWTNumber($value);
        if (floor($number) !== $number) {
            throw new InvalidArgumentException($this->Translate('The value must be a whole number.'));
        }

        return (int) $number;
    }

    private function NormalizeFWTWholeDegreeTemperature(mixed $value): int
    {
        return (int) round($this->NormalizeFWTNumber($value));
    }

    private function NormalizeFWTBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        throw new InvalidArgumentException($this->Translate('The value must be a boolean or 0 or 1.'));
    }

    private function NormalizeFWTSelection(mixed $value, array $options, ?string $errorMessage = null): int
    {
        $normalizedValue = $this->NormalizeFWTInteger($value);
        if (!array_key_exists($normalizedValue, $options)) {
            throw new InvalidArgumentException($errorMessage ?? $this->Translate('Invalid selection value.'));
        }

        return $normalizedValue;
    }

    private function IsValidFWTPanelID(mixed $value, int $maximum): bool
    {
        try {
            $panelID = $this->NormalizeFWTInteger($value);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $panelID >= 1 && $panelID <= $maximum;
    }

    private function NormalizeFWTControlValue(mixed $value, array $definition): array
    {
        if ($definition['type'] === 'boolean') {
            $normalizedValue = $this->NormalizeFWTBoolean($value);
            $rawValue = $normalizedValue ? 1 : 0;
        } elseif ($definition['type'] === 'enumeration') {
            $normalizedValue = $this->NormalizeFWTSelection($value, $definition['options']);
            $rawValue = $normalizedValue;
        } else {
            $numericValue = $this->NormalizeFWTNumber($value);
            if ($numericValue < $definition['minimum'] || $numericValue > $definition['maximum']) {
                throw new InvalidArgumentException(sprintf(
                    $this->Translate('The value must be between %s and %s.'),
                    (string) $definition['minimum'],
                    (string) $definition['maximum']
                ));
            }

            if ($definition['type'] === 'integer') {
                $normalizedValue = $this->NormalizeFWTInteger($numericValue);
                $rawValue = $normalizedValue * $definition['scale'];
            } else {
                $rawValue = (int) round($numericValue * $definition['scale']);
                $normalizedValue = (float) ($rawValue / $definition['scale']);
            }
        }

        return ['value' => $normalizedValue, 'raw' => $rawValue];
    }
}
