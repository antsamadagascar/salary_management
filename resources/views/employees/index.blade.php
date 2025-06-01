@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <!-- En-tête avec titre et bouton d'ajout -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800">Gestion des Employés</h1>
            <p class="text-muted">Liste et gestion des employés de l'entreprise</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('employees.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouvel Employé
            </a>
        </div>
    </div>

    <!-- Messages d'alerte -->
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

    <!-- Carte de filtres -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtres de Recherche</h6>
        </div>
        <div class="card-body">
            <form id="filterForm" method="GET" action="{{ route('employees.index') }}">
                <div class="row g-3">
                    <!-- Recherche textuelle -->
                    <div class="col-md-4">
                        <label for="search" class="form-label">Rechercher</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               placeholder="Nom, prénom, numéro d'employé..."
                               value="{{ request('search') }}">
                    </div>

                    <!-- Filtre par département -->
                    <div class="col-md-3">
                        <label for="department" class="form-label">Département</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">Tous les départements</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept['name'] }}" 
                                        {{ request('department') == $dept['name'] ? 'selected' : '' }}>
                                    {{ $dept['department_name'] ?? $dept['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
            
                    <!-- Filtre par statut -->
                    <div class="col-md-2">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous</option>
                            <option value="Active" {{ request('status') == 'Active' ? 'selected' : '' }}>Actif</option>
                            <option value="Inactive" {{ request('status') == 'Inactive' ? 'selected' : '' }}>Inactif</option>
                            <option value="Left" {{ request('status') == 'Left' ? 'selected' : '' }}>Parti</option>
                            <option value="Suspended" {{ request('status') == 'Suspended' ? 'selected' : '' }}>Suspendue</option>
                        </select>
                    </div>
                    <!-- Filtre par genre -->
                    <div class="col-md-2">
                        <label for="gender" class="form-label">Genre</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">Tous</option>
                            <option value="Male" {{ request('gender') == 'Male' ? 'selected' : '' }}>Homme</option>
                            <option value="Female" {{ request('gender') == 'Female' ? 'selected' : '' }}>Femme</option>
                            <option value="Other" {{ request('gender') == 'Other' ? 'selected' : '' }}>Autre</option>
                        </select>
                    </div>

                    <!-- Nombre d'éléments par page -->
                    <div class="col-md-2">
                        <label for="limit" class="form-label">Par page</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="10" {{ request('limit') == '10' ? 'selected' : '' }}>10</option>
                            <option value="20" {{ request('limit') == '20' ? 'selected' : '' }}>20</option>
                            <option value="50" {{ request('limit') == '50' ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('limit') == '100' ? 'selected' : '' }}>100</option>
                        </select>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="{{ route('employees.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Carte de résultats -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Liste des Employés ({{ count($employees) }} résultat{{ count($employees) > 1 ? 's' : '' }})
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="employeesList">
                @include('employees.partials.employee-list', ['employees' => $employees])
            </div>
        </div>
    </div>
</div>

<style>
.table th {
    background-color: #f8f9fc;
    border-bottom: 2px solid #e3e6f0;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
}

.employee-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
    font-size: 0.9rem;
}

.badge {
    font-size: 0.75rem;
}

.btn-action {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.search-highlight {
    background-color: #fff3cd;
    padding: 0.1rem 0.2rem;
    border-radius: 0.2rem;
}

#employeesList {
    min-height: 200px;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const filterForm = document.getElementById('filterForm');
    const employeesList = document.getElementById('employeesList');
    const refreshBtn = document.getElementById('refreshBtn');
    
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performSearch();
        }, 500);
    });

    // Filtrage automatique au changement des sélecteurs
    document.querySelectorAll('#department, #status,#gender, #limit').forEach(function(element) {
        element.addEventListener('change', function() {
            performSearch();
        });
    });

    // Actualisation
    refreshBtn.addEventListener('click', function() {
        performSearch();
    });

    function performSearch() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        
        employeesList.classList.add('loading');
        
        fetch(`{{ route('employees.search') }}?${params.toString()}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                employeesList.innerHTML = data.html;
                updateResultsCount(data.employees.length);
            } else {
                showAlert('error', data.message || 'Erreur lors de la recherche');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('error', 'Erreur de connexion');
        })
        .finally(() => {
            employeesList.classList.remove('loading');
        });
    }

    function updateResultsCount(count) {
        const countElement = document.querySelector('.card-header h6');
        if (countElement) {
            countElement.textContent = `Liste des Employés (${count} résultat${count > 1 ? 's' : ''})`;
        }
    }

    function showAlert(type, message) {
        const alertClass = type === 'error' ? 'alert-danger' : 'alert-success';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const container = document.querySelector('.container-fluid');
        const existingAlert = container.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        container.insertAdjacentHTML('afterbegin', alertHtml);
    }
});


function confirmDelete(name, employeeName) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'employé "${employeeName}" ? Cette action est irréversible.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/employees/${name}`;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        
        form.appendChild(csrfToken);
        form.appendChild(methodInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection