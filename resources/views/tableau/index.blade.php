@extends('layouts.app')
@section('content')
<div class="container">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>Liste Employés</span>
            <a href="{{ route('dashboard.formulaire') }}" class="btn btn-success btn-sm">Nouveau</a>
        </div>
        <div class="card-body">
            
            <!-- Recherche -->
            <form method="GET" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Rechercher..." value="{{ $search }}">
                    <button class="btn btn-outline-secondary">Chercher</button>
                </div>
            </form>

            <!-- Tableau -->
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Département</th>
                        <th>Salaire</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                        <tr>
                            <td>{{ $emp['employee_name'] ?? 'N/A' }}</td>
                            <td>{{ $emp['email'] ?? 'N/A' }}</td>
                            <td>{{ $emp['department'] ?? '-' }}</td>
                            <td>{{ isset($emp['salary']) ? number_format($emp['salary'], 2) . ' €' : '-' }}</td>
                            <td>{{ isset($emp['date_embauche']) ? date('d/m/Y', strtotime($emp['date_embauche'])) : '-' }}</td>
                            <td>
                                <a href="#" class="btn btn-sm btn-warning">Modifier</a>
                                <button class="btn btn-sm btn-danger">Supprimer</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Aucun employé trouvé</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
