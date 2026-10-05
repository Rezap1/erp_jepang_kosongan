<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Data Alumni Terpilih</title>
    <style>
        @page { margin: 14mm 13mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; line-height: 1.4; }
        .header { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #312e81; padding-bottom: 8px; }
        .header td { vertical-align: middle; }
        .logo { width: 55px; }
        .logo img { max-width: 45px; max-height: 45px; }
        .org { color: #4f46e5; font-size: 10px; font-weight: bold; letter-spacing: .8px; }
        h1 { margin: 1px 0 0; font-size: 17px; }
        .meta { margin-bottom: 12px; color: #64748b; }
        .meta strong { color: #172033; }
        .alumni { margin-bottom: 12px; border: 1px solid #cbd5e1; page-break-inside: avoid; }
        .alumni-head { width: 100%; border-collapse: collapse; background: #eef2ff; }
        .alumni-head td { padding: 8px 9px; vertical-align: middle; }
        .number { width: 28px; color: #6366f1; font-size: 15px; font-weight: bold; }
        .name { font-size: 12px; font-weight: bold; }
        .id { margin-top: 2px; color: #64748b; font-family: DejaVu Sans Mono, monospace; font-size: 8px; }
        .status { text-align: right; color: #166534; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .details { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .details td { width: 50%; border-top: 1px solid #e2e8f0; padding: 6px 9px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        .label { display: block; margin-bottom: 2px; color: #64748b; font-size: 7px; font-weight: bold; letter-spacing: .35px; text-transform: uppercase; }
        .value { color: #1e293b; font-size: 8.5px; }
        .footer { position: fixed; right: 0; bottom: -9mm; left: 0; border-top: .5px solid #cbd5e1; padding-top: 3px; color: #64748b; font-size: 7px; }
        .footer table { width: 100%; border-collapse: collapse; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php
        $display = static function ($value): string {
            $value = trim((string) ($value ?? ''));
            return $value !== '' && $value !== '-' ? $value : 'Belum tersedia';
        };
    @endphp

    <table class="header">
        <tr>
            <td class="logo">
                @php
                    $logoPath = public_path('img/logo.png.jpeg');
                @endphp
                @if(is_file($logoPath))<img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($logoPath)) }}" alt="Logo">@endif
            </td>
            <td>
                <div class="org">LPK WAKAMIYA</div>
                <h1>DATA ALUMNI</h1>
            </td>
        </tr>
    </table>

    <div class="meta">
        Tanggal Export: <strong>{{ $exportedAt->format('d-m-Y H:i') }} WIB</strong>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Jumlah Alumni Terpilih: <strong>{{ $alumni->count() }}</strong>
    </div>

    @foreach($alumni as $index => $row)
        @php
            $isLegacy = !empty($row['Is_Legacy']);
            $status = $isLegacy ? 'Legacy' : (\App\Helpers\SheetValue::isActive((array) $row) ? 'Aktif' : 'Nonaktif');
            $source = $isLegacy ? 'Legacy WMS' : (strtoupper((string) ($row['Source_Type'] ?? '')) === 'WMS' ? 'Dari Siswa WMS' : 'Input Manual');
        @endphp
        <section class="alumni">
            <table class="alumni-head">
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td><div class="name">{{ $display($row['Full_Name'] ?? null) }}</div><div class="id">{{ $display($row['Alumni_ID'] ?? null) }}</div></td>
                    <td class="status">{{ $status }}</td>
                </tr>
            </table>
            <table class="details">
                <tr><td><span class="label">NIK</span><span class="value">{{ $display($row['NIK'] ?? null) }}</span></td><td><span class="label">Jalur</span><span class="value">{{ $display($row['Pathway_Label'] ?? null) }}</span></td></tr>
                <tr><td><span class="label">Bidang Pekerjaan</span><span class="value">{{ $display($row['Work_Field'] ?? null) }}</span></td><td><span class="label">Wilayah Kerja</span><span class="value">{{ $display($row['Work_Region'] ?? null) }}</span></td></tr>
                <tr><td><span class="label">Nomor Visa</span><span class="value">{{ $display($row['Visa_Number'] ?? null) }}</span></td><td><span class="label">Jenis Visa</span><span class="value">{{ $display($row['Visa_Type'] ?? null) }}</span></td></tr>
                <tr><td><span class="label">Tanggal Keberangkatan</span><span class="value">{{ $display($row['Departure_Date'] ?? null) }}</span></td><td><span class="label">Sumber</span><span class="value">{{ $source }}</span></td></tr>
                <tr><td><span class="label">Program</span><span class="value">{{ $display($row['Program_Name'] ?? null) }}</span></td><td><span class="label">Batch</span><span class="value">{{ $display($row['Batch_Name'] ?? null) }}</span></td></tr>
                <tr><td><span class="label">Kelas</span><span class="value">{{ $display($row['Class_Name'] ?? null) }}</span></td><td><span class="label">Status</span><span class="value">{{ $status }}</span></td></tr>
            </table>
        </section>
    @endforeach

    <div class="footer"><table><tr><td>WAKAMIYA MANAGEMENT SYSTEM</td><td style="text-align:right">Halaman <span class="page-number"></span></td></tr></table></div>
</body>
</html>
