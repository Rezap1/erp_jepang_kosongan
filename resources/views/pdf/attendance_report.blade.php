<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 13mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #172033; font-size: 9px; line-height: 1.35; }
        .header { width: 100%; border-bottom: 2px solid #172033; padding-bottom: 7px; margin-bottom: 9px; }
        .header td { vertical-align: middle; }
        .logo { width: 54px; }
        .logo img { max-width: 45px; max-height: 45px; }
        .org { font-size: 10px; font-weight: bold; letter-spacing: .2px; }
        .company { font-size: 14px; font-weight: bold; }
        h1 { margin: 5px 0 9px; text-align: center; font-size: 14px; }
        h2 { margin: 12px 0 5px; font-size: 10px; text-transform: uppercase; }
        .meta, .summary, .report-table { width: 100%; border-collapse: collapse; }
        .meta { margin-bottom: 8px; }
        .meta td { padding: 1.5px 2px; vertical-align: top; }
        .meta .label { width: 18%; color: #596579; font-weight: bold; }
        .summary { table-layout: fixed; margin-bottom: 7px; }
        .summary td { border: 1px solid #cbd5e1; background: #f5f7fa; padding: 5px 3px; text-align: center; }
        .summary span { display: block; color: #64748b; font-size: 7px; text-transform: uppercase; }
        .summary strong { display: block; font-size: 12px; }
        .note { padding: 5px 7px; border: 1px solid #f2cf74; background: #fffbeb; color: #714b00; font-size: 7.5px; margin-bottom: 8px; }
        .report-table { table-layout: fixed; margin-bottom: 10px; page-break-inside: auto; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th { border: .5px solid #8290a4; background: #e7ecf2; padding: 4px 3px; text-align: left; font-size: 7.3px; text-transform: uppercase; }
        .report-table td { border: .5px solid #c4cdd8; padding: 3.5px 3px; vertical-align: top; overflow-wrap: break-word; }
        .report-table tbody tr:nth-child(even) td { background: #f8fafc; }
        .center { text-align: center !important; }
        .empty { padding: 15px !important; text-align: center; color: #64748b; font-style: italic; }
        .page-break { page-break-before: always; }
        .footer { position: fixed; left: 0; right: 0; bottom: -9mm; border-top: .5px solid #cbd5e1; padding-top: 3px; color: #64748b; font-size: 7px; }
        .footer table { width: 100%; border-collapse: collapse; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="logo">
                @php($logoPath = public_path(config('branding.assets.logo')))
                @if(is_file($logoPath))<img src="data:{{ mime_content_type($logoPath) }};base64,{{ base64_encode(file_get_contents($logoPath)) }}" alt="Logo">@endif
            </td>
            <td>
                <div class="org">{{ config('branding.application.short_name') }}</div>
                <div class="company">{{ config('branding.organization.name') }}</div>
            </td>
        </tr>
    </table>

    <h1>{{ $report['title'] }}</h1>
    <table class="meta">
        @if($report['is_individual'])
            <tr><td class="label">Nama Siswa</td><td>: {{ $report['student']['name'] }}</td><td class="label">NIS / ID Siswa</td><td>: {{ $report['student']['number'] }}</td></tr>
            <tr><td class="label">Kelas</td><td>: {{ $report['class']['name'] }}</td><td class="label">Jenis Rekap</td><td>: {{ $report['report_type_label'] }}</td></tr>
            <tr><td class="label">Periode</td><td>: {{ $report['period']['label'] }}</td><td class="label">Dicetak</td><td>: {{ \App\Helpers\DateHelper::format($report['printed_at'], 'd F Y, H:i') }} WIB</td></tr>
        @else
            <tr><td class="label">Kelas</td><td>: {{ $report['class']['name'] }}</td><td class="label">Jumlah Siswa</td><td>: {{ $report['student_count'] }}</td></tr>
            <tr><td class="label">Jenis Rekap</td><td>: {{ $report['report_type_label'] }}</td><td class="label">Dicetak</td><td>: {{ \App\Helpers\DateHelper::format($report['printed_at'], 'd F Y, H:i') }} WIB</td></tr>
            <tr><td class="label">Periode</td><td>: {{ $report['period']['label'] }}</td><td class="label">Guru</td><td>: {{ $report['teacher_name'] ?: '-' }}</td></tr>
        @endif
    </table>

    <table class="summary">
        <tr>
            <td><span>Total Data Kehadiran</span><strong>{{ $report['total_records'] }}</strong></td>
            @foreach($report['summary'] as $item)<td><span>{{ $item['label'] }}</span><strong>{{ $item['count'] }}</strong></td>@endforeach
        </tr>
    </table>
    <div class="note">{{ $report['absence_note'] }}</div>

    @if(! $report['is_individual'] && $report['report_type'] !== 'harian')
        <h2>Rekap Per Siswa</h2>
        <table class="report-table">
            <thead><tr><th style="width:5%">No</th><th style="width:26%">Nama Siswa</th><th style="width:15%">ID/NIS</th>@foreach($report['status_labels'] as $label)<th class="center">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($report['student_recap'] as $index => $student)
                    <tr><td class="center">{{ $index + 1 }}</td><td>{{ $student['student_name'] }}</td><td>{{ $student['student_number'] }}</td>@foreach($report['status_labels'] as $status => $label)<td class="center">{{ $student['counts'][$status] }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Detail Absensi</h2>
    <table class="report-table">
        @if($report['is_individual'])
            <thead><tr><th style="width:6%">No</th><th style="width:18%">Tanggal</th><th style="width:12%">Jam Masuk</th><th style="width:12%">Jam Pulang</th><th style="width:16%">Status</th><th style="width:36%">Catatan</th></tr></thead>
        @else
            <thead><tr><th style="width:4%">No</th><th style="width:20%">Nama</th><th style="width:12%">ID/NIS</th><th style="width:12%">Tanggal</th><th style="width:9%">Masuk</th><th style="width:9%">Keluar</th><th style="width:11%">Status</th><th style="width:23%">Keterangan</th></tr></thead>
        @endif
        <tbody>
            @forelse($report['rows'] as $index => $row)
                @if($report['is_individual'])
                    <tr><td class="center">{{ $index + 1 }}</td><td>{{ \App\Helpers\DateHelper::format($row['date'], 'd M Y') }}</td><td>{{ $row['check_in'] }}</td><td>{{ $row['check_out'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['notes'] }}</td></tr>
                @else
                    <tr><td class="center">{{ $index + 1 }}</td><td>{{ $row['student_name'] }}</td><td>{{ $row['student_number'] }}</td><td>{{ \App\Helpers\DateHelper::format($row['date'], 'd M Y') }}</td><td>{{ $row['check_in'] }}</td><td>{{ $row['check_out'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['notes'] }}</td></tr>
                @endif
            @empty
                <tr><td colspan="{{ $report['is_individual'] ? 6 : 8 }}" class="empty">Tidak ada data absensi pada periode yang dipilih.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer"><table><tr><td>{{ config('branding.application.name') }}</td><td style="text-align:right">Halaman <span class="page-number"></span></td></tr></table></div>
</body>
</html>
