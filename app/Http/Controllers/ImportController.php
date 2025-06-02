<?php

namespace App\Http\Controllers;

use App\Services\CompanyEmployeeService;
use App\Services\SalaryStructureService;
use App\Services\PayrollServiceImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    private const FILE_TYPES = ['employees', 'salary_structure', 'payroll'];
    private const MAX_FILE_SIZE = 2048; // in KB

    protected CompanyEmployeeService $companyEmployeeService;
    protected SalaryStructureService $salaryStructureService;
    protected PayrollServiceImport $payrollService;

    public function __construct(
        CompanyEmployeeService $companyEmployeeService,
        SalaryStructureService $salaryStructureService,
        PayrollServiceImport $payrollService
    ) {
        $this->companyEmployeeService = $companyEmployeeService;
        $this->salaryStructureService = $salaryStructureService;
        $this->payrollService = $payrollService;
    }

    public function showImportForm()
    {
        return view('import.form');
    }

    public function processImport(Request $request)
    {
        $validator = $this->validateFiles($request);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Erreurs de validation des fichiers');
        }

        return $this->handleImportProcess($request);
    }

    private function validateFiles(Request $request)
    {
        $rules = collect(self::FILE_TYPES)->mapWithKeys(fn($type) => [
            "{$type}_file" => 'required|file|mimes:csv,txt|max:' . self::MAX_FILE_SIZE,
        ])->toArray();

        $messages = collect(self::FILE_TYPES)->flatMap(fn($type) => [
            "{$type}_file.required" => "Le fichier des {$type} est requis",
            "{$type}_file.mimes" => "Le fichier des {$type} doit être au format CSV",
        ])->toArray();

        return Validator::make($request->all(), $rules, $messages);
    }

    private function handleImportProcess(Request $request)
    {
        DB::beginTransaction();
        $results = collect(self::FILE_TYPES)->mapWithKeys(fn($type) => [$type => ['success' => 0, 'errors' => []]])->toArray();

        try {
            // Ensure default company exists before any imports
            if (!$this->companyEmployeeService->ensureDefaultCompany()) {
                throw new \Exception('Impossible de créer l\'entreprise par défaut');
            }

            // Define import methods for each service
            $importMethods = [
                'employees' => fn() => $this->companyEmployeeService->import($request->file('employees_file')),
                'salary_structure' => fn() => $this->salaryStructureService->import($request->file('salary_structure_file')),
                'payroll' => fn() => $this->payrollService->import($request->file('payroll_file')),
            ];

            // Execute imports sequentially, stopping and rolling back on any error
            foreach ($importMethods as $type => $importMethod) {
                Log::info("Début import {$type}");
                $result = $importMethod();
                $results[$type] = $result;

                // If any errors occur, throw an exception to trigger rollback
                if (!empty($result['errors'])) {
                    throw new \Exception("Erreurs lors de l'import des {$type}: " . implode(', ', $result['errors']));
                }
            }

            // Calculate total success
            $totalSuccess = array_sum(array_column($results, 'success'));

            // Commit transaction only if all imports are successful
            DB::commit();
            $message = "Import terminé avec {$totalSuccess} succès";
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

    public function previewFiles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:' . self::MAX_FILE_SIZE,
            'type' => 'required|in:' . implode(',', self::FILE_TYPES),
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Fichier invalide'], 422);
        }

        try {
            $service = $this->getServiceForType($request->input('type'));
            $preview = $service->previewFile($request->file('file'), $request->input('type'));
            return response()->json(['data' => $preview]);
        } catch (\Exception $e) {
            Log::error('Erreur prévisualisation fichier', [
                'message' => $e->getMessage(),
                'type' => $request->input('type'),
            ]);
            return response()->json(['error' => 'Erreur lors de la prévisualisation: ' . $e->getMessage()], 500);
        }
    }

    public function checkDependencies(Request $request)
    {
        try {
            $dependencies = [
                'companies' => $this->companyEmployeeService->checkDependencies()['companies'],
                'employees' => $this->companyEmployeeService->checkDependencies()['employees'],
                'salary_components' => $this->salaryStructureService->checkDependencies()['salary_components'],
                'salary_structures' => $this->salaryStructureService->checkDependencies()['salary_structures'],
            ];
            return response()->json(['status' => 'success', 'dependencies' => $dependencies]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification des dépendances: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Erreur lors de la vérification: ' . $e->getMessage()], 500);
        }
    }

    private function getServiceForType(string $type): object
    {
        return match ($type) {
            'employees' => $this->companyEmployeeService,
            'salary_structure' => $this->salaryStructureService,
            'payroll' => $this->payrollService,
            default => throw new \InvalidArgumentException("Type de fichier inconnu: {$type}"),
        };
    }
}