<?php

namespace App\Services\generate;

use App\Services\api\ErpApiService;
use App\Services\import\PayrollServiceImport;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\payroll\PayrollDataService;


class SalaryService
{
    protected ErpApiService $apiService;
    protected PayrollServiceImport $payrollServiceImport;
    protected PayrollDataService $payrollDataService;

    public function __construct(ErpApiService $apiService, PayrollServiceImport $payrollServiceImport,PayrollDataService $payrollDataService)
    {
        $this->apiService = $apiService;
        $this->payrollServiceImport = $payrollServiceImport;
        $this->payrollDataService = $payrollDataService;
    }

    /**
     * Génère les salaires manquants pour un employé entre deux dates.
     *
     * @param string $employeeId Identifiant de l'employé (employee_number)
     * @param string $dateDebut Date de début au format 'd/m/Y'
     * @param string $dateFin Date de fin au format 'd/m/Y'
     * @param float|null $salaireBase Salaire de base à utiliser si aucun salaire antérieur n'est trouvé
     * @param string $salaryStructure Nom de la structure salariale à utiliser
     * @param string $ecraserSalaire Indique si les salaires existants doivent être écrasés
     * @return array Résultat avec les salaires créés, ignorés et erreurs
     */
    public function generateMissingPayrolls(string $employeeId, string $dateDebut, string $dateFin, ?float $salaireBase, string $salaryStructure, string $ecraserSalaire): array
    {
        $results = ['success' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $employee = $this->payrollServiceImport->findEmployeeByNumber($employeeId);
            if (!$employee) {
                $results['errors'][] = "Employé non trouvé: {$employeeId}";
                Log::error("Employé non trouvé pour génération des salaires: {$employeeId}");
                return $results;
            }

            // Verify if the salary structure exists
            if (!$this->apiService->resourceExists("Salary Structure/{$salaryStructure}")) {
                $results['errors'][] = "Structure salariale non trouvée: {$salaryStructure}";
                Log::error("Structure salariale non trouvée: {$salaryStructure}");
                return $results;
            }

            // Convert dates
            $startDate = Carbon::createFromFormat('d/m/Y', $dateDebut)->startOfMonth();
            $endDate = Carbon::createFromFormat('d/m/Y', $dateFin)->endOfMonth();

            if ($endDate->lt($startDate)) {
                $results['errors'][] = "La date de fin doit être postérieure à la date de début";
                Log::error("Date de fin antérieure à la date de début: {$dateDebut} - {$dateFin}");
                return $results;
            }

            // Load existing payrolls
            $existingPayrolls = $this->payrollServiceImport->getExistingPayrolls();
            Log::info("Fiches de paie existantes pour vérification: " . count($existingPayrolls));

            // Determine base salary to use
            $baseSalaryToUse = $salaireBase;
            if (is_null($salaireBase)) {
                $lastSalary = $this->findLastSalaryBefore($employee['name'], $startDate);
                $baseSalaryToUse = $lastSalary ? (float) $lastSalary['base'] : 0.0;
                if ($baseSalaryToUse === 0.0) {
                    $results['errors'][] = "Aucun salaire de base fourni et aucun salaire antérieur trouvé pour {$employee['name']}";
                    Log::error("Aucun salaire de base fourni et aucun salaire antérieur trouvé pour {$employee['name']}");
                    return $results;
                }
            }
            Log::info("Salaire de base à utiliser: {$baseSalaryToUse} pour employé {$employee['name']}");

            $currentDate = $startDate->copy();
            $companyName = 'My Company';

            while ($currentDate->lte($endDate)) {
                $monthStr = $currentDate->format('Y-m');
                $payrollKey = $this->payrollServiceImport->generatePayrollKey($employee['name'], $monthStr);

                if ($ecraserSalaire === '1') {
                    // Overwrite mode: Cancel/delete existing assignments and slips
                    $this->payrollDataService->cancelOrDeletePayroll($employee['name'], $monthStr);
                    $this->payrollDataService->cancelOrDeleteAssignment($employee['name'], $monthStr);
                } else {
                    // Skip if payroll exists and not overwriting
                    if (isset($existingPayrolls[$payrollKey])) {
                        $results['skipped']++;
                        Log::info("Fiche de paie existante pour {$employee['name']} - Mois: {$monthStr}, ignorée");
                        $currentDate->addMonth();
                        continue;
                    }
                }

                // Create new salary assignment
                $assignmentResult = $this->payrollServiceImport->createSalaryAssignment(
                    $employee['name'],
                    $salaryStructure,
                    $baseSalaryToUse,
                    $currentDate->format('d/m/Y'),
                    $companyName,
                    $ecraserSalaire
                );

                if (!$assignmentResult) {
                    $results['errors'][] = "Échec de la création du salary assignment pour {$employee['name']} - Mois: {$monthStr}";
                    Log::error("Échec de la création du salary assignment pour {$employee['name']} - Mois: {$monthStr}");
                    $currentDate->addMonth();
                    continue;
                }

                $results['success']++;
                Log::info("Assignment créé pour {$employee['name']} - Mois: {$monthStr}");

                // Create new salary slip
                $payrollData = $this->payrollServiceImport->preparePayrollData(
                    [
                        'Mois' => $currentDate->format('d/m/Y'),
                        'Salaire' => $salaryStructure,
                    ],
                    $companyName,
                    $employee['name']
                );

                $success = $this->apiService->createResource('Salary Slip', $payrollData);

                if ($success) {
                    $results['success']++;
                    $existingPayrolls[$payrollKey] = true;
                    Log::info("Fiche de paie créée pour {$employee['name']} - Mois: {$monthStr}");
                } else {
                    $results['errors'][] = "Échec de la création de la fiche de paie pour {$employee['name']} - Mois: {$monthStr}";
                    Log::error("Échec de la création de la fiche de paie pour {$employee['name']} - Mois: {$monthStr}");
                }

                $currentDate->addMonth();
            }

        } catch (\Exception $e) {
            $results['errors'][] = "Erreur générale lors de la génération des salaires: " . $e->getMessage();
            Log::error("Erreur générale dans generateMissingPayrolls: " . $e->getMessage());
        }

        Log::info("Résultat de la génération des salaires: " . json_encode($results));
        return $results;
    }

