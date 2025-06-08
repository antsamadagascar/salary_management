@extends('layouts.app')

@section('title', 'Détails du mois - ' . $monthName)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Détails de Paie - {{ $monthName }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="">Accueil</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('payroll.index') }}">Paie</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('payroll.stats.index') }}">Statistiques</a></li>
                        <li class="breadcrumb-item active">{{ $monthName }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Contrôles -->
    <div class="row mb-3">
        <div class="col-md-6">
            <a href="{{ route('payroll.stats.index') }}" class="btn btn-secondary">
                <i class="mdi mdi-arrow-left"></i> Retour aux Statistiques
            </a>
        </div>
        <div class="col-md-6">
            <div class="d-flex justify-content-end">
                <!-- <a href="{{ route('payroll.stats.export-month', $month) }}" 
                   class="btn btn-success me-2">
                    <i class="mdi mdi-download"></i> Exporter Excel
                </a> -->
                <button type="button" class="btn btn-info" onclick="toggleSummary()">
                    <i class="mdi mdi-chart-box"></i> Résumé
                </button>
            </div>
        </div>
    </div>

    <!-- Résumé -->
    <div id="summary-section" class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Employés</h6>
                            <h4 class="mb-0">{{ count($monthDetails) }}</h4>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-account-multiple-outline h1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Brut</h6>
                            <h4 class="mb-0">{{ number_format(array_sum(array_column($monthDetails, 'gross_pay')), 0, ',', ' ') }}</h4>
                            <small>{{ $currency }}</small>

                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-currency-usd h1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Déductions</h6>
                            <h4 class="mb-0">{{ number_format(array_sum(array_column($monthDetails, 'total_deduction')), 0, ',', ' ') }}</h4>
                            <small>{{ $currency }}</small>

                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-minus-circle-outline h1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-white bg-info mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Net</h6>
                            <h4 class="mb-0">{{ number_format(array_sum(array_column($monthDetails, 'net_pay')), 0, ',', ' ') }}</h4>
                            <small>{{ $currency }}</small>
                        </div>
                        <div class="align-self-center">
                            <i class="mdi mdi-cash-multiple h1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des détails -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Détails des Employés - {{ $monthName }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="employeesTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employé</th>
                                    <th>Département</th>
                                    <th>Poste</th>
                                    <th>Salaire Brut</th>
                                    <th>Déductions</th>
                                    <th>Salaire Net</th>
                                    <th>Devise</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthDetails as $employee)

                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    {{ strtoupper(substr($employee['employee_name'], 0, 2)) }}
                                                </div>
                                                <div>
                                                    <h6 class="mb-0">{{ $employee['employee_name'] }}</h6>
                                                    <small class="text-muted">{{ $employee['employee_id'] }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $employee['department'] }}</td>
                                        <td>{{ $employee['designation'] }}</td>
                                        <td class="text-end">
                                            {{ number_format($employee['gross_pay'], 0, ',', ' ') }} 
                                        </td>
                                        <td class="text-end text-danger">
                                            {{ number_format($employee['total_deduction'], 0, ',', ' ') }} 

                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            {{ number_format($employee['net_pay'], 0, ',', ' ') }} 

                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            {{  $employee['currency'] }}

                                        </td>

                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="showEmployeeDetails({{ json_encode($employee) }})">
                                                <i class="mdi mdi-eye"></i> Détails
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            Aucun employé trouvé pour ce mois
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal des détails employé -->
<div class="modal fade" id="employeeDetailsModal" tabindex="-1" aria-labelledby="employeeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeDetailsModalLabel">Détails de l'Employé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Informations générales -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Nom:</strong> <span id="modal-employee-name"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>ID:</strong> <span id="modal-employee-id"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Département:</strong> <span id="modal-department"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Poste:</strong> <span id="modal-designation"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Devise:</strong> <span> {{ $currency }}</span>
                    </div>
                </div>

                <!-- Résumé financier -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Salaire Brut</h6>
                                <h5 class="text-primary" id="modal-gross-pay"></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Déductions</h6>
                                <h5 class="text-danger" id="modal-deductions"></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="card-title">Salaire Net</h6>
                                <h5 class="text-success" id="modal-net-pay"></h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Détails des composants -->
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success mb-3">Gains</h6>
                        <div id="modal-earnings" class="list-group">
                            <!-- Sera rempli dynamiquement -->
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-danger mb-3">Déductions</h6>
                        <div id="modal-deductions-list" class="list-group">
                            <!-- Sera rempli dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeDataTable();
});

