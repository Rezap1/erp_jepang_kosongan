@extends('layouts.app')

@section('header', 'Detail Transaksi')

@section('content')
@php
    $tx = $presentation;
    $sourceUrl = $tx['source']['url'] ?? null;
    $sourceType = strtolower((string) ($tx['source']['type'] ?? ''));
    $canOpenSource = $sourceUrl
        && (($sourceType === 'payment' || $sourceType === 'paymentreversal') ? ($canAccessPayments ?? false) : true)
        && ($sourceType === 'invoice' ? ($canAccessInvoices ?? false) : true);
    $hasParty = !empty($tx['party']) && ($tx['party']['applicable'] ?? false);
    $evidenceApplicable = $tx['evidence']['applicable'] ?? false;
    $reversalApplicable = $tx['reversal']['applicable'] ?? false;
@endphp

<div class="max-w-6xl mx-auto space-y-5 pb-24">
    <x-universal.detail-layout
        title="{{ $tx['title'] ?? 'Detail Transaksi' }}"
        description="{{ $tx['description'] ?? 'Riwayat transaksi keuangan' }}"
        status="{{ $tx['type_label'] ?? 'Transaksi' }}"
        badgeColor="{{ $tx['type_color'] ?? 'slate' }}"
        :breadcrumbs="['Dasbor' => route('dashboard'), 'Keuangan' => '#', 'Riwayat Transaksi' => route('transactions.index'), 'Detail' => '#']"
    >
        <x-slot:actions>
            @if(($canAccessPayments ?? false) && !empty($tx['payment']['receipt_url']))
                <a href="{{ $tx['payment']['receipt_url'] }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white">Unduh Dokumen Pembayaran</a>
            @endif
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50">
                Kembali ke Riwayat Transaksi
            </a>
            @if($canMutateTransactions ?? false)
                <x-universal.action-button action="edit" url="{{ route('transactions.edit', $tx['transaction_id']) }}" />
            @endif
        </x-slot:actions>

        <x-slot:information>
            <div class="space-y-6">
                @if(!empty($tx['integrity_warning']))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900">
                        {{ $tx['integrity_warning'] }}
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-900 p-5 text-white">
                        <p class="text-xs font-bold uppercase text-slate-300">Nominal</p>
                        <p class="mt-2 text-2xl font-black">{{ $tx['amount_label'] }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-300">{{ $tx['type_label'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase text-slate-500">Tanggal</p>
                        <p class="mt-2 text-lg font-black text-slate-900">{{ $tx['date_label'] }}</p>
                        <p class="mt-1 break-words text-xs font-semibold text-slate-500">{{ $tx['reference_label'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase text-slate-500">Akun</p>
                        <p class="mt-2 text-base font-black text-slate-900">{{ $tx['account']['label'] }}</p>
                        @if($tx['account']['missing'] ?? false)
                            <p class="mt-1 text-xs font-semibold text-amber-700">Akun sumber tidak ditemukan di master account.</p>
                        @endif
                    </div>
                </div>

                <section class="rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Informasi Transaksi</h3>
                    <div class="mt-4 grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div><p class="text-xs font-bold text-slate-500">ID Transaksi</p><p class="break-all font-mono text-xs font-bold text-slate-800">{{ $tx['transaction_id'] }}</p></div>
                        <div><p class="text-xs font-bold text-slate-500">Jenis</p><p class="font-bold text-slate-800">{{ $tx['type_label'] }}</p></div>
                        <div><p class="text-xs font-bold text-slate-500">Kategori</p><p class="font-bold text-slate-800">{{ $tx['category_label'] }}</p></div>
                        <div class="md:col-span-2"><p class="text-xs font-bold text-slate-500">Deskripsi</p><p class="whitespace-pre-line font-semibold text-slate-700">{{ $tx['description'] }}</p></div>
                    </div>
                </section>

                <div class="grid grid-cols-1 {{ $hasParty ? 'lg:grid-cols-2' : '' }} gap-4">
                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Sumber Transaksi</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div>
                                <p class="text-xs font-bold text-slate-500">Jenis Sumber</p>
                                <p class="font-black text-slate-900">{{ $tx['source']['type_label'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500">Label Sumber</p>
                                <p class="font-black text-slate-900">{{ $tx['source']['label'] }}</p>
                            </div>
                            @if($tx['source']['official_reference_applicable'] ?? false)
                                <div>
                                    <p class="text-xs font-bold text-slate-500">{{ $tx['source']['official_reference_label'] ?? 'Nomor Referensi Resmi' }}</p>
                                    @if(!empty($tx['source']['official_reference']))
                                        <p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['source']['official_reference'] }}</p>
                                    @else
                                        <p class="font-bold text-amber-700">Tidak dapat di-resolve</p>
                                    @endif
                                </div>
                            @endif
                            @if($tx['source']['status_applicable'] ?? false)
                                <div>
                                    <p class="text-xs font-bold text-slate-500">Status</p>
                                    <p class="font-bold {{ !empty($tx['source']['status_label']) ? 'text-slate-800' : 'text-amber-700' }}">{{ $tx['source']['status_label'] ?? 'Tidak dapat di-resolve' }}</p>
                                </div>
                            @endif
                            @if($canOpenSource)
                                <a href="{{ $sourceUrl }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">
                                    Buka Detail Sumber
                                </a>
                            @endif
                        </div>
                    </section>

                    @if($hasParty)
                        <section class="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Pihak Terkait</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <div>
                                    <p class="text-xs font-bold text-slate-500">{{ $tx['party']['type_label'] }}</p>
                                    <p class="font-black text-slate-900">{{ $tx['party']['name'] }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-500">Konteks</p>
                                    <p class="font-semibold text-slate-700">{{ $tx['party']['context'] }}</p>
                                </div>
                            </div>
                        </section>
                    @endif
                </div>

                @if(!empty($tx['payment']))
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Pembayaran</h3>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                            <div><p class="text-xs font-bold text-slate-500">ID Pembayaran</p><p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['payment']['id'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Nominal Dibayar</p><p class="font-black text-slate-900">{{ $tx['payment']['amount_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Metode</p><p class="font-bold text-slate-800">{{ $tx['payment']['method_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Tanggal Bayar</p><p class="font-bold text-slate-800">{{ $tx['payment']['date_label'] }}</p></div>
                            @if(!empty($tx['payment']['invoice_id']))
                                <div><p class="text-xs font-bold text-slate-500">ID Invoice</p><p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['payment']['invoice_id'] }}</p></div>
                            @endif
                            <div><p class="text-xs font-bold text-slate-500">Nomor Kwitansi</p><p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['payment']['receipt_number'] }}</p></div>
                            @if(!empty($tx['payment']['reference_number']))
                                <div><p class="text-xs font-bold text-slate-500">Nomor Referensi</p><p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['payment']['reference_number'] }}</p></div>
                            @endif
                            <div><p class="text-xs font-bold text-slate-500">Status Verifikasi</p><p class="font-bold text-slate-800">{{ $tx['payment']['status_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Diverifikasi Oleh</p><p class="font-bold text-slate-800">{{ $tx['payment']['verified_by'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Waktu Verifikasi</p><p class="font-bold text-slate-800">{{ $tx['payment']['verified_at'] }}</p></div>
                            @if(!empty($tx['payment']['notes']))
                                <div class="md:col-span-2 lg:col-span-3"><p class="text-xs font-bold text-slate-500">Catatan Pembayaran</p><p class="whitespace-pre-line font-semibold text-slate-700">{{ $tx['payment']['notes'] }}</p></div>
                            @endif
                        </div>
                    </section>
                @endif

                @if($evidenceApplicable)
                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Bukti Pembayaran</h3>
                        <div class="mt-4">
                            @if($tx['evidence']['available'] ?? false)
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <a href="{{ $tx['evidence']['download_url'] }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800">
                                        {{ $tx['evidence']['label'] }}
                                    </a>
                                    @if($tx['evidence']['is_pdf'])
                                        <a href="{{ $tx['evidence']['inline_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                            Buka PDF
                                        </a>
                                    @endif
                                </div>
                                @if(!$tx['evidence']['is_pdf'])
                                    <button type="button" onclick="document.getElementById('payment-proof-modal').classList.remove('hidden')" class="mt-4 block max-w-md overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm">
                                        <img src="{{ $tx['evidence']['inline_url'] }}" alt="Bukti Pembayaran" class="h-auto max-h-80 w-full object-contain">
                                    </button>
                                @else
                                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-semibold text-slate-600">
                                        Bukti tersedia dalam format PDF.
                                    </div>
                                @endif
                            @else
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                                    {{ $tx['evidence']['message'] ?? 'Bukti pembayaran tidak tersedia.' }}
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                @if(!empty($tx['invoice']))
                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Tagihan</h3>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                            <div><p class="text-xs font-bold text-slate-500">Nomor Invoice</p><p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['invoice']['number'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Nominal Invoice</p><p class="font-black text-slate-900">{{ $tx['invoice']['amount_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Sisa</p><p class="font-bold text-slate-800">{{ $tx['invoice']['remaining_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Status</p><p class="font-bold text-slate-800">{{ $tx['invoice']['status_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Jatuh Tempo</p><p class="font-bold text-slate-800">{{ $tx['invoice']['due_date_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Kategori</p><p class="font-bold text-slate-800">{{ $tx['invoice']['category'] }}</p></div>
                            @if(!empty($tx['invoice']['period']))
                                <div><p class="text-xs font-bold text-slate-500">Periode</p><p class="font-bold text-slate-800">{{ $tx['invoice']['period'] }}</p></div>
                            @endif
                            @if(!empty($tx['invoice']['description']))
                                <div class="md:col-span-2 lg:col-span-4"><p class="text-xs font-bold text-slate-500">Deskripsi Invoice</p><p class="whitespace-pre-line font-semibold text-slate-700">{{ $tx['invoice']['description'] }}</p></div>
                            @endif
                        </div>
                    </section>
                @elseif(($tx['payment']['invoice_optional'] ?? false) === true)
                    <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm font-semibold text-emerald-800">
                        Pembayaran mandiri ini valid tanpa invoice. Detail pembayaran dan bukti tetap menjadi sumber audit utama.
                    </section>
                @elseif(($tx['payment']['invoice_missing'] ?? false) === true)
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-900">
                        Invoice terkait tidak ditemukan. Transaksi dan pembayaran tetap ditampilkan tanpa menyebabkan error.
                    </section>
                @endif

                @if(!empty($tx['payroll']))
                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Penggajian</h3>
                        <div class="mt-4 grid grid-cols-1 gap-4 text-sm md:grid-cols-2 lg:grid-cols-4">
                            <div><p class="text-xs font-bold text-slate-500">Nomor Payroll</p><p class="break-all font-mono text-xs font-bold text-slate-700">{{ $tx['payroll']['number'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Periode</p><p class="font-bold text-slate-800">{{ $tx['payroll']['period'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Gaji Bersih</p><p class="font-black text-slate-900">{{ $tx['payroll']['net_salary_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Status</p><p class="font-bold text-slate-800">{{ $tx['payroll']['status_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Gaji Pokok</p><p class="font-bold text-slate-800">{{ $tx['payroll']['base_salary_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Tunjangan</p><p class="font-bold text-slate-800">{{ $tx['payroll']['allowances_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Potongan</p><p class="font-bold text-slate-800">{{ $tx['payroll']['deductions_label'] }}</p></div>
                            <div><p class="text-xs font-bold text-slate-500">Tanggal Dibayar</p><p class="font-bold text-slate-800">{{ $tx['payroll']['paid_at'] }}</p></div>
                        </div>
                    </section>
                @endif
            </div>
        </x-slot:information>

        @if($reversalApplicable)
            <x-slot:related>
                <div class="space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-wide text-slate-400">Pembalikan / Koreksi</h3>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-bold text-slate-800">{{ $tx['reversal']['message'] }}</p>
                        @if(!empty($tx['reversal']['transaction']))
                            <a href="{{ $tx['reversal']['transaction']['url'] }}" class="mt-3 inline-flex max-w-full items-center justify-center rounded-lg bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                                <span class="break-all">{{ $tx['reversal']['transaction']['label'] }}: {{ $tx['reversal']['transaction']['id'] }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            </x-slot:related>
        @endif

        <x-slot:audit>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase text-slate-500">Nomor Referensi Transaksi</p>
                    <p class="mt-1 break-all font-mono text-xs font-bold text-slate-800">{{ $tx['transaction_id'] }}</p>
                </div>
                @if(!empty($tx['source']['official_reference']))
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">{{ $tx['source']['official_reference_label'] ?? 'Referensi Sumber' }}</p>
                        <p class="mt-1 break-all font-mono text-xs font-bold text-slate-800">{{ $tx['source']['official_reference'] }}</p>
                    </div>
                @endif
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase text-slate-500">Dibuat Oleh</p>
                    <p class="mt-1 font-bold text-slate-800">{{ $tx['audit']['created_by'] }}</p>
                    <p class="text-xs text-slate-500">{{ $tx['audit']['created_at'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase text-slate-500">Diperbarui Oleh</p>
                    <p class="mt-1 font-bold text-slate-800">{{ $tx['audit']['updated_by'] }}</p>
                    <p class="text-xs text-slate-500">{{ $tx['audit']['updated_at'] }}</p>
                </div>
            </div>
        </x-slot:audit>
    </x-universal.detail-layout>
</div>

@if($evidenceApplicable && ($tx['evidence']['available'] ?? false) && !($tx['evidence']['is_pdf'] ?? false))
    <div id="payment-proof-modal" class="fixed inset-0 z-[100] hidden bg-slate-900/90 p-4">
        <div class="flex h-full flex-col">
            <div class="mb-4 flex items-center justify-between text-white">
                <h3 class="text-sm font-bold">Bukti Pembayaran</h3>
                <button type="button" onclick="document.getElementById('payment-proof-modal').classList.add('hidden')" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20">Tutup</button>
            </div>
            <div class="flex min-h-0 flex-1 items-center justify-center overflow-auto">
                <img src="{{ $tx['evidence']['inline_url'] }}" alt="Bukti Pembayaran" class="max-h-full max-w-full rounded-xl object-contain shadow-2xl">
            </div>
        </div>
    </div>
@endif
@endsection
