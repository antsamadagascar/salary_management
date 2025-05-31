
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <main class="col-md-12 ms-sm-auto px-md-4">
            <!-- Formulaire personnalisé -->
            <div class="card mb-4">
                <div class="card-header">Ajouter un Nouveau Ticket</div>
                <div class="card-body">
                    <form action="#" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="ticketTitle" class="form-label">Titre du Ticket</label>
                            <input type="text" class="form-control" id="ticketTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="ticketDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="ticketDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="ticketPriority" class="form-label">Priorité</label>
                            <select class="form-select" id="ticketPriority" name="priority">
                                <option value="low">Faible</option>
                                <option value="medium">Moyenne</option>
                                <option value="high">Haute</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Soumettre</button>
                    </form>
                </div>
        </div>
</div>
@endsection