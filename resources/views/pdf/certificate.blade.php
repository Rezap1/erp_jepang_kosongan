@extends('pdf.layout')

@section('content')
<div style="text-align: center; margin-top: 50px;">
    <h1 style="font-size: 36px; color: #1e3a8a; margin-bottom: 5px;">SERTIFIKAT KELULUSAN</h1>
    <h2 style="font-size: 20px; color: #64748b; font-weight: normal; margin-top: 0;">Ini untuk menyatakan bahwa</h2>
    
    <h1 style="font-size: 48px; color: #0f172a; margin: 40px 0;">{{ $student['Full_Name'] ?? 'Nama Siswa' }}</h1>
    
    <p style="font-size: 16px; color: #334155; margin: 20px 80px; line-height: 1.6;">
        Telah berhasil menyelesaikan <strong>{{ $program['Program_Name'] ?? 'Program Pelatihan' }}</strong> di {{ config('branding.application.name') }} dan telah menunjukkan dedikasi serta kemampuan yang luar biasa dalam menguasai semua mata pelajaran yang disyaratkan.
    </p>

    <div style="margin-top: 80px;">
        <table style="width: 60%; margin: 0 auto; border: none;">
            <tr>
                <td style="border: none; text-align: center;">
                    <div style="border-bottom: 1px solid #333; width: 80%; margin: 0 auto; padding-bottom: 5px; font-weight: bold;">
                        {{ $director ?? 'Direktur' }}
                    </div>
                    <span style="font-size: 12px; color: #64748b;">Direktur, WMS</span>
                </td>
                <td style="border: none; text-align: center;">
                    <div style="border-bottom: 1px solid #333; width: 80%; margin: 0 auto; padding-bottom: 5px; font-weight: bold;">
                        {{ date('d F Y', strtotime($certificate['Issue_Date'] ?? $document_meta['generated_at'] ?? 'now')) }}
                    </div>
                    <span style="font-size: 12px; color: #64748b;">Tanggal Diterbitkan</span>
                </td>
            </tr>
        </table>
    </div>
</div>
@endsection
