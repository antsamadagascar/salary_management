@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Import des données RH</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Import</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages de succès/erreur -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle-outline me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle-outline me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle-outline me-2"></i>
            <strong>Erreurs de validation:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Résultats d'import -->
    @if(session('import_results'))
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Résultats de l'import</h5>
            </div>
            <div class="card-body">
                @php $results = session('import_results'); @endphp
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="mdi mdi-account-multiple text-primary display-4"></i>
                                <h5 class="mt-2">Employés</h5>
                                <p class="text-success mb-1">{{ $results['employees']['success'] }} importés</p>
                                @if(count($results['employees']['errors']) > 0)
                                    <p class="text-danger mb-0">{{ count($results['employees']['errors']) }} erreurs</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="mdi mdi-currency-usd text-info display-4"></i>
                                <h5 class="mt-2">Structure Salariale</h5>
                                <p class="text-success mb-1">{{ $results['salary_structure']['success'] }} importées</p>
                                @if(count($results['salary_structure']['errors']) > 0)
                                    <p class="text-danger mb-0">{{ count($results['salary_structure']['errors']) }} erreurs</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="mdi mdi-file-document text-warning display-4"></i>
                                <h5 class="mt-2">Données de Paie</h5>
                                <p class="text-success mb-1">{{ $results['payroll']['success'] }} importées</p>
                                @if(count($results['payroll']['errors']) > 0)
                                    <p class="text-danger mb-0">{{ count($results['payroll']['errors']) }} erreurs</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Détail des erreurs -->
                @php 
                    $allErrors = array_merge(
                        $results['employees']['errors'],
                        $results['salary_structure']['errors'],
                        $results['payroll']['errors']
                    );
                @endphp
                
                @if(count($allErrors) > 0)
                    <div class="mt-4">
                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#errorDetails">
                            Voir les erreurs ({{ count($allErrors) }})
                        </button>
                        <div class="collapse mt-3" id="errorDetails">
                            <div class="card border-danger">
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        @foreach($allErrors as $error)
                                            <li class="text-danger mb-1">
                                                <i class="mdi mdi-alert-circle-outline me-1"></i>
                                                {{ $error }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Formulaire d'import -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="mdi mdi-upload me-2"></i>
                Import des fichiers CSV
            </h5>
        </div>
        <div class="card-body">
            <form action="{{ route('import.process') }}" method="POST" enctype="multipart/form-data" id="importForm">
                @csrf
                
                <div class="row">
                    <!-- Fichier Employés -->
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="mdi mdi-account-multiple me-2"></i>
                                    Fichier Employés
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="employees_file" class="form-label">Sélectionner le fichier CSV</label>
                                    <input type="file" 
                                           class="form-control @error('employees_file') is-invalid @enderror" 
                                           id="employees_file" 
                                           name="employees_file" 
                                           accept=".csv,.txt"
                                           data-type="employees">
                                    @error('employees_file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="text-muted small">
                                    <strong>Format attendu:</strong><br>
                                    Ref, Nom, Prenom, genre, Date embauche, date naissance, company
                                </div>
                                <div id="employees_preview" class="mt-3" style="display: none;">
                                    <h6>Aperçu:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead id="employees_headers"></thead>
                                            <tbody id="employees_data"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fichier Structure Salariale -->
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="mdi mdi-currency-usd me-2"></i>
                                    Structure Salariale
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="salary_structure_file" class="form-label">Sélectionner le fichier CSV</label>
                                    <input type="file" 
                                           class="form-control @error('salary_structure_file') is-invalid @enderror" 
                                           id="salary_structure_file" 
                                           name="salary_structure_file" 
                                           accept=".csv,.txt"
                                           data-type="salary_structure">
                                    @error('salary_structure_file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="text-muted small">
                                    <strong>Format attendu:</strong><br>
                                    salary structure, name, Abbr, type, valeur, Remarque
                                </div>
                                <div id="salary_structure_preview" class="mt-3" style="display: none;">
                                    <h6>Aperçu:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead id="salary_structure_headers"></thead>
                                            <tbody id="salary_structure_data"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fichier Données de Paie -->
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-white">
                                <h6 class="mb-0">
                                    <i class="mdi mdi-file-document me-2"></i>
                                    Données de Paie
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="payroll_file" class="form-label">Sélectionner le fichier CSV</label>
                                    <input type="file" 
                                           class="form-control @error('payroll_file') is-invalid @enderror" 
                                           id="payroll_file" 
                                           name="payroll_file" 
                                           accept=".csv,.txt"
                                           data-type="payroll">
                                    @error('payroll_file')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="text-muted small">
                                    <strong>Format attendu:</strong><br>
                                    Mois, Ref Employe, Salaire Base, Salaire
                                </div>
                                <div id="payroll_preview" class="mt-3" style="display: none;">
                                    <h6>Aperçu:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead id="payroll_headers"></thead>
                                            <tbody id="payroll_data"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <!-- <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmImport" required>
                                <label class="form-check-label" for="confirmImport">
                                    Je confirme que les fichiers sont au bon format et prêts à être importés
                                </label>
                            </div> -->
                            <div>
                                <button type="button" class="btn btn-secondary me-2" onclick="window.history.back()">
                                    <i class="mdi mdi-arrow-left me-1"></i>
                                    Retour
                                </button>
                                <!-- <button type="submit" class="btn btn-primary" id="submitBtn" disabled> -->
                                <button type="submit" class="btn btn-primary" id="submitBtn" >
                                    <i class="mdi mdi-upload me-1"></i>
                                    <span id="submitText">Lancer l'import</span>
                                    <span id="submitSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Instructions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="mdi mdi-information-outline me-2"></i>
                Instructions d'import
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Format des fichiers CSV</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            Encodage UTF-8 recommandé
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            Séparateur : virgule (,)
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            Première ligne : en-têtes de colonnes
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            Format des dates : jj/mm/aaaa
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-warning">Points d'attention</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-alert-circle text-warning me-2"></i>
                            Vérifiez que tous les champs obligatoires sont remplis
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-alert-circle text-warning me-2"></i>
                            Les données existantes seront mises à jour
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-alert-circle text-warning me-2"></i>
                            L'import se fait dans l'ordre : Employés → Structure → Paie
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-alert-circle text-warning me-2"></i>
                            En cas d'erreur, toutes les modifications sont annulées
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('importForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');
    const confirmCheckbox = document.getElementById('confirmImport');
    const fileInputs = document.querySelectorAll('input[type="file"]');

    confirmCheckbox.addEventListener('change', function() {
        submitBtn.disabled = !this.checked;
    });


    fileInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            if (this.files.length > 0) {
                previewFile(this.files[0], this.dataset.type);
            }
        });
    });

    form.addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitText.textContent = 'Import en cours...';
        submitSpinner.style.display = 'inline-block';

        let hasAllFiles = true;
        fileInputs.forEach(function(input) {
            if (!input.files.length) {
                hasAllFiles = false;
            }
        });

        if (!hasAllFiles) {
            e.preventDefault();
            alert('Veuillez sélectionner tous les fichiers requis.');
            resetSubmitButton();
            return false;
        }
    });

    function resetSubmitButton() {
        submitBtn.disabled = !confirmCheckbox.checked;
        submitText.textContent = 'Lancer l\'import';
        submitSpinner.style.display = 'none';
    }

    function previewFile(file, type) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        fetch('{{ route("import.preview") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert('Erreur de prévisualisation: ' + data.error, 'danger');
                return;
            }

            displayPreview(type, data.data);
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('Erreur lors de la prévisualisation du fichier', 'danger');
        });
    }

    function displayPreview(type, data) {
        const previewContainer = document.getElementById(type + '_preview');
        const headersContainer = document.getElementById(type + '_headers');
        const dataContainer = document.getElementById(type + '_data');

        if (!data.headers || !data.data) {
            return;
        }

        headersContainer.innerHTML = '<tr>' + 
            data.headers.map(header => `<th class="bg-light">${header}</th>`).join('') + 
            '</tr>';

        dataContainer.innerHTML = '';
        Object.values(data.data).slice(0, 3).forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = data.headers.map(header => `<td>${row[header] || ''}</td>`).join('');
            dataContainer.appendChild(tr);
        });

        previewContainer.style.display = 'block';
        
        const totalInfo = previewContainer.querySelector('.total-info') || document.createElement('div');
        totalInfo.className = 'total-info text-muted small mt-2';
        totalInfo.innerHTML = `<i class="mdi mdi-information-outline me-1"></i>Total: ${data.total_rows} lignes`;
        if (!previewContainer.querySelector('.total-info')) {
            previewContainer.appendChild(totalInfo);
        }
    }

    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="mdi mdi-alert-circle-outline me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
</script>
@endsection