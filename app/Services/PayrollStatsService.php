<?php

// Fonctions essentielles :
// - getYearlyPayrollStats : Récupère les statistiques de paie par année
// - processPayrollDataByMonth : Traite les données de paie par mois
// - calculateMonthStats : Calcule les statistiques pour un mois donné
// - getPayrollComponents : Récupère les composants de paie (gains/déductions)
// - getAvailableYears : Récupère les années disponibles dynamiquement
// - getChartData : Prépare les données pour les graphiques d'évolution
// - getMonthDetails : Récupère les détails des employés pour un mois spécifique

namespace App\Services;

use App\Services\ErpApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class PayrollStatsService
{
    private ErpApiService $erpApiService;

    public function __construct(ErpApiService $erpApiService)
    {
        $this->erpApiService = $erpApiService;
    }

    /**
     *  fonction pour récuperé les statistiques de paie par année
     */
    public function getYearlyPayrollStats(int $year): array
    {
        try {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear()->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear()->format('Y-m-d');

            // condition pour récupérer toutes les fiches de paie de l'année
            $payslips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [
                    ['start_date', '>=', $startDate],
                    ['end_date', '<=', $endDate],
                    ['docstatus', '=', 1]
                ],
                'fields' => [
                    'name', 'employee', 'employee_name', 'department',
                    'designation', 'start_date', 'end_date', 'gross_pay',
                    'net_pay', 'total_deduction', 'currency', 'posting_date'
                ],
                'order_by' => 'start_date asc',
                'limit_page_length' => 1000
            ]);

            return $this->processPayrollDataByMonth($payslips);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des statistiques: " . $e->getMessage());
        }
    }

    /**
     * Traitement des données de paie par mois
     */
    private function processPayrollDataByMonth(array $payslips): array
    {
        $monthlyStats = [];
        $payslipsByMonth = [];

        // Groupena par mois le fiches de paie (ex:fiche de toutes les employes mois de janvier 2024)
        foreach ($payslips as $payslip) {
            $month = Carbon::parse($payslip['start_date'])->format('Y-m');
            
            if (!isset($payslipsByMonth[$month])) {
                $payslipsByMonth[$month] = [];
            }
            
            $payslipsByMonth[$month][] = $payslip;
        }

        // Calcul les statistiques pour chaque mois
        foreach ($payslipsByMonth as $month => $monthPayslips) {
            $monthStats = $this->calculateMonthStats($month, $monthPayslips);
            $monthlyStats[$month] = $monthStats;
        }

        // Triage du resultats par mois
        ksort($monthlyStats);

        return $monthlyStats;
    }

    /**
     * Calcule les statistiques pour un mois donné
     */
    private function calculateMonthStats(string $month, array $payslips): array
    {
        $stats = [
            'month' => $month,
            'month_name' => Carbon::createFromFormat('Y-m', $month)->locale('fr')->translatedFormat('F Y'),
            'total_employees' => count($payslips),
            'total_gross_pay' => 0,
            'total_deductions' => 0,
            'total_net_pay' => 0,
            'currency' => $payslips[0]['currency'] ?? 'MGA',
            'earnings_breakdown' => [],
            'deductions_breakdown' => [],
            'employees' => []
        ];

        foreach ($payslips as $payslip) {
            $stats['total_gross_pay'] += floatval($payslip['gross_pay'] ?? 0);
            $stats['total_deductions'] += floatval($payslip['total_deduction'] ?? 0);
            $stats['total_net_pay'] += floatval($payslip['net_pay'] ?? 0);

            // Récupere les détails des composants de salaire
            $components = $this->getPayrollComponents($payslip['name']);
            
            // Agrégation des gains
            foreach ($components['earnings'] as $earning) {
                $component = $earning['component'];
                if (!isset($stats['earnings_breakdown'][$component])) {
                    $stats['earnings_breakdown'][$component] = 0;
                }
                $stats['earnings_breakdown'][$component] += floatval($earning['amount']);
            }

            // Agrégation des déductions
            foreach ($components['deductions'] as $deduction) {
                $component = $deduction['component'];
                if (!isset($stats['deductions_breakdown'][$component])) {
                    $stats['deductions_breakdown'][$component] = 0;
                }
                $stats['deductions_breakdown'][$component] += floatval($deduction['amount']);
            }

            // Ajoute  détails de l'employé
            $stats['employees'][] = [
                'employee_id' => $payslip['employee'],
                'employee_name' => $payslip['employee_name'],
                'department' => $payslip['department'] ?? 'N/A',
                'designation' => $payslip['designation'] ?? 'N/A',
                'gross_pay' => floatval($payslip['gross_pay'] ?? 0),
                'total_deduction' => floatval($payslip['total_deduction'] ?? 0),
                'net_pay' => floatval($payslip['net_pay'] ?? 0),
                'earnings' => $components['earnings'],
                'deductions' => $components['deductions'],
                'currency' =>$payslip['currency']
            ];
        }

        return $stats;
    }

    /**
     * fonction pour  récuperer les composants de paie (gains et déductions)
     */
    private function getPayrollComponents(string $payslipName): array
    {
        $components = [
            'earnings' => [],
            'deductions' => []
        ];

       // try {
        //     // Méthode 1: Via Salary Detail
        //     $earnings = $this->erpApiService->getResource('Salary Detail', [
        //         'filters' => [
        //             ['parent', '=', $payslipName],
        //             ['parentfield', '=', 'earnings']
        //         ],
        //         'fields' => ['salary_component', 'amount', 'default_amount']
        //     ]);

        //     $deductions = $this->erpApiService->getResource('Salary Detail', [
        //         'filters' => [
        //             ['parent', '=', $payslipName],
        //             ['parentfield', '=', 'deductions']
        //         ],
        //         'fields' => ['salary_component', 'amount', 'default_amount']
        //     ]);

        //     $components['earnings'] = array_map(function ($item) {
        //         return [
        //             'component' => $item['salary_component'],
        //             'amount' => $item['amount'] ?? $item['default_amount'] ?? 0
        //         ];
        //     }, $earnings);

        //     $components['deductions'] = array_map(function ($item) {
        //         return [
        //             'component' => $item['salary_component'],
        //             'amount' => $item['amount'] ?? $item['default_amount'] ?? 0
        //         ];
        //     }, $deductions);

        // } catch (Exception $e) {
            // Méthode 2: Via le document complet
            try {
                $payslip = $this->erpApiService->getResourceByName('Salary Slip', $payslipName);
                
                if (isset($payslip['earnings'])) {
                    $components['earnings'] = array_map(function ($item) {
                        return [
                            'component' => $item['salary_component'] ?? 'Unknown',
                            'amount' => $item['amount'] ?? $item['default_amount'] ?? 0
                        ];
                    }, $payslip['earnings']);
                }

                if (isset($payslip['deductions'])) {
                    $components['deductions'] = array_map(function ($item) {
                        return [
                            'component' => $item['salary_component'] ?? 'Unknown',
                            'amount' => $item['amount'] ?? $item['default_amount'] ?? 0
                        ];
                    }, $payslip['deductions']);
                }
            } catch (Exception $e2) {
                // on  Retourne les composants vides en cas d'échec
            }
       // }

        return $components;
    }

    /**
     * Récupère les années disponibles (recure des year dynamiques mais non static (2023,..etc))
     */
    public function getAvailableYears(): array
    {
        try {
            $payslips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [['docstatus', '=', 1]],
                'fields' => ['start_date'],
                'order_by' => 'start_date asc',
                'limit_page_length' => 1000
            ]);

            $years = [];
            foreach ($payslips as $payslip) {
                $year = Carbon::parse($payslip['start_date'])->year;
                if (!in_array($year, $years)) {
                    $years[] = $year;
                }
            }

            // Si aucune année trouvée, ajouter l'année courante
            if (empty($years)) {
                $years[] = Carbon::now()->year;
            }

            rsort($years); // on Trie les annees retourner par ordre décroissant
            return $years;
        } catch (Exception $e) {
            return [Carbon::now()->year];
        }
    }

    /**
     * Récupère les données pour les graphiques (VERSION CORRIGÉE)
     */
    public function getChartData(int $year): array
    {
        $monthlyStats = $this->getYearlyPayrollStats($year);
        
        $chartData = [
            'labels' => [],           
            'gross_pay' => [],        
            'net_pay' => [],          
            'deductions' => [],       
            'employees' => [],        
            'earnings_components' => [],
            'deductions_components' => []
        ];

        // Collecte tous les composants uniques
        $allEarningsComponents = [];
        $allDeductionsComponents = [];

        foreach ($monthlyStats as $stats) {
            $chartData['labels'][] = $stats['month_name'];                   
            $chartData['gross_pay'][] = $stats['total_gross_pay'];           
            $chartData['net_pay'][] = $stats['total_net_pay'];              
            $chartData['deductions'][] = $stats['total_deductions'];         
            $chartData['employees'][] = $stats['total_employees'];         

            foreach ($stats['earnings_breakdown'] as $component => $amount) {
                if (!isset($allEarningsComponents[$component])) {
                    $allEarningsComponents[$component] = [];
                }
            }

            foreach ($stats['deductions_breakdown'] as $component => $amount) {
                if (!isset($allDeductionsComponents[$component])) {
                    $allDeductionsComponents[$component] = [];
                }
            }
        }

        // Rempli les données des composants
        foreach ($allEarningsComponents as $component => $data) {
            $chartData['earnings_components'][$component] = [];
            foreach ($monthlyStats as $stats) {
                $chartData['earnings_components'][$component][] = 
                    $stats['earnings_breakdown'][$component] ?? 0;
            }
        }

        foreach ($allDeductionsComponents as $component => $data) {
            $chartData['deductions_components'][$component] = [];
            foreach ($monthlyStats as $stats) {
                $chartData['deductions_components'][$component][] = 
                    $stats['deductions_breakdown'][$component] ?? 0;
            }
        }

        return $chartData;
    }

    /**
     * Récupère les détails d'un mois spécifique
     */
    public function getMonthDetails(string $month): array
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->format('Y-m-d');

            $payslips = $this->erpApiService->getResource('Salary Slip', [
                'filters' => [
                    ['start_date', '>=', $startDate],
                    ['end_date', '<=', $endDate],
                    ['docstatus', '=', 1]
                ],
                'fields' => [
                    'name', 'employee', 'employee_name', 'department',
                    'designation', 'gross_pay', 'net_pay', 'total_deduction','currency'
                ]
            ]);

            $details = [];
            foreach ($payslips as $payslip) {
                $components = $this->getPayrollComponents($payslip['name']);
                
                $details[] = [
                    'currency' => $payslip['currency'],
                    'employee_id' => $payslip['employee'],
                    'employee_name' => $payslip['employee_name'],
                    'department' => $payslip['department'] ?? 'N/A',
                    'designation' => $payslip['designation'] ?? 'N/A',
                    'gross_pay' => floatval($payslip['gross_pay'] ?? 0),
                    'total_deduction' => floatval($payslip['total_deduction'] ?? 0),
                    'net_pay' => floatval($payslip['net_pay'] ?? 0),
                    'earnings' => $components['earnings'],
                    'deductions' => $components['deductions']
                ];
            }

            return $details;
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des détails: " . $e->getMessage());
        }
    }

}