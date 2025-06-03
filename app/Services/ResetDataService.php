<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ResetDataService
{
    /**
     * Tables à réinitialiser dans l'ordre de suppression
     */
    private $tables = [
        'tabSalary Slip',
        'tabSalary Detail', 
        'tabSalary Structure Assignment',
        'tabSalary Component',
        'tabSalary Structure',
        'tabEmployee'
    ];

    /**
     * Réinitialise toutes les données
     *
     * @return array
     */
    public function resetAllData()
    {
        return DB::transaction(function () {
            $results = [];
            
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                
                // Suppression des données dans l'ordre
                foreach ($this->tables as $table) {
                    $deleted = $this->deleteFromTable($table);
                    $results[$table] = $deleted;
                }
                
                // Suppression spécifique de la compagnie
                $companyDeleted = $this->deleteCompany();
                $results['tabCompany'] = $companyDeleted;
                
                // Réinitialisation des clés auto-increment
                $this->resetAutoIncrement();
                
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                
                Log::info('Données réinitialisées avec succès', $results);
                
                return [
                    'success' => true,
                    'message' => 'Toutes les données ont été réinitialisées avec succès',
                    'deleted_records' => $results
                ];
                
            } catch (Exception $e) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                
                Log::error('Erreur lors de la réinitialisation des données: ' . $e->getMessage());
                
                throw $e;
            }
        });
    }

    /**
     * Supprime les données d'une table
     *
     * @param string $table
     * @return int
     */
    private function deleteFromTable($table)
    {
        return DB::table($table)->delete();
    }

    /**
     * Supprime la compagnie spécifique
     *
     * @return int
     */
    private function deleteCompany()
    {
        return DB::table('tabCompany')
            ->where('name', 'My Company')
            ->delete();
    }

    /**
     * Réinitialise les clés auto-increment de toutes les tables
     */
    private function resetAutoIncrement()
    {
        $allTables = array_merge($this->tables, ['tabCompany']);
        
        foreach ($allTables as $table) {
            try {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            } catch (Exception $e) {
                Log::warning("Impossible de réinitialiser AUTO_INCREMENT pour {$table}: " . $e->getMessage());
            }
        }
    }

    /**
     * Vérifie si des données existent
     *
     * @return array
     */
    public function checkDataExists()
    {
        $data = [];
        
        foreach ($this->tables as $table) {
            $count = DB::table($table)->count();
            $data[$table] = $count;
        }
        
        $companyCount = DB::table('tabCompany')
            ->where('name', 'My Company')
            ->count();
        $data['tabCompany (My Company)'] = $companyCount;
        
        return $data;
    }

    /**
     * Supprime seulement les données d'une table spécifique
     *
     * @param string $table
     * @return array
     */
    public function resetSpecificTable($table)
    {
        return DB::transaction(function () use ($table) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                
                if ($table === 'tabCompany') {
                    $deleted = $this->deleteCompany();
                } else {
                    $deleted = $this->deleteFromTable($table);
                }

                try {
                    DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                } catch (Exception $e) {
                    Log::warning("Impossible de réinitialiser AUTO_INCREMENT pour {$table}");
                }

                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                
                return [
                    'success' => true,
                    'message' => "Table {$table} réinitialisée avec succès",
                    'deleted_records' => $deleted
                ];
                
            } catch (Exception $e) {

                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                
                throw $e; 
            }
        });
    }
}