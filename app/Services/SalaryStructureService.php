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

                    // Créer tous les composants de salaire
                    foreach ($components as $component) {
                        $componentName = trim($component['name']);
                        if (!$this->ensureSalaryComponentExists($componentName, $component)) {
                            $results['errors'][] = "Impossible de créer/trouver le composant de salaire: {$componentName}";
                        }
                    }

                    // Créer ou mettre à jour la structure salariale
                    $structureData = $this->prepareSalaryStructureData($structureName, $components);
                    $resource = "Salary Structure/{$structureName}";
                    $success = $this->apiService->resourceExists($resource)
                        ? $this->apiService->updateResource($resource, $structureData)
                        : $this->apiService->createResource('Salary Structure', $structureData);

                    if ($success) {
                        $results['success']++;
                        // NOTE: Les assignments seront créés lors de l'import des fiches de paie
                        // avec les bons salaires de base et dates appropriées
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

    // MÉTHODE SUPPRIMÉE: Les assignments seront créés lors de l'import payroll

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
                'salary_component_abbr' => trim($componentData['Abbr']), 
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
            'docstatus' => 1,
            'is_active' => 'Yes',
        ];
    }

    /**
     * MÉTHODE CORRIGÉE: Amélioration du parsing des formules
     */
    private function parseFormula(string $valeur, string $remarque = ''): string
    {
        if (empty($valeur)) {
            return '';
        }

        $valeur = trim($valeur);
        $remarque = trim($remarque);

        // Gestion des pourcentages
        if (strpos($valeur, '%') !== false) {
            $percentage = str_replace(['%', ' '], '', $valeur);
            if (is_numeric($percentage)) {
                $baseFormula = $this->getBaseFormula($remarque);
                return "({$baseFormula}) * {$percentage} / 100";
            }
        }

        // Valeur numérique fixe
        if (is_numeric($valeur)) {
            return $valeur;
        }

        // Retourner la valeur telle quelle si c'est déjà une formule
        return $valeur;
    }

    /**
     * MÉTHODE CORRIGÉE: Amélioration de la détection de la base de calcul
     */
    private function getBaseFormula(string $remarque): string
    {
        if (empty($remarque)) {
            return 'base';
        }

        $remarque = strtolower(trim($remarque));

        // Si la remarque contient "salaire base + indemnité" ou similaire
        if (strpos($remarque, 'salaire base') !== false && 
            (strpos($remarque, '+') !== false || strpos($remarque, 'indemnité') !== false)) {
            return 'base + IND';
        }

        // Si c'est juste sur le salaire de base
        if (strpos($remarque, 'salaire base') !== false) {
            return 'base';
        }

        // Par défaut, utiliser la base
        return 'base';
    }
    
    public function submitDocument(string $doctype, $nameOrId): bool
    {
        try {
            $response = $this->apiService->updateResource("{$doctype}/{$nameOrId}", [
                'docstatus' => 1
            ]);
            return $response !== false;
        } catch (\Exception $e) {
            Log::error("Failed to submit {$doctype} {$nameOrId}: " . $e->getMessage());
            return false;
        }
    }
}