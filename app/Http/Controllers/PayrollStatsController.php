<?php
/*   
fonctionnalites (ALEA POSSIBLES) :
    -export csv des donnes dans chaque section afficher dans le dashboard 

*/
namespace App\Http\Controllers;

use App\Services\PayrollStatsService;
use App\Services\export\ExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollStatsController extends Controller
{
    private PayrollStatsService $statsService;
    private ExportService $exportService;

    public function __construct(PayrollStatsService $statsService, ExportService $exportService)
    {
        $this->statsService = $statsService;
        $this->exportService = $exportService;
    }

    /**
     * Affiche la page des statistiques de paie
     */
    public function index(Request $request): View
    {
        try {
            $currentYear = Carbon::now()->year;
            $year = $request->get('year', $currentYear);
            
            $availableYears = $this->statsService->getAvailableYears();
            $monthlyStats = $this->statsService->getYearlyPayrollStats($year);
            $chartData = $this->statsService->getChartData($year);
            
            return view('payroll.stats.index', compact(
                'monthlyStats',
                'chartData',
                'availableYears',
                'year'
            ));
        } catch (\Exception $e) {
            \Log::error('Erreur dans index: ' . $e->getMessage());
            return view('payroll.stats.index', [
                'monthlyStats' => [],
                'chartData' => [],
                'availableYears' => [Carbon::now()->year],
                'year' => Carbon::now()->year,
                'error' => 'Erreur lors du chargement des statistiques: ' . $e->getMessage()
            ]);
        }
    }

    public function getBreakdown(string $month): JsonResponse
    {
        try {
            $breakdown = $this->statsService->getMonthBreakdown($month);
            
            return response()->json([
                'success' => true,
                'breakdown' => $breakdown
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la répartition: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les données de graphique via AJAX
     */
    public function getChartData(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', Carbon::now()->year);
            $chartData = $this->statsService->getChartData($year);
            
            return response()->json([
                'success' => true,
                'data' => $chartData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche les détails d'un mois spécifique
     */
    public function showMonthDetails(string $month): View|RedirectResponse
    {
        try {
            $monthDetails = $this->statsService->getMonthDetails($month);
            $monthName = Carbon::createFromFormat('Y-m', $month)->locale('fr')->translatedFormat('F Y');
    
            $currency = count($monthDetails) > 0 ? $monthDetails[0]['currency'] : null;
    
            return view('payroll.stats.month-details', compact(
                'monthDetails',
                'month',
                'monthName',
                'currency'
            ));
        } catch (\Exception $e) {
            return redirect()->route('payroll.stats.index')
                ->withError('Erreur lors du chargement des détails: ' . $e->getMessage());
        }
    }
    

    /**
     * Exporte les statistiques mensuelles en Excel (EXPORT EXCEL  DONNNES TOTAL LIGNE POUR CHAQUE MOIS )
     */
    public function exportMonthlyStats(Request $request): Response|RedirectResponse|BinaryFileResponse
    {
        try {
            $year = $request->get('year', Carbon::now()->year);
            $monthlyStats = $this->statsService->getYearlyPayrollStats($year);
            
            $data = [];
            $headers = [
                'Mois',
                'Nombre d\'employés',
                'Total Brut',
                'Total Déductions',
                'Total Net'
            ];

            // Ajoute des colonnes des composants de salaire
            $allComponents = [];
            foreach ($monthlyStats as $stats) {
                foreach ($stats['earnings_breakdown'] as $component => $amount) {
                    if (!in_array($component, $allComponents)) {
                        $allComponents[] = $component;
                        $headers[] = $component . ' (Gains)';
                    }
                }
                foreach ($stats['deductions_breakdown'] as $component => $amount) {
                    if (!in_array($component, $allComponents)) {
                        $allComponents[] = $component;
                        $headers[] = $component . ' (Déductions)';
                    }
                }
            }

            // on construit les données
            foreach ($monthlyStats as $stats) {
                $row = [
                    $stats['month_name'],
                    $stats['total_employees'],
                    number_format($stats['total_gross_pay'], 2),
                    number_format($stats['total_deductions'], 2),
                    number_format($stats['total_net_pay'], 2)
                ];

                // Ajoute du montants des composants
                foreach ($allComponents as $component) {
                    if (isset($stats['earnings_breakdown'][$component])) {
                        $row[] = number_format($stats['earnings_breakdown'][$component], 2);
                    } elseif (isset($stats['deductions_breakdown'][$component])) {
                        $row[] = number_format($stats['deductions_breakdown'][$component], 2);
                    } else {
                        $row[] = '0.00';
                    }
                }

                $data[] = $row;
            }

            $filename = "statistiques_paie_{$year}.xlsx";
            
            return $this->exportService->exportToExcel($data, $headers, $filename);
        } catch (\Exception $e) {
            return redirect()->route('payroll.stats.index')
                ->withError('Erreur lors de l\'export: ' . $e->getMessage());
        }
    }

    /**
     * Exporte les détails d'un mois en Excel (EXPORT EXCEL DONNES DETAILES POUR UN MOIS DONNES (EX : MOIS JANVIER 2025))
     */
    public function exportMonthDetails(string $month): Response|RedirectResponse|BinaryFileResponse
    {
        try {
            $monthDetails = $this->statsService->getMonthDetails($month);
            $monthName = Carbon::createFromFormat('Y-m', $month)->locale('fr')->translatedFormat('F Y');
    
            $data = [];
            $headers = ['Employé', 'Département', 'Poste', 'Salaire Brut', 'Déductions', 'Salaire Net'];
    
            $allEarningsComponents = [];
            $allDeductionsComponents = [];
    
            foreach ($monthDetails as $employee) {
                foreach ($employee['earnings'] as $earning) {
                    if (!in_array($earning['component'], $allEarningsComponents)) {
                        $allEarningsComponents[] = $earning['component'];
                    }
                }
                foreach ($employee['deductions'] as $deduction) {
                    if (!in_array($deduction['component'], $allDeductionsComponents)) {
                        $allDeductionsComponents[] = $deduction['component'];
                    }
                }
            }
    
            foreach ($allEarningsComponents as $component) {
                $headers[] = $component . ' (Gain)';
            }
            foreach ($allDeductionsComponents as $component) {
                $headers[] = $component . ' (Déduction)';
            }
    
            foreach ($monthDetails as $employee) {
                $row = [
                    $employee['employee_name'],
                    $employee['department'],
                    $employee['designation'],
                    number_format($employee['gross_pay'], 2),
                    number_format($employee['total_deduction'], 2),
                    number_format($employee['net_pay'], 2)
                ];
    
                foreach ($allEarningsComponents as $component) {
                    $amount = 0;
                    foreach ($employee['earnings'] as $earning) {
                        if ($earning['component'] === $component) {
                            $amount = $earning['amount'];
                            break;
                        }
                    }
                    $row[] = number_format($amount, 2);
                }
    
                foreach ($allDeductionsComponents as $component) {
                    $amount = 0;
                    foreach ($employee['deductions'] as $deduction) {
                        if ($deduction['component'] === $component) {
                            $amount = $deduction['amount'];
                            break;
                        }
                    }
                    $row[] = number_format($amount, 2);
                }
    
                $data[] = $row;
            }
    
            $filename = "details_paie_{$month}.xlsx";
            return $this->exportService->exportToExcel($data, $headers, $filename);
        } catch (\Exception $e) {
            return redirect()->route('payroll.stats.month-details', $month)
                ->withError('Erreur lors de l\'export: ' . $e->getMessage());
        }
    }

    /**
     * API pour récupérer les statistiques d'une année
     */
    public function getYearlyStats(Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', Carbon::now()->year);
            $monthlyStats = $this->statsService->getYearlyPayrollStats($year);
            
            return response()->json([
                'success' => true,
                'data' => $monthlyStats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche la page des graphiques de salaires
     */
    public function graphsIndex(Request $request): View
    {
        try {
            $currentYear = Carbon::now()->year;
            $year = $request->get('year', $currentYear);
            
            $availableYears = $this->statsService->getAvailableYears();
            $chartData = $this->statsService->getChartData($year);

            return view('payroll.stats.graphe-salary', compact(
                'chartData',
                'availableYears',
                'year'
            ));
        } catch (\Exception $e) {
            return view('payroll.stats.graphe-salary', [
                'chartData' => [
                    'labels' => [],                    // Noms des mois
                    'gross_pay' => [],                 // Salaires bruts
                    'net_pay' => [],                   // Salaires nets
                    'deductions' => [],                // Déductions
                    'employees' => [],                 // Nombre d'employés
                    'earnings_components' => [],       // Composants de gains
                    'deductions_components' => []      // Composants de déductions
                ],
                'availableYears' => [Carbon::now()->year],
                'year' => Carbon::now()->year,
                'error' => 'Erreur lors du chargement des données: ' . $e->getMessage()
            ]);
        }
    }
}