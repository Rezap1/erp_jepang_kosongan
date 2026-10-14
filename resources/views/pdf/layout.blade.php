<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $document_meta['title'] ?? 'Document' }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: table;
            width: 100%;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }
        .header-right {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            width: 40%;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
        }
        .doc-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0 5px 0;
        }
        .meta-info {
            font-size: 10px;
            color: #64748b;
        }
        .footer {
            position: fixed;
            bottom: -10px;
            left: 0px;
            right: 0px;
            height: 30px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        th {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1 class="company-name">{{ config('branding.application.name') }}</h1>
            <h2 class="doc-title">{{ $document_meta['title'] ?? 'Document' }}</h2>
        </div>
        <div class="header-right">
            <div class="meta-info">
                <strong>No Dok:</strong> {{ $document_meta['document_id'] ?? '-' }}<br>
                <strong>Tanggal:</strong> {{ $document_meta['generated_at'] ?? date('Y-m-d') }}<br>
                <strong>Versi:</strong> {{ $document_meta['version'] ?? 'v1' }}
            </div>
        </div>
    </div>

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        Dokumen ini dibuat secara otomatis oleh {{ config('branding.application.name') }}.
    </div>
</body>
</html>
