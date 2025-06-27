@extends('layouts.app')

@section('title', 'Recherche des Salaires Employés')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h1 class="card-title h3 mb-0">Recherche des Salaires</h1>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Succès !</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Erreur !</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('filter.salary.result') }}" method="POST">
                        @csrf
                        
                        <!-- Structure Components -->
                        <div class="mb-3">
                            <label for="salaryComponents" class="form-label fw-bold">
                                Structure Components <span class="text-danger">*</span>
                            </label>
                            <select id="salaryComponents" name="salaryComponents" required 
                                    class="form-select @error('salaryComponents') is-invalid @enderror">
                                <option value="">Sélectionner une structure salariale</option>
                                @foreach($salaryComponents as $component)
                                    <option value="{{ $component['name'] }}" {{ old('salary_component') == $component['name'] ? 'selected' : '' }}>
                                        {{ $component['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('salary_component')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Conditions -->
                        <div class="mb-3">
                            <label for="conditions" class="form-label fw-bold">
                                Conditions d'application <span class="text-danger">*</span>
                            </label>
                            <select id="conditions" name="conditions" required 
                                    class="form-select @error('conditions') is-invalid @enderror">
                                <option value="">Sélectionner une condition d'application</option>
                                @foreach($conditions as $condition)
                                    <option value="{{ $condition['name'] }}" {{ old('conditions') == $condition['name'] ? 'selected' : '' }}>
                                        {{ $condition['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('conditions')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Salaire de base -->
                        <div class="mb-4">
                            <label for="salaire_base" class="form-label fw-bold">
                                Salaire de base <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" id="salaire_base" name="salaire_base" 
                                       value="{{ old('salaire_base') }}" step="0.01" min="0" 
                                       class="form-control @error('salaire_base') is-invalid @enderror" 
                                       placeholder="Ex: 50000.00">
                                <span class="input-group-text">Ar</span>
                            </div>
                            @error('salaire_base')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Rechercher
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Résultats -->
            @if(isset($results) && count($results) > 0)
            <div class="card shadow mt-4">
                <div class="card-header bg-success text-white">
                    <h2 class="card-title h4 mb-0">
                        Résultats ({{ count($results) }} trouvé(s))
                    </h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Matricule</th>
                                    <th>Nom</th>
                                    <th>Structure Salariale</th>
                                    <th>Composant</th>
                                    <th class="text-end">Montant</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $item)
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">{{ $item['employee'] }}</span>
                                    </td>
                                    <td class="fw-bold">{{ $item['employee_name'] }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $item['salary_structure'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $item['salary_component'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-success">
                                            {{ number_format($item['amount'], 2, ',', ' ') }} Ar
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        {{ \Carbon\Carbon::parse($item['posting_date'])->format('d/m/Y') }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @elseif(isset($results))
            <div class="card mt-4">
                <div class="card-body text-center py-5">
                    <div class="text-muted mb-3">
                        <h3>Aucun résultat trouvé</h3>
                        <p>Essayez de modifier vos critères de recherche.</p>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection