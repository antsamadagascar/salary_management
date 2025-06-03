<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Salaires Mensuels - {{ $employee['employee_name'] ?? 'N/A' }} - {{ $monthName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-info {
            margin-bottom: 20px;
        }
        .employee-info {
            background-color: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .employee-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .employee-info td {
            padding: 5px;
            border: none;
        }
        .period-info {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #0056b3;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .salary-table th,
        .salary-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .salary-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .positive {
            color: #28a745;
        }
        .negative {
            color: #dc3545;
        }
        .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .summary {
            margin-top: 30px;
            border: 2px solid #0056b3;
            padding: 15px;
        }
        .summary h3 {
            margin-top: 0;
            color: #0056b3;
            text-align: center;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .summary-total {
            font-size: 16px;
            font-weight: bold;
            color: #0056b3;
            text-align: center;
            background-color: #f8f9fa;
            padding: 10px;
            border: 2px solid #0056b3;
            margin-top: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <h1>BULLETIN DE SALAIRE MENSUEL</h1>
        <div class="company-info">
            <strong>Votre Entreprise</strong><br>
            Adresse de l'entreprise<br>
            Téléphone - Email
        </div>
    </div>

    <!-- Informations employé -->
    <div class="employee-info">
        <table>
            <tr>
                <td><strong>Employé:</strong></td>
                <td>{{ $employee['employee_name'] ?? 'N/A' }}</td>
                <td><strong>Matricule:</strong></td>
                <td>{{ $employee['employee_number'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Département:</strong></td>
                <td>{{ $employee['department'] ?? 'N/A' }}</td>
                <td><strong>Poste:</strong></td>
                <td>{{ $employee['designation'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Période -->
    <div class="period-info">
        PÉRIODE: {{ $monthName }}
    </div>

    @if(count($salarySlips) > 0)
        <!-- Tableau des fiches de paie -->
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Date de paie</th>
                    <th>Période</th>
                    <th>Salaire Brut</th>
                    <th>Déductions</th>
                    <th>Salaire Net</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalGross = 0;
                    $totalDeductions = 0;
                    $totalNet = 0;
                @endphp
                
                @foreach($salarySlips as $slip)
                    @php
                        $totalGross += $slip['gross_pay'] ?? 0;
                        $totalDeductions += $slip['total_deduction'] ?? 0;
                        $totalNet += $slip['net_pay'] ?? 0;
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($slip['posting_date'])->format('d/m/Y') }}</td>
                        <td>
                            {{ \Carbon\Carbon::parse($slip['start_date'])->format('d/m') }} - 
                            {{ \Carbon\Carbon::parse($slip['end_date'])->format('d/m/Y') }}
                        </td>
                        <td class="amount positive">{{ number_format($slip['gross_pay'] ?? 0, 2, ',', ' ') }} €</td>
                        <td class="amount negative">{{ number_format($slip['total_deduction'] ?? 0, 2, ',', ' ') }} €</td>
                        <td class="amount">{{ number_format($slip['net_pay'] ?? 0, 2, ',', ' ') }} €</td>
                        <td style="text-align: center;">{{ $slip['status'] ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2"><strong>TOTAUX</strong></td>
                    <td class="amount positive"><strong>{{ number_format($totalGross, 2, ',', ' ') }} €</strong></td>
                    <td class="amount negative"><strong>{{ number_format($totalDeductions, 2, ',', ' ') }} €</strong></td>
                    <td class="amount"><strong>{{ number_format($totalNet, 2, ',', ' ') }} €</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <!-- Résumé -->
        <div class="summary">
            <h3>RÉSUMÉ MENSUEL</h3>
            <table class="summary-table">
                <tr>
                    <td><strong>Total Salaire Brut:</strong></td>
                    <td class="amount positive">{{ number_format($totalGross, 2, ',', ' ') }} €</td>
                </tr>
                <tr>
                    <td><strong>Total Déductions:</strong></td>
                    <td class="amount negative">{{ number_format($totalDeductions, 2, ',', ' ') }} €</td>
                </tr>
                <tr>
                    <td><strong>Nombre de fiches:</strong></td>
                    <td class="amount">{{ count($salarySlips) }}</td>
                </tr>
            </table>
            
            <div class="summary-total">
                SALAIRE NET TOTAL: {{ number_format($totalNet, 2, ',', ' ') }} €
            </div>
        </div>
    @else
        <div class="no-data">
            <p>Aucune fiche de paie trouvée pour cette période.</p>
        </div>
    @endif

    <!-- Pied de page -->
    <div class="footer">
        <p>Document généré le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }}</p>
        <p>Ce document est confidentiel et destiné uniquement à {{ $employee['employee_name'] ?? 'l\'employé concerné' }}</p>
    </div>
</body>
</html>