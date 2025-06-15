<?php
namespace App\Http\Controllers;

use App\Services\import\EmployeeServiceImport;
use App\Services\import\SalaryStructureServiceImport;
use App\Services\import\PayrollServiceImport;
use App\Services\import\FiscalYearManagerService;
use App\Utils\FileValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    protected EmployeeServiceImport $employeeService;
    protected SalaryStructureServiceImport $salaryStructureService;
    protected PayrollServiceImport $payrollService;
    protected FiscalYearManagerService $fiscalYearManager;
    protected FileValidator $fileValidator;

    public function __construct(
        EmployeeServiceImport $employeeService,
        SalaryStructureServiceImport $salaryStructureService,
        PayrollServiceImport $payrollService,
        FiscalYearManagerService $fiscalYearManager,
        FileValidator $fileValidator
    ) {
        $this->employeeService = $employeeService;
        $this->salaryStructureService = $salaryStructureService;
        $this->payrollService = $payrollService;
        $this->fiscalYearManager = $fiscalYearManager;
        $this->fileValidator = $fileValidator;
    }

    public function showImportForm()
    {
        return view('import.form');
    }

    public function processImport(Request $request)
    {
        $fileValidator = $this->validateFiles($request);
        if ($fileValidator->fails()) {
            return redirect()->back()
                ->withErrors($fileValidator)
                ->withInput()
                ->with('error', 'Erreurs de validation des fichiers');
    }
        // Charge les données des employés 
        $employeesData = $this->fileValidator->loadEmployeesData($request->file('employees_file'));
        $validationContext = ['employees_data' => $employeesData];

        $structureValidationResult = $this->validateFileStructures($request, $validationContext);
        if (!$structureValidationResult['valid']) {
            return redirect()->back()
                ->with('error', 'Validation de structure échouée: ' . $structureValidationResult['message'])
                ->with('validation_errors', $structureValidationResult['errors'])
                ->with('error_summary', $this->generateErrorSummary($structureValidationResult['errors']))
                ->withInput();
        }

        return $this->handleImportProcess($request);
    }

    private function validateFileStructures(Request $request, array $context = []): array
    {
        $validationResults = [
            'valid' => true,
            'message' => '',
            'errors' => []
        ];

        foreach ($this->fileValidator->getFileTypes() as $type) {
            $file = $request->file("{$type}_file");
            $fileErrors = $this->fileValidator->validateFileStructure($type, $file, $context);

            if (!empty($fileErrors)) {
                $validationResults['valid'] = false;
                $validationResults['errors'][$type] = $fileErrors;
            }
        }

        if (!$validationResults['valid']) {
            $errorCount = array_sum(array_map('count', $validationResults['errors']));
            $validationResults['message'] = "Validation échouée avec {$errorCount} erreur(s)";
        }

        return $validationResults;
    }

    private function validateFiles(Request $request)
    {
        $rules = $this->fileValidator->generateFileValidationRules();
        $messages = $this->fileValidator->generateFileValidationMessages();
        
        return Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Gère le processus d'import avec création automatique des années fiscales
     * Import atomique : soit tout réussit, soit tout échoue
     */
    private function handleImportProcess(Request $request)
    {
        Log::info('Début du processus d\'import atomique - tous les fichiers validés');
        
        DB::beginTransaction();
        $errors = [];
        $results = [];

        try {
            // ÉTAPE 1: Vérification et création des années fiscales si nécessaire
            if ($request->hasFile('payroll_file')) {
                Log::info('Vérification des années fiscales pour les données payroll');
                $payrollData = $this->loadPayrollData($request->file('payroll_file'));
                
                $fiscalYearResult = $this->fiscalYearManager->ensureFiscalYearsExist($payrollData);
                
                if (!$fiscalYearResult['success']) {
                    $errors['fiscal_years'] = ["Erreur lors de la gestion des années fiscales: " . $fiscalYearResult['message']];
                    throw new \Exception("Échec de la gestion des années fiscales");
                }
                
                Log::info('Années fiscales gérées avec succès', $fiscalYearResult);
                $results['fiscal_years'] = $fiscalYearResult;
            }

            // ÉTAPE 2: Validation de tous les imports AVANT insertion
            $importMethods = [
                'employees' => fn() => $this->employeeService->import($request->file('employees_file')),
                'salary_structure' => fn() => $this->salaryStructureService->import($request->file('salary_structure_file')),
                'payroll' => fn() => $this->payrollService->import($request->file('payroll_file')),
            ];

            // Exécution de tous les imports
            foreach ($importMethods as $type => $importMethod) {
                if (!$request->hasFile("{$type}_file")) {
                    continue; // Passer si le fichier n'est pas fourni
                }
                
                Log::info("Début import {$type}");
                $result = $importMethod();
                $results[$type] = $result;

                // Si des erreurs sont détectées, on les collecte et on arrête
                if (!empty($result['errors'])) {
                    $errors[$type] = $result['errors'];
                    throw new \Exception("Erreurs détectées lors de l'import des {$type}");
                }
                
                Log::info("Import {$type} validé avec succès: {$result['success']} enregistrement(s)");
            }

            // Si on arrive ici, tout s'est bien passé
            $totalSuccess = array_sum(array_column($results, 'success'));
            DB::commit();

            $message = "Import terminé avec succès: {$totalSuccess} enregistrement(s) importé(s)";
            
            // Ajout des détails sur les années fiscales créées si applicable
            if (isset($results['fiscal_years']) && !empty($results['fiscal_years']['created_years'])) {
                $createdYears = implode(', ', $results['fiscal_years']['created_years']);
                $message .= ". Années fiscales créées: {$createdYears}";
            }
            
            Log::info($message, $results);

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur durant l\'import atomique', [
                'message' => $e->getMessage(),
                'errors' => $errors,
                'trace' => $e->getTraceAsString(),
            ]);

            // Retourner SEULEMENT les erreurs, pas de succès partiel
            return redirect()->back()
                ->with('error', 'Import annulé : Toutes les opérations ont été annulées en raison d\'erreurs.')
                ->with('import_errors', $errors)
                ->with('error_details', $this->formatImportErrors($errors))
                ->withInput();
        }
    }

    /**
     * Formate les erreurs d'import pour un affichage clair
     */
    private function formatImportErrors(array $errors): array
    {
        $formatted = [];
        
        foreach ($errors as $type => $typeErrors) {
            $formatted[$type] = [
                'count' => count($typeErrors),
                'errors' => $typeErrors,
                'type_label' => $this->getTypeLabel($type)
            ];
        }
        
        return $formatted;
    }

    /**
     * Retourne le libellé français du type d'import
     */
    private function getTypeLabel(string $type): string
    {
        $labels = [
            'employees' => 'Employés',
            'salary_structure' => 'Structure salariale',
            'payroll' => 'Paie',
            'fiscal_years' => 'Années fiscales'
        ];
        
        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Charge les données du fichier payroll pour l'analyse des années fiscales
     */
    private function loadPayrollData($file): array
    {
        try {
            if (!$file || !$file->isValid()) {
                return [];
            }

            $csvData = [];
            if (($handle = fopen($file->getPathname(), 'r')) !== false) {
                $headers = fgetcsv($handle);
                
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) === count($headers)) {
                        $csvData[] = array_combine($headers, $data);
                    }
                }
                fclose($handle);
            }

            return $csvData;
            
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement des données payroll: ' . $e->getMessage());
            return [];
        }
    }

    private function generateErrorSummary(array $errors): array
    {
        $summary = [];
        foreach ($errors as $fileType => $fileErrors) {
            $summary[$fileType] = [
                'total_errors' => count($fileErrors),
                'line_errors' => array_filter($fileErrors, fn($error) => str_contains($error, 'Ligne')),
                'structure_errors' => array_filter($fileErrors, fn($error) => !str_contains($error, 'Ligne'))
            ];
        }
        return $summary;
    }
}