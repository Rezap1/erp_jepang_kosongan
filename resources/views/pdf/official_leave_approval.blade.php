<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SURAT IZIN / CUTI RESMI - {{ $leave['Leave_ID'] ?? '' }}</title>
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
            font-size: 18px;
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
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
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
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- SHARED OFFICIAL PDF HEADER (H8.4 / H8.8) -->
    @include('pdf.components.header', ['company' => $company ?? $companyProfile['company'] ?? []])

    <div style="text-align: right; margin-top: -15px; margin-bottom: 15px;">
        <div style="font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 1px;">SURAT PERSETUJUAN CUTI</div>
        <div style="font-size: 11px; font-weight: 700; color: #2563eb; font-family: monospace;">NO: {{ $leave['Document_Number'] ?? $leave['Leave_ID'] ?? '-' }}</div>
    </div>

    <!-- INFORMATION CARDS -->
    <table class="info-grid">
        <tr>
            <td>
                <div class="card">
                    <div class="card-title">Identitas Pegawai Pemohon</div>
                    <div class="field-row">
                        <span class="field-label">Nama Lengkap:</span>
                        <span class="field-value">{{ $employee['Full_Name'] ?? $leave['Employee_Name'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">No. Pegawai:</span>
                        <span class="field-value">{{ $employee['Employee_Number'] ?? $employee['NIP'] ?? '-' }}</span>
                    </div>
                </div>
            </td>
            <td>
                <div class="card">
                    <div class="card-title">Detail Cuti & Persetujuan</div>
                    <div class="field-row">
                        <span class="field-label">Tipe Cuti:</span>
                        <span class="field-value">{{ $leave['Leave_Type'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Rentang Tanggal:</span>
                        <span class="field-value">{{ $leave['Start_Date'] ?? '-' }} s/d {{ $leave['End_Date'] ?? '-' }}</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Durasi Cuti:</span>
                        <span class="field-value">{{ $leave['Duration_Days'] ?? 1 }} Hari</span>
                    </div>
                    <div class="field-row">
                        <span class="field-label">Status Cuti:</span>
                        <span class="field-value" style="color: #15803d; text-transform: uppercase;">
                            {{ \App\Support\Presentation\IndonesianPresentation::status($leave['Status'] ?? 'APPROVED') }}
                        </span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-title">Alasan Pengajuan Cuti</div>
        <div style="font-size: 11px; color: #0f172a; font-style: italic;">
            "{{ $leave['Reason'] ?? 'TIDAK ADA CATATAN' }}"
        </div>
    </div>

    <!-- SHARED OFFICIAL PDF FOOTER (H8.5 / H8.8) -->
    @include('pdf.components.footer', [
        'document' => $document ?? $companyProfile['document'] ?? [],
        'verificationUrl' => $verificationUrl ?? null,
        'qrCodeSvg' => $qrCodeSvg ?? null,
        'notice' => 'Dokumen ini diterbitkan secara elektronik oleh ' . ($company['name'] ?? config('branding.application.name')) . ' dan memiliki kekuatan verifikasi sah.'
    ])

</body>
</html>
