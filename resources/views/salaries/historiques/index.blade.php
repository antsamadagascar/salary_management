@extends('layouts.app')

@section('content')
<div class="container px-4 py-5">
    <div class="card shadow-sm p-4">
        <h1 class="card-title fs-3 fw-bold text-dark mb-4">Historique des Salaires avec Statistiques</h1>
        
        <!-- Messages -->
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <!-- Formulaire de recherche -->
        <form action="{{ route('salaries.history.show') }}" method="POST" class="row g-3 mb-4">
            @csrf
            
            <!-- Sélection employé -->
            <div class="col-12">
                <label for="employe_id" class="form-label fw-medium">
                    Employé <span class="text-danger">*</span>
                </label>
                <select id="employe_id" name="employe_id"  class="form-select @error('employe_id') is-invalid @enderror">
                    <option value="">Sélectionner un employé</option>
                    @foreach($employees as $employee)
                   <option value="{{ $employee['name'] }}"
                        {{ old('employe_id', $request->employe_id ?? '') == $employee['name'] ? 'selected' : '' }}>
                        {{ $employee['employee_name'] }} ({{ $employee['employee_number'] ?? $employee['name'] }})
                    </option>

                    @endforeach
                </select>
                @error('employe_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Filtres dates -->
            <div class="col-md-4">
                <label for="year" class="form-label">Année (optionnel)</label>
                <select name="year" id="year" class="form-select">
                    <option value="">Toutes les années</option>
                    @foreach($availableYears as $availableYear)
                    <option value="{{ $availableYear }}" {{ old('year', $request->year ?? '') == $availableYear ? 'selected' : '' }}>
                        {{ $availableYear }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="date_debut" class="form-label fw-medium">
                    Date de début <span class="text-danger">*</span>
                </label>
                <input type="date" id="date_debut" name="date_debut"  
                       value="{{ old('date_debut', $request->date_debut ?? '') }}"
                       class="form-control @error('date_debut') is-invalid @enderror">
                @error('date_debut')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="col-md-4">
                <label for="date_fin" class="form-label fw-medium">
                    Date de fin <span class="text-danger">*</span>
                </label>
                <input type="date" id="date_fin" name="date_fin"  
                       value="{{ old('date_fin', $request->date_fin ?? '') }}"
                       class="form-control @error('date_fin') is-invalid @enderror">
                @error('date_fin')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            </div>

            <!-- Boutons -->
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary px-4 me-2">
                    Rechercher
                </button>
            </div>
        </form>

        <!-- Résultats -->
        @if(isset($salaryHistory))
            @if(count($salaryHistory) > 0)
                <!-- Informations employé -->
                <div class="alert alert-info mb-4">
                    <h5 class="mb-2">Employé sélectionné:</h5>
                    <strong>{{ $employee['employee_name'] ?? 'Nom non disponible' }}</strong> 
                    ({{ $employee['employee_number'] ?? $request->employe_id }})
                    <br>
                    <small class="text-muted">
                        Période: {{ date('d/m/Y', strtotime($request->date_debut)) }} au {{ date('d/m/Y', strtotime($request->date_fin)) }}
                    </small>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Salaire Moyen</h5>
                                <h3>{{ number_format($statistics['average_salary'], 2, ',', ' ') }} MGA</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Salaire Maximum</h5>
                                <h3>{{ number_format($statistics['max_salary'], 2, ',', ' ') }} MGA</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Salaire Minimum</h5>
                                <h3>{{ number_format($statistics['min_salary'], 2, ',', ' ') }} MGA</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Périodes</h5>
                                <h3>{{ $statistics['total_periods'] }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Évolution -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Évolution Salariale</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Premier salaire:</strong> 
                                   <span class="text-primary">{{ number_format($statistics['first_salary'], 2, ',', ' ') }} MGA</span>
                                </p>
                                <p><strong>Dernier salaire:</strong> 
                                   <span class="text-success">{{ number_format($statistics['last_salary'], 2, ',', ' ') }} MGA</span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Évolution totale:</strong> 
                                   <span class="fw-bold {{ $statistics['total_evolution_percent'] >= 0 ? 'text-success' : 'text-danger' }}">
                                       {{ $statistics['total_evolution_percent'] >= 0 ? '+' : '' }}{{ $statistics['total_evolution_percent'] }}%
                                   </span>
                                </p>
                                <p><strong>Évolution moyenne/mois:</strong> 
                                   <span class="{{ $statistics['avg_monthly_evolution'] >= 0 ? 'text-success' : 'text-danger' }}">
                                       {{ $statistics['avg_monthly_evolution'] >= 0 ? '+' : '' }}{{ number_format($statistics['avg_monthly_evolution'], 2, ',', ' ') }} MGA
                                   </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau historique -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Détail de l'Historique ({{ count($salaryHistory) }} périodes)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Période</th>
                                        <th>Montant</th>
                                        <th>Évolution</th>
                                        <th>Type</th>
                                        <th>Date Création</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($salaryHistory as $salary)
                                    <tr>
                                        <td><strong>{{ $salary['period'] }}</strong></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                {{ number_format($salary['amount'], 2, ',', ' ') }} MGA
                                            </span>
                                        </td>
                                        <td>
                                            @if($salary['evolution'] !== null)
                                                <span class="badge {{ $salary['evolution'] >= 0 ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $salary['evolution'] >= 0 ? '+' : '' }}{{ $salary['evolution'] }}%
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $salary['type'] }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ date('d/m/Y', strtotime($salary['created_at'])) }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            @else
                <!-- Aucun résultat -->
                <div class="alert alert-info text-center">
                    Aucun salaire trouvé pour cette période et cet employé.
                </div>
            @endif
        @endif
    </div>
</div>
@endsection