function initializeDataTable() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#employeesTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            },
            order: [[0, 'asc']],
            pageLength: 25,
            responsive: true,
            columnDefs: [
                {
                    targets: [3, 4, 5], // Colonnes des montants
                    className: 'text-end'
                }
            ]
        });
    }
}

function toggleSummary() {
    const summarySection = document.getElementById('summary-section');
    if (summarySection.style.display === 'none') {
        summarySection.style.display = 'block';
    } else {
        summarySection.style.display = 'none';
    }
}

function showEmployeeDetails(employee) {
    // Rempli les informations générales
    document.getElementById('modal-employee-name').textContent = employee.employee_name;
    document.getElementById('modal-employee-id').textContent = employee.employee_id;
    document.getElementById('modal-department').textContent = employee.department;
    document.getElementById('modal-designation').textContent = employee.designation;
    
    // Rempli le résumé financier
    document.getElementById('modal-gross-pay').textContent = 
        new Intl.NumberFormat('fr-FR').format(employee.gross_pay);
    document.getElementById('modal-deductions').textContent = 
        new Intl.NumberFormat('fr-FR').format(employee.total_deduction);
    document.getElementById('modal-net-pay').textContent = 
        new Intl.NumberFormat('fr-FR').format(employee.net_pay);
    
    // Rempli la liste des gains
    const earningsDiv = document.getElementById('modal-earnings');
    earningsDiv.innerHTML = '';
    
    if (employee.earnings && employee.earnings.length > 0) {
        employee.earnings.forEach(earning => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between';
            item.innerHTML = `
                <span>${earning.component}</span>
                <span class="fw-bold text-success">
                    ${new Intl.NumberFormat('fr-FR').format(earning.amount)} 
                </span>
            `;
            earningsDiv.appendChild(item);
        });
    } else {
        earningsDiv.innerHTML = '<div class="text-muted">Aucun gain détaillé</div>';
    }
    
    // Rempli la liste des déductions
    const deductionsDiv = document.getElementById('modal-deductions-list');
    deductionsDiv.innerHTML = '';
    
    if (employee.deductions && employee.deductions.length > 0) {
        employee.deductions.forEach(deduction => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex justify-content-between';
            item.innerHTML = `
                <span>${deduction.component}</span>
                <span class="fw-bold text-danger">
                    ${new Intl.NumberFormat('fr-FR').format(deduction.amount)} 
                </span
            `;
            deductionsDiv.appendChild(item);
        });
    } else {
        deductionsDiv.innerHTML = '<div class="text-muted">Aucune déduction détaillée</div>';
    }
    
    // Affichage du modal
    const modal = new bootstrap.Modal(document.getElementById('employeeDetailsModal'));
    modal.show();
}
</script>
@endsection


@section('styles')
<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 12px;
}

.table th {
    border-top: none;
}

.table-responsive {
    border-radius: 0.375rem;
}

#summary-section {
    transition: all 0.3s ease;
}

.card.bg-primary, .card.bg-success, .card.bg-warning, .card.bg-info {
    border: none;
}

.list-group-item {
    border: none;
    padding: 0.5rem 0;
    background: transparent;
}

.modal-body .card {
    border: 1px solid #dee2e6;
}
</style>
@endsection
