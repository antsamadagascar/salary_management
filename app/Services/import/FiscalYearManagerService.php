<?php

namespace App\Services\import;

use App\Services\api\ErpApiService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class FiscalYearManagerService
{
    private ErpApiService $erpApi;

    public function __construct(ErpApiService $erpApi)
    {
        $this->erpApi = $erpApi;
    }

    /**
     * Analyse de données de paie et crée les années fiscales manquantes
     */
    public function ensureFiscalYearsExist(array $payrollData): array
    {
        try {
            Log::info('Début de la vérification des années fiscales');
            
            // on extrait toutes les années uniques des données
            $years = $this->extractYearsFromData($payrollData);
            Log::info('Années détectées dans les données: ' . implode(', ', $years));
            
            $createdYears = [];
            $existingYears = [];
            
            // Vérifie et créer les années fiscales manquantes
            foreach ($years as $year) {
                $result = $this->createFiscalYearIfNotExists($year);
                if ($result['created']) {
                    $createdYears[] = $year;
                } else {
                    $existingYears[] = $year;
                }
            }
            
            Log::info('Années fiscales créées: ' . implode(', ', $createdYears));
            Log::info('Années fiscales existantes: ' . implode(', ', $existingYears));
            
            return [
                'success' => true,
                'message' => 'Années fiscales vérifiées/créées avec succès',
                'years_processed' => $years,
                'created_years' => $createdYears,
                'existing_years' => $existingYears
            ];
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la gestion des années fiscales: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
                'years_processed' => []
            ];
        }
    }

    /**
     * Extrait les années uniques des données de paie
     */
    private function extractYearsFromData(array $payrollData): array
    {
        $years = [];
        
        foreach ($payrollData as $record) {
            if (isset($record['Mois']) && !empty($record['Mois'])) {
                try {
                    $date = Carbon::createFromFormat('d/m/Y', $record['Mois']);
                    $year = $date->year;
                    if (!in_array($year, $years)) {
                        $years[] = $year;
                    }
                } catch (\Exception $e) {
                    Log::warning("Format de date invalide: {$record['Mois']}");
                    continue;
                }
            }
        }
        
        sort($years);
        return $years;
    }

    /**
     * Crée une année fiscale si elle n'existe pas
     */
    private function createFiscalYearIfNotExists(int $year): array
    {
        try {
            $fiscalYearName = $this->generateFiscalYearName($year);
            
            // Vérifie si l'année fiscale existe déjà
            $existingFiscalYear = $this->erpApi->getResourceByName('Fiscal Year', $fiscalYearName);
            
            if ($existingFiscalYear) {
                Log::info("Année fiscale {$fiscalYearName} existe déjà");
                return ['created' => false, 'name' => $fiscalYearName];
            }
            
            // on Crée la nouvelle année fiscale si elle n'existe pas
            $fiscalYearData = $this->prepareFiscalYearData($year, $fiscalYearName);
            $created = $this->erpApi->createResource('Fiscal Year', $fiscalYearData);
            
            if ($created) {
                Log::info("Année fiscale {$fiscalYearName} créée avec succès");
                return ['created' => true, 'name' => $fiscalYearName];
            } else {
                throw new Exception("Échec de la création de l'année fiscale {$fiscalYearName}");
            }
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la création de l'année fiscale {$year}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Génère le nom de l'année fiscale
     */
    private function generateFiscalYearName(int $year): string
    {
        return (string) $year;
    }

    /**
     * Prépare les données pour créer une année fiscale
     */
    private function prepareFiscalYearData(int $year, string $name): array
    {
        return [
            'year' => $name,
            'year_start_date' => "{$year}-01-01",
            'year_end_date' => "{$year}-12-31",
            'auto_created' => 1,
            'is_short_year' => 0
        ];
    }

    /**
     * Obtient toutes les années fiscales existantes
     */
    public function getExistingFiscalYears(): array
    {
        try {
            return $this->erpApi->getResource('Fiscal Year');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des années fiscales: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifie si une année fiscale spécifique existe
     */
    public function fiscalYearExists(int $year): bool
    {
        try {
            $fiscalYearName = $this->generateFiscalYearName($year);
            $fiscalYear = $this->erpApi->getResourceByName('Fiscal Year', $fiscalYearName);
            return $fiscalYear !== null;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification de l'année fiscale {$year}: " . $e->getMessage());
            return false;
        }
    }
}