@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>Import des fichiers</h4>
                </div>
                <div class="card-body">
                    
                    {{-- Zone des messages (sera mise à jour via AJAX) --}}
                    <div id="messages-container">
                        {{-- Messages de succès --}}
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>Succès !</strong> {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        {{-- Messages d'erreur généraux --}}
                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>Erreur !</strong> {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        {{-- Erreurs de validation détaillées --}}
                        @if(session('validation_errors'))
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle"></i> Erreurs de validation détaillées :</h5>
                                
                                @foreach(session('validation_errors') as $fileType => $fileErrors)
                                    <div class="mb-3">
                                        <h6 class="text-danger">
                                            <strong>📄 Fichier {{ ucfirst(str_replace('_', ' ', $fileType)) }} :</strong>
                                        </h6>
                                        
                                        <div class="card border-danger">
                                            <div class="card-body p-2">
                                                <ul class="mb-0 list-unstyled">
                                                    @foreach($fileErrors as $error)
                                                        <li class="mb-1">
                                                            <i class="fas fa-times-circle text-danger"></i>
                                                            <span class="ms-2">{{ $error }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                
                                <div class="mt-3 p-2 bg-light border-left border-warning">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Note :</strong> Tous les fichiers doivent être valides avant de pouvoir procéder à l'import.
                                    </small>
                                </div>
                            </div>
                        @endif

                        {{-- Erreurs de validation Laravel --}}
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle"></i> Erreurs de fichiers :</h5>
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Résultats d'import --}}
                        @if(session('import_results'))
                            <div class="alert alert-info">
                                <h5><i class="fas fa-chart-bar"></i> Résultats de l'import :</h5>
                                
                                @foreach(session('import_results') as $fileType => $result)
                                    <div class="mb-2">
                                        <strong>{{ ucfirst(str_replace('_', ' ', $fileType)) }} :</strong>
                                        <span class="badge bg-success">{{ $result['success'] ?? 0 }} succès</span>
                                        
                                        @if(!empty($result['errors']))
                                            <span class="badge bg-danger">{{ count($result['errors']) }} erreurs</span>
                                            <div class="mt-1">
                                                <small class="text-danger">
                                                    @foreach($result['errors'] as $error)
                                                        <div>• {{ $error }}</div>
                                                    @endforeach
                                                </small>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Spinner de chargement (caché par défaut) --}}
                    <div id="loading-spinner" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <div class="mt-3">
                            <h5 class="text-primary">Import en cours...</h5>
                            <p class="text-muted">Veuillez patienter pendant le traitement des fichiers</p>
                        </div>
                    </div>

                    {{-- Formulaire d'upload --}}
                    <form id="import-form" method="POST" action="{{ route('import.process') }}" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="row">
                            {{-- Fichier Employés --}}
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-users"></i> Employés</h6>
                                    </div>
                                    <div class="card-body">
                                        <label for="employees_file" class="form-label">Fichier des employés</label>
                                        <input type="file" 
                                               class="form-control @error('employees_file') is-invalid @enderror" 
                                               id="employees_file" 
                                               name="employees_file" 
                                               accept=".csv,.txt"
                                               >
                                        
                                        <small class="form-text text-muted mt-2">
                                            <strong>Colonnes attendues :</strong><br>
                                            Ref, Nom, Prenom, genre, Date embauche, date naissance, company
                                        </small>
                                        
                                        @error('employees_file')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Fichier Structure Salariale --}}
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0"><i class="fas fa-money-bill"></i> Structure Salariale</h6>
                                    </div>
                                    <div class="card-body">
                                        <label for="salary_structure_file" class="form-label">Fichier structure salariale</label>
                                        <input type="file" 
                                               class="form-control @error('salary_structure_file') is-invalid @enderror" 
                                               id="salary_structure_file" 
                                               name="salary_structure_file" 
                                               accept=".csv,.txt"
                                               >
                                        
                                        <small class="form-text text-muted mt-2">
                                            <strong>Colonnes attendues :</strong><br>
                                            salary structure, name, Abbr, type, valeur, company
                                        </small>
                                        
                                        @error('salary_structure_file')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Fichier Paie --}}
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0"><i class="fas fa-calculator"></i> Paie</h6>
                                    </div>
                                    <div class="card-body">
                                        <label for="payroll_file" class="form-label">Fichier de paie</label>
                                        <input type="file" 
                                               class="form-control @error('payroll_file') is-invalid @enderror" 
                                               id="payroll_file" 
                                               name="payroll_file" 
                                               accept=".csv,.txt"
                                               >
                                        
                                        <small class="form-text text-muted mt-2">
                                            <strong>Colonnes attendues :</strong><br>
                                            Mois, Ref Employe, Salaire Base, Salaire
                                        </small>
                                        
                                        @error('payroll_file')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Bouton de soumission --}}
                        <div class="row mt-4">
                            <div class="col-12 text-center">
                                <button type="submit" id="submit-btn" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload"></i> Lancer l'import
                                </button>
                            </div>
                        </div>

                        {{-- Aide --}}
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="fas fa-info-circle"></i> Instructions :</h6>
                                        <ul class="mb-0">
                                            <li>Tous les fichiers doivent être au format CSV</li>
                                            <li>Les dates doivent être au format DD/MM/YYYY</li>
                                            <li>Les fichiers doivent contenir les colonnes exactes mentionnées ci-dessus</li>
                                            <li>L'import est "tout ou rien" : si un fichier contient des erreurs, aucun import ne sera effectué</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Script simple pour le spinner --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('import-form');
    const submitBtn = document.getElementById('submit-btn');
    const spinner = document.getElementById('loading-spinner');
    const messagesContainer = document.getElementById('messages-container');

    form.addEventListener('submit', function(e) {
        // Affiche le spinner et masquer les messages précédents
        spinner.style.display = 'block';
        messagesContainer.style.display = 'none';
        
        // Désactivation du bouton de soumission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Import en cours...';
        
        // Faire défiler vers le spinner
        spinner.scrollIntoView({ behavior: 'smooth' });
        
    });

    // Masque le spinner si la page se recharge (en cas de retour avec erreurs)
    window.addEventListener('pageshow', function(event) {
        // Si la page est chargée depuis le cache du navigateur, masquer le spinner
        if (event.persisted) {
            spinner.style.display = 'none';
            messagesContainer.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload"></i> Lancer l\'import';
        }
    });


    setTimeout(function() {
        if (spinner.style.display === 'block') {
            spinner.style.display = 'none';
            messagesContainer.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload"></i> Lancer l\'import';
        }
    }, 30000); // 30 secondes
});
</script>

@push('styles')
<style>
.border-left {
    border-left: 4px solid;
}
.border-warning {
    border-left-color: #ffc107 !important;
}
.list-unstyled li {
    padding: 2px 0;
}
.card-header h6 {
    margin: 0;
}
</style>
@endpush
@endsection