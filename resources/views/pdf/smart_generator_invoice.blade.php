<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $data['doc_number'] ?? 'INV-WMS-0001' }}</title>
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
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-no {
            font-size: 11.5px;
            color: #334155;
            font-weight: 800;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 10.5px;
            font-weight: 800;
            border-radius: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-unpaid {
            background-color: #fef3c7;
            color: #92400e;
            border: 1.5px solid #d97706;
        }
        .status-paid {
            background-color: #dcfce7;
            color: #166534;
            border: 1.5px solid #15803d;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 22px;
            border-collapse: collapse;
        }
        .meta-label {
            font-size: 9.5px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }
        .meta-val-title {
            font-size: 12.5px;
            font-weight: 800;
            color: #0f172a;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            page-break-inside: auto;
            break-inside: auto;
        }
        .items-table thead { display: table-header-group; }
        .items-table tr { page-break-inside: avoid; break-inside: avoid; }
        .items-table th {
            color: #334155;
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
            letter-spacing: 0.3px;
        }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            color: #0f172a;
            font-weight: 600;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .bank-box {
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 10.5px;
            color: #0f172a;
        }
        .bank-title {
            font-weight: 800;
            color: #0f172a;
            font-size: 11px;
            margin-bottom: 5px;
        }
        .grand-total-row {
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            padding-top: 6px;
        }
        .notes-box {
            font-size: 10px;
            color: #1e293b;
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
            margin-top: 45px;
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
                <div class="doc-title">TAGIHAN RESMI</div>
                <div class="doc-no">No: {{ $data['doc_number'] ?? 'INV-WMS-0001' }}</div>
            </td>
            <td style="text-align: right; vertical-align: top;">
                @if(strtoupper($data['status'] ?? '') === 'LUNAS' || strtoupper($data['status'] ?? '') === 'PAID')
                    <span class="status-badge status-paid">PAID / LUNAS</span>
                @else
                    <span class="status-badge status-unpaid">{{ $data['status'] ?? 'UNPAID' }}</span>
                @endif
            </td>
        </tr>
    </table>

    <!-- CLIENT & DATES META -->
    <table class="meta-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="meta-label">DITUJUAN KEPADA KLIEN:</div>
                <div class="meta-val-title">{{ $data['client_name'] ?? '-' }}</div>
                <div style="font-size: 10.5px; color: #1e293b; font-weight: 600; margin-top: 3px; line-height: 1.4;">
                    {!! nl2br(e($data['client_address'] ?? 'Ds. Sukareja Blok.Karanganyar RT.07/RW 03 Kec.Balongan Kab.Indramayu')) !!}
                    @if(!empty($data['client_email']))
                        <br><span style="color: #475569;">Email:</span> {{ $data['client_email'] }}
                    @endif
                </div>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table style="width: 100%; font-size: 10.5px; border-collapse: collapse;">
                    <tr>
                        <td style="color: #475569; font-weight: 700;">Tanggal Terbit:</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a;">{{ isset($data['issue_date']) ? date('Y-m-d', strtotime($data['issue_date'])) : '2026-08-04' }}</td>
                    </tr>
                    <tr>
                        <td style="color: #475569; font-weight: 700; padding-top: 4px;">Jatuh Tempo:</td>
                        <td style="text-align: right; font-weight: 800; color: #0f172a; padding-top: 4px;">{{ isset($data['due_date']) ? date('Y-m-d', strtotime($data['due_date'])) : '2026-09-02' }}</td>
                    </tr>
                    <tr>
                        <td style="color: #475569; font-weight: 700; padding-top: 4px;">Mata Uang:</td>
                        <td style="text-align: right; font-weight: 900; color: #0f172a; padding-top: 4px;">{{ $data['currency'] ?? 'IDR' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ITEMIZATION TABLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">DESKRIPSI LAYANAN / PRODUK</th>
                <th style="width: 10%; text-align: center;">QTY</th>
                <th style="width: 20%; text-align: right;">HARGA SATUAN</th>
                <th style="width: 20%; text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['items'] ?? [] as $item)
            <tr>
                <td style="font-weight: 700;">{{ $item['name'] }}</td>
                <td class="text-center">{{ $item['qty'] }}</td>
                <td class="text-right">Rp {{ number_format($item['price'], 0, ',', '.') }}</td>
                <td class="text-right" style="font-weight: 800; font-size: 11.5px;">Rp {{ number_format($item['total'] ?? ($item['qty'] * $item['price']), 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td style="font-weight: 700;">Sisa Angsuran Biaya Pengurusan Dokumen Ke Jepang</td>
                <td class="text-center">1</td>
                <td class="text-right">Rp 11.550.000</td>
                <td class="text-right" style="font-weight: 800; font-size: 11.5px;">Rp 11.550.000</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SUMMARY & BANK INFO -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <td style="width: 52%; vertical-align: top;">
                <div class="bank-box">
                    <div class="bank-title">Informasi Pembayaran Bank:</div>
                    <table style="width: 100%; font-size: 10px;">
                        <tr>
                            <td style="width: 60px; color: #475569; font-weight: 700;">Bank:</td>
                            <td><strong style="color: #0f172a;">{{ $data['bank_name'] ?? config('branding.bank.name') }}</strong></td>
                        </tr>
                        <tr>
                            <td style="color: #475569; font-weight: 700; padding-top: 3px;">No. Rek:</td>
                            <td style="padding-top: 3px;"><strong style="color: #047857; font-size: 11px;">{{ $data['bank_account'] ?? config('branding.bank.account_number') }}</strong></td>
                        </tr>
                        <tr>
                            <td style="color: #475569; font-weight: 700; padding-top: 3px;">A.N:</td>
                            <td style="padding-top: 3px;"><strong style="color: #0f172a;">{{ $data['bank_holder'] ?? config('branding.bank.account_holder') }}</strong></td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 45%; vertical-align: top;">
                <table style="width: 100%; font-size: 10.5px; border-collapse: collapse;">
                    <tr>
                        <td style="color: #475569; font-weight: 700;">Subtotal:</td>
                        <td class="text-right" style="font-weight: 800; color: #0f172a;">Rp {{ number_format($data['subtotal'] ?? 11550000, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="color: #475569; font-weight: 700; padding-top: 3px;">PPN ({{ $data['ppn_percent'] ?? 0 }}%):</td>
                        <td class="text-right" style="font-weight: 800; color: #0f172a; padding-top: 3px;">Rp {{ number_format($data['ppn_amount'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                    @if(($data['shipping'] ?? 0) > 0)
                    <tr>
                        <td style="color: #475569; font-weight: 700; padding-top: 3px;">Ongkir/Lainnya:</td>
                        <td class="text-right" style="font-weight: 800; color: #0f172a; padding-top: 3px;">Rp {{ number_format($data['shipping'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total-row">
                        <td style="padding-top: 8px;">Grand Total:</td>
                        <td class="text-right" style="padding-top: 8px; color: #0f172a; font-size: 13.5px; font-weight: 900;">
                            Rp {{ number_format($data['grand_total'] ?? 11550000, 0, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- NOTES & SIGNATURE -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="notes-box">
                    <strong style="color: #0f172a; font-size: 10.5px;">Catatan & Ketentuan:</strong><br>
                    <span style="font-style: italic; color: #1e293b; font-weight: 600;">
                        {!! nl2br(e($data['notes'] ?? 'Pembayaran dilakukan ke rekening resmi yang tercantum pada dokumen.')) !!}
                    </span>
                </div>
            </td>
            <td style="width: 45%; vertical-align: top; text-align: center;">
                <div class="signature-box">
                    <div style="font-size: 10px; color: #334155; font-weight: 700;">Hormat Kami,</div>
                    <div class="signature-img-container">
                        @if(!empty($data['stamp']))
                            <img src="{{ $data['stamp'] }}" style="position: absolute; opacity: 0.85; max-height: 55px; left: 10px;">
                        @endif
                        @if(!empty($data['signature']))
                            <img src="{{ $data['signature'] }}" style="position: relative; z-index: 10;">
                        @endif
                    </div>
                    <div style="font-weight: 900; color: #0f172a; font-size: 11px; border-bottom: 1.5px solid #0f172a; display: inline-block; padding-bottom: 1px;">
                        {{ $data['company_name'] ?? config('branding.organization.name') }}
                    </div>
                    <div style="font-size: 9.5px; color: #334155; font-weight: 700; margin-top: 2px;">
                        {{ $data['signer_title'] ?? 'Finance & Accounting Dept.' }}
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
