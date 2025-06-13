@extends('layouts.app')

@section('title', 'Génération des Salaires')

@section('content')
<div class="container px-4 py-5">
    <div class="card shadow-sm p-4">
        <h1 class="card-title fs-3 fw-bold text-dark mb-4">Configuration Salaires de base</h1>

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

        <!-- Message d'aperçu -->
        @if(session('preview'))
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                {{ session('preview') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Formulaire -->
        <form action="{{ route('salaries.config.generate') }}" method="POST" class="row g-3" id="salaryConfigForm">
            @csrf

            <!-- Sélection de la composante salariale -->
            <div class="col-12">
                <label for="salary_components" class="form-label fw-medium">
                    Composantes Salariales <span class="text-danger">*</span>
                </label>
                <select id="salary_components" name="salary_components" required class="form-select @error('salary_components') is-invalid @enderror">
                    <option value="">Sélectionner une composante salariale</option>
                    @foreach($salaryComponents as $component)
                        <option value="{{ $component['name'] }}" {{ session('form_data.salary_components') == $component['name'] ? 'selected' : '' }}>
                            {{ $component['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('salary_components')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Montant -->
            <div class="col-12">
                <label for="Montant" class="form-label fw-medium">
                    Montant <span class="text-danger">*</span>
                </label>
                <input type="number" id="Montant" name="Montant" step="0.01" min="0" required
                       class="form-control @error('Montant') is-invalid @enderror" value="{{ session('form_data.Montant') }}">
                @error('Montant')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Sélection de la condition -->
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

            <!-- Sélection de l'option -->
            <div class="col-12">
                <label for="options" class="form-label fw-medium">
                    Options <span class="text-danger">*</span>
                </label>
                <select id="options" name="options" required class="form-select @error('options') is-invalid @enderror">
                    <option value="">Sélectionner une option salariale</option>
                    @foreach($options as $option)
                        <option value="{{ $option['name'] }}" {{ session('form_data.options') == $option['name'] ? 'selected' : '' }}>
                            {{ $option['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('options')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Pourcentage -->
            <div class="col-12">
                <label for="pourcentage" class="form-label fw-medium">
                    Pourcentage (%) <span class="text-danger">*</span>
                </label>
                <input type="number" id="pourcentage" name="pourcentage" step="0.01" min="0" required
                       class="form-control @error('pourcentage') is-invalid @enderror" placeholder="Ex: 30" value="{{ session('form_data.pourcentage') }}">
                @error('pourcentage')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Boutons -->
            <div class="col-12 text-end">
                <button type="button" class="btn btn-secondary px-4 me-2" onclick="previewForm()">Aperçu</button>
                <button type="submit" class="btn btn-primary px-4">Configurer</button>
            </div>
        </form>

        <!-- Tableau d'aperçu -->
        @if(session('preview_data'))
            <div class="mt-5">
                <h2 class="fs-4 fw-bold text-dark mb-3">Aperçu des Modifications</h2>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Employé</th>
                                <th>Salaire Actuel</th>
                                <th>Nouveau Salaire</th>
                                <th>Différence</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(session('preview_data') as $item)
                                <tr>
                                    <td>{{ $item['employee_name'] }}</td>
                                    <td>{{ number_format($item['current_base_salary'], 2) }}</td>
                                    <td>{{ number_format($item['new_base_salary'], 2) }}</td>
                                    <td>{{ number_format($item['difference'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">Aucun employé affecté par ces critères.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    function previewForm() {
        const form = document.getElementById('salaryConfigForm');
        form.action = '{{ route('salaries.config.preview') }}';
        form.submit();
        form.action = '{{ route('salaries.config.generate') }}'; // Restaurer l'action originale
    }
</script>
@endsection