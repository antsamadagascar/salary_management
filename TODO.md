# Jour1 :
I-  Fonctionnalites :

1.Authentification login/logout compte erpnext (ok)

2.Documentation swagger complet des api erpenxt (ok)

3.Creation service erpnextService (pour l'appele des methode:get,update,getByID,post,delete)

3.Multi Import donnees csv(3 fichiers) (no):
    -Traitement validation import sur le format de date:
        -retourne erreur si date inexistantes 

    -Validation import toutes ou rien :
        -check Validation ensemble
        -Si contient un  erreur :auccun donnes ne s'insert

4.Gestions Employés(no) :
    -Listes Employés 
    -Filtrage Employés par criteres de recherche

5.Fiche de paie employés (salaire par mois) (no) :
    -Affichage fiche de paie d'un employés specifique 
    -Export pdf du fiche de paie specifique d'un employés 

6.Filtrage par mois contenent (informations pour chaque employer) (no)
    -Affichage tableau(employe,elements de salaires,total)
    -Total global (nombre employer,salaires,elements salaires)


## Api AND DOCTYPE:

### Doctypes:
-company:
    ->select * from tabCompany;

-branche (name)
    ->SELECT * from tabBranch tb 

-Department (name,departement_name) :
    ->SELECT  * from tabDepartment td 

-Employee(last_name,first_name,gender,date_of_birth,salutation,date_of_joining,status,branch,department,employee_number,ctc,salary_currency,salary_mode):
    ->SELECT * from `tabEmployee` te 

-Salary Structure :
    ->SELECT * FROM `tabSalary Structure` tss 

-Salary Component:
    ->select * from `tabSalary Component` tsc 

-Salary Detail:
    ->select * from `tabSalary Detail` tsd 

Salary Slip:
    ->select * from `tabSalary Slip` tss 

### API (endoint:epnext.localhost:8000/):
-branche:
    -/api/resource/branch

## workflow :
Company
   ↓
Employee (Fichier 1)
   ↓
Salary Component (Fichier 2A)
   ↓
Salary Structure + Salary Detail (Fichier 2B)
   ↓
Salary Structure Assignment (optionnel)
   ↓
Salary Slip (Fichier 3)

## INSTALLATION MODULE HR :
bench get-app https://github.com/frappe/hrms
bench --site erpnext.localhost install-app hrms
bench --site erpnext.localhost migrate
bench stop
bench start 

## Modele et format import csv:
Mappage : colone doctype ->colonnes csv
Fichier 1: 
-Employee:
    last_name ->Nom
    first_name ->Prenom
    gender -> genre
    date_of_joining ->Date embauche
    date_of_birth ->date naissance
    company ->company

-Company:
    company_name ->company
    default_currency(Default :USD) 

->Dependance :doctype company doit existe avant d'inserer dans employee 

Fichier 2: 
-Salary Structure 
    -name ->salary structure

-Salary Component:
    -salary_component ->name
    -salary_component_abbr ->abbr
    -type ->type

-Salary Detail:
    -Salary Structure(name) -> salary structure
    -Salary Component(name) ->name
    -Salary Component(salary_component_abbr) ->abbr
    -type ->type
    -formula ->valeur
    -condition ->Remarque

FIchier 3:
-Salary Slip:
    -employe

Salary Structure: "gasy1"
├── Earnings:
│   ├── Salary Detail 1:
│   │   ├── salary_component: "Salaire Base"
│   │   ├── abbr: "SB"  
│   │   ├── formula: "base"
│   │   └── amount: 0
│   └── Salary Detail 2:
│       ├── salary_component: "Indemnité"
│       ├── abbr: "IND"
│       ├── formula: "SB * 0.30"
│       └── condition: "SB > 0"
└── Deductions:
    └── Salary Detail 3:
        ├── salary_component: "Taxe sociale"
        ├── abbr: "TS"
        ├── formula: "(SB + IND) * 0.20"
        └── condition: "(SB + IND) > 0"