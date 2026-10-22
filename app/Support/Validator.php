<?php

declare(strict_types=1);

namespace AxaKenneth\Support;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? self::humanize($field);
            $value = isset($data[$field]) && is_scalar($data[$field]) ? trim((string) $data[$field]) : '';
            $fieldRules = array_filter(explode('|', $ruleString));

            $hasRequired = in_array('required', $fieldRules, true);
            if (!$hasRequired && $value === '') {
                continue;
            }

            foreach ($fieldRules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if ($name === 'required' && $value === '') {
                    $errors[$field] = $label . ' is required.';
                    break;
                }
                if ($value === '') {
                    continue;
                }

                switch ($name) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = 'Enter a valid email address.';
                        }
                        break;
                    case 'url':
                        if (filter_var($value, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $value)) {
                            $errors[$field] = 'Enter a valid http or https URL.';
                        }
                        break;
                    case 'int':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $errors[$field] = $label . ' must be a whole number.';
                        }
                        break;
                    case 'max':
                        if ($parameter !== null && mb_strlen($value) > (int) $parameter) {
                            $errors[$field] = $label . ' must be ' . (int) $parameter . ' characters or fewer.';
                        }
                        break;
                    case 'min':
                        if ($parameter !== null && mb_strlen($value) < (int) $parameter) {
                            $errors[$field] = $label . ' must be at least ' . (int) $parameter . ' characters.';
                        }
                        break;
                    case 'in':
                        $allowed = $parameter !== null ? explode(',', $parameter) : [];
                        if (!in_array($value, $allowed, true)) {
                            $errors[$field] = 'Choose a valid ' . strtolower($label) . '.';
                        }
                        break;
                    case 'date':
                        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                        if (!$date || $date->format('Y-m-d') !== $value) {
                            $errors[$field] = 'Enter a valid date.';
                        }
                        break;
                    case 'password':
                        if (mb_strlen($value) < 12) {
                            $errors[$field] = 'Use at least 12 characters.';
                        } elseif (!preg_match('/[A-Za-z]/', $value) || !preg_match('/[0-9]/', $value)) {
                            $errors[$field] = 'Include at least one letter and one number.';
                        }
                        break;
                    case 'accepted':
                        if (!in_array(strtolower($value), ['1', 'on', 'yes', 'true'], true)) {
                            $errors[$field] = $label . ' must be confirmed.';
                        }
                        break;
                    case 'same':
                        if ($parameter !== null && $value !== (string) ($data[$parameter] ?? '')) {
                            $errors[$field] = $label . ' does not match.';
                        }
                        break;
                }

                if (isset($errors[$field])) {
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, string> $errors
     */
    public static function first(array $errors): string
    {
        return $errors === [] ? '' : (string) reset($errors);
    }

    private static function humanize(string $field): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $field));
    }
}
