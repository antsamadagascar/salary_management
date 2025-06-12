@extends('layouts.app')

@section('title', 'Génération des Salaires')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Génération Automatique des Salaires</h1>
        
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <form id="generateSalaryForm" class="space-y-6">
            @csrf
            
            <!-- Sélection de l'employé -->
            <div>
                <label for="employe_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Employé <span class="text-red-500">*</span>
                </label>
                <select id="employe_id" name="employe_id" required 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Sélectionner un employé</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee['name'] }}">
                            {{ $employee['employee_name'] }} ({{ $employee['employee_number'] ?? $employee['name'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-2">
                        Date de début <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="date_debut" name="date_debut" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="date_fin" class="block text-sm font-medium text-gray-700 mb-2">
                        Date de fin <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="date_fin" name="date_fin" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Salaire de base -->
            <div>
                <label for="salaire_base" class="block text-sm font-medium text-gray-700 mb-2">
                    Salaire de base (utilisé si aucun salaire antérieur) <span class="text-red-500">*</span>
                </label>
                <input type="number" id="salaire_base" name="salaire_base" step="0.01" min="0" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Ex: 50000.00">
            </div>

            <!-- Boutons -->
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="resetForm()" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Réinitialiser
                </button>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50"
                        id="submitBtn">
                    <span id="btnText">Générer les Salaires</span>
                    <span id="btnLoader" class="hidden">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Génération...
                    </span>
                </button>
            </div>
        </form>

        <!-- Zone de résultats -->
        <div id="results" class="mt-8 hidden">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Résultats de la génération</h3>
            <div id="resultsContent" class="space-y-2"></div>
        </div>
    </div>
</div>

<!-- Modal de confirmation -->
<div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Confirmer la génération</h3>
        <p id="confirmMessage" class="text-gray-600 mb-6"></p>
        <div class="flex justify-end space-x-3">
            <button onclick="closeModal()" 
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                Annuler
            </button>
            <button onclick="confirmGeneration()" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Confirmer
            </button>
        </div>
    </div>
</div>

<script>
document.getElementById('generateSalaryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Afficher la confirmation
    const employeeName = document.getElementById('employe_id').selectedOptions[0].text;
    const dateDebut = new Date(data.date_debut).toLocaleDateString('fr-FR');
    const dateFin = new Date(data.date_fin).toLocaleDateString('fr-FR');
    
    document.getElementById('confirmMessage').textContent = 
        `Voulez-vous générer les salaires pour ${employeeName} du ${dateDebut} au ${dateFin} ?`;
    
    document.getElementById('confirmModal').classList.remove('hidden');
    document.getElementById('confirmModal').classList.add('flex');
});

function closeModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    document.getElementById('confirmModal').classList.remove('flex');
}

function confirmGeneration() {
    closeModal();
    
    const form = document.getElementById('generateSalaryForm');
    const formData = new FormData(form);
    
    // Afficher le loader
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoader = document.getElementById('btnLoader');
    
    submitBtn.disabled = true;
    btnText.classList.add('hidden');
    btnLoader.classList.remove('hidden');
    
    // Envoyer la requête
    fetch('{{ route("salaries.generate") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
        }
    })
    .then(response => response.json())
    .then(data => {
        displayResults(data);
    })
    .catch(error => {
        console.error('Erreur:', error);
        displayResults({
            success: false,
            message: 'Une erreur est survenue lors de la génération.'
        });
    })
    .finally(() => {
        // Masquer le loader
        submitBtn.disabled = false;
        btnText.classList.remove('hidden');
        btnLoader.classList.add('hidden');
    });
}

function displayResults(data) {
    const resultsDiv = document.getElementById('results');
    const resultsContent = document.getElementById('resultsContent');
    
    resultsContent.innerHTML = '';
    
    if (data.success) {
        resultsContent.innerHTML = `
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <strong>Succès :</strong> ${data.message}
            </div>
        `;
        
        if (data.data && data.data.length > 0) {
            const salariesHtml = data.data.map(salary => `
                <div class="bg-blue-50 border border-blue-200 p-3 rounded">
                    <strong>Période :</strong> ${new Date(salary.from_date).toLocaleDateString('fr-FR')} - ${new Date(salary.to_date).toLocaleDateString('fr-FR')}<br>
                    <strong>Montant :</strong> ${parseFloat(salary.base || 0).toLocaleString('fr-FR')} €<br>
                    <strong>Structure :</strong> ${salary.salary_structure || 'N/A'}
                </div>
            `).join('');
            
            resultsContent.innerHTML += `
                <h4 class="font-semibold mt-4 mb-2">Salaires générés :</h4>
                ${salariesHtml}
            `;
        }
    } else {
        resultsContent.innerHTML = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <strong>Erreur :</strong> ${data.message}
            </div>
        `;
    }
    
    resultsDiv.classList.remove('hidden');
}

function resetForm() {
    document.getElementById('generateSalaryForm').reset();
    document.getElementById('results').classList.add('hidden');
}

// Validation des dates
document.getElementById('date_fin').addEventListener('change', function() {
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = this.value;
    
    if (dateDebut && dateFin && new Date(dateFin) < new Date(dateDebut)) {
        alert('La date de fin doit être postérieure à la date de début.');
        this.value = '';
    }
});
</script>
@endsection