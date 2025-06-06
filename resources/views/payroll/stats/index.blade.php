@extends('layouts.app')

@section('title', 'Statistiques de Paie')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Statistiques de Paie</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Paie</a></li>
                        <li class="breadcrumb-item active">Statistiques</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres et contrôles -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('payroll.stats.index') }}" class="d-flex align-items-end">
                        <div class="me-3">
                            <label for="year" class="form-label">Année</label>
                            <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                                @foreach($availableYears as $availableYear)
                                    <option value="{{ $availableYear }}" {{ $availableYear == $year ? 'selected' : '' }}>
                                        {{ $availableYear }}
                                    </option>
                                @endforeach
                            </select>
                            <!-- <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                                <option value="2023" {{ $year == 2023 ? 'selected' : '' }}>2023</option>
                                <option value="2024" {{ $year == 2024 ? 'selected' : '' }}>2024</option>
                                <option value="2025" {{ $year == 2025 ? 'selected' : '' }}>2025</option>
                            </select> -->
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-end">
                        <a href="{{ route('payroll.stats.export', ['year' => $year]) }}" 
                           class="btn btn-success me-2">
                            <i class="mdi mdi-download"></i> Exporter Excel
                        </a>
                        <button type="button" class="btn btn-info" onclick="toggleCharts()">
                            <i class="mdi mdi-chart-line"></i> Afficher/Masquer Graphiques
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(isset($error))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle-outline me-2"></i>{{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle-outline me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle-outline me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

  <!-- Graphiques -->
  <!-- Graphiques -->
<div id="charts-section" class="row mb-4">
    <!-- Graphique principal - Évolution totale -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Évolution Totale des Salaires {{ $year }}</h5>
            </div>
            <div class="card-body">
                <canvas id="totalSalariesChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Graphiques séparés -->
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Composants de Gains</h6>
            </div>
            <div class="card-body">
                <canvas id="earningsChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-3">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Composants de Déductions</h6>
            </div>
            <div class="card-body">
                <canvas id="deductionsChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>
    
</div>

    <!-- Statistiques globales -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1">
                                @if(!empty($monthlyStats))
                                    {{ number_format(collect($monthlyStats)->sum('total_employees'), 0, ',', ' ') }}
                                @else
                                    0
                                @endif
                            </h3>
                            <p class="mb-0">Total Employés</p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-white-10 rounded">
                                <i class="mdi mdi-account-group font-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1">
                                @if(!empty($monthlyStats))
                                    {{ number_format(collect($monthlyStats)->sum('total_gross_pay'), 0, ',', ' ') }}
                                @else
                                    0
                                @endif
                                <small>MGA</small>
                            </h3>
                            <p class="mb-0">Total Brut</p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-white-10 rounded">
                                <i class="mdi mdi-currency-usd font-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1">
                                @if(!empty($monthlyStats))
                                    {{ number_format(collect($monthlyStats)->sum('total_deductions'), 0, ',', ' ') }}
                                @else
                                    0
                                @endif
                                <small>MGA</small>
                            </h3>
                            <p class="mb-0">Total Déductions</p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-white-10 rounded">
                                <i class="mdi mdi-minus-circle font-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-1">
                                @if(!empty($monthlyStats))
                                    {{ number_format(collect($monthlyStats)->sum('total_net_pay'), 0, ',', ' ') }}
                                @else
                                    0
                                @endif
                                <small>MGA</small>
                            </h3>
                            <p class="mb-0">Total Net</p>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-white-10 rounded">
                                <i class="mdi mdi-cash font-20"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des statistiques mensuelles -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistiques Mensuelles - {{ $year }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="statsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mois</th>
                                    <th class="text-center">Employés</th>
                                    <th class="text-end">Total Brut</th>
                                    <th class="text-end">Total Déductions</th>
                                    <th class="text-end">Total Net</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthlyStats as $stats)
                                    <tr>
                                        <td>
                                            <strong>{{ $stats['month_name'] }}</strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary">{{ $stats['total_employees'] }}</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-success fw-bold">
                                                {{ number_format($stats['total_gross_pay'], 0, ',', ' ') }} MGA
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-danger fw-bold">
                                                {{ number_format($stats['total_deductions'], 0, ',', ' ') }} MGA
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('payroll.stats.month-details', $stats['month']) }}" 
                                               class="text-decoration-none">
                                                <span class="text-info fw-bold">
                                                    {{ number_format($stats['total_net_pay'], 0, ',', ' ') }} MGA
                                                </span>
                                            </a>
                                        </td>
                                        <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('payroll.stats.month-details', $stats['month']) }}" 
                                            class="btn btn-sm btn-outline-primary"
                                            title="Voir les détails du mois {{ $stats['month'] }}">
                                                <i class="mdi mdi-eye"></i> Détails
                                            </a>
                                            <a href="{{ route('payroll.stats.export-month', $stats['month']) }}" 
                                            class="btn btn-sm btn-outline-success"
                                            title="Exporter les données du mois {{ $stats['month'] }}">
                                                <i class="mdi mdi-download"></i> Export
                                            </a>
                                        </div>
                                    </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="mdi mdi-database-remove font-48 text-muted mb-3"></i>
                                                <h5 class="text-muted">Aucune donnée disponible</h5>
                                                <p class="text-muted mb-0">Aucune statistique de paie trouvée pour l'année {{ $year }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(!empty($monthlyStats))
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td>TOTAL</td>
                                        <td class="text-center">
                                            <span class="badge bg-dark">
                                                {{ number_format(collect($monthlyStats)->sum('total_employees'), 0, ',', ' ') }}
                                            </span>
                                        </td>
                                        <td class="text-end text-success">
                                            {{ number_format(collect($monthlyStats)->sum('total_gross_pay'), 0, ',', ' ') }} MGA
                                        </td>
                                        <td class="text-end text-danger">
                                            {{ number_format(collect($monthlyStats)->sum('total_deductions'), 0, ',', ' ') }} MGA
                                        </td>
                                        <td class="text-end text-info">
                                            {{ number_format(collect($monthlyStats)->sum('total_net_pay'), 0, ',', ' ') }} MGA
                                        </td>
                                        <td class="text-center">-</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour la répartition des composants -->
