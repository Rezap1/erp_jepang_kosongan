<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - {{ $slipNumber }}</title>
    @vite('resources/css/app.css')
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="p-8 flex justify-center items-start min-h-screen">
    <div class="w-[800px] bg-white shadow-xl p-12 relative">
        <div class="absolute top-12 right-12 text-right">
            <h1 class="text-3xl font-black text-slate-800 uppercase tracking-widest">Slip Gaji</h1>
            <p class="text-sm font-bold text-slate-500 mt-1">{{ $slipNumber }}</p>
        </div>
        
        <div class="flex items-center gap-4 mb-12">
            <div class="w-16 h-16 bg-slate-900 flex items-center justify-center rounded-lg">
                <span class="text-white font-black text-xl">WMS</span>
            </div>
            <div>
                <h2 class="font-bold text-slate-800">{{ $companyProfile['company']['name'] ?? $brand['organization']['name'] }}</h2>
                <p class="text-sm text-slate-500">Sistem HR & Penggajian Enterprise</p>
            </div>
        </div>
        
        <div class="grid grid-cols-2 gap-8 mb-12 text-sm border-b border-slate-200 pb-8">
            <div>
                <p class="font-bold text-slate-400 mb-1">KARYAWAN</p>
                <p class="font-black text-slate-800 text-lg">{{ $payroll['Employee_ID'] ?? 'Tidak Diketahui' }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-slate-400 mb-1">PERIODE</p>
                <p class="font-black text-slate-800 text-lg">{{ $payroll['Payroll_Period'] ?? '-' }}</p>
            </div>
        </div>
        
        <table class="w-full text-sm mb-12">
            <tr class="border-b-2 border-slate-800">
                <th class="text-left py-2 uppercase font-bold text-slate-600">Deskripsi</th>
                <th class="text-right py-2 uppercase font-bold text-slate-600">Pendapatan</th>
                <th class="text-right py-2 uppercase font-bold text-slate-600">Potongan</th>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Gaji Pokok</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Basic_Salary'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Tunjangan</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Allowance'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Bonus</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Bonus'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Lembur</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Overtime'] ?? 0, 0, ',', '.') }}</td>
                <td class="py-3 text-right">-</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Potongan</td>
                <td class="py-3 text-right">-</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Deduction'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">Pajak</td>
                <td class="py-3 text-right">-</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['Tax'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr class="border-b border-slate-100">
                <td class="py-3 font-semibold text-slate-700">BPJS</td>
                <td class="py-3 text-right">-</td>
                <td class="py-3 text-right">Rp {{ number_format($payroll['BPJS'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </table>
        
        <div class="flex justify-end mb-16">
            <div class="w-72 bg-slate-50 p-6 border border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Gaji Bersih</p>
                <p class="text-2xl font-black text-slate-900">Rp {{ number_format($payroll['Net_Salary'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        
        <div class="flex justify-between items-end border-t border-slate-200 pt-8 mt-auto">
            <div class="w-32 h-32 border-2 border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400 font-bold bg-slate-50">
                QR Placeholder
            </div>
            <div class="text-center">
                <div class="w-48 h-20 border-b-2 border-slate-800 mb-2 flex items-center justify-center text-slate-300 italic font-medium">Tanda Tangan Digital</div>
                <p class="font-bold text-slate-800">Departemen HR</p>
                <p class="text-xs text-slate-500">Dibuat: {{ date('d M Y') }}</p>
            </div>
        </div>
        
    </div>
    
    <div class="fixed top-8 left-8">
        <button onclick="window.print()" class="px-6 py-3 bg-slate-900 text-white font-bold rounded shadow-xl hover:bg-slate-800 transition-colors">Cetak PDF</button>
        <a href="javascript:history.back()" class="block mt-4 text-center text-sm font-bold text-slate-500 hover:text-slate-800">Kembali</a>
    </div>
</body>
</html>



