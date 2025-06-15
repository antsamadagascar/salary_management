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
        .details-section {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        .details-header {
            background-color: #0056b3;
            color: white;
            padding: 10px;
            font-weight: bold;
            text-align: center;
            font-size: 14px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .details-table th,
        .details-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        .details-table th {
            background-color: #f1f3f4;
            font-weight: bold;
            font-size: 11px;
        }
        .details-table td {
            font-size: 11px;
        }
        .slip-separator {
            border-top: 3px solid #0056b3;
            margin: 40px 0 20px 0;
            page-break-before: auto;
        }
        .slip-title {
            background-color: #e7f3ff;
            padding: 10px;
            border: 1px solid #0056b3;
            font-weight: bold;
            text-align: center;
            margin-bottom: 15px;
        }
        .earnings-section {
            background-color: #f8fff8;
            border: 1px solid #28a745;
        }
        .deductions-section {
            background-color: #fff8f8;
            border: 1px solid #dc3545;
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
        .slip-summary {
            background-color: #f8f9fa;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
        }
        .slip-summary table {
            width: 100%;
        }
        .slip-summary td {
            padding: 5px;
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <h1>BULLETIN DE SALAIRE MENSUEL DÉTAILLÉ</h1>
        <div class="company-info">
            <strong>{{ $employee['company'] ?? 'N/A' }}</strong><br>
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
        @php
            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $currency = null;
        @endphp

        @foreach($salarySlips as $index => $slip)
            @php
                $totalGross += $slip['gross_pay'] ?? 0;
                $totalDeductions += $slip['total_deduction'] ?? 0;
                $totalNet += $slip['net_pay'] ?? 0;
                if (!$currency && isset($slip['currency'])) {
                    $currency = $slip['currency'];
                }
            @endphp

            @if($index > 0)
                <div class="slip-separator"></div>
            @endif

            <div class="slip-title">
                FICHE DE PAIE N° {{ $index + 1 }} - 
                Période du {{ \Carbon\Carbon::parse($slip['start_date'])->format('d/m') }} au {{ \Carbon\Carbon::parse($slip['end_date'])->format('d/m/Y') }}
                (Payée le {{ \Carbon\Carbon::parse($slip['posting_date'])->format('d/m/Y') }})
            </div>

            <!-- Résumé de la fiche -->
            <div class="slip-summary">
                <table>
                    <tr>
                        <td><strong>Salaire Brut:</strong></td>
                        <td class="amount positive">{{ number_format($slip['gross_pay'] ?? 0, 2, ',', ' ') }} {{ $slip['currency'] ?? '' }}</td>
                        <td><strong>Total Déductions:</strong></td>
                        <td class="amount negative">{{ number_format($slip['total_deduction'] ?? 0, 2, ',', ' ') }} {{ $slip['currency'] ?? '' }}</td>
                        <td><strong>Salaire Net:</strong></td>
                        <td class="amount">{{ number_format($slip['net_pay'] ?? 0, 2, ',', ' ') }} {{ $slip['currency'] ?? '' }}</td>
                    </tr>
                </table>
            </div>

            <!-- Détails des gains -->
            @if(isset($slip['earnings']) && is_array($slip['earnings']) && count($slip['earnings']) > 0)
                <div class="details-section earnings-section">
                    <div class="details-header" style="background-color: #28a745;">
                        DÉTAIL DES GAINS
                    </div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Type de gain</th>
                                <th>Montant</th>
                                <th>Montant calculé</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slip['earnings'] as $earning)
                                <tr>
                                    <td>{{ $earning['salary_component'] ?? 'N/A' }}</td>
                                    <td class="amount positive">{{ number_format($earning['amount'] ?? 0, 2, ',', ' ') }}</td>
                                    <td class="amount positive">{{ number_format($earning['amount'] ?? 0, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Détails des déductions -->
            @if(isset($slip['deductions']) && is_array($slip['deductions']) && count($slip['deductions']) > 0)
                <div class="details-section deductions-section">
                    <div class="details-header" style="background-color: #dc3545;">
                        DÉTAIL DES DÉDUCTIONS
                    </div>
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Type de déduction</th>
                                <th>Montant</th>
                                <th>Montant calculé</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slip['deductions'] as $deduction)
                                <tr>
                                    <td>{{ $deduction['salary_component'] ?? 'N/A' }}</td>
                                    <td class="amount negative">{{ number_format($deduction['amount'] ?? 0, 2, ',', ' ') }}</td>
                                    <td class="amount negative">{{ number_format($deduction['amount'] ?? 0, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Statut de la fiche -->
            <div style="text-align: center; margin: 15px 0; font-weight: bold;">
                Statut: 
                @if(($slip['status'] ?? '') === 'Submitted')
                    <span style="color: #28a745;">Traitée</span>
                @elseif(($slip['status'] ?? '') === 'Draft')
                    <span style="color: #ffc107;">Brouillon</span>
                @elseif(($slip['status'] ?? '') === 'Cancelled')
                    <span style="color: #dc3545;">Annulée</span>
                @else
                    {{ $slip['status'] ?? 'N/A' }}
                @endif
            </div>

        @endforeach

        <!-- Résumé global -->
        <div class="summary">
            <h3>RÉSUMÉ MENSUEL GLOBAL</h3>
            <table class="summary-table">
                <tr>
                    <td><strong>Total Salaire Brut:</strong></td>
                    <td class="amount positive">{{ number_format($totalGross, 2, ',', ' ') }} {{ $currency ?? '' }}</td>
                </tr>
                <tr>
                    <td><strong>Total Déductions:</strong></td>
                    <td class="amount negative">{{ number_format($totalDeductions, 2, ',', ' ') }} {{ $currency ?? '' }}</td>
                </tr>
                <tr>
                    <td><strong>Nombre de fiches de paie:</strong></td>
                    <td class="amount">{{ count($salarySlips) }}</td>
                </tr>
            </table>
            
            <div class="summary-total">
                SALAIRE NET TOTAL DU MOIS: {{ number_format($totalNet, 2, ',', ' ') }} {{ $currency ?? '' }}
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
        <p><em>Document détaillé comprenant tous les éléments de calcul des salaires</em></p>
    </div>
</body>
</html>