<div class="modal fade" id="breakdownModal" tabindex="-1" aria-labelledby="breakdownModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="breakdownModalLabel">Répartition des Composants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="breakdownContent">
                    <!-- Contenu dynamique -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/chart.js') }}"></script> 
<script>
// Fonction pour basculer l'affichage des graphiques
function toggleCharts() {
    const chartsSection = document.getElementById('charts-section');
    if (chartsSection.style.display === 'none') {
        chartsSection.style.display = 'block';
    } else {
        chartsSection.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const chartData = @json($chartData ?? []);
    console.log('chartData:', chartData);

    // Graphique principal - Totaux
    if (document.getElementById('totalSalariesChart')) {
        const ctx1 = document.getElementById('totalSalariesChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: [
                    {
                        label: 'Salaire Brut Total',
                        data: chartData.gross_pay || [],
                        borderColor: '#28a745',
                        backgroundColor: '#28a74520',
                        borderWidth: 3,
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Salaire Net Total',
                        data: chartData.net_pay || [],
                        borderColor: '#007bff',
                        backgroundColor: '#007bff20',
                        borderWidth: 3,
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Total Déductions',
                        data: chartData.deductions || [],
                        borderColor: '#dc3545',
                        backgroundColor: '#dc354520',
                        borderWidth: 3,
                        tension: 0.1,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value) + ' MGA';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                       new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' MGA';
                            }
                        }
                    }
                }
            }
        });
    }

    // Graphique des gains
    if (document.getElementById('earningsChart') && chartData.earnings_components) {
        const ctx2 = document.getElementById('earningsChart').getContext('2d');
        const earningsDatasets = [];
        const colors = ['#ffc107', '#6f42c1', '#20c997', '#fd7e14', '#e83e8c'];
        
        Object.keys(chartData.earnings_components).forEach((component, index) => {
            earningsDatasets.push({
                label: component,
                data: chartData.earnings_components[component] || [],
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length] + '20',
                borderWidth: 2,
                tension: 0.1,
                fill: false
            });
        });

        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: earningsDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                }
            }
        });
    }

    // Graphique des déductions
    if (document.getElementById('deductionsChart') && chartData.deductions_components) {
        const ctx3 = document.getElementById('deductionsChart').getContext('2d');
        const deductionsDatasets = [];
        const deductionColors = ['#dc3545', '#6c757d', '#17a2b8', '#343a40'];
        
        Object.keys(chartData.deductions_components).forEach((component, index) => {
            deductionsDatasets.push({
                label: component,
                data: chartData.deductions_components[component] || [],
                borderColor: deductionColors[index % deductionColors.length],
                backgroundColor: deductionColors[index % deductionColors.length] + '20',
                borderWidth: 2,
                tension: 0.1,
                fill: false
            });
        });

        new Chart(ctx3, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: deductionsDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                }
            }
        });
}

});

</script>
@endsection

@push('styles')
<style>
.avatar-sm {
    height: 3rem;
    width: 3rem;
}

.avatar-title {
    align-items: center;
    background-color: #6c757d;
    color: #fff;
    display: flex;
    font-weight: 500;
    height: 100%;
    justify-content: center;
    width: 100%;
}

.bg-white-10 {
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.font-20 {
    font-size: 20px;
}

.font-48 {
    font-size: 48px;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

#charts-section {
    transition: all 0.3s ease;
}
</style>
@endpush