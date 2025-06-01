@extends('layouts.app')

@section('title', 'Fiche de Paie - ' . ($salarySlip['employee_name'] ?? 'N/A'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>
                            Fiche de Paie Détaillée
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('payroll.salary-slip.pdf', $salarySlip['name']) }}" 
                               class="btn btn-danger btn-sm">
                                <i class="fas fa-file-pdf me-1"></i>
                                Export PDF
                            </a>
                            <a href="{{ route('payroll.employee.show', $salarySlip['employee']) }}" 
                               class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>
                                Retour
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- En-tête de la fiche -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-user me-2"></i>
                                Informations Employé
                            </h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>Nom:</strong></td>
                                    <td>{{ $salarySlip['employee_name'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Matricule:</strong></td>
                                    <td><code>{{ $salarySlip['employee_details']['employee_number'] ?? 'N/A' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Département:</strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            {{ $salarySlip['employee_details']['department'] ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Poste:</strong></td>
                                    <td>{{ $salarySlip['employee_details']['designation'] ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-calendar me-2"></i>
                                Informations Période
                            </h6>
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td><strong>Date de paie:</strong></td>
                                    <td>{{ \Carbon\Carbon::parse($salarySlip['posting_date'])->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Période:</strong></td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($salarySlip['start_date'])->format('d/m/Y') }} - 
                                        {{ \Carbon\Carbon::parse($salarySlip['end_date'])->format('d/m/Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Statut:</strong></td>
                                    <td>
                                        @php
                                            $statusClass = match($salarySlip['status'] ?? '') {
                                                'Submitted' => 'bg-success',
                                                'Draft' => 'bg-warning',
                                                'Cancelled' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">
                                            {{ $salarySlip['status'] ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Référence:</strong></td>
                                    <td><code>{{ $salarySlip['name'] }}</code></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Détails des gains et déductions -->
                    <div class="row">
                        <!-- Gains -->
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-plus-circle me-2"></i>
                                        Gains
                                    </h6>
                                </div>
                                <div class="card-body">
                                    @if(isset($salarySlip['earnings']) && count($salarySlip['earnings']) > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Composant</th>
                                                        <th class="text-end">Montant</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($salarySlip['earnings'] as $earning)
                                                        <tr>
                                                            <td>{{ $earning['salary_component'] ?? 'N/A' }}</td>
                                                            <td class="text-end">
                                                                <strong class="text-success">
                                                                    {{ number_format($earning['amount'] ?? 0, 2, ',', ' ') }} €
                                                                </strong>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="table-success">
                                                    <tr>
                                                        <th>Total Gains</th>
                                                        <th class="text-end">
                                                            {{ number_format($salarySlip['gross_pay'] ?? 0, 2, ',', ' ') }} €
                                                        </th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-muted text-center">Aucun gain détaillé disponible</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Déductions -->
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-minus-circle me-2"></i>
                                        Déductions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    @if(isset($salarySlip['deductions']) && count($salarySlip['deductions']) > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Composant</th>
                                                        <th class="text-end">Montant</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($salarySlip['deductions'] as $deduction)
                                                        <tr>
                                                            <td>{{ $deduction['salary_component'] ?? 'N/A' }}</td>
                                                            <td class="text-end">
                                                                <strong class="text-danger">
                                                                    -{{ number_format($deduction['amount'] ?? 0, 2, ',', ' ') }} €
                                                                </strong>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="table-warning">
                                                    <tr>
                                                        <th>Total Déductions</th>
                                                        <th class="text-end">
                                                            -{{ number_format($salarySlip['total_deduction'] ?? 0, 2, ',', ' ') }} €
                                                        </th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-muted text-center">Aucune déduction détaillée disponible</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Résumé final -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-calculator me-2"></i>
                                        Résumé de Paie
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="border-end">
                                                <h4 class="text-success mb-1">
                                                    {{ number_format($salarySlip['gross_pay'] ?? 0, 2, ',', ' ') }} €
                                                </h4>
                                                <p class="text-muted mb-0">Salaire Brut</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border-end">
                                                <h4 class="text-warning mb-1">
                                                    -{{ number_format($salarySlip['total_deduction'] ?? 0, 2, ',', ' ') }} €
                                                </h4>
                                                <p class="text-muted mb-0">Total Déductions</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-primary mb-1">
                                                <strong>{{ number_format($salarySlip['net_pay'] ?? 0, 2, ',', ' ') }} €</strong>
                                            </h4>
                                            <p class="text-muted mb-0"><strong>Salaire Net</strong></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informations supplémentaires -->
                    @if(isset($salarySlip['letter_head']) || isset($salarySlip['remarks']))
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Informations Complémentaires
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        @if(isset($salarySlip['remarks']) && $salarySlip['remarks'])
                                            <div class="mb-3">
                                                <strong>Remarques:</strong>
                                                <p class="mt-2">{{ $salarySlip['remarks'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-end {
    border-right: 1px solid #dee2e6 !important;
}

.table-sm th, .table-sm td {
    padding: 0.5rem;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75rem;
}

code {
    background-color: #f8f9fa;
    color: #e83e8c;
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header h6 {
    font-weight: 600;
}

@media (max-width: 768px) {
    .border-end {
        border-right: none !important;
        border-bottom: 1px solid #dee2e6 !important;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
    }
}
</style>
@endsection