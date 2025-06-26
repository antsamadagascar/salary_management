@extends('layouts.app')

@section('title', 'Résultats du filtrage des salaires')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm p-4">
        <h2 class="fs-4 fw-bold mb-4">Résultats du filtrage des salaires</h2>

        @if(empty($salaries))
            <div class="alert alert-warning">Aucun salaire trouvé pour les critères spécifiés.</div>
        @else
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>Structure salariale</th>
                        <th>Composant</th>
                        <th>Montant</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($salaries as $s)
                        <tr>
                            <td>{{ $s['employee'] }}</td>
                            <td>{{ $s['employee_name'] }}</td>
                            <td>{{ $s['salary_structure'] }}</td>
                            <td>{{ $s['salary_component'] }}</td>
                            <td>{{ number_format($s['amount'], 2, '.', ' ') }}</td>
                            <td>{{ \Carbon\Carbon::parse($s['posting_date'])->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