    /**
     * Trouve le dernier salaire antérieur à une date donnée pour un employé.
     *
     * @param string $employeeRef Nom de l'employé
     * @param Carbon $beforeDate Date avant laquelle chercher
     * @return array|null Données du dernier salaire ou null si non trouvé
     */
    private function findLastSalaryBefore(string $employeeRef, Carbon $beforeDate): ?array
    {
        try {
            $assignments = $this->apiService->getResource('Salary Structure Assignment', [
                'filters' => [
                    ['employee', '=', $employeeRef],
                    ['from_date', '<', $beforeDate->format('Y-m-d')],
                    ['docstatus', '=', 1]
                ],
                'fields' => ['name', 'base', 'from_date'],
                'order_by' => 'from_date desc',
                'limit_page_length' => 1
            ]);

            return $assignments[0] ?? null;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la recherche du dernier salaire pour {$employeeRef}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupère la liste des structures salariales depuis l'API.
     *
     * @param array $params Paramètres optionnels pour l'appel API (filtres, champs, etc.)
     * @return array Liste des structures salariales
     */
    public function getSalaryStructures(array $params = []): array
    {
        try {
            $defaultParams = [
                'fields' => [],
                'limit_page_length' => 100
            ];
            $params = array_merge($defaultParams, $params);
            $structures = $this->apiService->getResource('Salary Structure', $params);
            Log::info("Structures salariales récupérées: " . count($structures));
            return $structures;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des structures salariales: " . $e->getMessage());
            return [];
        }
    }

    public function getAverageSalary(): float {
        try {
            $salaries = $this->apiService->getResource('Salary Structure Assignment', [
                'filters' => [
                    ['docstatus', '=', 1]
                ],
                'fields' => ['base'],
                'limit_page_length' => 1000
            ]);

            if (empty($salaries)) {
                Log::info("Aucun salaire soumis trouvé pour le calcul de la moyenne.");
                return 0;
            }

            $total = array_sum(array_column($salaries, 'base'));
            $moyenne = $total / count($salaries);
            Log::info("Moyenne des salaires calculée: {$moyenne} (basée sur " . count($salaries) . " assignments)");
            return round($moyenne, 2);
        } catch (\Exception $e) {
            Log::error('Erreur lors du calcul de la moyenne des salaires : ' . $e->getMessage());
            return 0;
        }
    }
}