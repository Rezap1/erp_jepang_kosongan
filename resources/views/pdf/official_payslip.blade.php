<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SLIP GAJI RESMI - {{ $payroll['Payroll_Number'] ?? $payroll['Payroll_ID'] ?? '' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.5;
            background: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .company-sub {
            font-size: 10px;
            color: #64748b;
            font-weight: 700;
            margin-top: 2px;
        }
        .doc-title {
            text-align: right;
            vertical-align: bottom;
        }
        .doc-title-text {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-subtitle {
            font-size: 11px;
            font-weight: 700;
            color: #2563eb;
            font-family: monospace;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-grid td {
            vertical-align: top;
            width: 50%;
            padding: 0 5px;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .card-title {
            font-size: 10px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .field-row {
            margin-bottom: 4px;
        }
        .field-label {
            font-size: 10px;
            color: #64748b;
            display: inline-block;
            width: 110px;
        }
        .field-value {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .table-salary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .table-salary th {
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
        }
        .table-salary td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .summary-box {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .summary-box td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        .footer-table td {
            vertical-align: top;
            width: 50%;
        }
        .qr-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            width: 220px;
            text-align: center;
        }
        .signature-box {
            text-align: center;
            padding-top: 10px;
        }
        .signature-line {
            margin-top: 55px;
            border-bottom: 1px solid #0f172a;
            width: 180px;
            display: inline-block;
        }
        .disclaimer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- HEADER BRANDING -->
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">{{ $company['name'] ?? config('branding.organization.name') }}</div>
                <div class="company-sub">{{ $company['tagline'] ?? 'Human Resource & Payroll Engine' }}</div>
                <div style="font-size: 9px; color: #64748b; margin-top: 4px;">
                    {{ $company['address'] ?? '' }} &bull; {{ $company['contact'] ?? '' }}
                </div>
            </td>
            <td class="doc-title">
                <div class="doc-title-text">SLIP GAJI RESMI</div>
                <div class="doc-subtitle">NO: {{ $payroll['Payroll_Number'] ?? $payroll['Payroll_ID'] ?? '-' }}</div>
            </td>
        </tr>
    </table>

    <!-- INFORMATION CARDS -->
    <table class="info-grid">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Informasi Pegawai</div>
                    <div class="field-row">
                        <span class="field-label">Nama Lengkap:</span>
                        <span class="field-value">{{ $employee['Full_Name'] ?? 'Data pegawai tidak ditemukan' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">No. Pegawai:</span>
                        <span class="field-value">{{ $employee['Employee_Number'] ?? $employee['NIP'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">NIP / No. Pegawai:</span>
                        <span class="field-value">{{ $employee['Employee_Number'] ?? '-' }}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="card-title">Detail Penggajian</div>
                    <div class="field-row">
                        <span class="field-label">Periode Penggajian:</span>
                        <span class="field-value">{{ $payroll['Payroll_Period'] ?? date('Y-m') }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">No. Dokumen:</span>
                        <span class="field-value">{{ $payroll['Document_Number'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Status Pembayaran:</span>
                        <span class="field-value" style="color: #15803d; text-transform: uppercase;">
                            {{ \App\Support\Presentation\IndonesianPresentation::status($payroll['Status'] ?? 'Draft') }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- SALARY BREAKDOWN TABLE -->
    <div style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase;">Rincian Komponen Gaji & Potongan (Salary Breakdown):</div>
    
    <table class="table-salary">
        <thead>
            <tr>
                <th style="width: 50%;">Komponen Penerimaan (Pendapatan)</th>
                <th style="width: 50%;">Komponen Potongan (Deductions)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <!-- INCOME COLUMN -->
                <td style="vertical-align: top; border-right: 1px solid #e2e8f0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="color: #64748b;">Gaji Pokok (Base Salary):</td>
                            <td style="text-align: right; font-weight: bold;">Rp {{ number_format((float)($payroll['Base_Salary'] ?? $details['base_salary'] ?? 0), 0, ',', '.') }}</td>
                        </tr>
                        @if(($details['allowances'] ?? 0) > 0 || ($payroll['Total_Allowances'] ?? 0) > 0)
                            <tr>
                                <td style="color: #64748b;">Tunjangan & Inisiatif:</td>
                                <td style="text-align: right; font-weight: bold; color: #166534;">+ Rp {{ number_format((float)($details['allowances'] ?? $payroll['Total_Allowances'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if(($details['bonus'] ?? 0) > 0)
                            <tr>
                                <td style="color: #64748b;">Bonus / Kinerja:</td>
                                <td style="text-align: right; font-weight: bold; color: #166534;">+ Rp {{ number_format((float)$details['bonus'], 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if(($details['overtime'] ?? 0) > 0)
                            <tr>
                                <td style="color: #64748b;">Upah Lembur:</td>
                                <td style="text-align: right; font-weight: bold; color: #166534;">+ Rp {{ number_format((float)$details['overtime'], 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        <tr style="border-top: 1px solid #cbd5e1; background: #f8fafc;">
                            <td style="font-weight: 800; text-transform: uppercase;">Total Gaji Kotor (Gross):</td>
                            <td style="text-align: right; font-weight: 900; color: #0f172a;">
                                Rp {{ number_format((float)($details['gross_salary'] ?? (($payroll['Base_Salary'] ?? 0) + ($payroll['Total_Allowances'] ?? 0))), 0, ',', '.') }}
                            </td>
                        </tr>
                    </table>
                </td>

                <!-- DEDUCTION COLUMN -->
                <td style="vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse;">
                        @if(($details['late_deduction'] ?? 0) > 0)
                            <tr>
                                <td style="color: #b91c1c;">Potongan Terlambat Phase F:</td>
                                <td style="text-align: right; font-weight: bold; color: #b91c1c;">- Rp {{ number_format((float)$details['late_deduction'], 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if(($details['absence_deduction'] ?? 0) > 0)
                            <tr>
                                <td style="color: #b91c1c;">Potongan Absen / Mangkir:</td>
                                <td style="text-align: right; font-weight: bold; color: #b91c1c;">- Rp {{ number_format((float)$details['absence_deduction'], 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if(($payroll['Tax'] ?? $details['tax'] ?? 0) > 0)
                            <tr>
                                <td style="color: #64748b;">Pajak PPh21:</td>
                                <td style="text-align: right; font-weight: bold; color: #b91c1c;">- Rp {{ number_format((float)($payroll['Tax'] ?? $details['tax'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        @if(($payroll['BPJS'] ?? $details['bpjs'] ?? 0) > 0)
                            <tr>
                                <td style="color: #64748b;">Iuran BPJS:</td>
                                <td style="text-align: right; font-weight: bold; color: #b91c1c;">- Rp {{ number_format((float)($payroll['BPJS'] ?? $details['bpjs'] ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        <tr style="border-top: 1px solid #cbd5e1; background: #f8fafc;">
                            <td style="font-weight: 800; text-transform: uppercase;">Total Potongan:</td>
                            <td style="text-align: right; font-weight: 900; color: #b91c1c;">
                                - Rp {{ number_format((float)($payroll['Total_Deductions'] ?? $details['total_deduction'] ?? 0), 0, ',', '.') }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- GRAND NET SALARY SUMMARY -->
    <table class="summary-box">
        <tr>
            <td style="width: 50%;"></td>
            <td>
                <table style="width: 100%; border-collapse: collapse; border: 2px solid #0f172a; background: #f8fafc; padding: 6px 12px; border-radius: 6px;">
                    <tr>
                        <td style="font-weight: 900; font-size: 12px; text-transform: uppercase;">GAJI BERSIH DITERIMA (NET SALARY):</td>
                        <td style="text-align: right; font-weight: 900; font-size: 15px; color: #15803d;">
                            Rp {{ number_format((float)($payroll['Net_Salary'] ?? 0), 0, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- FOOTER SIGNATURE & QR CODE -->
    <table class="footer-table">
        <tr>
            <td>
                <div class="qr-box">
                    <div style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">Verifikasi Keabsahan Slip Gaji</div>
                    @if(!empty($qrCodeSvg))
                        <div style="margin: 0 auto; display: inline-block;">
                            {!! $qrCodeSvg !!}
                        </div>
                    @else
                        <div style="font-size: 9px; color: #2563eb; font-weight: bold; word-break: break-all; margin: 10px 0;">
                            {{ $verificationUrl }}
                        </div>
                    @endif
                    <div style="font-size: 8px; color: #64748b; margin-top: 4px;">Pindai QR Code untuk memverifikasi otentisitas dokumen slip gaji ini secara publik.</div>
                </div>
            </td>
            <td>
                <div class="signature-box">
                    <div style="font-size: 10px; font-weight: 700; color: #64748b;">Disetujui & Diterbitkan Oleh:</div>
                    <div style="font-size: 9px; color: #94a3b8; margin-top: 2px;">Departemen HR & Keuangan WMS</div>
                    
                    <div class="signature-line"></div>
                    <div style="font-size: 11px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        {{ $payroll['Approved_By'] ?? 'HR & Finance Director' }}
                    </div>
                    <div style="font-size: 9px; color: #64748b;">
                        Tanggal: {{ !empty($payroll['Paid_Date']) ? \App\Helpers\DateHelper::format($payroll['Paid_Date'], 'd M Y') : date('d M Y') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- DISCLAIMER -->
    <div class="disclaimer">
        Dokumen ini merupakan Slip Gaji Resmi yang diterbitkan secara elektronik oleh {{ config('branding.application.name') }}. Informasi keuangan ini bersifat rahasia dan hanya ditujukan untuk pemilik gaji yang bersangkutan.
    </div>

</body>
</html>
