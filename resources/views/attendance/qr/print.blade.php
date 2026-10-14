<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Presensi {{ $qr['QR_TYPE'] === 'STUDENT' ? 'Siswa' : 'Pegawai' }} - {{ $qr['LABEL'] ?? config('branding.organization.name') }}</title>
    @php $isPdf = $isPdf ?? request()->has('pdf'); @endphp
    @if(!$isPdf)
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        /* Base styles suitable for dompdf and browser */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #111827;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .print-container {
            width: 100%;
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            box-sizing: border-box;
            border: 1px solid #e2e8f0;
            min-height: 297mm; /* A4 height */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #0369a1;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .sys-name {
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 40px;
        }
        .title {
            font-size: 32px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .location {
            font-size: 18px;
            color: #334155;
            font-weight: 500;
            margin-bottom: 40px;
        }
        .qr-wrapper {
            padding: 20px;
            background: #ffffff;
            border: 4px solid #0f172a;
            border-radius: 24px;
            display: inline-block;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .qr-image {
            width: 300px;
            height: 300px;
            display: block;
        }
        .instructions {
            font-size: 16px;
            color: #475569;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .sub-instructions {
            font-size: 14px;
            color: #64748b;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background-color: #10b981;
            color: white;
            font-weight: bold;
            border-radius: 9999px;
            font-size: 14px;
            margin-bottom: 40px;
            letter-spacing: 1px;
        }
        .status-inactive {
            background-color: #ef4444;
        }
        .footer {
            margin-top: auto;
            padding-top: 40px;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px dashed #cbd5e1;
            width: 80%;
        }

        /* Print Specific Styles */
        @media print {
            body {
                background-color: #ffffff;
            }
            .print-container {
                border: none;
                box-shadow: none;
                padding: 0;
                width: 210mm;
                height: 297mm;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }

        /* Action Buttons (Browser Only) */
        .actions {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #0ea5e9;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-secondary {
            background-color: #475569;
        }
    </style>
</head>
<body>

    @if(!$isPdf)
    <div class="actions no-print">
        <button onclick="window.print()" class="btn">🖨️ Cetak QR (A4)</button>
        <a href="{{ route('attendance.qr.pdf', $qr['QR_ID']) }}" class="btn btn-secondary">⬇️ Unduh PDF</a>
        <a href="{{ route('attendance.qr.index') }}" class="btn btn-secondary">Kembali</a>
    </div>
    @endif

    <div class="print-container">
        <div>
            <div class="logo">{{ config('branding.application.short_name') }}</div>
            <div class="sys-name">Management System</div>
        </div>

        <div class="title">
            PRESENSI {{ $qr['QR_TYPE'] === 'STUDENT' ? 'SISWA' : 'PEGAWAI' }}
        </div>
        
        <div class="location">
            {{ $qr['LABEL'] ?? 'Lokasi Presensi' }}
        </div>

        @inject('qrService', 'App\Services\Core\PermanentQrService')
        @php
            $qrUrl = $qrService->getCanonicalQrUrl($qr);
            
            // EPS Rev.5.1: Generate Local PNG Base64 to guarantee DomPDF compatibility
            $options = new \chillerlan\QRCode\QROptions([
                'version'         => \chillerlan\QRCode\Common\Version::AUTO,
                'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
                'eccLevel'        => \chillerlan\QRCode\Common\EccLevel::M,
                'scale'           => 8,
                'outputBase64'    => true,
                'margin'          => 2,
            ]);
            
            $qrBase64 = (new \chillerlan\QRCode\QRCode($options))->render($qrUrl);
        @endphp

        <div class="qr-wrapper">
            <img src="{{ $qrBase64 }}" alt="QR Code" class="qr-image" style="width: 220px; height: 220px; object-fit: contain;">
        </div>

        @if(strtoupper($qr['STATUS'] ?? '') === 'ACTIVE')
            <div class="status-badge">🟢 STATUS: AKTIF</div>
        @else
            <div class="status-badge status-inactive">🔴 STATUS: TIDAK AKTIF</div>
        @endif

        <div class="instructions">Scan QR Code ini menggunakan akun WMS Anda.</div>
        <div class="sub-instructions">Pastikan GPS aktif & Anda berada di area LPK.</div>

        <div class="footer">
            Identifier: {{ $qr['IDENTIFIER'] }}<br>
            WMS Enterprise Dynamic QR Security Engine EPS Rev.5.0
        </div>
    </div>

    @if(!$isPdf)
    <script>
        // Auto-print prompt if route name is 'attendance.qr.print'
        if (window.location.href.includes('/print')) {
            setTimeout(() => {
                window.print();
            }, 1000);
        }
    </script>
    @endif
</body>
</html>
