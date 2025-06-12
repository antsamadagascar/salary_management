<?php

namespace App\Services;

use App\Services\api\ErpApiService;
use Exception;
use Carbon\Carbon;

class SalaryService extends BaseService
{
    protected $erpApiService;

    public function __construct(ErpApiService $erpApiService)
    {
        $this->erpApiService = $erpApiService;
    }

    /**
     * Récupère les salaires d'un employé pour une période donnée
     */
    public function getSalariesByEmployee(string $employeeId, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $filters = [
                'employee' => $employeeId,
                'from_date' => ['>=', $startDate->format('Y-m-d')],
                'to_date' => ['<=', $endDate->format('Y-m-d')]
            ];
            
            $params = $this->buildBasicParams($filters);
            return $this->erpApiService->getResource('Salary Structure Assignment', $params);
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des salaires: " . $e->getMessage());
        }
    }

    /**
     * Récupère le dernier salaire avant une date donnée
     */
    public function getLastSalaryBefore(string $employeeId, Carbon $beforeDate): ?array
    {
        try {
            $filters = [
                'employee' => $employeeId,
                'from_date' => ['<', $beforeDate->format('Y-m-d')]
            ];
            
            $params = $this->buildBasicParams($filters);
            $params['order_by'] = 'from_date desc';
            $params['limit'] = 1;
            
            $salaries = $this->erpApiService->getResource('Salary Structure Assignment', $params);
            return !empty($salaries) ? $salaries[0] : null;
            
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération du dernier salaire: " . $e->getMessage());
        }
    }

    /**
     * Crée un nouveau salaire
     */
    public function createSalary(array $salaryData): array
    {
        try {
            return $this->erpApiService->createResource('Salary Structure Assignment', $salaryData);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la création du salaire: " . $e->getMessage());
        }
    }

    /**
     * Récupère une structure de salaire par défaut pour l'employé
     */
    public function getDefaultSalaryStructure(): ?string
    {
        try {
            $params = [
                'fields' => ['name'],
                'filters' => [['is_active', '=', 1]],
                'limit' => 1
            ];
            
            $structures = $this->erpApiService->getResource('Salary Structure', $params);
            return !empty($structures) ? $structures[0]['name'] : null;
            
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Retourne les champs par défaut pour les salaires ERPNext
     */
    protected function getDefaultFields(): array
    {
        return ['name', 'employee', 'from_date', 'to_date', 'salary_structure', 'base', 'docstatus'];
    }
}