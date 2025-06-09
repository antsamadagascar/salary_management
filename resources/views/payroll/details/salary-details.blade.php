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
                    <!-- <div class="card-tools">
                        <button type="button" class="btn btn-success btn-sm"  id="exportBtn" onclick="exportCsv()">
                            <i class="fas fa-download"></i> Exporter CSV
                        </button>
                    </div> -->
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
                                <select id="monthFilter" class="form-control">
                                    <option value="">-- Sélectionnez un mois --</option>
                                    @foreach($availableMonths as $month)
                                        <option value="{{ $month['value'] }}" 
                                                {{ $currentMonth === $month['value'] ? 'selected' : '' }}>
                                            {{ $month['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>

                    <!-- Message d'information initial -->
                    <div id="selectionPrompt" class="alert alert-info text-center" 
                         style="display: {{ $currentMonth ? 'none' : 'block' }}">
                        <i class="fas fa-info-circle"></i>
                        <strong>Veuillez sélectionner un mois</strong> pour afficher les données de paie.
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
                                    <span class="info-box-text"> Salaire Brut Total</span>
                                    <span class="info-box-number" id="totalGrossPay">
                                        {{ number_format($totals['total_gross_pay'], 2) }} 
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
                                        {{ number_format($totals['total_deductions'], 2) }} 
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
                                        {{ number_format($totals['total_net_pay'], 2) }} 
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Totaux par composants -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-plus-circle"></i> Répartition des Gains
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="earningsBreakdown">
                                        @if(!empty($totals['earnings_breakdown']))
                                            @foreach($totals['earnings_breakdown'] as $component => $amount)
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>{{ $component }}</span>
                                                    <span class="info-box-number">
                                                        {{ number_format($amount, 2) }} 
                                                    </span>
                                                </div>
                                            @endforeach
                                        @else
                                            <p class="text-muted">Aucun composant de gain</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-warning">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-minus-circle"></i> Répartition des Déductions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div id="deductionsBreakdown">
                                        @if(!empty($totals['deductions_breakdown']))
                                            @foreach($totals['deductions_breakdown'] as $component => $amount)
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>{{ $component }}</span>
                                                    <span class="info-box-number">
                                                        {{ number_format($amount, 2) }} 
                                                    </span>
                                                </div>
                                            @endforeach
                                        @else
                                            <p class="text-muted">Aucune déduction</p>
                                        @endif
                                    </div>
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
                    <div class="table-responsive" id="payrollTable" 
                         style="display: {{ $currentMonth ? 'block' : 'none' }}">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID Employé</th>
                                    <th>Nom</th>
                                    <th>Salaire Brut</th>
                                    <th>Déductions</th>
                                    <th>Salaire Net</th>
                                    <th>Devise</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payrollTableBody">
                                @forelse($payrollData as $employee)
                                    <tr>
                                        <td>{{ $employee['employee_id'] }}</td>
                                        <td><strong>{{ $employee['employee_name'] }}</strong></td>
                                        <td class="text-right">
                                            <span class="badge badge-success text-dark">
                                                {{ number_format($employee['gross_pay'], 2) }} {{ $employee['currency'] ?? 'Ar' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <span class="badge badge-warning text-dark">
                                                {{ number_format($employee['total_deduction'], 2) }} {{ $employee['currency'] ?? 'Ar' }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <span class="badge badge-primary text-dark">
                                                {{ number_format($employee['net_pay'], 2) }} {{ $employee['currency'] ?? 'Ar' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary text-dark">{{ $employee['currency'] ?? 'Ar' }}</span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" 
                                                    onclick="showEmployeeDetails('{{ $employee['employee_id'] }}', {{ json_encode($employee) }})">
                                                <i class="fas fa-eye"></i> Détails
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">
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
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeEmployeeModal()">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const PayrollManager = {
    currentMonth: @json($currentMonth),
    init() {
        this.bindEvents();
        this.updateUI();
    },
    bindEvents() {
        const monthFilter = document.getElementById('monthFilter');
        if (monthFilter) {
            monthFilter.addEventListener('change', () => this.handleMonthChange());
        }
    },
    
    // Gestion du changement de mois
    handleMonthChange() {
        const selectedMonth = document.getElementById('monthFilter').value;
        
        if (!selectedMonth) {
            this.showEmptyState();
            return;
        }
        
        this.loadPayrollData(selectedMonth);
    },
    
    // Chargement des données via AJAX
    async loadPayrollData(month) {
        this.showLoading(true);
        
        try {
            const response = await fetch(`{{ route('payroll.stats.data') }}?month=${month}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentMonth = month;
                this.updatePayrollTable(data.data);
                this.updateTotals(data.totals);
                this.updateBreakdowns(data.totals);
                this.showDataSections(true);
            } else {
                alert('Erreur: ' + data.message);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des données');
        } finally {
            this.showLoading(false);
        }
    },
    
    // Mise à jour du tableau
    updatePayrollTable(payrollData) {
        const tbody = document.getElementById('payrollTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (payrollData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">
                        <i class="fas fa-inbox"></i>
                        Aucune donnée de paie trouvée pour ce mois
                    </td>
                </tr>
            `;
            return;
        }
        
        payrollData.forEach(employee => {
            const currency = employee.currency || 'Ar';
            tbody.innerHTML += `
                <tr>
                    <td>${employee.employee_id}</td>
                    <td><strong>${employee.employee_name}</strong></td>
                    <td class="text-right">
                        <span class="badge badge-success text-dark">
                            ${this.formatMoney(employee.gross_pay)} ${currency}
                        </span>
                    </td>
                    <td class="text-right">
                        <span class="badge badge-warning text-dark">
                            ${this.formatMoney(employee.total_deduction)} ${currency}
                        </span>
                    </td>
                    <td class="text-right">
                        <span class="badge badge-primary text-dark">
                            ${this.formatMoney(employee.net_pay)} ${currency}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-primary text-dark">${currency}</span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-info btn-sm" 
                                onclick="PayrollManager.showEmployeeDetails('${employee.employee_id}', ${JSON.stringify(employee).replace(/"/g, '&quot;')})">
                            <i class="fas fa-eye"></i> Détails
                        </button>
                    </td>
                </tr>
            `;
        });
    },
    
    // Mise à jour des totaux
    updateTotals(totals) {
        const elements = {
            totalEmployees: totals.total_employees,
            totalGrossPay: this.formatMoney(totals.total_gross_pay) + ' ',
            totalDeductions: this.formatMoney(totals.total_deductions) + ' ',
            totalNetPay: this.formatMoney(totals.total_net_pay) + ' '
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });
    },
    
    // Mise à jour des répartitions
    updateBreakdowns(totals) {
        this.updateBreakdown('earningsBreakdown', totals.earnings_breakdown, 'badge-success', 'Aucun composant de gain');
        this.updateBreakdown('deductionsBreakdown', totals.deductions_breakdown, 'badge-warning', 'Aucune déduction');
    },
    
    // Méthode utilitaire pour les répartitions
    updateBreakdown(containerId, data, badgeClass, emptyMessage) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        container.innerHTML = '';
        
        if (data && Object.keys(data).length > 0) {
            Object.entries(data).forEach(([component, amount]) => {
                container.innerHTML += `
                    <div class="d-flex justify-content-between mb-2">
                        <span>${component}</span>
                        <span class="badge ${badgeClass} text-dark">
                            ${this.formatMoney(amount)} Ar
                        </span>
                    </div>
                `;
            });
        } else {
            container.innerHTML = `<p class="text-muted">${emptyMessage}</p>`;
        }
    },
    
    // Affichage des détails employé
    showEmployeeDetails(employeeId, employeeData) {
        document.getElementById('modalEmployeeName').textContent = employeeData.employee_name;
        
        const currency = employeeData.currency || 'Ar';
        
        // Gains
        const earningsDiv = document.getElementById('earningsDetails');
        this.populateDetails(earningsDiv, employeeData.earnings, 'badge-success', currency, 'Aucun élément de gain');
        
        // Déductions
        const deductionsDiv = document.getElementById('deductionsDetails');
        this.populateDetails(deductionsDiv, employeeData.deductions, 'badge-warning', currency, 'Aucune déduction');

        if (typeof $ !== 'undefined') {
            $('#employeeDetailsModal').modal('show');
        }
    },
    
    // Méthode utilitaire pour remplir les détails
    populateDetails(container, data, badgeClass, currency, emptyMessage) {
        if (!container) return;
        
        container.innerHTML = '';
        
        if (data && data.length > 0) {
            data.forEach(item => {
                container.innerHTML += `
                    <div class="d-flex justify-content-between mb-2">
                        <span>${item.component}</span>
                        <span class="badge ${badgeClass} text-dark">
                            ${this.formatMoney(item.amount)} ${currency}
                        </span>
                    </div>
                `;
            });
        } else {
            container.innerHTML = `<p class="text-muted">${emptyMessage}</p>`;
        }
    },
    
    // Gestion des états d'affichage
    showEmptyState() {
        this.currentMonth = null;
        this.showDataSections(false);
        document.getElementById('selectionPrompt').style.display = 'block';
     //   document.getElementById('exportBtn').disabled = true;
    },
    
    showDataSections(show) {
        const sections = ['totalsSection', 'breakdownsSection', 'payrollTable'];
        const display = show ? 'block' : 'none';
        
        sections.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.style.display = display;
        });
        
        document.getElementById('selectionPrompt').style.display = show ? 'none' : 'block';
      //  document.getElementById('exportBtn').disabled = !show;
    },
    
    showLoading(show) {
        document.getElementById('loadingSpinner').style.display = show ? 'block' : 'none';
        document.getElementById('payrollTable').style.display = show ? 'none' : 'block';
    },
    
    updateUI() {
        if (this.currentMonth) {
            this.showDataSections(true);
        } else {
            this.showEmptyState();
        }
    },
    
    // Formatage des montants
    formatMoney(amount) {
        return parseFloat(amount).toLocaleString('fr-FR', {minimumFractionDigits: 2});
    }
};


function showEmployeeDetails(employeeId, employeeData) {
    const modalEmployeeName = document.getElementById('modalEmployeeName');
    if (modalEmployeeName) modalEmployeeName.textContent = employeeData.employee_name;
    
    // Affiche les gains
    const earningsDiv = document.getElementById('earningsDetails');
    if (earningsDiv) {
        earningsDiv.innerHTML = '';
        if (employeeData.earnings && employeeData.earnings.length > 0) {
            employeeData.earnings.forEach(earning => {
                earningsDiv.innerHTML += `
                    <div class="d-flex justify-content-between mb-2">
                        <span>${earning.component}</span>
                        <span class="badge badge-success text-dark">
                            ${parseFloat(earning.amount).toLocaleString('fr-FR', {minimumFractionDigits: 2})} 
                        </span>
                    </div>
                `;
            });
        } else {
            earningsDiv.innerHTML = '<p class="text-muted">Aucun élément de gain</p>';
        }
    }
    
    // Affiche les déductions
    const deductionsDiv = document.getElementById('deductionsDetails');
    if (deductionsDiv) {
        deductionsDiv.innerHTML = '';
        if (employeeData.deductions && employeeData.deductions.length > 0) {
            employeeData.deductions.forEach(deduction => {
                deductionsDiv.innerHTML += `
                    <div class="d-flex justify-content-between mb-2">
                        <span>${deduction.component}</span>
                        <span class="badge badge-warning text-dark">
                            ${parseFloat(deduction.amount).toLocaleString('fr-FR', {minimumFractionDigits: 2})} 
                        </span>
                    </div>
                `;
            });
        } else {
            deductionsDiv.innerHTML = '<p class="text-muted">Aucune déduction</p>';
        }
    }
    
    // Affiche le modal si jQuery est disponible
    if (typeof $ !== 'undefined') {
        $('#employeeDetailsModal').modal('show');
    }
}
// Fonction pour fermer le modal
function closeEmployeeModal() {
    const modal = document.getElementById('employeeDetailsModal');
    if (modal) {
        // Méthode Bootstrap si disponible
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#employeeDetailsModal').modal('hide');
        } else {
            // Méthode alternative sans Bootstrap
            modal.style.display = 'none';
            modal.classList.remove('show');
            document.body.classList.remove('modal-open');
            
            // Supprime le backdrop s'il existe
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        }
    }
}
// Export CSV (alea possible)
function exportCsv() {
    if (!PayrollManager.currentMonth) {
        alert('Veuillez sélectionner un mois avant d\'exporter');
        return;
    }
    const urlTemplate = `{{ route('payroll.stats.export-month', ['month' => '__MONTH__']) }}`;
    const exportUrl = urlTemplate.replace('__MONTH__', PayrollManager.currentMonth);
    window.location.href = exportUrl;
}


document.addEventListener('DOMContentLoaded', () => {
    PayrollManager.init();
});
</script>
@endsection
@section('styles')
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
    color:black;
    font-weight: 700;
}
.info-box-number {
    display: block;
    margin-top: .25rem;
    color:black;
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
@endSection