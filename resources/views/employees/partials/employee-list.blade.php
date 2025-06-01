@if(count($employees) > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="15%">Employé</th>
                    <th width="15%">Nom Complet</th>
                    <th width="12%">N° Employé</th>
                    <th width="15%">Département</th>
                    <th width="12%">Poste</th>
                    <th width="10%">Statut</th>
                    <th width="8%">Contact</th>
                    <th width="8%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($employees as $index => $employee)
                    <tr>
                        <!-- Avatar et nom -->
                        <td>
                            <div class="employee-avatar" style="background-color: {{ sprintf('#%06X', mt_rand(0, 0xFFFFFF)) }}">
                                {{ strtoupper(substr($employee['first_name'] ?? '', 0, 1) . substr($employee['last_name'] ?? '', 0, 1)) }}
                            </div>
                        </td>
                        
                        <!-- Nom d'affichage -->
                        <td>
                            <div class="fw-bold text-primary">
                                {{ $employee['employee_name'] ?? 'N/A' }}
                            </div>
                            @if(!empty($employee['personal_email']))
                                <small class="text-muted">
                                    <i class="fas fa-envelope me-1"></i>{{ $employee['personal_email'] }}
                                </small>
                            @endif
                        </td>
                        
                        <!-- Nom complet -->
                        <td>
                            <div class="fw-medium">
                                {{ ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '') }}
                            </div>
                            @if(!empty($employee['gender']))
                                <small class="text-muted">
                                    <i class="fas fa-{{ $employee['gender'] === 'Male' ? 'mars' : ($employee['gender'] === 'Female' ? 'venus' : 'genderless') }} me-1"></i>
                                    {{ $employee['gender'] }}
                                </small>
                            @endif
                        </td>
                        
                        <!-- Numéro d'employé -->
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $employee['employee_number'] ?? 'N/A' }}
                            </span>
                        </td>
                        
                        <!-- Département -->
                        <td>
                            @if(!empty($employee['department']))
                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-building me-1"></i>{{ $employee['department'] }}
                                </span>
                            @else
                                <span class="text-muted">Non défini</span>
                            @endif
                        </td>
                        
                        <!-- Poste/Désignation -->
                        <td>
                            @if(!empty($employee['designation']))
                                <div class="fw-medium">{{ $employee['designation'] }}</div>
                            @else
                                <span class="text-muted">Non défini</span>
                            @endif
                            @if(!empty($employee['date_of_joining']))
                                <small class="text-muted d-block">
                                    <i class="fas fa-calendar me-1"></i>
                                    Depuis {{ \Carbon\Carbon::parse($employee['date_of_joining'])->format('M Y') }}
                                </small>
                            @endif
                        </td>
                        
                        <!-- Statut -->
                        <td>
                            @php
                                $status = $employee['status'] ?? 'Active';
                                $statusClass = match($status) {
                                    'Active' => 'bg-success',
                                    'Inactive' => 'bg-warning',
                                    'Left' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                                $statusIcon = match($status) {
                                    'Active' => 'fa-check-circle',
                                    'Inactive' => 'fa-pause-circle',
                                    'Left' => 'fa-times-circle',
                                    default => 'fa-question-circle'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                <i class="fas {{ $statusIcon }} me-1"></i>
                                {{ $status === 'Active' ? 'Actif' : ($status === 'Inactive' ? 'Inactif' : ($status === 'Left' ? 'Parti' : $status)) }}
                            </span>
                        </td>
                        
                        <!-- Contact -->
                        <td>
                            @if(!empty($employee['cell_number']))
                                <a href="tel:{{ $employee['cell_number'] }}" 
                                   class="btn btn-sm btn-outline-primary btn-action mb-1 d-block" 
                                   title="Appeler">
                                    <i class="fas fa-phone me-1"></i>
                                    <small>{{ $employee['cell_number'] }}</small>
                                </a>
                            @endif
                            @if(!empty($employee['personal_email']))
                                <a href="mailto:{{ $employee['personal_email'] }}" 
                                   class="btn btn-sm btn-outline-info btn-action d-block" 
                                   title="Envoyer un email">
                                    <i class="fas fa-envelope me-1"></i>
                                    <small>Email</small>
                                </a>
                            @endif
                            @if(empty($employee['cell_number']) && empty($employee['personal_email']))
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        
                        <!-- Actions -->
                        <td>
                            <div class="btn-group-vertical" role="group">
                                <a href="{{ route('employees.show', $employee['name']) }}" 
                                   class="btn btn-sm btn-outline-info btn-action mb-1" 
                                   title="Voir les détails">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <!-- <a href="{{ route('employees.edit', $employee['name']) }}" 
                                   class="btn btn-sm btn-outline-warning btn-action mb-1" 
                                   title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger btn-action" 
                                        onclick="confirmDelete('{{ $employee['name'] }}', '{{ $employee['employee_name'] ?? 'Cet employé' }}')"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button> -->
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <!-- État vide -->
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="fas fa-users text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
        </div>
        <h5 class="text-muted mb-3">Aucun employé trouvé</h5>
        <p class="text-muted mb-4">
            @if(request()->hasAny(['search', 'department', 'status']))
                Aucun employé ne correspond aux critères de recherche.
                <br>Essayez de modifier vos filtres ou 
                <a href="{{ route('employees.index') }}" class="text-primary">supprimez tous les filtres</a>.
            @else
                Il n'y a actuellement aucun employé enregistré dans le système.
            @endif
        </p>
        @if(!request()->hasAny(['search', 'department', 'status']))
            <a href="{{ route('employees.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Ajouter le premier employé
            </a>
        @endif
    </div>
@endif