@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <main class="col-md-12 ms-sm-auto px-md-4">
            <!-- En-tête -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Tableau de bord</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refresh-data">
                        <i class="fas fa-sync-alt"></i> Rafraîchir
                    </button>
                </div>
            </div>

            <!-- Tableau amélioré -->
            <div class="card mb-4">
                <div class="card-header">Liste des Tickets</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="ticketsTable" class="table table-striped table-hover table-stackable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Priorité</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td data-label="ID">{{ $ticket['id'] }}</td>
                                        <td data-label="Titre">{{ $ticket['title'] }}</td>
                                        <td data-label="Priorité">
                                            <span class="badge" style="background-color: {{ $ticket['priority_color'] }}">
                                                {{ $ticket['priority'] }}
                                            </span>
                                        </td>
                                        <td data-label="Statut">
                                            <span class="badge" style="background-color: {{ $ticket['status_color'] }}">
                                                {{ $ticket['status'] }}
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <button class="btn btn-sm btn-outline-primary me-2">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal{{ $ticket['id'] }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal de suppression spécifique -->
                                    <div class="modal fade" 
                                         id="deleteModal{{ $ticket['id'] }}" 
                                         tabindex="-1" 
                                         aria-labelledby="deleteModalLabel{{ $ticket['id'] }}" 
                                         aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel{{ $ticket['id'] }}">
                                                        Confirmer la suppression
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Êtes-vous sûr de vouloir supprimer le ticket "{{ $ticket['title'] }}" ?
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                    <form action="{{ route('tickets.destroy', $ticket['id']) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger">Supprimer</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#ticketsTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                'copy',
                {
                    extend: 'csv',
                    text: 'Exporter en CSV',
                    title: 'Liste des Tickets'
                },
                {
                    extend: 'pdf',
                    text: 'Exporter en PDF',
                    title: 'Liste des Tickets',
                    customize: function(doc) {
                        doc.content[1].table.widths = ['10%', '40%', '20%', '20%', '10%'];
                    }
                }
            ],
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            }
        });
    });
</script>

@endsection

