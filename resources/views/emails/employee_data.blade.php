<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Profil Karyawan</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.6; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { background: #1e293b; color: #ffffff; padding: 25px 30px; text-align: center; }
        .header h2 { margin: 0; font-size: 22px; font-weight: 600; }
        .header p { margin: 5px 0 0 0; opacity: 0.8; font-size: 13px; }
        .content { padding: 30px; }
        .section-title { font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 25px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table th { width: 35%; text-align: left; padding: 8px 12px; font-size: 13px; color: #64748b; background: #f8fafc; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .info-table td { width: 65%; padding: 8px 12px; font-size: 13px; color: #1e293b; font-weight: 500; border-bottom: 1px solid #f1f5f9; }
        .badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 4px; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ config('branding.application.name') }}</h2>
            <p>Informasi Data Profil Karyawan Resmi</p>
        </div>
        <div class="content">
            <p>Halo,</p>
            <p>Berikut adalah ringkasan data profil karyawan untuk <strong>{{ $employee['Full_Name'] ?? 'Karyawan' }}</strong> ({{ $employee['Employee_Number'] ?? '-' }}):</p>

            <!-- A. Personal Information -->
            <div class="section-title">A. Informasi Pribadi</div>
            <table class="info-table">
                <tr><th>Nama Lengkap</th><td>{{ $employee['Full_Name'] ?? '-' }}</td></tr>
                <tr><th>Jenis Kelamin</th><td>{{ $employee['Gender'] ?? '-' }}</td></tr>
                <tr><th>Tempat, Tgl Lahir</th><td>{{ ($employee['Birth_Place'] ?? '-') . ', ' . ($employee['Birth_Date'] ?? '-') }}</td></tr>
                <tr><th>NIK (KTP)</th><td><strong>{{ $employee['National_ID'] ?? '-' }}</strong></td></tr>
                <tr><th>Alamat</th><td>{{ $employee['Address'] ?? '-' }}</td></tr>
            </table>

            <!-- B. Contact Information -->
            <div class="section-title">B. Informasi Kontak</div>
            <table class="info-table">
                <tr><th>Email</th><td>{{ $employee['Email'] ?? '-' }}</td></tr>
                <tr><th>Nomor Telepon</th><td>{{ trim((string) ($employee['Phone_Number'] ?? '')) !== '' ? $employee['Phone_Number'] : 'Belum diisi' }}</td></tr>
            </table>

            <!-- C. Employment Information -->
            <div class="section-title">C. Informasi Kepegawaian</div>
            <table class="info-table">
                <tr><th>NIP / No. Karyawan</th><td>{{ $employee['Employee_Number'] ?? '-' }}</td></tr>
                <tr><th>ID Karyawan</th><td>{{ $employee['Employee_ID'] ?? '-' }}</td></tr>
                <tr><th>Departemen</th><td>{{ $employee['Department_Name'] ?? '-' }}</td></tr>
                <tr><th>Jabatan</th><td>{{ $employee['Position_Name'] ?? '-' }}</td></tr>
                <tr><th>Tanggal Bergabung</th><td>{{ $employee['Join_Date'] ?? '-' }}</td></tr>
                <tr><th>Status Kerja</th><td>{{ \App\Support\Presentation\IndonesianPresentation::status($employee['Employment_Status'] ?? null) }}</td></tr>
                <tr><th>Status Akun</th><td>
                    @if(($employee['Is_Active'] ?? 'TRUE') === 'TRUE')
                        <span class="badge badge-active">AKTIF</span>
                    @else
                        <span class="badge badge-inactive">NONAKTIF</span>
                    @endif
                </td></tr>
            </table>

            <!-- D. Tax Information -->
            <div class="section-title">D. Informasi Perpajakan</div>
            <table class="info-table">
                <tr><th>NPWP / Tax Number</th><td><strong>{{ $employee['Tax_Number'] ?? '-' }}</strong></td></tr>
            </table>

            <!-- E. Banking Information -->
            <div class="section-title">E. Informasi Perbankan</div>
            <table class="info-table">
                <tr><th>Nama Bank</th><td>{{ $employee['Bank_Name'] ?? '-' }}</td></tr>
                <tr><th>Nomor Rekening</th><td><strong>{{ $employee['Bank_Account_Number'] ?? '-' }}</strong></td></tr>
                <tr><th>Nama Pemilik Rekening</th><td>{{ $employee['Account_Holder_Name'] ?? '-' }}</td></tr>
            </table>

            <p style="margin-top: 25px; font-size: 12px; color: #64748b; font-style: italic;">
                Catatan: Informasi sensitif (NIK, NPWP, Nomor Rekening) pada email ini telah dilindungi sesuai dengan kebijakan keamanan informasi EPS Rev.1.0.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('branding.application.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
