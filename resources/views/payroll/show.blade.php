@extends('layouts.app')

@section('title', 'Fiche Employé - ' . ($employee['employee_name'] ?? 'N/A'))

@section('content')
<div class="container-fluid">
    <!-- En-tête avec informations employé -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>
                            Fiche Employé
                        </h5>
                        <a href="{{ route('payroll.index') }}" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>
                            Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nom:</strong></td>
                                    <td>{{ $employee['employee_name'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Réference:</strong></td>
                                    <td><code>{{ $employee['employee_number'] ?? 'N/A' }}</code></td>
                                </tr>
                                <!-- <tr>
                                    <td><strong>Département:</strong></td>
                                    <td>
                                        <span class="badge bg-info">{{ $employee['department'] ?? 'N/A' }}</span>
                                    </td>
                                </tr> -->
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <!-- <tr>
                                    <td><strong>Poste:</strong></td>
                                    <td>{{ $employee['designation'] ?? 'N/A' }}</td>
                                </tr> -->
                                <tr>
                                    <td><strong>Entreprise:</strong></td>
                                    <td>{{ $employee['company'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Date d'embauche:</strong></td>
                                    <td>{{ isset($employee['date_of_joining']) ? \Carbon\Carbon::parse($employee['date_of_joining'])->format('d/m/Y') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                    <h4>{{ number_format($stats['total_net_pay'], 0, ',', ' ') }} </h4>
                    <p class="mb-0">Total Net</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <h4>{{ number_format($stats['average_net_pay'], 0, ',', ' ') }} </h4>
                    <p class="mb-0">Moyenne Mensuelle</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-minus-circle fa-2x mb-2"></i>
                    <h4>{{ number_format($stats['total_deductions'], 0, ',', ' ') }} </h4>
                    <p class="mb-0">Total Déductions</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <h4>{{ $stats['months_count'] }}</h4>
                    <p class="mb-0">Mois Travaillés</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Salaires par mois -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check me-2"></i>
                        Salaires par Mois
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($salariesByMonth) > 0)
                        <div class="accordion" id="salariesAccordion">
                            @foreach($salariesByMonth as $index => $monthData)
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading{{ $index }}">
                                        <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapse{{ $index }}" 
                                                aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" 
                                                aria-controls="collapse{{ $index }}">
                                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                <span>
                                                    <i class="fas fa-calendar me-2"></i>
                                                    {{ $monthData['month_name'] }}
                                                </span>
                                                <div class="d-flex gap-3">
                                                    @php
                                                        $monthTotal = array_sum(array_column($monthData['slips'], 'net_pay'));
                                                    @endphp
                                                    <span class="badge bg-success">
                                                        {{ number_format($monthTotal, 0, ',', ' ') }} net
                                                    </span>
                                                    <span class="badge bg-secondary">
                                                        {{ count($monthData['slips']) }} fiche(s)
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse{{ $index }}" 
                                         class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" 
                                         aria-labelledby="heading{{ $index }}" 
                                         data-bs-parent="#salariesAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-end mb-3">
                                                <a href="{{ route('payroll.employee.monthly.pdf', [$employee['name'], $monthData['month']]) }}" 
                                                   class="btn btn-danger btn-sm">
                                                    <i class="fas fa-file-pdf me-1"></i>
                                                    Export PDF Mensuel
                                                </a>
                                            </div>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-sm table-striped">
                                                    <thead class="table-dark">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Période</th>
                                                            <th class="text-end">Brut</th>
                                                            <th class="text-end">Déductions</th>
                                                            <th class="text-end">Net</th>
                                                            <th>Statut</th>
                                                            <th class="text-center">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($monthData['slips'] as $slip)
                                                            <tr>
                                                                <td>
                                                                    {{ \Carbon\Carbon::parse($slip['posting_date'])->format('d/m/Y') }}
                                                                </td>
                                                                <td>
                                                                    <small class="text-muted">
                                                                        {{ \Carbon\Carbon::parse($slip['start_date'])->format('d/m') }} - 
                                                                        {{ \Carbon\Carbon::parse($slip['end_date'])->format('d/m') }}
                                                                    </small>
                                                                </td>
                                                                <td class="text-end">
                                                                    <strong>{{ number_format($slip['gross_pay'] ?? 0, 2, ',', ' ') }} </strong>
                                                                </td>
                                                                <td class="text-end text-danger">
                                                                    -{{ number_format($slip['total_deduction'] ?? 0, 2, ',', ' ') }} 
                                                                </td>
                                                                <td class="text-end">
                                                                    <strong class="text-success">
                                                                        {{ number_format($slip['net_pay'] ?? 0, 2, ',', ' ') }} 
                                                                    </strong>
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        $statusClass = match($slip['status'] ?? '') {
                                                                            'Submitted' => 'bg-success',
                                                                            'Draft' => 'bg-warning',
                                                                            'Cancelled' => 'bg-danger',
                                                                            default => 'bg-secondary'
                                                                        };
                                                                    @endphp
                                                                    <span class="badge {{ $statusClass }}">
                                                                        {{ $slip['status'] ?? 'N/A' }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-center">
                                                                    <div class="btn-group btn-group-sm">
                                                                        <a href="{{ route('payroll.salary-slip.show', $slip['name']) }}"
                                                                        class="btn btn-outline-primary btn-sm"
                                                                        title="Voir détails">
                                                                            <i class="fas fa-eye"></i>
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                    <tfoot class="table-secondary">
                                                        <tr>
                                                            <th colspan="2">Total {{ $monthData['month_name'] }}</th>
                                                            <th class="text-end">
                                                                {{ number_format(array_sum(array_column($monthData['slips'], 'gross_pay')), 2, ',', ' ') }} 
                                                            </th>
                                                            <th class="text-end text-danger">
                                                                -{{ number_format(array_sum(array_column($monthData['slips'], 'total_deduction')), 2, ',', ' ') }} 
                                                            </th>
                                                            <th class="text-end text-success">
                                                                <strong>{{ number_format(array_sum(array_column($monthData['slips'], 'net_pay')), 2, ',', ' ') }} </strong>
                                                            </th>
                                                            <th colspan="2"></th>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune fiche de paie trouvée</h5>
                            <p class="text-muted">Cet employé n'a pas encore de fiches de paie enregistrées.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.accordion-button:not(.collapsed) {
    background-color: #e7f3ff;
    color: #0056b3;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.badge {
    font-size: 0.75rem;
}

.table-sm th, .table-sm td {
    padding: 0.5rem;
}

.btn-group-sm > .btn, .btn-sm {
    font-size: 0.875rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    font-weight: 600;
}

code {
    background-color: #f8f9fa;
    color: #e83e8c;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
}
</style>
@endsection