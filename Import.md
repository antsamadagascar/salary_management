# Structure des Doctypes Frappe ERPNext - Logique Exacte selon CSV

## 1. 🏢 DOCTYPE: `Company` (Créé en premier)

### Table: `tabCompany`

| Field | Value |
|-------|-------|
| `company_name` | My Company |
| `default_currency` | USD |
| `country` | Madagascar |

⚠️ **DÉPENDANCE** : Company doit exister AVANT Employee

---

## 2. 👥 DOCTYPE: `Employee` (Fichier 1)

### Mappage CSV → Frappe

| Colonne CSV | → | Champ Frappe | Value (Emp 1) | Value (Emp 2) |
|-------------|---|--------------|---------------|---------------|
| `Nom` | → | `last_name` | Rakoto | Rasoa |
| `Prenom` | → | `first_name` | Alain | Jeanne |
| `genre` | → | `gender` | Male | Female |
| `Date embauche` | → | `date_of_joining` | 2024-04-03 | 2024-06-08 |  
| `date naissance` | → | `date_of_birth` | 1980-01-01 | 1990-01-01 |
| `company` | → | `company` | My Company | My Company |

### Table: `tabEmployee` (Résultat final)

| Field | Value (Emp 1) | Value (Emp 2) |
|-------|---------------|---------------|
| `name` | EMP-001 | EMP-002 |
| `employee_number` | 1 | 2 |
| `last_name` | Rakoto | Rasoa |
| `first_name` | Alain | Jeanne |
| `gender` | Male | Female |
| `date_of_joining` | 2024-04-03 | 2024-06-08 |
| `date_of_birth` | 1980-01-01 | 1990-01-01 |
| `company` | My Company | My Company |

---

## 3. 💰 DOCTYPE: `Salary Structure` (Fichier 2)

### Mappage CSV → Frappe

| Colonne CSV | → | Champ Frappe |
|-------------|---|--------------|
| `salary structure` | → | `name` |

### Table: `tabSalary Structure`

| Field | Value |
|-------|-------|
| `name` | gasy1 |
| `company` | My Company |

---

## 4. 🧮 DOCTYPE: `Salary Component` (Fichier 2)

### Mappage CSV → Frappe

| Colonne CSV | → | Champ Frappe |
|-------------|---|--------------|
| `name` | → | `salary_component` |
| `Abbr` | → | `salary_component_abbr` |
| `type` | → | `type` |

### Table: `tabSalary Component`

| Field | Comp 1 | Comp 2 | Comp 3 |
|-------|--------|--------|--------|
| `name` | SC-001 | SC-002 | SC-003 |
| `salary_component` | Salaire Base | Indemnité | Taxe sociale |
| `salary_component_abbr` | SB | IND | TS |
| `type` | Earning | Earning | Deduction |

---

## 5. 📋 DOCTYPE: `Salary Detail` (Fichier 2 - Table enfant)

### Mappage CSV → Frappe (Champs réels)

| Colonne CSV | → | Champ Frappe | Notes |
|-------------|---|--------------|-------|
| `salary structure` | → | `parent` | Lien vers Salary Structure |
| `name` | → | `salary_component` | Lien vers Salary Component |
| `Abbr` | → | `abbr` | Abréviation |
| `type` | → | `parentfield` | earnings/deductions |
| `valeur` | → | `formula` | Formule de calcul |
| `Remarque` | → | `condition` | Condition d'application |
| | → | `amount_based_on_formula` | 1 si formule, 0 si fixe |
| | → | `amount` | 0.000000000 par défaut |

### Table: `tabSalary Detail` (Earnings)

| Field | Detail 1 | Detail 2 |
|-------|----------|----------|
| `name` | SD-001 | SD-002 |
| `parent` | gasy1 | gasy1 |  
| `parenttype` | Salary Structure | Salary Structure |
| `parentfield` | earnings | earnings |
| `salary_component` | Salaire Base | Indemnité |
| `abbr` | SB | IND |
| `amount` | 0.000000000 | 0.000000000 |
| `formula` | base | SB * 0.30 |
| `condition` | *(NULL)* | SB > 0 |
| `amount_based_on_formula` | 1 | 1 |
| `statistical_component` | 0 | 0 |
| `idx` | 1 | 2 |

