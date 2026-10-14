<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi - {{ $data['doc_number'] ?? 'KWI-WMS-0001' }}</title>
    @php
        $theme = strtolower($data['theme'] ?? 'emerald');
        $primaryColor = '#047857'; // emerald
        $secondaryColor = '#059669';
        $lightBg = '#ecfdf5';
        $textColor = '#064e3b';
        
        if ($theme === 'indigo') {
            $primaryColor = '#4338ca';
            $secondaryColor = '#4f46e5';
            $lightBg = '#eef2ff';
            $textColor = '#1e1b4b';
        } elseif ($theme === 'crimson') {
            $primaryColor = '#be123c';
            $secondaryColor = '#e11d48';
            $lightBg = '#fff1f2';
            $textColor = '#881337';
        }
    @endphp
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 12mm 15mm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid {{ $primaryColor }};
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header-logo {
            width: 70px;
            vertical-align: top;
        }
        .header-logo img {
            max-width: 65px;
            max-height: 65px;
            object-fit: contain;
        }
        .header-details {
            vertical-align: top;
            padding-left: 10px;
        }
        .company-name {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .company-tagline {
            font-size: 10px;
            font-weight: 800;
            color: {{ $primaryColor }};
            font-style: italic;
            margin-bottom: 3px;
        }
        .company-contact {
            font-size: 10px;
            color: #1e293b;
            font-weight: 600;
            line-height: 1.35;
        }
        .company-npwp {
            text-align: right;
            vertical-align: top;
            font-size: 9.5px;
            color: #334155;
            font-weight: 800;
            line-height: 1.35;
        }
        .doc-title-bar {
            width: 100%;
            margin-bottom: 18px;
            border-collapse: collapse;
        }
        .doc-title {
            font-size: 17px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-sub {
            font-size: 9.5px;
            color: #475569;
            font-weight: 700;
        }
        .doc-no {
            font-size: 11px;
            color: #334155;
            font-weight: 800;
        }
        .status-badge {
            font-size: 10.5px;
            font-weight: 800;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 8px;
        }
        .fields-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            page-break-inside: auto;
            break-inside: auto;
        }
        .fields-table tr { page-break-inside: avoid; break-inside: avoid; }
        .fields-table td {
            padding: 10px 0;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        .field-label {
            width: 145px;
            font-weight: 800;
            color: #334155;
            font-size: 10.5px;
        }
        .field-value {
            font-size: 11px;
            color: #0f172a;
            font-weight: 600;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .terbilang-card {
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            font-weight: 800;
            font-style: italic;
            color: #0f172a;
            font-size: 10.5px;
        }
        .bottom-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .amount-card {
            background-color: #ecfdf5;
            border: 1.5px solid #059669;
            border-radius: 8px;
            padding: 10px 14px;
            width: 220px;
        }
        .amount-label {
            font-size: 9px;
            font-weight: 900;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .amount-val {
            font-size: 18px;
            font-weight: 900;
            color: #064e3b;
        }
        .signature-box {
            width: 200px;
            text-align: center;
            float: right;
        }
        .signature-img-container {
            height: 60px;
            margin: 4px 0;
            position: relative;
        }
        .signature-img-container img {
            max-height: 55px;
            max-width: 180px;
            object-fit: contain;
        }
        .footer-watermark {
            margin-top: 50px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
            font-weight: 600;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT HEADER -->
    <table class="header-table">
        <tr>
            @if(!empty($data['company_logo']))
            <td class="header-logo">
                <img src="{{ $data['company_logo'] }}" alt="Logo">
            </td>
            @endif
            <td class="header-details">
                <div class="company-name">{{ $data['company_name'] ?? config('branding.organization.name') }}</div>
                <div class="company-tagline">{{ $data['company_tagline'] ?? 'Growing Together With Integrity' }}</div>
                <div class="company-contact">
                    {{ $data['company_address'] ?? config('branding.organization.address') }}<br>
                    Telp: {{ $data['company_phone'] ?? config('branding.organization.phone') }} | Email: {{ $data['company_email'] ?? config('branding.organization.email') }}
                </div>
            </td>
            <td class="company-npwp">
                NPWP USAHA<br>
                <span style="font-size: 10.5px; color: #0f172a;">{{ $data['company_npwp'] ?? config('branding.organization.npwp') }}</span>
            </td>
        </tr>
    </table>

    <!-- DOCUMENT TITLE BAR -->
    <table class="doc-title-bar">
        <tr>
            <td>
                <div class="doc-title">KWITANSI PEMBAYARAN</div>
                <div class="doc-sub">Bukti Transaksi Resmi Terverifikasi</div>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <span class="status-badge">{{ $data['status'] ?? 'PAID / LUNAS' }}</span>
                <span class="doc-no">No: {{ $data['doc_number'] ?? 'KWI-WMS-0001' }}</span>
            </td>
        </tr>
    </table>

    <!-- FIELDS TABLE -->
    <table class="fields-table">
        <tr>
            <td class="field-label">Telah Diterima Dari:</td>
            <td class="field-value">
                <strong style="font-size: 12px; color: #0f172a;">{{ $data['client_name'] ?? '-' }}</strong>
            </td>
        </tr>
        <tr>
            <td class="field-label" style="padding-top: 12px;">Uang Sejumlah:</td>
            <td class="field-value">
                <div class="terbilang-card">
                    {{ $data['terbilang'] ?? '# Tiga Puluh Tiga Juta Empat Ratus Lima Puluh Ribu Rupiah #' }}
                </div>
            </td>
        </tr>
        <tr>
            <td class="field-label">Untuk Pembayaran:</td>
            <td class="field-value">
                {!! nl2br(e($data['payment_for'] ?? 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang')) !!}
            </td>
        </tr>
    </table>

    <!-- BOTTOM ROW: AMOUNT CARD & SIGNATURE -->
    <table class="bottom-section">
        <tr>
            <td style="vertical-align: bottom;">
                <div class="amount-card">
                    <div class="amount-label">JUMLAH TOTAL NOMINAL:</div>
                    <div class="amount-val">
                        Rp {{ number_format($data['kwitansi_amount'] ?? 33450000, 0, ',', '.') }}
                    </div>
                </div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <div class="signature-box">
                    <div style="font-size: 10px; color: #334155; font-weight: 700;">
                        {{ $data['issue_city'] ?? config('branding.document.issue_city') }}, {{ isset($data['issue_date']) ? date('Y-m-d', strtotime($data['issue_date'])) : now()->toDateString() }}
                    </div>
                    <div class="signature-img-container">
                        @if(!empty($data['stamp']))
                            <img src="{{ $data['stamp'] }}" style="position: absolute; opacity: 0.85; max-height: 55px; left: 10px;">
                        @endif
                        @if(!empty($data['signature']))
                            <img src="{{ $data['signature'] }}" style="position: relative; z-index: 10;">
                        @endif
                    </div>
                    <div style="font-weight: 900; color: #0f172a; font-size: 11px; border-bottom: 1.5px solid #0f172a; display: inline-block; padding-bottom: 1px;">
                        {{ $data['signer_name'] ?? config('branding.document.signer_name') }}
                    </div>
                    <div style="font-size: 9.5px; color: #334155; font-weight: 700; margin-top: 2px;">
                        {{ $data['company_name'] ?? config('branding.organization.name') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- WATERMARK FOOTER -->
    <div class="footer-watermark">
        Dokumen resmi diterbitkan secara sah oleh komputer {{ $data['company_name'] ?? config('branding.organization.name') }}.
    </div>

</body>
</html>
