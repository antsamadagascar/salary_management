<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

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

    public function validateFileStructure(string $type, UploadedFile $file, ?array $context = null): array
    {
        Log::info("Validation de structure pour {$type}");
        
        $errors = [];
        $employeesData = $context['employees_data'] ?? [];

        try {
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                return [$this->getMessage('read_error', ['type' => $type])];
            }

            $headers = fgetcsv($handle, 0, ',');
            if (!$headers) {
                fclose($handle);
                return [$this->getMessage('empty_file', ['type' => $type])];
            }

            $headers = array_map('trim', $headers);
            $structureErrors = $this->validateHeaders($type, $headers);
            $errors = array_merge($errors, $structureErrors);

            $lineNumber = 2;
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $rowErrors = $this->validateDataRow($type, $row, $lineNumber, $employeesData);
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

        $missingHeaders = array_diff($expectedHeaders, $headers);
        if (!empty($missingHeaders)) {
            $errors[] = $this->getMessage('missing_headers', [
                'type' => $type,
                'headers' => implode(', ', $missingHeaders)
            ]);
        }

        $extraHeaders = array_diff($headers, $expectedHeaders);
        if (!empty($extraHeaders)) {
            Log::warning($this->getMessage('extra_headers', [
                'type' => $type,
                'headers' => implode(', ', $extraHeaders)
            ]));
        }

        return $errors;
    }

    public function validateDataRow(string $type, array $row, int $lineNumber, array $employeesData = []): array
    {
        $errors = [];
        $rules = $this->getValidationRules($type);

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
        
        
        if ($type === 'payroll' && isset($rules['date_consistency'])) {
            $employeeRef = trim($row[$rules['date_consistency']['employee_ref_field']] ?? '');
            $salaryDate = trim($row[$rules['date_consistency']['salary_date_field']] ?? '');

            if (isset($employeesData[$employeeRef])) {
                $hireDate = $employeesData[$employeeRef]['Date embauche'];
                
                if ($this->isDateBefore($salaryDate, $hireDate, 'd/m/Y')) {
                    $errors[] = $this->getMessage('date_before_hire', [
                        'line' => $lineNumber,
                        'salary_date' => $salaryDate,
                        'hire_date' => $hireDate
                    ]);
                }
            }
        }

        return $errors;
    }

    private function isValidDate(string $date, string $format = 'd/m/Y'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function isDateBefore(string $date1, string $date2, string $format): bool
    {
        $d1 = \DateTime::createFromFormat($format, $date1);
        $d2 = \DateTime::createFromFormat($format, $date2);
        
        return $d1 && $d2 && $d1 < $d2;
    }

    public function loadEmployeesData(UploadedFile $file): array
    {
        $employees = [];
        $handle = fopen($file->getRealPath(), 'r');
        
        fgetcsv($handle, 0, ','); // Skip header
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $ref = trim($row[0]);
            $employees[$ref] = [
                'Date embauche' => trim($row[4])
            ];
        }
        
        fclose($handle);
        return $employees;
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