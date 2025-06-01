<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;

class SalaryStructureService
{
    protected ErpApiService $apiService;

    public function __construct(ErpApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public function import(UploadedFile $file): array
    {
        $results = ['success' => 0, 'errors' => []];
        try {
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);
            $structureGroups = collect($csv->getRecords())->groupBy(fn($record) => trim($record['salary structure']))->toArray();

            foreach ($structureGroups as $structureName => $components) {
                try {
                    if (!$this->apiService->resourceExists('Company/My Company')) {
                        $results['errors'][] = "Impossible de trouver l'entreprise: My Company";
                        continue;
                    }

                    foreach ($components as $component) {
                        $componentName = trim($component['name']);
                        if (!$this->ensureSalaryComponentExists($componentName, $component)) {
                            $results['errors'][] = "Impossible de créer/trouver le composant de salaire: {$componentName}";
                        }
                    }

                    $structureData = $this->prepareSalaryStructureData($structureName, $components);
                    $resource = "Salary Structure/{$structureName}";
                    $success = $this->apiService->resourceExists($resource)
                        ? $this->apiService->updateResource($resource, $structureData)
                        : $this->apiService->createResource('Salary Structure', $structureData);

                    if ($success) {
                        $results['success']++;
                    } else {
                        $results['errors'][] = "Échec de la création/mise à jour de la structure salariale: {$structureName}";
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Erreur structure {$structureName}: " . $e->getMessage();
                }
            }
        } catch (CsvException $e) {
            $results['errors'][] = "Erreur de lecture du fichier CSV: " . $e->getMessage();
        }

        return $results;
    }

    public function previewFile(UploadedFile $file, string $type): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        $preview = array_slice($records, 0, 5, true);

        return [
            'headers' => $headers,
            'data' => $preview,
            'total_rows' => count($records),
            'type' => $type,
        ];
    }

    public function checkDependencies(): array
    {
        return [
            'salary_components' => [], 
            'salary_structures' => array_column($this->apiService->getResource('Salary Structure'), 'name'),
        ];
    }

    private function ensureSalaryComponentExists(string $componentName, array $componentData): bool
    {
        try {
            if ($this->apiService->resourceExists("Salary Component/{$componentName}")) {
                return true;
            }

            $salaryComponentData = [
                'salary_component' => $componentName,
                'type' => trim($componentData['type']) === 'earning' ? 'Earning' : 'Deduction',
                'depends_on_payment_days' => 0,
                'is_tax_applicable' => trim($componentData['type']) === 'earning' ? 1 : 0,
                'company' => 'My Company',
            ];

            return $this->apiService->createResource('Salary Component', $salaryComponentData);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création du composant de salaire {$componentName}: " . $e->getMessage());
            return false;
        }
    }

    private function prepareSalaryStructureData(string $structureName, array $components): array
    {
        $earnings = [];
        $deductions = [];

        foreach ($components as $component) {
            $componentData = [
                'salary_component' => trim($component['name']),
                'abbr' => trim($component['Abbr']),
                'amount_based_on_formula' => 1, 
                'formula' => $this->parseFormula(trim($component['valeur']), trim($component['Remarque'] ?? '')),
            ];

            if (trim($component['type']) === 'earning') {
                $earnings[] = $componentData;
            } else {
                $deductions[] = $componentData;
            }
        }

        return [
            'name' => $structureName,
            'company' => 'My Company',
            'earnings' => $earnings,
            'deductions' => $deductions,
            'is_active' => 'Yes',
        ];
    }

    private function parseFormula(string $valeur, string $remarque = ''): string
    {
        if (empty($valeur)) {
            return '';
        }

        $valeur = trim($valeur);
        $remarque = trim($remarque);

        if (strpos($valeur, '%') !== false) {
            $percentage = str_replace(['%', ' '], '', $valeur);
            if (is_numeric($percentage)) {
                $baseFormula = $this->getBaseFormula($remarque);
                return "({$baseFormula}) * {$percentage} / 100";
            }
        }

        // Si c'est un montant fixe
        if (is_numeric($valeur)) {
            return $valeur;
        }

        return $valeur;
    }

    private function getBaseFormula(string $remarque): string
    {
        if (empty($remarque)) {
            return 'base';
        }

        $remarque = strtolower(trim($remarque));
        
        // Cas: "salaire base"
        if (strpos($remarque, 'salaire base') !== false && strpos($remarque, '+') === false) {
            return 'base';
        }
        
        // Cas: "salaire base + indemnité"
        if (strpos($remarque, 'salaire base') !== false && strpos($remarque, 'indemnité') !== false) {
            return 'base + IND'; 
        }
        

        return 'base'; // Par défaut
    }
}