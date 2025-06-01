@extends('layouts.app')

@section('title', 'Tableau de Paie')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fas fa-money-check-alt"></i>
                        Tableau de Paie
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportCsv()">
                            <i class="fas fa-download"></i> Exporter CSV
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    @if(isset($error))
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ $error }}
                        </div>
                    @endif

                    <!-- Filtre par mois -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="monthFilter">
                                    <i class="fas fa-calendar"></i> Filtrer par mois :
                                </label>
                                <select id="monthFilter" class="form-control" onchange="filterByMonth()">
                                    @forelse($availableMonths as $month)
                                        <option value="{{ $month['value'] }}" 
                                                {{ $currentMonth == $month['value'] ? 'selected' : '' }}>
                                            {{ $month['label'] }}
                                        </option>
                                    @empty
                                        <option value="{{ $currentMonth }}">
                                            {{ \Carbon\Carbon::createFromFormat('Y-m', $currentMonth)->format('F Y') }}
                                        </option>
                                    @endforelse
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé des totaux -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info">
                                    <i class="fas fa-users"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Employés</span>
                                    <span class="info-box-number" id="totalEmployees">
                                        {{ $totals['total_employees'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-success">
                                    <i class="fas fa-dollar-sign"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Salaire Brut Total</span>
                                    <span class="info-box-number" id="totalGrossPay">
                                        {{ number_format($totals['total_gross_pay'], 2) }} €
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning">
                                    <i class="fas fa-minus-circle"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Déductions</span>
                                    <span class="info-box-number" id="totalDeductions">
                                        {{ number_format($totals['total_deductions'], 2) }} €
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Salaire Net Total</span>
                                    <span class="info-box-number" id="totalNetPay">
                                        {{ number_format($totals['total_net_pay'], 2) }} €
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading spinner -->
                    <div id="loadingSpinner" class="text-center" style="display: none;">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Chargement...</span>
                        </div>
                    </div>

                    <!-- Tableau des employés -->
                    <div class="table-responsive" id="payrollTable">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID Employé</th>
                                    <th>Nom</th>
                                    <th>Département</th>
                                    <th>Poste</th>
                                    <th>Salaire Brut</th>
                                    <th>Déductions</th>
                                    <th>Salaire Net</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payrollTableBody">
                                @forelse($payrollData as $employee)
                                    <tr>
                                        <td>{{ $employee['employee_id'] }}</td>
                                        <td>
                                            <strong>{{ $employee['employee_name'] }}</strong>
                                        </td>
                                        <td>{{ $employee['department'] }}</td>
                                        <td>{{ $employee['designation'] }}</td>
                                        <td class="text-right">
                                            <span class="badge badge-success">
                                                {{ number_format($employee['gross_pay'], 2) }} €
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <span class="badge badge-warning">
                                                {{ number_format($employee['total_deduction'], 2) }} €
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <span class="badge badge-primary">
                                                {{ number_format($employee['net_pay'], 2) }} €
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-info btn-sm" 
                                                    onclick="showEmployeeDetails('{{ $employee['employee_id'] }}', {{ json_encode($employee) }})">
                                                <i class="fas fa-eye"></i> Détails
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <i class="fas fa-inbox"></i>
                                            Aucune donnée de paie trouvée pour ce mois
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

<!-- Modal pour les détails de l'employé -->
<div class="modal fade" id="employeeDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user"></i>
                    Détails de Paie - <span id="modalEmployeeName"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-success">
                            <i class="fas fa-plus-circle"></i> Éléments de Gain
                        </h6>
                        <div id="earningsDetails"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-warning">
                            <i class="fas fa-minus-circle"></i> Éléments de Déduction
                        </h6>
                        <div id="deductionsDetails"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterByMonth() {
    const selectedMonth = document.getElementById('monthFilter').value;
    const loadingSpinner = document.getElementById('loadingSpinner');
    const payrollTable = document.getElementById('payrollTable');
    
    // Afficher le spinner
    loadingSpinner.style.display = 'block';
    payrollTable.style.display = 'none';
    
    // Faire la requête AJAX
    fetch(`{{ route('stats.data') }}?month=${selectedMonth}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updatePayrollTable(data.data);
            updateTotals(data.totals);
        } else {
            alert('Erreur lors du chargement des données: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors du chargement des données');
    })
    .finally(() => {
        // Masquer le spinner
        loadingSpinner.style.display = 'none';
        payrollTable.style.display = 'block';
    });
}

function updatePayrollTable(payrollData) {
    const tbody = document.getElementById('payrollTableBody');
    tbody.innerHTML = '';
    
    if (payrollData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center">
                    <i class="fas fa-inbox"></i>
                    Aucune donnée de paie trouvée pour ce mois
                </td>
            </tr>
        `;
        return;
    }
    
    payrollData.forEach(employee => {
        const row = `
            <tr>
                <td>${employee.employee_id}</td>
                <td><strong>${employee.employee_name}</strong></td>
                <td>${employee.department}</td>
                <td>${employee.designation}</td>
                <td class="text-right">
                    <span class="badge badge-success">
                        ${parseFloat(employee.gross_pay).toLocaleString('fr-FR', {minimumFractionDigits: 2})} €
                    </span>
                </td>
                <td class="text-right">
                    <span class="badge badge-warning">
                        ${parseFloat(employee.total_deduction).toLocaleString('fr-FR', {minimumFractionDigits: 2})} €
                    </span>
                </td>
                <td class="text-right">
                    <span class="badge badge-primary">
                        ${parseFloat(employee.net_pay).toLocaleString('fr-FR', {minimumFractionDigits: 2})} €
                    </span>
                </td>
                <td>
                    <button type="button" 
                            class="btn btn-info btn-sm" 
                            onclick="showEmployeeDetails('${employee.employee_id}', ${JSON.stringify(employee).replace(/"/g, '&quot;')})">
                        <i class="fas fa-eye"></i> Détails
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

function updateTotals(totals) {
    document.getElementById('totalEmployees').textContent = totals.total_employees;
    document.getElementById('totalGrossPay').textContent = 
        parseFloat(totals.total_gross_pay).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
    document.getElementById('totalDeductions').textContent = 
        parseFloat(totals.total_deductions).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
    document.getElementById('totalNetPay').textContent = 
        parseFloat(totals.total_net_pay).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
}

function showEmployeeDetails(employeeId, employeeData) {
    document.getElementById('modalEmployeeName').textContent = employeeData.employee_name;
    
    // Afficher les gains
    const earningsDiv = document.getElementById('earningsDetails');
    earningsDiv.innerHTML = '';
    if (employeeData.earnings && employeeData.earnings.length > 0) {
        employeeData.earnings.forEach(earning => {
            earningsDiv.innerHTML += `
                <div class="d-flex justify-content-between mb-2">
                    <span>${earning.component}</span>
                    <span class="badge badge-success">
                        ${parseFloat(earning.amount).toLocaleString('fr-FR', {minimumFractionDigits: 2})} €
                    </span>
                </div>
            `;
        });
    } else {
        earningsDiv.innerHTML = '<p class="text-muted">Aucun élément de gain</p>';
    }
    
    // Afficher les déductions
    const deductionsDiv = document.getElementById('deductionsDetails');
    deductionsDiv.innerHTML = '';
    if (employeeData.deductions && employeeData.deductions.length > 0) {
        employeeData.deductions.forEach(deduction => {
            deductionsDiv.innerHTML += `
                <div class="d-flex justify-content-between mb-2">
                    <span>${deduction.component}</span>
                    <span class="badge badge-warning">
                        ${parseFloat(deduction.amount).toLocaleString('fr-FR', {minimumFractionDigits: 2})} €
                    </span>
                </div>
            `;
        });
    } else {
        deductionsDiv.innerHTML = '<p class="text-muted">Aucune déduction</p>';
    }
    
    $('#employeeDetailsModal').modal('show');
}

function exportCsv() {
    const selectedMonth = document.getElementById('monthFilter').value;
    window.location.href = `{{ route('stats.export') }}?month=${selectedMonth}`;
}
</script>
@endpush

@push('styles')
<style>
.info-box {
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    border-radius: .25rem;
    background-color: #fff;
    display: flex;
    margin-bottom: 1rem;
    min-height: 80px;
    padding: .5rem;
    position: relative;
    width: 100%;
}

.info-box .info-box-icon {
    border-radius: .25rem;
    align-items: center;
    display: flex;
    font-size: 1.875rem;
    justify-content: center;
    text-align: center;
    width: 70px;
    color: #fff;
}

.info-box .info-box-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    line-height: 1.8;
    margin-left: .5rem;
    padding: 0 .5rem;
}

.info-box .info-box-number {
    display: block;
    margin-top: .25rem;
    font-weight: 700;
}

.table th {
    vertical-align: middle;
}

.badge {
    font-size: 0.9em;
}

.modal-body .d-flex {
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

.modal-body .d-flex:last-child {
    border-bottom: none;
}
</style>
@endpush