@extends('layouts.app')
@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">Ajouter Employé</div>
        <div class="card-body">
            
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form action="{{ route('dashboard.store') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="employee_name" class="form-control" 
                           value="{{ old('employee_name') }}" required>
                    @error('employee_name')<div class="text-danger">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" 
                           value="{{ old('email') }}" required>
                    @error('email')<div class="text-danger">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Salaire</label>
                    <input type="number" name="salary" class="form-control" 
                           value="{{ old('salary') }}" step="0.01">
                    @error('salary')<div class="text-danger">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Date Embauche</label>
                    <input type="date" name="date_embauche" class="form-control" 
                           value="{{ old('date_embauche') }}">
                    @error('date_embauche')<div class="text-danger">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Département</label>
                    <select name="department" class="form-control">
                        <option value="">-- Choisir --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept['id'] }}" {{ old('department') == $dept['id'] ? 'selected' : '' }}>
                                {{ $dept['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ route('dashboard.tableau') }}" class="btn btn-secondary">Voir Liste</a>
            </form>
        </div>
    </div>
</div>
@endsection 