<?php

namespace App\Services;

abstract class BaseService
{
    /**
     * Construit les paramètres de base pour les requêtes API
     */
    protected function buildBasicParams(array $filters = [], array $fields = []): array
    {
        $params = [
            'fields' => !empty($fields) ? $fields : $this->getDefaultFields(),
            'filters' => []
        ];

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $params['filters'][] = [$field, $value[0], $value[1]];
            } else {
                $params['filters'][] = [$field, '=', $value];
            }
        }

        return $params;
    }

    /**
     * Applique les filtres côté client
     */
    protected function applyClientSideFilters(array $data, array $filters): array
    {
        // Logique de filtrage côté client si nécessaire
        // À implémenter selon vos besoins spécifiques
        return $data;
    }

    /**
     * Retourne les champs par défaut pour ce service
     * À surcharger dans chaque service enfant
     */
    protected function getDefaultFields(): array
    {
        return ['name'];
    }
}