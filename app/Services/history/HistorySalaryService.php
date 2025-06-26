<?php

namespace App\Services\history;

use App\Services\api\ErpApiService;
use App\Services\employee\EmployeeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class HistorySalaryService
{
    private ErpApiService $erpApiService;
    private EmployeeService $employeeService;

    public function __construct(ErpApiService $erpApiService,EmployeeService $employeeService)
    {
        $this->erpApiService = $erpApiService;
        $this->employeeService = $employeeService;
    }

    public function getSalaryHistory(array $params): array
    {
        try {
            $filters = [
                'employee' => $params['employe_id'],
                'start_date' => ['>=', $params['date_debut']],
                'end_date' => ['<=', $params['date_fin']]
            ];

            // Filtre par année si spécifié
            if (!empty($params['year'])) {
                $filters['start_date'] = ['>=', $params['year'] . '-01-01'];
                $filters['end_date'] = ['<=', $params['year'] . '-12-31'];
            }

            $salaries = $this->erpApiService->getResource('Salary Slip', [
                'filters' => json_encode($filters),
                'fields' => [
                    'name', 'employee', 'employee_name', 'start_date', 'end_date',
                    'gross_pay', 'net_pay', 'creation', 'modified'
                ],
                'order_by' => 'start_date asc',
                'limit_page_length' => 0
            ]);

            return $this->formatSalaryHistory($salaries);

        } catch (Exception $e) {
            Log::error("Erreur lors de la récupération de l'historique: " . $e->getMessage());
            throw new Exception("Impossible de récupérer l'historique des salaires");
        }
    }

    public function calculateSalaryStatistics(array $salaryHistory): array
    {
        if (empty($salaryHistory)) {
            return [
                'average_salary' => 0,
                'max_salary' => 0,
                'min_salary' => 0,
                'total_periods' => 0,
                'first_salary' => 0,
                'last_salary' => 0,
                'total_evolution_percent' => 0,
                'avg_monthly_evolution' => 0
            ];
        }

        $amounts = array_column($salaryHistory, 'amount');
        $totalPeriods = count($salaryHistory);
        
        $stats = [
            'average_salary' => round(array_sum($amounts) / $totalPeriods, 2),
            'max_salary' => max($amounts),
            'min_salary' => min($amounts),
            'total_periods' => $totalPeriods,
            'first_salary' => $salaryHistory[0]['amount'],
            'last_salary' => end($salaryHistory)['amount']
        ];

        // Calcul de l'évolution
        if ($stats['first_salary'] > 0) {
            $stats['total_evolution_percent'] = round(
                (($stats['last_salary'] - $stats['first_salary']) / $stats['first_salary']) * 100, 
                2
            );
        } else {
            $stats['total_evolution_percent'] = 0;
        }

        // Évolution moyenne mensuelle
        if ($totalPeriods > 1) {
            $stats['avg_monthly_evolution'] = round(
                ($stats['last_salary'] - $stats['first_salary']) / ($totalPeriods - 1), 
                2
            );
        } else {
            $stats['avg_monthly_evolution'] = 0;
        }

        return $stats;
    }

    public function exportSalaryHistory(array $salaryHistory, array $statistics, array $employee, string $format = 'csv')
    {
        $fileName = 'historique_salaires_' . $employee['employee_number'] . '_' . date('Y-m-d') . '.' . $format;
        
        if ($format === 'csv') {
            return $this->exportToCsv($salaryHistory, $statistics, $employee, $fileName);
        }
        
        throw new Exception("Format d'export non supporté: " . $format);
    }

    private function formatSalaryHistory(array $salaries): array
    {
        $formattedHistory = [];
        $previousAmount = null;

        foreach ($salaries as $salary) {
            $amount = floatval($salary['net_pay'] ?? $salary['gross_pay'] ?? 0);
            
            // Calcul de l'évolution par rapport au salaire précédent
            $evolution = null;
            if ($previousAmount !== null && $previousAmount > 0) {
                $evolution = round((($amount - $previousAmount) / $previousAmount) * 100, 2);
            }

            $formattedHistory[] = [
                'period' => $this->formatPeriod($salary['start_date'], $salary['end_date']),
                'amount' => $amount,
                'evolution' => $evolution,
                'type' => $this->determineSalaryType($salary),
                'created_at' => $salary['creation'],
                'raw_data' => $salary
            ];

            $previousAmount = $amount;
        }

        return $formattedHistory;
    }

    private function formatPeriod(string $startDate, string $endDate): string
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('M Y');
        }
        
        return $start->format('M Y') . ' - ' . $end->format('M Y');
    }

    private function determineSalaryType(array $salary): string
    {
        // Logique pour déterminer si le salaire a été généré automatiquement ou modifié
        $created = new \DateTime($salary['creation']);
        $modified = new \DateTime($salary['modified'] ?? $salary['creation']);
        
        if ($modified > $created->modify('+1 minute')) {
            return 'Modifié';
        }
        
        return 'Généré';
    }

    // private function exportToCsv(array $salaryHistory, array $statistics, array $employee, string $fileName)
    // {
    //     $headers = [
    //         'Content-Type' => 'text/csv',
    //         'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
    //     ];

    //     $callback = function() use ($salaryHistory, $statistics, $employee) {
    //         $file = fopen('php://output', 'w');
            
    //         // En-tête du fichier
    //         fputcsv($file, ['HISTORIQUE DES SALAIRES'], ';');
    //         fputcsv($file, [''], ';');
    //         fputcsv($file, ['Employé:', $employee['employee_name']], ';');
    //         fputcsv($file, ['Numéro:', $employee['employee_number']], ';');
    //         fputcsv($file, ['Date export:', date('d/m/Y H:i')], ';');
    //         fputcsv($file, [''], ';');
            
    //         // Statistiques
    //         fputcsv($file, ['STATISTIQUES'], ';');
    //         fputcsv($file, ['Salaire moyen:', number_format($statistics['average_salary'], 2, ',', ' ') . ' €'], ';');
    //         fputcsv($file, ['Salaire maximum:', number_format($statistics['max_salary'], 2, ',', ' ') . ' €'], ';');
    //         fputcsv($file, ['Salaire minimum:', number_format($statistics['min_salary'], 2, ',', ' ') . ' €'], ';');
    //         fputcsv($file, ['Nombre de périodes:', $statistics['total_periods']], ';');
    //         fputcsv($file, ['Évolution totale:', $statistics['total_evolution_percent'] . '%'], ';');
    //         fputcsv($file, [''], ';');
            
    //         // En-têtes du tableau
    //         fputcsv($file, ['Période', 'Montant (€)', 'Évolution (%)', 'Type', 'Date création'], ';');
            
    //         // Données
    //         foreach ($salaryHistory as $salary) {
    //             fputcsv($file, [
    //                 $salary['period'],
    //                 number_format($salary['amount'], 2, ',', ' '),
    //                 $salary['evolution'] ? number_format($salary['evolution'], 2, ',', ' ') : '',
    //                 $salary['type'],
    //                 date('d/m/Y', strtotime($salary['created_at']))
    //             ], ';');
    //         }
            
    //         fclose($file);
    //     };

    //     return response()->stream($callback, 200, $headers);
    // }
}