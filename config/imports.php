<?php
//fichier1 :employees
//fichier2:salary structure
//fichier3:payroll

return [
    'file_types' => ['employees', 'salary_structure', 'payroll'],
    
    'headers' => [
        'employees' => ['Ref', 'Nom', 'Prenom', 'genre', 'Date embauche', 'date naissance', 'company'],
        'salary_structure' => ['salary structure', 'name', 'Abbr', 'type', 'valeur', 'company'],
        'payroll' => ['Mois', 'Ref Employe', 'Salaire Base', 'Salaire']
    ],

    'validation_rules' => [
        'employees' => [
            'required_fields' => [
                0 => 'Ref', //required in erpnext
              //1 => 'Nom',// not required in erpnext
                2 => 'Prenom',//required in erpnext
                3 => 'genre',//required in erpnext
                4 => 'Date embauche',//required in erpnext
                5 => 'date naissance',//required in erpnext
                6 => 'company'//required in erpnext
            ],
            'numeric_fields' => [
                0 => 'Ref'
            ],
            'date_fields' => [
                4 => ['field' => 'Date embauche', 'format' => 'd/m/Y'],
                5 => ['field' => 'date naissance', 'format' => 'd/m/Y']
            ],
            'enum_fields' => [
                3 => ['field' => 'genre', 'values' => ['Masculin', 'Feminin']]
            ]
        ],

        'salary_structure' => [
            'required_fields' => [
                0 => 'salary structure', //required in erpnext
                1 => 'name', // required in erpnext
                2 => 'Abbr', //required in erpnext
                3 => 'type', //required in erpnext
             // 4 => 'valeur', //required in erpnext
                5 => 'company' //required in erpnext
            ],
            // 'numeric_fields' => [
            //     4 => 'valeur'  // -> possible string (formula) or value :5000 ohatra
            // ],
            'enum_fields' => [
                3 => ['field' => 'type', 'values' => ['earning', 'deduction']]
            ]
        ],
        'payroll' => [
            'required_fields' => [
                0 => 'Mois', //require in erpnext
                1 => 'Ref Employe', // required in erpnext
             //   2 => 'Salaire Base', // required in erpenxt
                3 => 'Salaire' // required in erpenxt
            ],
            'numeric_fields' => [
                1 => 'Ref Employe',
                2 => 'Salaire Base'
            ],
            'date_fields' => [
                0 => ['field' => 'Mois', 'format' => 'd/m/Y']
             ]

        ]
    ],

    'messages' => [
        'file_required' => 'Le fichier des :type est requis',
        'file_mimes' => 'Le fichier des :type doit être au format CSV',
        'missing_headers' => 'Colonnes manquantes dans :type: :headers',
        'extra_headers' => 'Colonnes supplémentaires dans :type: :headers',
        'empty_file' => 'Fichier :type vide ou format invalide',
        'read_error' => 'Impossible de lire le fichier :type',
        'required_field' => 'Ligne :line: :field requis',
        'invalid_date' => 'Ligne :line: Format de :field invalide (attendu: :format)',
        'invalid_enum' => 'Ligne :line: :field doit être :values',
        'invalid_numeric' => 'Ligne :line: :field doit être numérique',
        'negative_not_allowed' => "Ligne :line - Le champ « :field » ne peut pas être négatif."
    ]
];