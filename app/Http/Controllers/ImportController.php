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
     */
    private function handleImportProcess(Request $request)
    {
        Log::info('Début du processus d\'import - tous les fichiers validés');
        
        DB::beginTransaction();
        $results = collect($this->fileValidator->getFileTypes())
            ->mapWithKeys(fn($type) => [$type => ['success' => 0, 'errors' => []]])
            ->toArray();

        try {
            // ÉTAPE 1: on Vérifie et crée si les années fiscales nécessaires avant l'import payroll
            if ($request->hasFile('payroll_file')) {
                Log::info('Vérification des années fiscales pour les données payroll');
                $payrollData = $this->loadPayrollData($request->file('payroll_file'));
                
                $fiscalYearResult = $this->fiscalYearManager->ensureFiscalYearsExist($payrollData);
                
                if (!$fiscalYearResult['success']) {
                    throw new \Exception("Erreur lors de la gestion des années fiscales: " . $fiscalYearResult['message']);
                }
                
                Log::info('Années fiscales gérées avec succès', $fiscalYearResult);
                $results['fiscal_years'] = $fiscalYearResult;
            }

            // ÉTAPE 2:on Procéde aux imports dans l'ordre
            $importMethods = [
                'employees' => fn() => $this->employeeService->import($request->file('employees_file')),
                'salary_structure' => fn() => $this->salaryStructureService->import($request->file('salary_structure_file')),
                'payroll' => fn() => $this->payrollService->import($request->file('payroll_file')),
            ];

            foreach ($importMethods as $type => $importMethod) {
                if (!$request->hasFile("{$type}_file")) {
                    continue; // Passer si le fichier n'est pas fourni
                }
                
                Log::info("Début import {$type}");
                $result = $importMethod();
                $results[$type] = $result;

                if (!empty($result['errors'])) {
                    throw new \Exception("Erreurs lors de l'import des {$type}: " . implode(', ', $result['errors']));
                }
                
                Log::info("Import {$type} terminé avec succès: {$result['success']} enregistrement(s)");
            }

            $totalSuccess = array_sum(array_column($results, 'success'));
            DB::commit();

            $message = "Import terminé avec succès: {$totalSuccess} enregistrement(s) importé(s)";
            
            // on ajout les détails sur les années fiscales créées si applicable (facultatife)
            if (isset($results['fiscal_years']) && !empty($results['fiscal_years']['created_years'])) {
                $createdYears = implode(', ', $results['fiscal_years']['created_years']);
                $message .= ". Années fiscales créées: {$createdYears}";
            }
            
            Log::info($message, $results);

            return redirect()->back()->with('success', $message)->with('import_results', $results);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur durant l\'import', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'results' => $results,
            ]);

            return redirect()->back()
                ->with('error', 'Import annulé: ' . $e->getMessage())
                ->with('import_results', $results)
                ->withInput();
        }
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