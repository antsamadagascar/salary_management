<?php
namespace App\Services\filter;

use App\Services\api\ErpApiService;
use Illuminate\Support\Facades\Log;

class FilterService 
{
    private ErpApiService $erpApiService;

    public function __construct(ErpApiService $erpApiService) {
        $this->erpApiService = $erpApiService;
    }

    public function getSalaryByCriteria(array $params): array 
    {
        try {
            $salarySlips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode([['docstatus', '=', 1]]),
                'fields' => json_encode(['name', 'employee', 'employee_name', 'salary_structure', 'posting_date']),
                'limit_page_length' => 0
            ]);

            $resultats = [];

            foreach ($salarySlips as $slip) {
                $details = $this->erpApiService->getResource("Salary Slip/{$slip['name']}");

                $components = array_merge($details['earnings'] ?? [], $details['deductions'] ?? []);

                foreach ($components as $c) {
                    if ($c['salary_component'] !== $params['salaryComponents']) continue;

                    $montant = (float) ($c['amount'] ?? 0);
                    $valide = match ($params['conditions']) {
                        'inferieur' => $montant < $params['salaire_base'],
                        'superieur' => $montant > $params['salaire_base'],
                        'egal' => abs($montant - $params['salaire_base']) < 0.1,
                        'inferieur_egal' => $montant <= $params['salaire_base'],
                        'superieur_egal' => $montant >= $params['salaire_base'],
                        default => false
                    };

                    if ($valide) {
                        $resultats[] = [
                            'employee' => $details['employee'],
                            'employee_name' => $details['employee_name'],
                            'salary_structure' => $details['salary_structure'],
                            'salary_component' => $c['salary_component'],
                            'amount' => $montant,
                            'posting_date' => $details['posting_date']
                        ];
                    }
                }
            }

            return $resultats;

        } catch (\Exception $e) {
            Log::error("Erreur récupération salaires par critère : " . $e->getMessage());
            return [];
        }
    }


}
