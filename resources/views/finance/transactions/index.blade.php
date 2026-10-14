@extends('layouts.app')
@section('header', 'Transaksi Keuangan')
@section('content')

<x-universal.index-layout 
    title="Pencatatan Transaksi" 
    description="Kelola dan catat semua pemasukan dan pengeluaran manual (kas)."
    :breadcrumbs="['Dashboard' => route('dashboard'), 'Keuangan' => '#', 'Transaksi' => route('transactions.index')]"
>
    <x-slot:headerActions>
        @if($canMutateTransactions ?? false)
            <a href="{{ route('transactions.create') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 text-sm font-bold rounded-xl shadow-sm hover:shadow transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                Catat Transaksi
            </a>
        @endif
        <x-universal.multi-export route-prefix="transactions" />
    </x-slot:headerActions>

    <x-slot:toolbar>
        <form method="GET" action="{{ route('transactions.index') }}" class="flex flex-col sm:flex-row gap-4 items-center mb-6 w-full">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full sm:w-auto bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" placeholder="Dari Tanggal">
            <span class="text-slate-400 hidden sm:block">-</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full sm:w-auto bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors" placeholder="Sampai Tanggal">
            
            <select name="type" class="w-full sm:w-auto bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-2.5 transition-colors">
                <option value="">Semua Tipe Transaksi</option>
                <option value="Income" {{ request('type') === 'Income' ? 'selected' : '' }}>Pemasukan</option>
                <option value="Expense" {{ request('type') === 'Expense' ? 'selected' : '' }}>Pengeluaran</option>
            </select>
            
            <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl shadow-sm transition-colors text-sm whitespace-nowrap">Filter</button>
            @if(request('type') || request('date_from') || request('date_to'))
                <a href="{{ route('transactions.index') }}" class="w-full sm:w-auto px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition-colors text-sm text-center whitespace-nowrap">Reset</a>
            @endif
        </form>
    </x-slot:toolbar>

    @if(($transactionGroups ?? collect())->count() > 0)
        <div class="space-y-3 mb-6">
            @foreach($transactionGroups as $group)
                <details class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden group">
                    <summary class="cursor-pointer list-none p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-slate-50">
                        <div>
                            <h3 class="text-sm font-black text-slate-800">{{ $group['title'] }}</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">{{ $group['total'] }} transaksi tercatat</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs font-black">
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700">Masuk Rp {{ number_format($group['income'], 0, ',', '.') }}</span>
                            <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700">Keluar Rp {{ number_format($group['expense'], 0, ',', '.') }}</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">Net Rp {{ number_format($group['net'], 0, ',', '.') }}</span>
                            <span class="text-slate-400 group-open:rotate-180 transition-transform">v</span>
                        </div>
                    </summary>
                    <div class="border-t border-slate-100 divide-y divide-slate-100">
                        @foreach($group['items'] as $trx)
                            @php $isIncome = strcasecmp($trx['type'] ?? '', 'Income') === 0; @endphp
                            <a href="{{ route('transactions.show', $trx['transaction_id']) }}" class="flex flex-col gap-3 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800">{{ $trx['source']['label'] ?? '-' }}</p>
                                    <p class="text-xs font-semibold text-slate-600">{{ $trx['party']['name'] ?? '-' }} | {{ $trx['payment']['method_label'] ?? $trx['source']['type_label'] ?? '-' }} | {{ $trx['source']['status_label'] ?? '-' }}</p>
                                    <p class="mt-1 break-all text-[11px] text-slate-500 font-mono">Referensi: {{ $trx['transaction_id'] ?? '-' }}</p>
                                </div>
                                <span class="text-sm font-black shrink-0 {{ $isIncome ? 'text-emerald-600' : 'text-rose-600' }}">{{ $isIncome ? '+' : '-' }} {{ $trx['amount_label'] ?? 'Rp 0' }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    <x-universal.data-table :empty="count($transactions) === 0" empty-title="Belum Ada Transaksi" empty-description="Belum ada transaksi pengeluaran/pemasukan yang dicatat.">
        <x-slot:header>
            <th class="px-6 py-4">Transaksi</th>
            <th class="px-6 py-4">Pihak / Sumber</th>
            <th class="px-6 py-4">Nominal</th>
            <th class="px-6 py-4">Tanggal Transaksi</th>
            <th class="px-6 py-4">Akun Kas</th>
            <th class="px-6 py-4 text-right">Aksi</th>
        </x-slot:header>

        @foreach($transactions as $trx)
            @php
                $isIncome = strcasecmp($trx['type'] ?? '', 'Income') === 0;
                $badgeColor = $isIncome ? 'green' : 'red';
                $typeText = strtoupper($trx['type_label'] ?? ($isIncome ? 'PEMASUKAN' : 'PENGELUARAN'));
            @endphp
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4">
                    <div class="font-bold text-slate-800 text-sm">{{ $trx['source']['label'] ?? '-' }}</div>
                    <div class="text-[10px] text-slate-500 mt-1 break-all font-mono">Referensi: {{ $trx['transaction_id'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4">
                    <x-badge color="{{ $badgeColor }}">{{ $typeText }}</x-badge>
                    <div class="text-xs font-bold text-slate-700 mt-1">{{ $trx['party']['name'] ?? '-' }}</div>
                    <div class="text-[11px] font-semibold text-slate-500 mt-0.5">{{ $trx['source']['type_label'] ?? '-' }} | {{ $trx['source']['status_label'] ?? '-' }}</div>
                </td>
                <td class="px-6 py-4 font-black text-slate-800 text-sm {{ $isIncome ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $isIncome ? '+' : '-' }} {{ $trx['amount_label'] ?? 'Rp 0' }}
                </td>
                <td class="px-6 py-4 text-sm font-bold text-slate-700">
                    {{ $trx['date_label'] ?? '-' }}
                </td>
                <td class="px-6 py-4 font-bold text-slate-600 text-xs">
                    {{ $trx['account']['label'] ?? '-' }}
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="wms-action-group">
                        <x-universal.action-button action="detail" url="{{ route('transactions.show', $trx['transaction_id']) }}" />
                        @if($canMutateTransactions ?? false)
                            <x-universal.action-button action="edit" url="{{ route('transactions.edit', $trx['transaction_id']) }}" />
                            <x-universal.action-button action="delete" url="{{ route('transactions.destroy', $trx['transaction_id']) }}" confirmMessage="Yakin ingin membatalkan/menghapus transaksi ini?" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        
        <x-slot:pagination>
            @if(method_exists($transactions, 'links'))
                <x-universal.pagination :paginator="$transactions" />
            @endif
        </x-slot:pagination>
    </x-universal.data-table>

</x-universal.index-layout>
@endsection
