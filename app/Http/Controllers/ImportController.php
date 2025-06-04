<?php

namespace App\Http\Controllers;

use App\Services\import\EmployeeServiceImport;
use App\Services\import\SalaryStructureServiceImport;
use App\Services\import\PayrollServiceImport;
use App\Services\import\ImportService;
use App\Services\Erp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    private const FILE_TYPES = ['employees', 'salary_structure', 'payroll'];

    protected EmployeeServiceImport $employeeService;
    protected SalaryStructureServiceImport $salaryStructureService;
    protected PayrollServiceImport $payrollService;
    protected ImportService $importService;

    public function __construct(
        EmployeeServiceImport $employeeService,
        SalaryStructureServiceImport $salaryStructureService,
        PayrollServiceImport $payrollService,
        ImportService $importService
    ) {
        $this->employeeService = $employeeService;
        $this->salaryStructureService = $salaryStructureService;
        $this->payrollService = $payrollService;
        $this->importService = $importService;
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
            "{$type}_file" => 'required|file|mimes:csv,txt',
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
            $importMethods = [
                'employees' => fn() => $this->employeeService->import($request->file('employees_file')),
                'salary_structure' => fn() => $this->salaryStructureService->import($request->file('salary_structure_file')),
                'payroll' => fn() => $this->payrollService->import($request->file('payroll_file')),
            ];

            foreach ($importMethods as $type => $importMethod) {
                Log::info("Début import {$type}");
                $result = $importMethod();
                $results[$type] = $result;

                if (!empty($result['errors'])) {
                    throw new \Exception("Erreurs lors de l'import des {$type}: " . implode(', ', $result['errors']));
                }
            }

            $totalSuccess = array_sum(array_column($results, 'success'));

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
            'file' => 'required|file|mimes:csv,txt',
            'type' => 'required|in:' . implode(',', self::FILE_TYPES),
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Fichier invalide'], 422);
        }

        try {
            $service = $this->importService->getServiceForType($request->input('type'));
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
}
