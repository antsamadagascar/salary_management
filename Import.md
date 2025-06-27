# Guide Frappe ERPNext - Import Paie Madagascar

## 🎯 OBJECTIF
Importer 3 fichiers CSV pour créer un système de paie complet dans Frappe ERPNext.

## 📁 FICHIERS À TRAITER
1. **employees.csv** → Employés
2. **salary_structure.csv** → Structure salariale + composants
3. **salary_slips.csv** → Bulletins de paie

## ⚡ PROCESSUS RAPIDE

### 1. Créer Company (prérequis)
```
Company: "My Company"
Currency: USD
Country: Madagascar
```

### 2. Import Employés (Fichier 1)
**CSV → Frappe Employee**
- `Nom` → `last_name`
- `Prenom` → `first_name` 
- `genre` → `gender`
- `Date embauche` → `date_of_joining`
- `date naissance` → `date_of_birth`

### 3. Import Structure Salariale (Fichier 2)
**Une ligne CSV = 3 doctypes créés :**

**Salary Component :**
- `name` → nom du composant
- `Abbr` → abréviation
- `type` → Earning/Deduction

**Salary Structure :**
- `salary structure` → nom de la structure

**Salary Detail (table enfant) :**
- `valeur` → `formula` (ex: "30%" devient "SB * 0.30")
- `Remarque` → `condition` (ex: "salaire base" devient "SB > 0")
- `type` → `parentfield` (earnings ou deductions)

### 4. Import Bulletins (Fichier 3)
**CSV → Frappe Salary Slip**
- `Mois` → `start_date` / `end_date`
- `Ref Employe` → `employee` (lien)
- `Salaire Base` → `base`
- `Salaire` → `salary_structure` (lien)

## 🔑 POINTS CLÉS

**Ordre obligatoire :** Company → Employee → Salary Component → Salary Structure → Salary Slip

**Conversions importantes :**
- Remarque "salaire base" → Condition `SB > 0`
- Valeur "30%" → Formule `SB * 0.30`
- Type "earning" → Parentfield `earnings`

**Relations :**
- Employee lié à Company
- Salary Detail lié à Salary Structure
- Salary Slip lié à Employee et Salary Structure

## 💡 RÉSULTAT
Système de paie fonctionnel avec calculs automatiques basés sur les formules et conditions définies.