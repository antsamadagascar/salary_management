@extends('layouts.app')

@section('title', 'Réinitialisation des Données')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            Réinitialisation des Données - ATTENTION
                        </h3>
                    </div>
                    
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-warning"></i> AVERTISSEMENT CRITIQUE</h5>
                            <p>Cette action va <strong>SUPPRIMER DÉFINITIVEMENT</strong> toutes les données suivantes :</p>
                        </div>

                        <!-- Affichage des données existantes -->
                        <div class="mb-4">
                            <h5>Données actuelles dans la base :</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Table</th>
                                            <th>Nombre d'enregistrements</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($existingData as $table => $count)
                                        <tr class="{{ $count > 0 ? 'table-warning' : '' }}">
                                            <td>{{ $table }}</td>
                                            <td>
                                                <span class="badge {{ $count > 0 ? 'bg-warning' : 'bg-success' }}">
                                                    {{ $count }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if($hasData)
                        <!-- Formulaire de confirmation -->
                        <form id="resetForm">
                             @csrf
                            <!-- <div class="mb-3">
                                <label class="form-label fw-bold">
                                    Pour confirmer la suppression, tapez exactement : 
                                    <code>CONFIRMER_SUPPRESSION</code>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="confirmationInput" 
                                       name="confirmation"
                                       placeholder="Tapez ici pour confirmer..."
                                       required>
                                <div class="invalid-feedback" id="confirmationError"></div>
                            </div>  -->

                            <div class="d-grid gap-2">
                                <!-- <button type="submit" 
                                        class="btn btn-danger btn-lg" 
                                        id="resetBtn" 
                                        disabled>
                                    <i class="fas fa-trash"></i>
                                    SUPPRIMER TOUTES LES DONNÉES
                                </button> -->
                                <button type="submit" 
                                        class="btn btn-danger btn-lg" 
                                        id="resetBtn" 
                                        >
                                    <i class="fas fa-trash"></i>
                                    SUPPRIMER TOUTES LES DONNÉES
                                </button>
                                <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    Annuler et retourner au tableau de bord
                                </a>
                            </div>
                        </form>
                        @else
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Aucune donnée à supprimer</h5>
                            <p>Il n'y a actuellement aucune donnée dans les tables concernées.</p>
                            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                                Retourner au tableau de bord
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Résultats -->
                <div id="results" class="mt-4" style="display: none;"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const confirmationInput = document.getElementById('confirmationInput');
            const resetBtn = document.getElementById('resetBtn');
            const resetForm = document.getElementById('resetForm');
            const resultsDiv = document.getElementById('results');

            // Fonction pour obtenir le token CSRF
            function getCsrfToken() {
                const metaToken = document.querySelector('meta[name="csrf-token"]');
                if (metaToken) {
                    return metaToken.getAttribute('content');
                }
                
                const csrfInput = document.querySelector('input[name="_token"]');
                if (csrfInput) {
                    return csrfInput.value;
                }
                
                if (typeof window.Laravel !== 'undefined' && window.Laravel.csrfToken) {
                    return window.Laravel.csrfToken;
                }
                
                console.error('Token CSRF introuvable');
                return null;
            }

            // Active/désactive le bouton selon la saisie (seulement si l'input existe)
            if (confirmationInput) {
                confirmationInput.addEventListener('input', function() {
                    const isValid = this.value === 'CONFIRMER_SUPPRESSION';
                    resetBtn.disabled = !isValid;
                    
                    if (this.value.length > 0 && !isValid) {
                        this.classList.add('is-invalid');
                        document.getElementById('confirmationError').textContent = 
                            'Le texte de confirmation ne correspond pas.';
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }

            // Gestion du formulaire
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Vérifie  la confirmation seulement si l'input existe
                    if (confirmationInput && confirmationInput.value !== 'CONFIRMER_SUPPRESSION') {
                        alert('Veuillez confirmer la suppression correctement.');
                        return;
                    }

                    // Confirmation finale
                    if (!confirm('DERNIÈRE CHANCE : Êtes-vous absolument certain de vouloir supprimer toutes ces données ? Cette action est IRRÉVERSIBLE.')) {
                        return;
                    }

                    const csrfToken = getCsrfToken();
                    if (!csrfToken) {
                        alert('Erreur: Token CSRF introuvable. Veuillez recharger la page.');
                        return;
                    }

                    resetBtn.disabled = true;
                    resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression en cours...';

                    // Prépare les données pour l'envoi
                    const formData = new FormData();
                    formData.append('_token', csrfToken);
                    // Ajoute de confirmation seulement si l'input existe
                    if (confirmationInput) {
                        formData.append('confirmation', confirmationInput.value);
                    } else {
                        // Si pas d'input de confirmation, envoyer une valeur par défaut
                        formData.append('confirmation', 'CONFIRMER_SUPPRESSION');
                    }

                    // Envoi de la requête avec FormData
                    fetch('{{ route("reset-data.all") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur HTTP: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        resultsDiv.style.display = 'block';
                        
                        if (data.success) {
                            resultsDiv.innerHTML = `
                                <div class="alert alert-success">
                                    <h5><i class="fas fa-check-circle"></i> Suppression réussie</h5>
                                    <p>${data.message}</p>
                                    <details>
                                        <summary>Détails des suppressions</summary>
                                        <pre>${JSON.stringify(data.deleted_records, null, 2)}</pre>
                                    </details>
                                </div>
                            `;
                            
                            // Masque le formulaire
                            resetForm.style.display = 'none';
                        } else {
                            resultsDiv.innerHTML = `
                                <div class="alert alert-danger">
                                    <h5><i class="fas fa-exclamation-circle"></i> Erreur</h5>
                                    <p>${data.message}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        resultsDiv.style.display = 'block';
                        resultsDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-circle"></i> Erreur réseau</h5>
                                <p>Une erreur s'est produite lors de la suppression: ${error.message}</p>
                            </div>
                        `;
                    })
                    .finally(() => {
                        resetBtn.disabled = false;
                        resetBtn.innerHTML = '<i class="fas fa-trash"></i> SUPPRIMER TOUTES LES DONNÉES';
                    });
                });
            }
        });
    </script>
@endSection