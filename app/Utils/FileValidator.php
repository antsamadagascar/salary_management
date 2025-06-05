<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;

class FileValidator
{
    private array $config;

    public function __construct()
    {
        $this->config = config('imports');
    }

    public function getFileTypes(): array
    {
        return $this->config['file_types'];
    }

    public function getHeaders(string $type): array
    {
        return $this->config['headers'][$type] ?? [];
    }

    public function getValidationRules(string $type): array
    {
        return $this->config['validation_rules'][$type] ?? [];
    }

    public function getMessage(string $key, array $replacements = []): string
    {
        $message = $this->config['messages'][$key] ?? $key;
        
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace(":{$placeholder}", $value, $message);
        }
        
        return $message;
    }

    public function validateFileStructure(string $type, $file): array
    {
        Log::info("Validation de structure pour {$type}");
        
        $errors = [];

        try {
            // Read CSV file
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return [$this->getMessage('read_error', ['type' => $type])];
            }

            // Get headers (first line)
            $headers = fgetcsv($handle, 0, ',');
            if (!$headers) {
                fclose($handle);
                return [$this->getMessage('empty_file', ['type' => $type])];
            }

            // Clean headers and validate structure
            $headers = array_map('trim', $headers);
            $structureErrors = $this->validateHeaders($type, $headers);
            $errors = array_merge($errors, $structureErrors);

            // Validate data rows
            $lineNumber = 2;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rowErrors = $this->validateDataRow($type, $row, $lineNumber);
                $errors = array_merge($errors, $rowErrors);
                $lineNumber++;
            }

            fclose($handle);

        } catch (\Exception $e) {
            $errors[] = "Erreur lors de la lecture du fichier {$type}: " . $e->getMessage();
            Log::error("Erreur validation fichier {$type}", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $errors;
    }

    private function validateHeaders(string $type, array $headers): array
    {
        $errors = [];
        $expectedHeaders = $this->getHeaders($type);

        // Check missing headers
        $missingHeaders = array_diff($expectedHeaders, $headers);
        if (!empty($missingHeaders)) {
            $errors[] = $this->getMessage('missing_headers', [
                'type' => $type,
                'headers' => implode(', ', $missingHeaders)
            ]);
        }

        // Log extra headers as warning
        $extraHeaders = array_diff($headers, $expectedHeaders);
        if (!empty($extraHeaders)) {
            Log::warning($this->getMessage('extra_headers', [
                'type' => $type,
                'headers' => implode(', ', $extraHeaders)
            ]));
        }

        return $errors;
    }

    public function validateDataRow(string $type, array $row, int $lineNumber): array
    {
        $errors = [];
        $rules = $this->getValidationRules($type);

        // Validate required fields
        if (isset($rules['required_fields'])) {
            foreach ($rules['required_fields'] as $index => $fieldName) {
                if (empty(trim($row[$index] ?? ''))) {
                    $errors[] = $this->getMessage('required_field', [
                        'line' => $lineNumber,
                        'field' => $fieldName
                    ]);
                }
            }
        }

        // Validate date fields
        if (isset($rules['date_fields'])) {
            foreach ($rules['date_fields'] as $index => $dateConfig) {
                $value = trim($row[$index] ?? '');
                if (!empty($value) && !$this->isValidDate($value, $dateConfig['format'])) {
                    $errors[] = $this->getMessage('invalid_date', [
                        'line' => $lineNumber,
                        'field' => $dateConfig['field'],
                        'format' => $dateConfig['format']
                    ]);
                }
            }
        }

        // Validate enum fields 
        if (isset($rules['enum_fields'])) {
            foreach ($rules['enum_fields'] as $index => $enumConfig) {
                $value = trim($row[$index] ?? '');
                if (!empty($value) && !in_array($value, $enumConfig['values'])) {
                    $errors[] = $this->getMessage('invalid_enum', [
                        'line' => $lineNumber,
                        'field' => $enumConfig['field'],
                        'values' => implode(' ou ', $enumConfig['values'])
                    ]);
                }
            }
        }

        // Validate numeric fields (return error if value is not)
        if (isset($rules['numeric_fields'])) {
            foreach ($rules['numeric_fields'] as $index => $fieldName) {
                $value = trim($row[$index] ?? '');
                if (!empty($value)) {
                    if (!is_numeric($value)) {
                        $errors[] = $this->getMessage('invalid_numeric', [
                            'line' => $lineNumber,
                            'field' => $fieldName
                        ]);
                    } elseif ($value < 0) {
                        $errors[] = $this->getMessage('negative_not_allowed', [
                            'line' => $lineNumber,
                            'field' => $fieldName
                        ]);
                    }
                }
            }
        }        
        
        if ($type === 'salary_structure') {
            $index = 4; // colonne 'valeur'
            $fieldName = 'valeur';
            $value = trim($row[$index] ?? '');
        
            if ($value === '') {
                $errors[] = $this->getMessage('required_field', [
                    'line' => $lineNumber,
                    'field' => $fieldName
                ]);
            } else {
                if (is_numeric($value)) {
                    if ($value < 0) {
                        $errors[] = $this->getMessage('negative_not_allowed', [
                            'line' => $lineNumber,
                            'field' => $fieldName
                        ]);
                    }
                }
            }
        }
        
        return $errors;
    }

    private function isValidDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public function generateFileValidationRules(): array
    {
        return collect($this->getFileTypes())->mapWithKeys(fn($type) => [
            "{$type}_file" => 'required|file|mimes:csv,txt',
        ])->toArray();
    }

    public function generateFileValidationMessages(): array
    {
        return collect($this->getFileTypes())->flatMap(fn($type) => [
            "{$type}_file.required" => $this->getMessage('file_required', ['type' => $type]),
            "{$type}_file.mimes" => $this->getMessage('file_mimes', ['type' => $type]),
        ])->toArray();
    }
}