### Table: `tabSalary Detail` (Deductions)

| Field | Detail 3 |
|-------|----------|
| `name` | SD-003 |
| `parent` | gasy1 |
| `parenttype` | Salary Structure |
| `parentfield` | deductions |
| `salary_component` | Taxe sociale |
| `abbr` | TS |
| `amount` | 0.000000000 |
| `formula` | (SB + IND) * 0.20 |
| `condition` | (SB + IND) > 0 |
| `amount_based_on_formula` | 1 |
| `statistical_component` | 0 |
| `idx` | 1 |

---

## 6. 💸 DOCTYPE: `Salary Slip` (Fichier 3)

### Mappage CSV → Frappe

| Colonne CSV | → | Champ Frappe |
|-------------|---|--------------|
| `Mois` | → | `start_date` / `end_date` |
| `Ref Employe` | → | `employee` (lien vers Employee) |
| `Salaire Base` | → | `base` |
| `Salaire` | → | `salary_structure` (lien vers Salary Structure) |

### Table: `tabSalary Slip` (Avril 2025)

| Field | Slip 1 | Slip 2 |
|-------|--------|--------|
| `name` | SAL-2025-04-001 | SAL-2025-04-002 |
| `employee` | 1 | 2 |
| `employee_name` | Alain Rakoto | Jeanne Rasoa |
| `salary_structure` | gasy1 | gasy1 |
| `start_date` | 2025-04-01 | 2025-04-01 |
| `end_date` | 2025-04-30 | 2025-04-30 |
| `base` | 1500000 | 900000 |
| `company` | My Company | My Company |

---

## 🔄 LOGIQUE DE TRAITEMENT (Avec vrais champs)

### Fichier 2 - Transformation CSV vers tabSalary Detail

**Ligne CSV:**
```csv
gasy1,Indemnité,IND,earning,30%,salaire base
```

**Devient dans tabSalary Detail:**
```sql
INSERT INTO `tabSalary Detail` VALUES (
  'SD-002',                    -- name (auto-généré)
  '2025-06-01 12:00:00',      -- creation
  'Administrator',             -- owner
  'gasy1',                     -- parent (Salary Structure)
  'earnings',                  -- parentfield (car type=earning)
  'Salary Structure',          -- parenttype
  'Indemnité',                -- salary_component
  'IND',                      -- abbr
  0.000000000,                -- amount (car formule)
  'SB * 0.30',               -- formula (converti de "30%")
  'SB > 0',                  -- condition (converti de "salaire base")
  1,                         -- amount_based_on_formula
  0,                         -- statistical_component
  2                          -- idx
);
```

### Conversion des "Remarques" en conditions SQL

| Remarque CSV | → | Condition Frappe |
|-------------|---|------------------|
| *(vide)* | → | `NULL` |
| "salaire base" | → | `SB > 0` |
| "salaire base + indemnité" | → | `(SB + IND) > 0` |

### Conversion des "Valeurs" en formules

| Valeur CSV | → | Formule Frappe |
|-----------|---|----------------|
| "100%" | → | `base` |
| "30%" | → | `SB * 0.30` |
| "20%" | → | `(SB + IND) * 0.20` |

---

## ⚠️ POINTS CRITIQUES

1. **`condition`** = Votre "Remarque" (pas une formule, mais une condition textuelle)
2. **`formula`** = Votre "valeur" (100%, 30%, 20%)  
3. **Ordre d'insertion** : Company → Employee → Salary Component → Salary Structure → Salary Slip
4. **Relations multiples** : Un CSV ligne = plusieurs doctypes connectés

**La "Remarque" va bien dans `condition`, pas dans `formula` !**