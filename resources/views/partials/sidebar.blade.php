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
                            <a class="nav-link active" href="{{ route('dashboard') }}">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        
                        <!-- Section Employés -->
                        <li class="nav-item">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>GESTION EMPLOYÉS</span>
                            </h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-users"></i> Liste des Employés
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-user-plus"></i> Ajouter Employé
                            </a>
                        </li>
                        
                        <!-- Section Salaires -->
                        <li class="nav-item">
                            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                                <span>GESTION SALAIRES</span>
                            </h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-money-bill-wave"></i> Éléments de Salaire
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-file-invoice-dollar"></i> Fiches de Paie
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-chart-bar"></i> Rapport Mensuel
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
                        <li class="nav-item">
                            <a class="nav-link" href="">
                                <i class="fas fa-file-export"></i> Export Rapports
                            </a>
                        </li>
                        
                        <!-- Section Configuration -->
                        <li class="nav-item">
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
                        </li>
                        
                        <hr>
                    </ul>
                </div>
            </div>
        </nav>
    
    </div>
</div>