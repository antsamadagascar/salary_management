@extends('layouts.app')

@section('title', 'Gestion de la Paie')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Liste des Employés
                    </h5>
                </div>

                <div class="card-body">
                    <!-- Barre de recherche -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('payroll.search') }}" class="d-flex">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Rechercher par nom, matricule ou département..." 
                                       value="{{ $search ?? '' }}">
                                <button type="submit" class="btn btn-primary ms-2">
                                    <i class="fas fa-search"></i>
                                </button>
                                @if(isset($search) && $search)
                                    <a href="{{ route('payroll.index') }}" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-times"></i>
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>

                    <!-- Messages d'erreur -->
                    @if(isset($error))
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            {{ $error }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Table des employés -->
                    @if(count($employees) > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Réference</th>
                                        <th>Nom</th>
                                        <!--<th>Département</th>
                                        <th>Poste</th> -->
                                        <th>Entreprise</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employees as $employee)
                                        <tr>
                                            <td>
                                                <code>{{ $employee['employee_number'] ?? 'N/A' }}</code>
                                            </td>
                                            <td>
                                                <strong>{{ $employee['employee_name'] ?? 'N/A' }}</strong>
                                            </td>
                                            <!-- <td>
                                                <span class="badge bg-info">
                                                    {{ $employee['department'] ?? 'N/A' }}
                                                </span>
                                            </td>
                                            <td>{{ $employee['designation'] ?? 'N/A' }}</td> -->
                                            <td>{{ $employee['company'] ?? 'N/A' }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('payroll.employee.show', $employee['name']) }}" 
                                                   class="btn btn-primary btn-sm" title="Voir la fiche">
                                                    <i class="fas fa-eye"></i>
                                                    Fiche
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                {{ count($employees) }} employé(s) trouvé(s)
                            </small>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun employé trouvé</h5>
                            @if(isset($search) && $search)
                                <p class="text-muted">Aucun résultat pour "{{ $search }}"</p>
                                <a href="{{ route('payroll.index') }}" class="btn btn-outline-primary">
                                    Afficher tous les employés
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table th {
    border-top: none;
    font-weight: 600;
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

.btn-sm {
    font-size: 0.875rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
</style>
@endsection