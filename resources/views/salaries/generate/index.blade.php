@extends('layouts.app')

@section('title', 'Génération des Salaires')

@section('content')
<div class="container px-4 py-5">
    <div class="card shadow-sm p-4">
        <h1 class="card-title fs-3 fw-bold text-dark mb-4">Génération des Salaires</h1>

        <!-- Message de succès -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Message d'erreur -->
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ route('salaries.generate') }}" method="POST" class="row g-3">
            @csrf

            <!-- Sélection de l'employé -->
            <div class="col-12">
                <label for="employe_id" class="form-label fw-medium">
                    Employé <span class="text-danger">*</span>
                </label>
                <select id="employe_id" name="employe_id" required class="form-select @error('employe_id') is-invalid @enderror">
                    <option value="">Sélectionner un employé</option>
                    @foreach($employees as $employee)
                    <option value="{{ $employee['employee_number'] }}">
                        {{ $employee['employee_name'] }} ({{ $employee['employee_number'] ?? $employee['name'] }})
                    </option>
                    @endforeach
                </select>
                @error('employe_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Sélection de la structure salariale -->
            <div class="col-12">
                <label for="salary_structure" class="form-label fw-medium">
                    Structure salariale <span class="text-danger">*</span>
                </label>
                <select id="salary_structure" name="salary_structure" required class="form-select @error('salary_structure') is-invalid @enderror">
                    <option value="">Sélectionner une structure salariale</option>
                    @foreach($salaryStructures as $structure)
                        <option value="{{ $structure['name'] ?? 'g1' }}">
                            {{  $structure['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('salary_structure')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Dates -->
            <div class="col-md-6">
                <label for="date_debut" class="form-label fw-medium">
                    Date de début <span class="text-danger">*</span>
                </label>
                <input type="date" id="date_debut" name="date_debut" required class="form-control @error('date_debut') is-invalid @enderror">
                @error('date_debut')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="date_fin" class="form-label fw-medium">
                    Date de fin <span class="text-danger">*</span>
                </label>
                <input type="date" id="date_fin" name="date_fin" required class="form-control @error('date_fin') is-invalid @enderror">
                @error('date_fin')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Salaire de base -->
            <div class="col-12">
                <label for="salaire_base" class="form-label fw-medium">
                    Salaire de base <span class="text-danger">*</span>
                </label>
                <input type="number" id="salaire_base" name="salaire_base" step="0.01" min="0" required
                       class="form-control @error('salaire_base') is-invalid @enderror" placeholder="Ex: 50000.00">
                @error('salaire_base')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Bouton -->
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary px-4">Générer</button>
            </div>
        </form>
    </div>
</div>
@endsection