<?php

namespace App\Services\import;

use App\Services\ErpApiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Exception as CsvException;

class SalaryStructureServiceImport
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
                    $firstComponent = reset($components);
                    $companyName = trim($firstComponent['company']);
                    
                    if (!$this->apiService->resourceExists("Company/{$companyName}")) {
                        $results['errors'][] = "Impossible de trouver l'entreprise: {$companyName}";
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

    private function ensureSalaryComponentExists(string $componentName, array $componentData): bool
    {
        try {
            if ($this->apiService->resourceExists("Salary Component/{$componentName}")) {
                return true;
            }

            $salaryComponentData = [
                'salary_component' => $componentName,
                'salary_component_abbr' => trim($componentData['Abbr']), 
                'type' => trim($componentData['type']) === 'earning' ? 'Earning' : 'Deduction',
                'depends_on_payment_days' => 0,
                'is_tax_applicable' => trim($componentData['type']) === 'earning' ? 1 : 0,
                'company' => trim($componentData['company']), 
                'currency' => 'MGA'
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
        
        $firstComponent = reset($components);
        $companyName = trim($firstComponent['company']);

        foreach ($components as $component) {
            $valeur = trim($component['valeur']);
        
            $isFormula = !is_numeric($valeur) ? 1 : 0;
        
            $componentData = [
                'salary_component' => trim($component['name']),
                'abbr' => trim($component['Abbr']),
                'amount_based_on_formula' => $isFormula,
            ];
        
            if ($isFormula) {
                $componentData['formula'] = $this->parseFormula($valeur);
            } else {
                $componentData['amount'] = (float)$valeur;
            }
        
            if (trim($component['type']) === 'earning') {
                $earnings[] = $componentData;
            } else {
                $deductions[] = $componentData;
            }
        }
        
        return [
            'name' => $structureName,
            'company' => $companyName, 
            'earnings' => $earnings,
            'deductions' => $deductions,
            'docstatus' => 1,
            'is_active' => 'Yes',
            'currency'=> 'MGA'
        ];
    }

    /**
     * MÉTHODE SIMPLIFIÉE: Parse les formules - tout est dynamique
     */
    private function parseFormula(string $valeur): string
    {
        if (empty($valeur)) {
            return '';
        }

        $valeur = trim($valeur);

        // Si c'est "base", on garde tel quel (salaire de base dans ERPNext)
        if (strtolower($valeur) === 'base') {
            return 'base';
        }

        if (is_numeric($valeur)) {
            return $valeur;
        }
        return $valeur;
    }

}