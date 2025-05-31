// Script pour gérer le changement de thème et la sidebar
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser le thème
    initTheme();
    
    // Gérer l'affichage correct de l'offcanvas sur mobile
    setupMobileNavigation();
});

// Fonction pour changer de thème
function changeTheme(themeName) {
    // Supprimer tous les attributs data-theme précédents
    document.body.removeAttribute('data-theme');
    
    // Appliquer le nouveau thème sauf s'il s'agit du thème par défaut
    if (themeName !== 'default') {
        document.body.setAttribute('data-theme', themeName);
    }
    
    // Enregistrer la préférence dans le localStorage
    localStorage.setItem('theme', themeName);
    
    // Mettre à jour la couleur de fond de la sidebar
    updateSidebarColors();
}

// Fonction pour initialiser le thème au chargement de la page
function initTheme() {
    // Récupérer le thème enregistré ou utiliser le thème par défaut
    const savedTheme = localStorage.getItem('theme') || 'default';
    changeTheme(savedTheme);
}

// Fonction pour mettre à jour les couleurs de la sidebar en fonction du thème
function updateSidebarColors() {
    // Cette fonction est appelée après le changement de thème
    // Les variables CSS gèrent déjà les couleurs, mais on peut ajouter des ajustements spécifiques ici
    console.log('Sidebar colors updated');
}

// Configuraton pour la navigation mobile
function setupMobileNavigation() {
    // S'assurer que l'offcanvas fonctionne correctement sur mobile
    const sidebarMenu = document.getElementById('sidebarMenu');
    
    if (sidebarMenu) {
        // Utiliser l'API Bootstrap pour l'offcanvas
        const offcanvas = new bootstrap.Offcanvas(sidebarMenu);
        
        // Fermer l'offcanvas quand on clique sur un lien (en mode mobile)
        const navLinks = sidebarMenu.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    offcanvas.hide();
                }
            });
        });
    }
    
    // Ajuster le comportement en cas de redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            // S'assurer que l'offcanvas est visible en mode desktop
            const sidebarMenu = document.getElementById('sidebarMenu');
            if (sidebarMenu && sidebarMenu.classList.contains('show') === false && window.innerWidth >= 768) {
                sidebarMenu.classList.add('show');
            }
        }
    });
}