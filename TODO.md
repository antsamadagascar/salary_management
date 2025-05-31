# Jour1 :
I-  Fonctionnalites :
1.Authentification login/logout compte erpnext
2.Documentation swagger complet des api erpenxt
3.Creation service erpnextService (pour l'appele des methode :get,update,getByID,post,delete) 
3.Multi Import donnees csv:
    -Traitement validation import sur le format de date:
        -retourne erreur si date inexistantes 
    -Import toutes ou rien :
        -check Validation ensemble
        -Si contient un seul erreur :auccun donnes ne s'insert
4.


## PROMPT :
creer moi un dashboard baser sur la theme de :gestions des empplyer et leurs salaires .Qui est un projet erpnext qui va consommer les api
du erpnext en python.
Creer juste un dahboard simple .Base sur ce que on peut attendre comme resulats(A note que le dashboard est une vue gloabale mais cette theme est jsute pour vous aider a savoir les attentes a venir):
"Lien via API uniquement entre NewApp -> ExistingApp

existingapp


newapp
mettre un login (compte erpnext)
Import Fichier CSV
Données
employé
element de salaire
etc…
Validation import sur le format de date (erreur si date inexistante)
Liste des employés avec filtre de recherche
Fiche employé avec ses salaires par mois
fiche de paie pour 1 mois avec export PDF soigné
Tableau avec filtre mois contenant les employés et les éléments de salaires et le total

Import : préparer l’import sur l’appli newapp (format à envoyer cet après midi)
".
En laravel crer cela dans dashoard/index :comme ceci ajouter layout :
{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app')

@section('content')

ET ici modifier creer les sidebar necessaire et organiser baser sur le theme:
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar à gauche -->
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
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('dashboard') }}">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('suppliers.index') }}">
                                <i class="fas fa-users"></i> Fournisseurs
                            </a>
                        </li>
                        <hr>
                    </ul>
                </div>
            </div>
        </nav>
    
    </div>
</div>