<?php

namespace App\Services\employee;

use App\Services\api\ErpApiService;
use Exception;

class EmployeeService
{
    private ErpApiService $erpApiService;

    public function __construct(ErpApiService $erpApiService)
    {
        $this->erpApiService = $erpApiService;
    }

    public function getEmployees(array $filters = []): array
    {
        try {
            $params = $this->buildBasicParams($filters);
            $allEmployees = $this->erpApiService->getResource('Employee', $params);
  
            return $this->applyClientSideFilters($allEmployees, $filters);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des employés: " . $e->getMessage());
        }
    }

    public function getEmployeeByName(string $name): ?array
    {
        try {
            return $this->erpApiService->getResourceByName('Employee', $name);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération de l'employé: " . $e->getMessage());
        }
    }

    public function createEmployee(array $data): bool
    {
        try {
            return $this->erpApiService->createResource('Employee', $data);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la création de l'employé: " . $e->getMessage());
        }
    }

    public function updateEmployee(string $name, array $data): bool
    {
        try {
            return $this->erpApiService->updateResource("Employee/{$name}", $data);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la mise à jour de l'employé: " . $e->getMessage());
        }
    }

    public function deleteEmployee(string $name): bool
    {
        try {
            return $this->erpApiService->deleteResource("Employee/{$name}");
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la suppression de l'employé: " . $e->getMessage());
        }
    }

 
    public function searchEmployees(string $searchTerm, array $additionalFilters = []): array
    {
        try {
            $filters = array_merge(['search' => $searchTerm], $additionalFilters);
            return $this->getEmployees($filters);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la recherche d'employés: " . $e->getMessage());
        }
    }

    public function getEmployeesByDepartment(string $department): array
    {
        try {
            return $this->getEmployees(['department' => $department]);
        } catch (Exception $e) {
            throw new Exception("Erreur lors du filtrage par département: " . $e->getMessage());
        }
    }


    public function getEmployeesByStatus(string $status): array
    {
        try {
            return $this->getEmployees(['status' => $status]);
        } catch (Exception $e) {
            throw new Exception("Erreur lors du filtrage par statut: " . $e->getMessage());
        }
    }

    public function getDepartments(): array
    {
        try {
            return $this->erpApiService->getResource('Department');
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des départements: " . $e->getMessage());
        }
    }

    private function buildBasicParams(array $filters): array
    {
        $params = [
            'fields' => json_encode([
                'name',
                'employee_name',
                'first_name',
                'last_name',
                'employee_number',
                'department',
                'designation',
                'status',
                'date_of_joining',
                'company',
                'gender',
                'cell_number',
                'personal_email'
            ]),
            'limit_page_length' => $filters['limit'] ?? 100, 
        ];

        if (!empty($filters['status']) && in_array($filters['status'], ['Active', 'Inactive', 'Left'])) {
            $params['filters'] = json_encode([['status', '=', $filters['status']]]);
        }

        if (!empty($filters['order_by'])) {
            $params['order_by'] = $filters['order_by'];
        }

        return $params;
    }

    private function applyClientSideFilters(array $employees, array $filters): array
    {
        if (empty($employees)) {
            return [];
        }

        $filtered = $employees;

        // Filtre de recherche (nom, prénom, numéro d'employé)
        if (!empty($filters['search'])) {
            $searchTerm = strtolower(trim($filters['search']));
            $filtered = array_filter($filtered, function ($employee) use ($searchTerm) {
                $searchableFields = [
                    $employee['employee_name'] ?? '',
                    $employee['first_name'] ?? '',
                    $employee['last_name'] ?? '',
                    $employee['employee_number'] ?? '',
                ];
                
                foreach ($searchableFields as $field) {
                    if (stripos($field, $searchTerm) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Filtre par département
        if (!empty($filters['department'])) {
            $filtered = array_filter($filtered, function ($employee) use ($filters) {
                return ($employee['department'] ?? '') === $filters['department'];
            });
        }

        // Filtre par poste/désignation
        if (!empty($filters['designation'])) {
            $filtered = array_filter($filtered, function ($employee) use ($filters) {
                return stripos($employee['designation'] ?? '', $filters['designation']) !== false;
            });
        }

        // Filtre par sexe
        if (!empty($filters['gender'])) {
            $filtered = array_filter($filtered, function ($employee) use ($filters) {
                return ($employee['gender'] ?? '') === $filters['gender'];
            });
        }

        // Filtre par statut (si pas déjà appliqué côté serveur)
        if (!empty($filters['status']) && !isset($params['filters'])) {
            $filtered = array_filter($filtered, function ($employee) use ($filters) {
                return ($employee['status'] ?? 'Active') === $filters['status'];
            });
        }

        // Tri
        if (!empty($filters['order_by'])) {
            $this->sortEmployees($filtered, $filters['order_by']);
        }

        // Pagination côté client
        if (!empty($filters['page']) && !empty($filters['limit'])) {
            $page = (int) $filters['page'];
            $limit = (int) $filters['limit'];
            $offset = ($page - 1) * $limit;
            $filtered = array_slice($filtered, $offset, $limit);
        }

        return array_values($filtered); 
    }

    private function sortEmployees(array &$employees, string $orderBy): void
    {
        $parts = explode(' ', trim($orderBy));
        $field = $parts[0] ?? 'employee_name';
        $direction = strtolower($parts[1] ?? 'asc');

        usort($employees, function ($a, $b) use ($field, $direction) {
            $valueA = $a[$field] ?? '';
            $valueB = $b[$field] ?? '';
            
            $comparison = strcasecmp($valueA, $valueB);
            
            return $direction === 'desc' ? -$comparison : $comparison;
        });
    }

    public function getEmployeeStats(): array
    {
        try {
            $allEmployees = $this->getEmployees();
            
            $stats = [
                'total' => count($allEmployees),
                'active' => 0,
                'inactive' => 0,
                'left' => 0,
                'Suspended'=>0,
                'by_department' => [],
                'by_gender' => ['Male' => 0, 'Female' => 0, 'Other' => 0],
            ];

            foreach ($allEmployees as $employee) {

                $status = $employee['status'] ?? 'Active';
                switch ($status) {
                    case 'Active':
                        $stats['active']++;
                        break;
                    case 'Inactive':
                        $stats['inactive']++;
                        break;
                    case 'Left':
                        $stats['left']++;
                        break;
                    case 'Suspended':
                            $stats['Suspended']++;
                            break;
                }

                // Compter par département
                $department = $employee['department'] ?? 'Non défini';
                $stats['by_department'][$department] = ($stats['by_department'][$department] ?? 0) + 1;

                // Compter par sexe
                $gender = $employee['gender'] ?? 'Other';
                if (isset($stats['by_gender'][$gender])) {
                    $stats['by_gender'][$gender]++;
                }
            }

            return $stats;
        } catch (Exception $e) {
            throw new Exception("Erreur lors du calcul des statistiques: " . $e->getMessage());
        }
    }

    /**
     * Récupére un employé par son ID
     */
    public function getEmployeeByID(string $employeeId): ?array
    {
        try {
            return $this->erpApiService->getResourceByName('Employee', $employeeId);
        } catch (Exception $e) {
            return null;
        }
    }

}