<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle ?? 'Laporan WMS' }}</title>
    <style>
        @page { size: A4 {{ !empty($isLandscape) ? 'landscape' : 'portrait' }}; margin: 14mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1f2937; margin: 14mm; line-height: 1.35; background: #fff; }
        .header-container { width: 100%; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .header-container table, .metadata-table, .document-context, .summary-cards { width: 100%; border-collapse: collapse; }
        .header-logo { width: 58px; vertical-align: middle; padding-right: 10px; }
        .header-logo img { max-width: 52px; max-height: 52px; }
        .header-text { vertical-align: middle; }
        .company-name { font-size: 16px; font-weight: bold; letter-spacing: .3px; }
        .company-address { font-size: 8.5px; color: #64748b; margin-top: 2px; }
        .report-title { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; color: #0f172a; margin: 10px 0 8px; }
        .metadata-table { margin-bottom: 12px; font-size: 9px; }
        .metadata-table td { padding: 2px 3px; vertical-align: top; }
        .metadata-table td:nth-child(odd) { color: #64748b; font-weight: bold; }
        .document-context { margin: 0 0 12px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .document-context td { width: 50%; padding: 7px 9px; vertical-align: top; }
        .summary-cards { margin: 0 0 12px; table-layout: fixed; }
        .summary-cards td { border: 1px solid #dbe3ec; background: #f8fafc; text-align: center; padding: 5px; }
        .summary-cards span { display: block; color: #64748b; font-size: 8px; text-transform: uppercase; }
        .summary-cards strong { display: block; font-size: 14px; }
        .enterprise-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; page-break-inside: auto; }
        .enterprise-table thead { display: table-header-group; }
        .enterprise-table tr { page-break-inside: avoid; }
        .enterprise-table th { background: #e2e8f0; color: #0f172a; font-weight: bold; padding: 6px 5px; border: .5px solid #94a3b8; text-align: left; font-size: 8.5px; text-transform: uppercase; }
        .enterprise-table td { padding: 5px; border: .5px solid #cbd5e1; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        .enterprise-table tr:nth-child(even) td { background: #f8fafc; }
        .empty-state { text-align: center; color: #64748b; font-style: italic; padding: 16px !important; }
        .exec-summary { margin-bottom: 12px; padding: 7px 9px; border: 1px solid #dbe3ec; background: #f8fafc; }
        .print-toolbar { text-align: center; padding: 10px; background: #fef3c7; margin-bottom: 12px; }
        .print-btn { padding: 8px 16px; background: #2563eb; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        .footer { margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 5px; text-align: center; color: #64748b; font-size: 8px; }
        @media print { body { margin: 0; } .no-print { display: none !important; } }
    </style>
</head>
<body onload="window.print()">
    <div class="print-toolbar no-print">
        Jika dialog cetak tidak terbuka otomatis, <button class="print-btn" type="button" onclick="window.print()">Cetak Laporan</button>
    </div>
    <div class="header-container">
        <table><tr>
            <td class="header-logo">
                @php($imagePath = public_path(config('branding.assets.logo')))
                @if(is_file($imagePath))
                    <img src="data:{{ mime_content_type($imagePath) }};base64,{{ base64_encode(file_get_contents($imagePath)) }}" alt="Logo">
                @endif
            </td>
            <td class="header-text">
                <div class="company-name">{{ config('branding.organization.name') }}</div>
                <div class="company-address">Laporan resmi {{ config('branding.application.name') }}</div>
            </td>
        </tr></table>
    </div>
    <div class="report-title">{{ $reportTitle ?? 'Laporan WMS' }}</div>
    <table class="metadata-table">
        <tr><td width="17%">Nomor Dokumen</td><td width="33%">: {{ $documentNumber ?? '-' }}</td><td width="17%">Dibuat Oleh</td><td>: {{ $generatedBy ?? 'Sistem' }}</td></tr>
        <tr><td>Dibuat Pada</td><td>: {{ $generatedDate ?? '-' }}</td><td>Total Data</td><td>: {{ $totalRecords ?? 0 }}</td></tr>
        @if(!empty($scopeLabel))<tr><td>Ruang Lingkup</td><td colspan="3">: {{ $scopeLabel }}</td></tr>@endif
    </table>
    @if(!empty($executive_summary))<div class="exec-summary"><table>{!! $executive_summary !!}</table></div>@endif
    @yield('content')
    <div class="footer">{{ config('branding.application.name') }} - dokumen dicetak pada {{ $generatedDate ?? '-' }}</div>
</body>
</html>
