<!DOCTYPE html>
<html>
<head>
    <title>Generate Salary</title>
</head>
<body>
    @extends('layouts.app')

    @section('content')
        <div class="container">
            <div class="card">
                <div class="card-body">
                    <h2>Generate Salary</h2>

                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Tableau -->
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Salaire</th>
                                <th>Date Début</th>
                                <th>Date Fin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $emp)
                                <tr>
                                    <form action="{{ route('generate.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="employee_name" value="{{ $emp['name'] }}">
                                        <td>{{ $emp['employee_name'] ?? 'N/A' }}</td>
                                        <td>
                                            <input type="number" name="salary" class="form-control" step="0.01" min="0" required>
                                            @error('salary')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="month" name="date_debut" class="form-control" required>
                                            @error('date_debut')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="month" name="date_fin" class="form-control" required>
                                            @error('date_fin')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </td>
                                        <td>
                                            <button type="submit" class="btn btn-sm btn-primary">Generate Salary</button>
                                        </td>
                                    </form>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">Aucun employé trouvé</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endsection
</body>
</html>