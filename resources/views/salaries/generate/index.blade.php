@extends('layouts.app')

@section('title', 'Génération des Salaires')

@section('content')
<div class="container px-4 py-5">
    <div class="card shadow-sm p-4">
        <h1 class="card-title fs-3 fw-bold text-dark mb-4">Génération des Salaires</h1>
        
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
                <select id="employe_id" name="employe_id" required class="form-select">
                    <option value="">Sélectionner un employé</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee['name'] }}">
                            {{ $employee['employee_name'] }} ({{ $employee['employee_number'] ?? $employee['name'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Dates -->
            <div class="col-md-6">
                <label for="date_debut" class="form-label fw-medium">
                    Date de début <span class="text-danger">*</span>
                </label>
                <input type="date" id="date_debut" name="date_debut" required class="form-control">
            </div>
            <div class="col-md-6">
                <label for="date_fin" class="form-label fw-medium">
                    Date de fin <span class="text-danger">*</span>
                </label>
                <input type="date" id="date_fin" name="date_fin" required class="form-control">
            </div>

            <!-- Salaire de base -->
            <div class="col-12">
                <label for="salaire_base" class="form-label fw-medium">
                    Salaire de base <span class="text-danger">*</span>
                </label>
                <input type="number" id="salaire_base" name="salaire_base" step="0.01" min="0" required
                       class="form-control" placeholder="Ex: 50000.00">
            </div>

            <!-- Bouton -->
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary px-4">Générer</button>
            </div>
        </form>
    </div>
</div>
@endsection