@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Contenu principal -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Header avec bouton menu mobile -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard - Gestion Employés & Salaires</h1>
                <button class="btn btn-primary d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <!-- Statistiques principales -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Employés</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-employees">127</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Salaire Total (Mois)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">485 750 €</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-euro-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Fiches de Paie (Mois)</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">124</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Dernière Sync API</div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">Il y a 2h</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-sync fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Actions Rapides</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <button class="btn btn-primary w-100" onclick="syncWithERPNext()">
                                        <i class="fas fa-sync"></i> Synchroniser ERPNext
                                    </button>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="#" class="btn btn-success w-100">
                                        <i class="fas fa-file-csv"></i> Importer CSV
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="#" class="btn btn-info w-100">
                                        <i class="fas fa-file-invoice"></i> Générer Fiches de Paie
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="#" class="btn btn-warning w-100">
                                        <i class="fas fa-file-pdf"></i> Export Rapport PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau récapitulatif des employés -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-users"></i> Employés Récents</h5>
                            <div>
                                <input type="text" class="form-control form-control-sm" id="searchEmployees" placeholder="Rechercher..." style="width: 200px; display: inline-block;">
                                <a href="#" class="btn btn-sm btn-primary ms-2">Voir tous</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="employeesTable">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Département</th>
                                            <th>Poste</th>
                                            <th>Salaire de Base</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <span class="avatar-title rounded-circle bg-primary">
                                                            JD
                                                        </span>
                                                    </div>
                                                    Jean Dupont
                                                </div>
                                            </td>
                                            <td>Informatique</td>
                                            <td>Développeur Senior</td>
                                            <td>4 500 €</td>
                                            <td>
                                                <span class="badge bg-success">
                                                    Actif
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <span class="avatar-title rounded-circle bg-success">
                                                            ML
                                                        </span>
                                                    </div>
                                                    Marie Lefebvre
                                                </div>
                                            </td>
                                            <td>RH</td>
                                            <td>Responsable RH</td>
                                            <td>3 800 €</td>
                                            <td>
                                                <span class="badge bg-success">
                                                    Actif
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <span class="avatar-title rounded-circle bg-info">
                                                            PM
                                                        </span>
                                                    </div>
                                                    Pierre Martin
                                                </div>
                                            </td>
                                            <td>Ventes</td>
                                            <td>Commercial</td>
                                            <td>2 900 €</td>
                                            <td>
                                                <span class="badge bg-success">
                                                    Actif
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <span class="avatar-title rounded-circle bg-warning">
                                                            SB
                                                        </span>
                                                    </div>
                                                    Sophie Bernard
                                                </div>
                                            </td>
                                            <td>Marketing</td>
                                            <td>Chef de Projet</td>
                                            <td>3 200 €</td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    Congé
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <span class="avatar-title rounded-circle bg-danger">
                                                            AL
                                                        </span>
                                                    </div>
                                                    Antoine Leroy
                                                </div>
                                            </td>
                                            <td>Comptabilité</td>
                                            <td>Comptable</td>
                                            <td>2 700 €</td>
                                            <td>
                                                <span class="badge bg-success">
                                                    Actif
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique des salaires -->
            <div class="row">
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> Évolution des Salaires (6 derniers mois)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salaryChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-tasks"></i> État de Synchronisation</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-sm font-weight-bold">Employés</span>
                                    <span class="text-sm">95%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 95%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-sm font-weight-bold">Éléments Salaire</span>
                                    <span class="text-sm">87%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: 87%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-sm font-weight-bold">Fiches de Paie</span>
                                    <span class="text-sm">92%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: 92%"></div>
                                </div>
                            </div>
                            
                            <button class="btn btn-primary btn-sm w-100 mt-3" onclick="refreshSyncStatus()">
                                <i class="fas fa-refresh"></i> Actualiser
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Fonction de recherche dans le tableau
document.getElementById('searchEmployees').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const tableRows = document.querySelectorAll('#employeesTable tbody tr');
    
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Graphique des salaires
const ctx = document.getElementById('salaryChart').getContext('2d');
const salaryChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Nov 2024', 'Déc 2024', 'Jan 2025', 'Fév 2025', 'Mar 2025', 'Avr 2025'],
        datasets: [{
            label: 'Total Salaires',
            data: [420000, 435000, 448000, 465000, 475000, 485750],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' €';
                    }
                }
            }
        }
    }
});

// Fonctions AJAX
function syncWithERPNext() {
    const btn = event.target;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Synchronisation...';
    btn.disabled = true;
    
    fetch('', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la synchronisation: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erreur réseau: ' + error);
    })
    .finally(() => {
        btn.innerHTML = '<i class="fas fa-sync"></i> Synchroniser ERPNext';
        btn.disabled = false;
    });
}

function refreshSyncStatus() {
    fetch('')
    .then(response => response.json())
    .then(data => {
        document.querySelectorAll('.progress-bar').forEach((bar, index) => {
            const percentages = [data.employees, data.salary_elements, data.payslips];
            bar.style.width = percentages[index] || '0%';
            bar.parentElement.previousElementSibling.querySelector('.text-sm:last-child').textContent = percentages[index] || '0%';
        });
    });
}

// Auto-refresh des statistiques toutes les 5 minutes
setInterval(() => {
    fetch('')
    .then(response => response.json())
    .then(data => {
        document.getElementById('total-employees').textContent = data.total_employees || 0;
        // Mettre à jour autres statistiques...
    });
}, 300000);
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.avatar {
    width: 2rem;
    height: 2rem;
}

.avatar-title {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
}

.sidebar-heading {
    font-size: 0.75rem;
    text-transform: uppercase;
}

.nav-link {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    margin-bottom: 0.125rem;
}

.nav-link:hover {
    background-color: rgba(0,0,0,0.075);
}

.nav-link.active {
    background-color: #0d6efd;
    color: white;
}
</style>
@endsection