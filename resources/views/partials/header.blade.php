<header class="navbar navbar-dark sticky-top bg-primary flex-md-nowrap p-0 shadow custom-header">
    <!-- Bouton de bascule pour la sidebar à gauche -->
    <button class="navbar-toggler d-md-none collapsed" type="button" 
            data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" 
            aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Conteneur pour les éléments alignés à droite -->
    <div class="navbar-nav ms-auto d-flex flex-row align-items-center">
        <!-- Sélecteur de thème -->
        <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle px-3" href="#" id="themeDropdown" role="button" 
               data-bs-toggle="dropdown" aria-expanded="false">Thème
                <i class="fas fa-palette"></i>
            </a>
            <ul class="dropdown-menu" aria-labelledby="themeDropdown">
                <li><a class="dropdown-item" href="#" onclick="changeTheme('default')">Thème par défaut</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeTheme('light')">Thème clair</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeTheme('blue')">Thème bleu</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeTheme('green')">Thème vert</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeTheme('violet')">Thème violet</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeTheme('red')">Thème rouge</a></li>
            </ul>
        </div>

        <div class="nav-item dropdown">
        <a class="nav-link dropdown-toggle px-3" href="#" id="accountDropdown" role="button" 
               data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user"></i> {{ $userName }}
            </a>    
            <ul class="dropdown-menu" aria-labelledby="accountDropdown">
                <li><a class="dropdown-item" href="#">Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <form method="GET" action="{{ route('logout') }}">
                    @csrf
                    <li><button type="submit" class="dropdown-item">Déconnexion</button></li>
                </form>
            </ul>
        </div>
    </div>
</header>