<?php

namespace App\Http\Controllers;

use App\Services\resetData\ResetDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResetDataController extends Controller
{
    private $resetDataService;

    public function __construct(ResetDataService $resetDataService)
    {
        $this->resetDataService = $resetDataService;
    }

    /**
     * Affiche la page de confirmation de suppression
     *
     * @return \Illuminate\View\View
     */
    public function showConfirmation()
    {
        $existingData = $this->resetDataService->checkDataExists();
        
        return view('reset-data/reset-data-confirmation', [
            'existingData' => $existingData,
            'hasData' => array_sum($existingData) > 0
        ]);
    }

    /**
     * Affiche le statut des données existantes
     *
     * @return JsonResponse
     */
    public function checkData(): JsonResponse
    {
        $data = $this->resetDataService->checkDataExists();
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'total_records' => array_sum($data)
        ]);
    }

    /**
     * Réinitialise toutes les données après confirmation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resetAllData(Request $request): JsonResponse
    {
        // Validation de la confirmation
        $request->validate([
            'confirmation' => 'required|in:CONFIRMER_SUPPRESSION'
        ], [
            'confirmation.required' => 'Vous devez confirmer la suppression',
            'confirmation.in' => 'Confirmation invalide. Tapez exactement "CONFIRMER_SUPPRESSION"'
        ]);

        $result = $this->resetDataService->resetAllData();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Réinitialise une table spécifique
     *
     * @param Request $request
     * @param string $table
     * @return JsonResponse
     */
    public function resetSpecificTable(Request $request, $table): JsonResponse
    {
        // Tables autorisées
        $allowedTables = [
            'tabCompany',
            'tabEmployee', 
            'tabSalary Structure',
            'tabSalary Component',
            'tabSalary Structure Assignment',
            'tabSalary Detail',
            'tabSalary Slip'
        ];

        if (!in_array($table, $allowedTables)) {
            return response()->json([
                'success' => false,
                'message' => 'Table non autorisée'
            ], 400);
        }

        // Validation de la confirmation
        $request->validate([
            'confirmation' => 'required|in:CONFIRMER_SUPPRESSION'
        ]);

        $result = $this->resetDataService->resetSpecificTable($table);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * API pour confirmation avec double vérification
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmReset(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:check,confirm',
            'confirmation_text' => 'required_if:action,confirm'
        ]);

        if ($request->action === 'check') {
            // Première étape : vérifier les données existantes
            $data = $this->resetDataService->checkDataExists();
            
            return response()->json([
                'success' => true,
                'step' => 'check',
                'data' => $data,
                'total_records' => array_sum($data),
                'message' => 'Données à supprimer trouvées'
            ]);
        }

        if ($request->action === 'confirm') {
            // Deuxième étape : confirmation finale
            if ($request->confirmation_text !== 'SUPPRIMER_TOUTES_LES_DONNEES') {
                return response()->json([
                    'success' => false,
                    'message' => 'Texte de confirmation incorrect'
                ], 400);
            }

            $result = $this->resetDataService->resetAllData();
            
            return response()->json($result, $result['success'] ? 200 : 500);
        }
    }

}