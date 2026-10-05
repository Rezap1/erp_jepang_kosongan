<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Data Alumni - LPK Wakamiya</title>
    <style>
        @page { margin: 12mm 13mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; line-height: 1.45; }
        table { border-collapse: collapse; }
        .document-header { width: 100%; margin-bottom: 13px; border-radius: 8px; background: #172554; color: #fff; }
        .document-header td { padding: 11px 13px; vertical-align: middle; }
        .document-header .logo-cell { width: 64px; padding-right: 0; }
        .document-header img { max-width: 49px; max-height: 49px; }
        .eyebrow { color: #a5b4fc; font-size: 7px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase; }
        .document-title { margin-top: 1px; font-size: 18px; font-weight: bold; letter-spacing: .3px; }
        .document-subtitle { margin-top: 1px; color: #dbeafe; font-size: 8px; }
        .header-meta { width: 155px; text-align: right; }
        .header-meta strong { display: block; font-size: 12px; }
        .header-meta span { color: #bfdbfe; font-size: 7px; }
        .accent { height: 3px; margin: -16px 13px 12px; background: #6366f1; }
        .profile-hero { width: 100%; margin-bottom: 14px; border: 1px solid #dbe4f0; background: #f8fafc; }
        .profile-hero td { padding: 15px; vertical-align: middle; }
        .profile-photo-cell { width: 132px; border-right: 1px solid #dbe4f0; text-align: center; }
        .profile-photo { max-width: 102px; max-height: 118px; border: 3px solid #fff; }
        .photo-fallback { display: inline-block; width: 102px; height: 116px; border: 1px solid #c7d2fe; background: #e0e7ff; color: #3730a3; font-size: 31px; font-weight: bold; line-height: 116px; text-align: center; }
        .profile-name { margin: 0; color: #0f172a; font-size: 21px; font-weight: bold; line-height: 1.15; }
        .profile-id { margin-top: 4px; color: #64748b; font-family: DejaVu Sans Mono, monospace; font-size: 8px; letter-spacing: .4px; }
        .badges { margin: 9px 0 10px; }
        .badge { display: inline-block; margin-right: 5px; border-radius: 10px; padding: 3px 8px; font-size: 7px; font-weight: bold; letter-spacing: .25px; text-transform: uppercase; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #e2e8f0; color: #475569; }
        .status-legacy { background: #fef3c7; color: #92400e; }
        .pathway-tg { background: #e0e7ff; color: #4338ca; }
        .pathway-magang { background: #fef3c7; color: #b45309; }
        .pathway-engineer { background: #cffafe; color: #0e7490; }
        .pathway-unknown { background: #e2e8f0; color: #475569; }
        .hero-facts { width: 100%; }
        .hero-facts td { width: 50%; padding: 5px 10px 3px 0; }
        .hero-label, .field-label { display: block; color: #64748b; font-size: 6.5px; font-weight: bold; letter-spacing: .55px; text-transform: uppercase; }
        .hero-value { display: block; margin-top: 2px; color: #1e293b; font-size: 10px; font-weight: bold; }
        .section { margin-bottom: 12px; }
        .section-heading { margin-bottom: 6px; border-left: 3px solid #6366f1; padding: 3px 0 3px 8px; color: #1e3a8a; font-size: 9px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
        .info-grid { width: 100%; border-spacing: 6px 5px; border-collapse: separate; table-layout: fixed; }
        .info-grid td { width: 50%; border: 1px solid #e2e8f0; border-radius: 5px; background: #f8fafc; padding: 8px 9px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .field-value { display: block; margin-top: 2px; color: #172033; font-size: 9px; font-weight: bold; }
        .document-footer { position: fixed; right: 0; bottom: -11mm; left: 0; border-top: .6px solid #cbd5e1; padding-top: 4px; color: #64748b; font-size: 7px; }
        .document-footer table { width: 100%; }
        .document-footer .generated { text-align: center; }
        .multiple-intro { margin: -2px 0 10px; color: #64748b; font-size: 8px; }
        .compact-card { margin-bottom: 10px; border: 1px solid #cbd5e1; page-break-inside: avoid; }
        .compact-head { width: 100%; background: #eef2ff; }
        .compact-head td { padding: 9px 10px; vertical-align: middle; }
        .compact-photo-cell { width: 78px; text-align: center; }
        .compact-photo { max-width: 62px; max-height: 70px; border: 2px solid #fff; }
        .compact-fallback { display: inline-block; width: 62px; height: 68px; border: 1px solid #c7d2fe; background: #e0e7ff; color: #3730a3; font-size: 20px; font-weight: bold; line-height: 68px; text-align: center; }
        .compact-number { width: 27px; color: #6366f1; font-size: 15px; font-weight: bold; }
        .compact-name { color: #0f172a; font-size: 13px; font-weight: bold; line-height: 1.2; }
        .compact-id { margin-top: 2px; color: #64748b; font-family: DejaVu Sans Mono, monospace; font-size: 7px; }
        .compact-details { width: 100%; table-layout: fixed; }
        .compact-details td { width: 50%; border-top: 1px solid #e2e8f0; padding: 6px 9px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .compact-details .field-value { font-size: 8px; font-weight: normal; }
    </style>
</head>
<body>
    @php
        $display = static function ($value): string {
            $value = trim((string) ($value ?? ''));
            return $value !== '' && $value !== '-' ? $value : 'Belum tersedia';
        };
        $date = static function ($value) use ($display): string {
            $value = trim((string) ($value ?? ''));
            if ($value === '') {
                return 'Belum tersedia';
            }
            try {
                return ucfirst(\Carbon\Carbon::parse($value)->locale('id')->translatedFormat('d F Y'));
            } catch (\Throwable) {
                return $display($value);
            }
        };
        $statusFor = static function (array $row): array {
            if (!empty($row['Is_Legacy'])) {
                return ['Legacy', 'status-legacy'];
            }
            return \App\Helpers\SheetValue::isActive($row)
                ? ['Aktif', 'status-active']
                : ['Nonaktif', 'status-inactive'];
        };
        $pathwayClassFor = static function ($value): string {
            return match ($value) {
                'TG' => 'pathway-tg',
                'MAGANG' => 'pathway-magang',
                'ENGINEER' => 'pathway-engineer',
                default => 'pathway-unknown',
            };
        };
        $initialFor = static function ($value): string {
            $value = trim((string) ($value ?? ''));
            return $value !== '' ? mb_strtoupper(mb_substr($value, 0, 1)) : 'A';
        };
        $logoPath = public_path('img/logo.png.jpeg');
        $logoData = is_file($logoPath) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)) : null;
        $isSingle = $alumni->count() === 1;
        $generatedDate = ucfirst($exportedAt->copy()->locale('id')->translatedFormat('d F Y'));
    @endphp

    <table class="document-header">
        <tr>
            <td class="logo-cell">@if($logoData)<img src="{{ $logoData }}" alt="Logo LPK Wakamiya">@endif</td>
            <td>
                <div class="eyebrow">LPK WAKAMIYA</div>
                <div class="document-title">DATA ALUMNI</div>
                <div class="document-subtitle">Profil Alumni LPK Wakamiya</div>
            </td>
            <td class="header-meta"><strong>{{ $alumni->count() }}</strong><span>ALUMNI TERPILIH</span></td>
        </tr>
    </table>
    <div class="accent"></div>

    @if($isSingle)
        @php
            $row = (array) $alumni->first();
            [$status, $statusClass] = $statusFor($row);
            $source = !empty($row['Is_Legacy']) ? 'Legacy WMS' : (strtoupper((string) ($row['Source_Type'] ?? '')) === 'WMS' ? 'Dari Siswa WMS' : 'Input Manual');
        @endphp
        <table class="profile-hero">
            <tr>
                <td class="profile-photo-cell">
                    @if(!empty($row['Photo_Data_URI']))
                        <img class="profile-photo" src="{{ $row['Photo_Data_URI'] }}" alt="Foto {{ $display($row['Full_Name'] ?? null) }}">
                    @else
                        <span class="photo-fallback">{{ $initialFor($row['Full_Name'] ?? null) }}</span>
                    @endif
                </td>
                <td>
                    <h1 class="profile-name">{{ $display($row['Full_Name'] ?? null) }}</h1>
                    <div class="profile-id">{{ $display($row['Alumni_ID'] ?? null) }}</div>
                    <div class="badges">
                        <span class="badge {{ $statusClass }}">{{ $status }}</span>
                        <span class="badge {{ $pathwayClassFor($row['Pathway'] ?? null) }}">{{ $display($row['Pathway_Label'] ?? null) }}</span>
                    </div>
                    <table class="hero-facts"><tr>
                        <td><span class="hero-label">Bidang Pekerjaan</span><span class="hero-value">{{ $display($row['Work_Field'] ?? null) }}</span></td>
                        <td><span class="hero-label">Wilayah Kerja</span><span class="hero-value">{{ $display($row['Work_Region'] ?? null) }}</span></td>
                    </tr></table>
                </td>
            </tr>
        </table>

        <section class="section">
            <div class="section-heading">A. Informasi Pribadi</div>
            <table class="info-grid">
                <tr><td><span class="field-label">Alumni ID</span><span class="field-value">{{ $display($row['Alumni_ID'] ?? null) }}</span></td><td><span class="field-label">Nama</span><span class="field-value">{{ $display($row['Full_Name'] ?? null) }}</span></td></tr>
                <tr><td><span class="field-label">NIK</span><span class="field-value">{{ $display($row['NIK'] ?? null) }}</span></td><td><span class="field-label">Program</span><span class="field-value">{{ $display($row['Program_Name'] ?? null) }}</span></td></tr>
                <tr><td><span class="field-label">Batch</span><span class="field-value">{{ $display($row['Batch_Name'] ?? null) }}</span></td><td><span class="field-label">Kelas</span><span class="field-value">{{ $display($row['Class_Name'] ?? null) }}</span></td></tr>
                <tr><td><span class="field-label">Sumber</span><span class="field-value">{{ $source }}</span></td><td><span class="field-label">Status</span><span class="field-value">{{ $status }}</span></td></tr>
            </table>
        </section>

        <section class="section">
            <div class="section-heading">B. Pekerjaan &amp; Penempatan</div>
            <table class="info-grid">
                <tr><td><span class="field-label">Jalur</span><span class="field-value">{{ $display($row['Pathway_Label'] ?? null) }}</span></td><td><span class="field-label">Bidang Pekerjaan</span><span class="field-value">{{ $display($row['Work_Field'] ?? null) }}</span></td></tr>
                <tr><td><span class="field-label">Wilayah Kerja</span><span class="field-value">{{ $display($row['Work_Region'] ?? null) }}</span></td><td><span class="field-label">Tanggal Keberangkatan</span><span class="field-value">{{ $date($row['Departure_Date'] ?? null) }}</span></td></tr>
            </table>
        </section>

        <section class="section">
            <div class="section-heading">C. Informasi Visa</div>
            <table class="info-grid"><tr><td><span class="field-label">Jenis Visa</span><span class="field-value">{{ $display($row['Visa_Type'] ?? null) }}</span></td><td><span class="field-label">Nomor Visa</span><span class="field-value">{{ $display($row['Visa_Number'] ?? null) }}</span></td></tr></table>
        </section>
    @else
        <div class="multiple-intro">Ringkasan profil profesional untuk {{ $alumni->count() }} Alumni terpilih.</div>
        @foreach($alumni as $index => $profile)
            @php
                $row = (array) $profile;
                [$status, $statusClass] = $statusFor($row);
                $visa = $display($row['Visa_Type'] ?? null).' / '.$display($row['Visa_Number'] ?? null);
            @endphp
            <section class="compact-card">
                <table class="compact-head"><tr>
                    <td class="compact-number">{{ $index + 1 }}</td>
                    <td class="compact-photo-cell">
                        @if(!empty($row['Photo_Data_URI']))
                            <img class="compact-photo" src="{{ $row['Photo_Data_URI'] }}" alt="Foto {{ $display($row['Full_Name'] ?? null) }}">
                        @else
                            <span class="compact-fallback">{{ $initialFor($row['Full_Name'] ?? null) }}</span>
                        @endif
                    </td>
                    <td><div class="compact-name">{{ $display($row['Full_Name'] ?? null) }}</div><div class="compact-id">{{ $display($row['Alumni_ID'] ?? null) }}</div><div class="badges"><span class="badge {{ $statusClass }}">{{ $status }}</span><span class="badge {{ $pathwayClassFor($row['Pathway'] ?? null) }}">{{ $display($row['Pathway_Label'] ?? null) }}</span></div></td>
                </tr></table>
                <table class="compact-details">
                    <tr><td><span class="field-label">NIK</span><span class="field-value">{{ $display($row['NIK'] ?? null) }}</span></td><td><span class="field-label">Bidang Pekerjaan</span><span class="field-value">{{ $display($row['Work_Field'] ?? null) }}</span></td></tr>
                    <tr><td><span class="field-label">Wilayah Kerja</span><span class="field-value">{{ $display($row['Work_Region'] ?? null) }}</span></td><td><span class="field-label">Visa</span><span class="field-value">{{ $visa }}</span></td></tr>
                    <tr><td><span class="field-label">Tanggal Keberangkatan</span><span class="field-value">{{ $date($row['Departure_Date'] ?? null) }}</span></td><td><span class="field-label">Program / Batch / Kelas</span><span class="field-value">{{ $display($row['Program_Name'] ?? null) }} / {{ $display($row['Batch_Name'] ?? null) }} / {{ $display($row['Class_Name'] ?? null) }}</span></td></tr>
                </table>
            </section>
        @endforeach
    @endif

    <div class="document-footer"><table><tr><td>WAKAMIYA MANAGEMENT SYSTEM</td><td class="generated">Generated: {{ $generatedDate }} &bull; {{ $exportedAt->format('H:i') }} WIB</td><td style="width:126px"></td></tr></table></div>
</body>
</html>
