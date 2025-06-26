@extends('layouts.app')

@section('title', 'Recherche  des Salaires employes')

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

         <form action="{{ route('filter.salary.result') }}" method="POST" class="row g-3">

            @csrf

            <!-- Sélection de la structure salariale -->
            <div class="col-12">
                <label for="salaryComponents" class="form-label fw-medium">
                    Structure Components <span class="text-danger">*</span>
                </label>
                <select id="salaryComponents" name="salaryComponents" required class="form-select @error('salaryComponents') is-invalid @enderror">
                    <option value="">Sélectionner une structure salariale</option>
                    @foreach($salaryComponents as $components)
                        <option value="{{ $components['name']}}">
                            {{  $components['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('salary_structure')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <label for="conditions" class="form-label fw-medium">
                    Conditions d'application <span class="text-danger">*</span>
                </label>
                <select id="conditions" name="conditions" required class="form-select @error('conditions') is-invalid @enderror">
                    <option value="">Sélectionner une condition d'application</option>
                    @foreach($conditions as $condition)
                        <option value="{{ $condition['name'] }}" {{ session('form_data.conditions') == $condition['name'] ? 'selected' : '' }}>
                            {{ $condition['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('conditions')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Salaire de base -->
            <div class="col-12">    
                <label for="salaire_base" class="form-label fw-medium">
                    Salaire de base <span class="text-danger">*</span>
                </label>
                <input type="number" id="salaire_base" name="salaire_base" step="0.01" min="0" 
                       class="form-control @error('salaire_base') is-invalid @enderror" placeholder="Ex: 50000.00">
                @error('salaire_base')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>


            <!-- Bouton -->
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary px-4">Filtré</button>
            </div>
        </form>
    </div>
</div>
@endSection