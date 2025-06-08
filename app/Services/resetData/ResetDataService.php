<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ResetDataService
{
    /**
     * Tables à réinitialiser dans l'ordre de dépendance
     * (ordre inverse pour éviter les conflits de clés étrangères)
     */
    private array $tables = [
        'tabSalary Slip',
        'tabSalary Detail', 
        'tabSalary Structure Assignment',
        'tabSalary Component',
        'tabSalary Structure',
        'tabEmployee'
    ];

    /**
     * Mapping des tables vers leurs préfixes de série ERPNext réels
     * Basé sur les données actuelles de la base
     */
    private array $seriesMapping = [
        'tabEmployee' => 'HR-EMP-',                      // HR-EMP-YYYY-##### 
        'tabSalary Structure Assignment' => 'HR-SSA-',   // HR-SSA-25-06-00211
        'tabSalary Slip' => 'Sal Slip/None/',           // Sal Slip/None/00187
        // Les autres documents n'utilisent  pas de naming series
        // 'tabSalary Structure' => null,               // Nom libre
        // 'tabSalary Component' => null,               // Nom libre  
        // 'tabSalary Detail' => null,                  // Document enfant
    ];

    /**
     * Réinitialise toutes les données
     */
    public function resetAllData(): array
    {
        return DB::transaction(function () {
            $results = [];
            
            try {
                $this->disableForeignKeyChecks();

                // Réinitialise les tables principales
                foreach ($this->tables as $table) {
                    $deleted = $this->deleteFromTable($table);
                    $results[$table] = $deleted;
                    Log::info("Table '{$table}' réinitialisée: {$deleted} enregistrements supprimés");
                }

                // Réinitialise la table Company
                $companyDeleted = $this->deleteCompany();
                $results['tabCompany'] = $companyDeleted;
                Log::info("Entreprise 'My Company' supprimée: {$companyDeleted} enregistrements");

                // Réinitialise les séries de numérotation
                $this->resetAllNameSeries();

                $this->enableForeignKeyChecks();

                Log::info('Toutes les données ont été réinitialisées avec succès', $results);

                return [
                    'success' => true,
                    'message' => 'Toutes les données ont été réinitialisées avec succès',
                    'deleted_records' => $results,
                    'total_deleted' => array_sum($results)
                ];
                
            } catch (Exception $e) {
                $this->enableForeignKeyChecks();
                Log::error('Erreur lors de la réinitialisation des données', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Vérifie l'existence des données dans toutes les tables
     */
    public function checkDataExists(): array
    {
        $data = [];

        foreach ($this->tables as $table) {
            try {
                $count = DB::table($table)->count();
                $data[$table] = $count;
            } catch (Exception $e) {
                $data[$table] = "Erreur: " . $e->getMessage();
                Log::warning("Impossible de compter les enregistrements pour la table '{$table}'", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        try {
            $companyCount = DB::table('tabCompany')
                ->where('name', 'My Company')
                ->count();
            $data['tabCompany (My Company)'] = $companyCount;
        } catch (Exception $e) {
            $data['tabCompany (My Company)'] = "Erreur: " . $e->getMessage();
        }

        return $data;
    }

    /**
     * Réinitialise une table spécifique
     */
    public function resetSpecificTable(string $table): array
    {
        if (!$this->isValidTable($table)) {
            throw new Exception("Table '{$table}' non autorisée pour la réinitialisation");
        }

        return DB::transaction(function () use ($table) {
            try {
                $this->disableForeignKeyChecks();

                $deleted = ($table === 'tabCompany') 
                    ? $this->deleteCompany() 
                    : $this->deleteFromTable($table);

                $this->resetTableSeries($table);

                $this->enableForeignKeyChecks();

                Log::info("Table '{$table}' réinitialisée avec succès", [
                    'deleted_records' => $deleted
                ]);

                return [
                    'success' => true,
                    'message' => "Table '{$table}' réinitialisée avec succès",
                    'deleted_records' => $deleted
                ];

            } catch (Exception $e) {
                $this->enableForeignKeyChecks();
                Log::error("Erreur lors de la réinitialisation de la table '{$table}'", [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Supprime tous les enregistrements d'une table
     */
    private function deleteFromTable(string $table): int
    {
        if (!$this->tableExists($table)) {
            Log::warning("La table '{$table}' n'existe pas");
            return 0;
        }

        return DB::table($table)->delete();
    }

    /**
     * Supprime l'entreprise "My Company"
     */
    private function deleteCompany(): int
    {
        return DB::table('tabCompany')
            ->where('name', 'My Company')
            ->delete();
    }

    /**
     * Réinitialise toutes les séries de numérotation ERPNext
     * Gère les différents formats de naming series
     */
    private function resetAllNameSeries(): void
    {
        foreach ($this->seriesMapping as $table => $prefix) {
            if ($prefix) {
                $this->resetSeries($prefix);
                
                // Pour les séries avec format année/mois comme HR-SSA-25-06-
                if (strpos($prefix, 'HR-') === 0) {
                    $currentYear = date('y'); // Format 2 chiffres (25 pour 2025)
                    $currentMonth = date('m');
                    $yearlyPrefix = $prefix . $currentYear . '-' . $currentMonth . '-';
                    $this->resetSeries($yearlyPrefix);
                }
            }
        }
    }

    /**
     * Réinitialise la série pour une table spécifique
     * Gère les différents formats selon le type de document
     */
    private function resetTableSeries(string $table): void
    {
        if (isset($this->seriesMapping[$table]) && $this->seriesMapping[$table]) {
            $prefix = $this->seriesMapping[$table];
            $this->resetSeries($prefix);
            
            // Pour les séries HR avec format année/mois
            if (strpos($prefix, 'HR-') === 0) {
                $currentYear = date('y'); // Format 2 chiffres
                $currentMonth = date('m');
                $yearlyPrefix = $prefix . $currentYear . '-' . $currentMonth . '-';
                $this->resetSeries($yearlyPrefix);
            }
        }
    }

    /**
     * Réinitialise une série spécifique
     */
    private function resetSeries(string $prefix): void
    {
        try {
            $updated = DB::table('tabSeries')
                ->where('name', $prefix)
                ->update(['current' => 0]);
                
            if ($updated > 0) {
                Log::info("Série '{$prefix}' réinitialisée");
            }
        } catch (Exception $e) {
            Log::warning("Impossible de réinitialiser la série '{$prefix}'", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Désactive les contraintes de clés étrangères
     */
    private function disableForeignKeyChecks(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    /**
     * Réactive les contraintes de clés étrangères
     */
    private function enableForeignKeyChecks(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Vérifie si une table est autorisée pour la réinitialisation
     */
    private function isValidTable(string $table): bool
    {
        return in_array($table, $this->tables) || $table === 'tabCompany';
    }

    /**
     * Vérifie si une table existe dans la base de données
     */
    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (Exception $e) {
            Log::warning("Impossible de vérifier l'existence de la table '{$table}'", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtient la liste des tables gérées par ce service
     */
    public function getManagedTables(): array
    {
        return array_merge($this->tables, ['tabCompany']);
    }

    /**
     * Réinitialise uniquement les séries de numérotation
     */
    public function resetSeriesOnly(): array
    {
        $results = [];
        
        foreach ($this->seriesMapping as $table => $prefix) {
            try {
                $updated = DB::table('tabSeries')
                    ->where('name', $prefix)
                    ->update(['current' => 0]);
                    
                $results[$table] = $updated;
            } catch (Exception $e) {
                $results[$table] = "Erreur: " . $e->getMessage();
                Log::error("Erreur lors de la réinitialisation de la série pour '{$table}'", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => true,
            'message' => 'Séries de numérotation réinitialisées',
            'results' => $results
        ];
    }
}