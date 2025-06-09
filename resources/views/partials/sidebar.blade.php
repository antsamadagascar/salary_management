<div class="container-fluid">
    <div class="row">
         <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar offcanvas offcanvas-start offcanvas-md" tabindex="-1" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="sidebarMenuLabel">ERP Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="sidebar-header text-center py-4">
                    <img src="{{ asset('logos/erpnext-logo.svg') }}" alt="Logo" class="img-fluid mb-2" style="max-height: 60px;">
                    <h5 class="mb-0">ERP Management</h5>
                </div>
                <hr>
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <!-- Dashboard Principal -->
                        <li class="nav-item">
                            <a class="nav-link active" href="{{ route('payroll.stats.index') }}">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        
                        <!-- Section Employés -->
                        <li class="nav-item">
                        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1rem;">
                            <span>GESTION EMPLOYÉS</span>
                        </h6>
                    </li>
                        <a class="nav-link {{ request()->routeIs('employees.index') ? 'active' : '' }}" href="{{ route('employees.index') }}">
                            <i class="fas fa-users me-2"></i>Liste des Employés
                        </a>
                        <!-- <a class="nav-link {{ request()->routeIs('employees.create') ? 'active' : '' }}" href="{{ route('employees.create') }}">
                            <i class="fas fa-user-plus me-2"></i>Ajouter Employé
                        </a>
                         -->
                            
                        <!-- Section Salaires -->
                        <li class="nav-item">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>GESTION SALAIRES</span>
                            </h6>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="{{ route('payroll.index') }}">
                                <i class="fas fa-money-bill-wave"></i> Éléments de Salaire
                            </a>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('payroll.index') }}">
                                <i class="fas fa-file-invoice-dollar"></i> Fiches Employés  
                            </a>
                        </li>
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-chart-bar"></i> Rapport Mensuel
                            </a>
                        </li> -->
                        <li class="nav-item">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>Statistiques Salaire</span>
                            </h6>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('payroll.stats.salary-details') }}">
                            <i class="fas fa-chart-bar"></i> Month
                            </a>
                        </li>
                        <!-- Section Import/Export -->
                        <li class="nav-item">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>IMPORT/EXPORT</span>
                            </h6>
                        </li>

                        <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('import.*') ? 'active' : '' }}" 
                        href="{{ route('import.form') }}">
                                <i class="fas fa-file-csv"></i> Import CSV
                            </a>
                        </li> 
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="{{ route('reset-data.show') }}">
                             <i class="fas fa-trash-restore"></i> Reset Data

                            </a>
                        </li> -->
                        <!-- Section Configuration -->
                        <!-- <li class="nav-item">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>CONFIGURATION</span>
                            </h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-cog"></i> Paramètres ERPNext
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-history"></i> Logs API
                            </a>
                        </li> -->
                        
                        <hr>
                    </ul>
                </div>
            </div>
        </nav>
    
    </div>
</div>