@extends('layouts.app')
@section('header', 'Student Billing')
@section('content')

<div class="min-w-0 max-w-full space-y-6">
    <x-page-header title="My Billing & Invoices" description="Manage and review your invoices, payment history, and overdue statements." :breadcrumbs="['Dashboard' => route('dashboard.student'), 'Billing' => '#']" />
    
    <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-2xl p-5 sm:p-6 shadow-md flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Tagihan Aktif Belum Lunas</p>
                <h3 class="text-2xl sm:text-3xl font-black text-white mt-1 break-words">
                    Rp {{ number_format(collect($myInvoices)->whereIn('Status', ['Waiting Payment', 'Partial Paid', 'OVERDUE'])->sum('Remaining_Amount'), 0, ',', '.') }}
                </h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center font-bold text-xl">💳</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Rekening Pembayaran Resmi</p>
                <p class="text-xs text-slate-500 font-medium">{{ $bank['name'] ?? 'BANK BCA' }}</p>
                <p class="text-lg font-black text-slate-800 tracking-wider mt-0.5 break-all" id="accountNumber">{{ $bank['account_number'] ?? '888-999-777' }}</p>
                <p class="text-[11px] font-bold text-slate-400 break-words">a.n. {{ $bank['account_holder'] ?? config('branding.bank.account_holder') }}</p>
            </div>
            <button onclick="copyAccountNumber()" class="p-3 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition-colors cursor-pointer border border-emerald-100 shadow-xs flex shrink-0 flex-col items-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                <span class="text-[9px] font-bold uppercase mt-1" id="copyText">Copy</span>
            </button>
        </div>
    </div>

    <script>
        function copyAccountNumber() {
            var accountNumber = document.getElementById("accountNumber").innerText;
            navigator.clipboard.writeText(accountNumber).then(function() {
                var copyText = document.getElementById("copyText");
                copyText.innerText = "Tersalin!";
                setTimeout(function() {
                    copyText.innerText = "Salin";
                }, 2000);
            });
        }
    </script>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden min-w-0">
        <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="font-bold text-slate-800">Daftar Tagihan Saya</h3>
            @if(($educationPaymentState['collectible'] ?? false) !== true)
                <span class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-800 sm:w-auto">Penagihan Tidak Aktif</span>
            @elseif(($educationPaymentState['status'] ?? null) === 'paid')
                <span class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-800 sm:w-auto">Biaya Pendidikan Lunas</span>
            @elseif((float)($educationPaymentState['remaining_payable'] ?? 0) <= 0)
                <span class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 sm:w-auto">Pembayaran sedang diproses</span>
            @else
                <a href="{{ route('student.billing.self-service') }}" class="inline-flex min-h-11 w-full items-center justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold sm:w-auto">Bayar Mandiri</a>
            @endif
        </div>
        <div class="space-y-3 p-4 md:hidden" data-mobile-billing-cards>
            @forelse($myInvoices as $item)
                @php
                    $status = $item['Display_Status'] ?? ($item['Status'] ?? 'Draft');
                    $remaining = (float)($item['Remaining_Amount'] ?? ($item['Amount'] ?? 0));
                    $paid = (float)($item['Paid_Amount'] ?? 0);
                    $amount = (float)($item['Amount'] ?? 0);
                @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm {{ $status === 'OVERDUE' ? 'border-rose-200 bg-rose-50/40' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="break-words text-sm font-black text-slate-900">{{ $item['Category'] ?? 'Tagihan Pendidikan' }}</p>
                            <p class="mt-1 break-all font-mono text-[11px] font-bold text-slate-400">{{ $item['Invoice_ID'] ?? '' }}</p>
                        </div>
                        <div class="shrink-0">
                            @if($status === 'OVERDUE')
                                <span class="inline-flex rounded-lg bg-rose-500 px-2.5 py-1 text-[10px] font-black uppercase text-white">OVERDUE</span>
                            @elseif($status === 'Paid')
                                <span class="inline-flex rounded-lg bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-800">LUNAS</span>
                            @elseif($status === 'Partial Paid')
                                <span class="inline-flex rounded-lg bg-purple-100 px-2.5 py-1 text-[10px] font-black uppercase text-purple-800">SEBAGIAN</span>
                            @elseif($status === 'Waiting Payment')
                                <span class="inline-flex rounded-lg bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase text-amber-800">MENUNGGU</span>
                            @else
                                <span class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-600">{{ $status }}</span>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Total Tagihan</dt>
                            <dd class="mt-1 font-black text-slate-900">Rp {{ number_format($amount, 0, ',', '.') }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Sudah Dibayar</dt>
                            <dd class="mt-1 font-black text-emerald-700">Rp {{ number_format($paid, 0, ',', '.') }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Sisa</dt>
                            <dd class="mt-1 font-black {{ $remaining > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rp {{ number_format($remaining, 0, ',', '.') }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Jatuh Tempo</dt>
                            <dd class="mt-1 font-bold {{ $status === 'OVERDUE' ? 'text-rose-700' : 'text-slate-800' }}">{{ !empty($item['Due_Date']) ? \App\Helpers\DateHelper::format($item['Due_Date'], 'd M Y') : '-' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <a href="{{ route('student.billing.show', $item['Invoice_ID']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white shadow-sm transition-colors hover:bg-blue-700">
                            Detail / Bayar
                        </a>
                        <a href="{{ route('student.billing.invoice-pdf', $item['Invoice_ID']) }}" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-50">
                            Unduh PDF
                        </a>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">Belum ada tagihan aktif.</div>
            @endforelse
        </div>
        <div class="app-table-responsive hidden md:block" data-desktop-billing-table>
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4">ID Tagihan</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4 text-center">Jatuh Tempo</th>
                        <th class="px-6 py-4 text-center">Total Tagihan</th>
                        <th class="px-6 py-4 text-center">Sisa Tagihan</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($myInvoices as $item)
                        @php
                            $status = $item['Display_Status'] ?? ($item['Status'] ?? 'Draft');
                            $remaining = (float)($item['Remaining_Amount'] ?? ($item['Amount'] ?? 0));
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors {{ $status === 'OVERDUE' ? 'bg-rose-50/50' : '' }}">
                            <td class="px-6 py-4 font-mono font-bold text-slate-800">{{ $item['Invoice_ID'] ?? '' }}</td>
                            <td class="px-6 py-4 font-bold text-slate-800">{{ $item['Category'] ?? '' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-xs font-bold {{ $status === 'OVERDUE' ? 'text-rose-600' : 'text-slate-700' }}">
                                    {{ !empty($item['Due_Date']) ? \App\Helpers\DateHelper::format($item['Due_Date'], 'd M Y') : '-' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-black text-slate-800">Rp {{ number_format($item['Amount'] ?? 0, 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center font-bold {{ $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                Rp {{ number_format($remaining, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($status === 'OVERDUE')
                                    <span class="px-2.5 py-1 text-[11px] font-black rounded-lg bg-rose-500 text-white uppercase shadow-xs">⚠️ OVERDUE</span>
                                @elseif($status === 'Paid')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-100 text-emerald-800 uppercase">✅ PAID</span>
                                @elseif($status === 'Partial Paid')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-purple-100 text-purple-800 uppercase">🟪 PARTIAL PAID</span>
                                @elseif($status === 'Waiting Payment')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-amber-100 text-amber-800 uppercase">⏳ MENUNGGU PEMBAYARAN</span>
                                @elseif($status === 'Cancelled')
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-200 text-slate-700 uppercase">❌ DIBATALKAN</span>
                                @else
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-100 text-slate-600 uppercase">📝 DRAF</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('student.billing.show', $item['Invoice_ID']) }}" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold rounded-xl text-xs transition-colors shadow-xs">
                                    Detail / Bayar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-slate-400">Tidak ada data tagihan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($selfServicePayments->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6 min-w-0">
            <h3 class="font-bold text-slate-800 mb-4">Pembayaran Mandiri</h3>
            <div class="space-y-3">
                @foreach($selfServicePayments as $payment)
                    <div class="flex flex-col gap-3 rounded-xl bg-slate-50 border border-slate-100 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="break-all font-mono text-xs font-bold text-slate-500">{{ $payment['Payment_ID'] ?? '' }}</p>
                            <p class="font-black text-slate-800">Rp {{ number_format($payment['Amount_Paid'] ?? 0, 0, ',', '.') }}</p>
                            @if(!empty($payment['Notes']))
                                <p class="mt-1 text-xs font-semibold text-rose-700">Catatan Finance: {{ $payment['Notes'] }}</p>
                            @endif
                        </div>
                        <div class="flex w-full flex-col items-start gap-2 sm:w-auto sm:items-end">
                            <span class="inline-flex w-fit px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 text-[11px] font-bold">{{ \App\Support\Presentation\IndonesianPresentation::status($payment['Status'] ?? 'Waiting Verification') }}</span>
                            @if(\App\Support\Finance\PaymentStatus::is($payment['Status'] ?? '', 'Need Revision'))
                                <form action="{{ route('student.billing.payment-proof.replace', $payment['Payment_ID']) }}" method="POST" enctype="multipart/form-data" class="w-full space-y-2 sm:w-72">
                                    @csrf
                                    <label class="block text-xs font-bold text-slate-700" for="proof-revision-{{ $loop->index }}">Unggah bukti pengganti</label>
                                    <input id="proof-revision-{{ $loop->index }}" type="file" name="Proof_File" accept="image/jpeg,image/png,application/pdf" required class="block min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:font-bold file:text-emerald-700">
                                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700">Kirim Ulang Bukti</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
