# README — Module de Gestion des Salaires

## Fonctionnalité 1 : Génération des salaires

### Objectif  
Générer automatiquement les salaires manquants pour un employé entre deux dates, sans dupliquer les salaires déjà existants.

### Entrées
- `employe_id` : Identifiant de l'employé
- `date_debut` : Date de début
- `date_fin` : Date de fin
- `salaire_base` : Montant à utiliser si aucun salaire antérieur n’est trouvé

### Sortie
Liste des salaires créés pour les mois où aucun salaire n’existe déjà.

### Règles
- Vérifier tous les mois entre `date_debut` et `date_fin`
- Si un salaire existe déjà pour un mois donné, ne rien faire
- Sinon :
  - Chercher le dernier salaire avant `date_debut` et l’utiliser
  - Si aucun salaire précédent, utiliser `salaire_base`
  - Générer un nouveau salaire pour le mois concerné

---

## Fonctionnalité 2 : Modification du salaire de base par condition

### Objectif  
Modifier automatiquement le salaire de base des employés selon une règle basée sur certains éléments de salaire.

### Entrées
- `pourcentage` : Valeur du changement (ex. 20 pour +20%)
- `elements_cibles` : Liste des éléments de salaire utilisés dans la condition (ex. indemnité, taxe sociale)

### Sortie
Nouveaux salaires de base appliqués aux employés qui remplissent la condition.

### Règles
- Pour chaque employé, vérifier si la condition sur les éléments de salaire est remplie
- Si oui :
  - Appliquer le pourcentage sur le salaire de base
  - Mettre à jour le nouveau salaire de base
