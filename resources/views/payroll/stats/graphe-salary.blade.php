@extends('layouts.app')

@section('title', 'Graphiques d\'évolution des salaires')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line mr-2"></i>
                        Évolution des Salaires
                    </h3>
                    <div class="card-tools">
                        <select id="yearSelect" class="form-control" style="width: 120px;">
                            @foreach($availableYears as $availableYear)
                                <option value="{{ $availableYear }}" {{ $availableYear == $year ? 'selected' : '' }}>
                                    {{ $availableYear }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    @if(isset($error))
                        <div class="alert alert-danger">
                            {{ $error }}
                        </div>
                    @endif

                    <!-- Graphique unifié - Évolution des salaires et composants -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Évolution des Salaires et Composants</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="unifiedSalariesChart" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphique du nombre d'employés -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Évolution du Nombre d'Employés</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="employeesChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card {
        box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
        margin-bottom: 1rem;
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    
    .chart-container {
        position: relative;
        height: 400px;
        width: 100%;
    }
    
    .loading-spinner {
        display: none;
        text-align: center;
        padding: 20px;
    }
    
    .alert {
        margin-bottom: 1rem;
    }
</style>
@endpush

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let unifiedSalariesChart, employeesChart;
    
    // Données initiales depuis le serveur
    let chartData = @json($chartData);
    
    // Vérifier si les données sont valides
    if (!chartData || !chartData.labels || chartData.labels.length === 0) {
        console.warn('Aucune donnée disponible pour les graphiques');
        showNoDataMessage();
        return;
    }
    
    // Initialiser les graphiques
    initializeCharts();
    
    // Gestion du changement d'année
    document.getElementById('yearSelect').addEventListener('change', function() {
        const selectedYear = this.value;
        loadChartData(selectedYear);
    });
    
    function showNoDataMessage() {
        document.querySelectorAll('canvas').forEach(canvas => {
            const container = canvas.parentElement;
            container.innerHTML = '<div class="alert alert-info">Aucune donnée disponible pour cette période.</div>';
        });
    }
    
    function initializeCharts() {
        try {
            createUnifiedSalariesChart();
            createEmployeesChart();
        } catch (error) {
            console.error('Erreur lors de l\'initialisation des graphiques:', error);
        }
    }
    
    function createUnifiedSalariesChart() {
        const ctx = document.getElementById('unifiedSalariesChart');
        if (!ctx) return;
        
        const datasets = [
            {
                label: 'Salaire Brut Total',
                data: chartData.gross_pay || [],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Salaire Net Total',
                data: chartData.net_pay || [],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Total Déductions',
                data: chartData.deductions || [],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.4
            }
        ];
        
        // Ajouter les composants de gains
        const earningsColors = ['#17a2b8', '#ffc107', '#6f42c1', '#fd7e14', '#20c997'];
        let earningsColorIndex = 0;
        if (chartData.earnings_components && Object.keys(chartData.earnings_components).length > 0) {
            Object.keys(chartData.earnings_components).forEach(component => {
                datasets.push({
                    label: `Gain: ${component}`,
                    data: chartData.earnings_components[component],
                    borderColor: earningsColors[earningsColorIndex % earningsColors.length],
                    backgroundColor: earningsColors[earningsColorIndex % earningsColors.length] + '20',
                    fill: false,
                    tension: 0.4
                });
                earningsColorIndex++;
            });
        }
        
        // Ajouter les composants de déductions
        const deductionsColors = ['#e83e8c', '#6610f2', '#fd7e14', '#795548'];
        let deductionsColorIndex = 0;
        if (chartData.deductions_components && Object.keys(chartData.deductions_components).length > 0) {
            Object.keys(chartData.deductions_components).forEach(component => {
                datasets.push({
                    label: `Déduction: ${component}`,
                    data: chartData.deductions_components[component],
                    borderColor: deductionsColors[deductionsColorIndex % deductionsColors.length],
                    backgroundColor: deductionsColors[deductionsColorIndex % deductionsColors.length] + '20',
                    fill: false,
                    tension: 0.4
                });
                deductionsColorIndex++;
            });
        }
        
        unifiedSalariesChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Évolution des Salaires et Composants'
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value) + ' €';
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }
    
    function createEmployeesChart() {
        const ctx = document.getElementById('employeesChart');
        if (!ctx) return;
        
        employeesChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.labels || [],
                datasets: [{
                    label: 'Nombre d\'employés',
                    data: chartData.employees || [],
                    backgroundColor: '#6c757d',
                    borderColor: '#495057',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Évolution du Nombre d\'Employés'
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    function loadChartData(year) {
        showLoadingSpinner();
        
        fetch(`/payroll/stats/chart-data?year=${year}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    chartData = data.data;
                    updateAllCharts();
                } else {
                    console.error('Erreur:', data.message);
                    alert('Erreur lors du chargement des données: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données');
            })
            .finally(() => {
                hideLoadingSpinner();
            });
    }
    
    function updateAllCharts() {
        if (unifiedSalariesChart) {
            unifiedSalariesChart.data.labels = chartData.labels;
            unifiedSalariesChart.data.datasets[0].data = chartData.gross_pay;
            unifiedSalariesChart.data.datasets[1].data = chartData.net_pay;
            unifiedSalariesChart.data.datasets[2].data = chartData.deductions;
            
            let datasetIndex = 3;
            const earningsColors = ['#17a2b8', '#ffc107', '#6f42c1', '#fd7e14', '#20c997'];
            let earningsColorIndex = 0;
            if (chartData.earnings_components) {
                Object.keys(chartData.earnings_components).forEach(component => {
                    if (unifiedSalariesChart.data.datasets[datasetIndex]) {
                        unifiedSalariesChart.data.datasets[datasetIndex].data = chartData.earnings_components[component];
                    } else {
                        unifiedSalariesChart.data.datasets.push({
                            label: `Gain: ${component}`,
                            data: chartData.earnings_components[component],
                            borderColor: earningsColors[earningsColorIndex % earningsColors.length],
                            backgroundColor: earningsColors[earningsColorIndex % earningsColors.length] + '20',
                            fill: false,
                            tension: 0.4
                        });
                    }
                    datasetIndex++;
                    earningsColorIndex++;
                });
            }
            
            const deductionsColors = ['#e83e8c', '#6610f2', '#fd7e14', '#795548'];
            let deductionsColorIndex = 0;
            if (chartData.deductions_components) {
                Object.keys(chartData.deductions_components).forEach(component => {
                    if (unifiedSalariesChart.data.datasets[datasetIndex]) {
                        unifiedSalariesChart.data.datasets[datasetIndex].data = chartData.deductions_components[component];
                    } else {
                        unifiedSalariesChart.data.datasets.push({
                            label: `Déduction: ${component}`,
                            data: chartData.deductions_components[component],
                            borderColor: deductionsColors[deductionsColorIndex % deductionsColors.length],
                            backgroundColor: deductionsColors[deductionsColorIndex % deductionsColors.length] + '20',
                            fill: false,
                            tension: 0.4
                        });
                    }
                    datasetIndex++;
                    deductionsColorIndex++;
                });
            }
            
            // Supprimer les datasets excédentaires
            unifiedSalariesChart.data.datasets.length = datasetIndex;
            unifiedSalariesChart.update();
        }
        
        if (employeesChart) {
            employeesChart.data.labels = chartData.labels;
            employeesChart.data.datasets[0].data = chartData.employees;
            employeesChart.update();
        }
    }
    
    function showLoadingSpinner() {
        document.querySelectorAll('.loading-spinner').forEach(spinner => {
            spinner.style.display = 'block';
        });
    }
    
    function hideLoadingSpinner() {
        document.querySelectorAll('.loading-spinner').forEach(spinner => {
            spinner.style.display = 'none';
        });
    }
});
</script>
@endsection