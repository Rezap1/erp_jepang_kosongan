@extends('layouts.app')
@section('header', 'Smart Generator Invoice & Kwitansi Pro V3')

@section('content')
<div id="smart-generator-app" x-data="smartGenerator()" x-init="init()" class="smart-generator-app space-y-6" :aria-busy="renderState === 'printing' || renderState === 'generating' ? 'true' : 'false'">

    <!-- TOP NAVBAR HEADER -->
    <div class="smart-generator-ui bg-slate-900/95 backdrop-blur-xl text-white rounded-2xl p-4 sm:p-5 border border-slate-800 shadow-2xl flex flex-wrap items-center justify-between gap-4">
        <!-- BRAND BADGE -->
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 bg-gradient-to-br from-emerald-500 via-teal-500 to-emerald-700 rounded-2xl flex items-center justify-center text-white font-black shadow-[0_4px_20px_rgba(16,185,129,0.35)] shrink-0 border border-white/20">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div>
                <h1 class="text-base font-black tracking-wide text-white flex items-center gap-2" x-text="company.company_name || 'Nama LPK / Perusahaan'"></h1>
                <p class="text-xs font-semibold text-emerald-400 flex items-center gap-1.5 mt-0.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Smart Generator Invoice & Kwitansi Pro V3
                </p>
            </div>
        </div>

        <!-- NAVIGATION TABS -->
        <div class="flex flex-wrap items-center bg-slate-950/80 p-1.5 rounded-2xl border border-slate-800/90 shadow-inner gap-1">
            <button @click="activeTab = 'kop'" :class="{'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-[0_4px_14px_rgba(16,185,129,0.35)]': activeTab === 'kop', 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50': activeTab !== 'kop'}" class="px-4 py-2 text-xs font-extrabold rounded-xl transition-all duration-200 flex items-center gap-2">
                📁 <span>Profil Kop Surat</span>
            </button>
            <button @click="activeTab = 'invoice'" :class="{'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-[0_4px_14px_rgba(16,185,129,0.35)]': activeTab === 'invoice', 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50': activeTab !== 'invoice'}" class="px-4 py-2 text-xs font-extrabold rounded-xl transition-all duration-200 flex items-center gap-2">
                📄 <span>Tagihan</span>
            </button>
            <button @click="activeTab = 'kwitansi'" :class="{'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-[0_4px_14px_rgba(16,185,129,0.35)]': activeTab === 'kwitansi', 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50': activeTab !== 'kwitansi'}" class="px-4 py-2 text-xs font-extrabold rounded-xl transition-all duration-200 flex items-center gap-2">
                📜 <span>Kwitansi</span>
            </button>
            <button @click="activeTab = 'history'" :class="{'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-[0_4px_14px_rgba(16,185,129,0.35)]': activeTab === 'history', 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50': activeTab !== 'history'}" class="px-4 py-2 text-xs font-extrabold rounded-xl transition-all duration-200 flex items-center gap-2 relative">
                ⏱️ <span>Riwayat</span>
                <span x-show="history.length > 0" x-text="history.length" class="ml-1 px-2 py-0.5 bg-emerald-500 text-slate-950 rounded-full text-[10px] font-black"></span>
            </button>
        </div>

        <!-- RIGHT ACTIONS -->
        <div class="flex items-center gap-3">
            <button @click="openEmailModal = true" class="px-4 py-2 bg-emerald-500/10 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/30 hover:border-transparent rounded-xl text-xs font-extrabold transition-all duration-200 flex items-center gap-2 shadow-sm">
                🚀 <span>Kirim Email</span>
            </button>
            <div class="flex items-center gap-2 pl-3 border-l border-slate-800">
                <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-ping"></span>
                <span class="text-xs font-bold text-slate-300">admin</span>
            </div>
        </div>
    </div>

    <!-- MAIN DUAL PANE GENERATOR GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT FORM CONTROL PANE (6 COLS) -->
        <div class="smart-generator-ui lg:col-span-6 bg-slate-900/95 backdrop-blur-xl text-slate-200 rounded-2xl p-5 sm:p-6 border border-slate-800 shadow-2xl space-y-6">
            
            <!-- HEADER PANE CONTROLS -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div>
                    <h2 class="text-sm font-black text-white uppercase tracking-wider flex items-center gap-2" x-text="getPaneTitle()"></h2>
                    <p class="text-xs text-slate-400 mt-1">Kelola input data dokumen di bawah. Pratinjau diperbarui secara real-time.</p>
                </div>
                <div class="flex items-center gap-2" x-show="activeTab === 'invoice' || activeTab === 'kwitansi'">
                    <button type="button" @click="loadSampleData()" class="px-3 py-1.5 bg-emerald-950/60 text-emerald-300 hover:bg-emerald-600 hover:text-white border border-emerald-700/50 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                        🛠️ <span>Data Contoh</span>
                    </button>
                    <button type="button" @click="resetForm()" class="px-3 py-1.5 bg-rose-950/50 text-rose-300 hover:bg-rose-600 hover:text-white border border-rose-700/50 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                        🔄 <span>Atur Ulang</span>
                    </button>
                </div>
            </div>

            <!-- MODE SWITCHER (TAGIHAN SISWA vs INVOICE MANUAL) -->
            <div x-show="activeTab === 'invoice' || activeTab === 'kwitansi'" class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800 space-y-2 shadow-inner">
                <label class="block text-[11px] font-black text-slate-400 uppercase tracking-wider">Sumber Dokumen Tagihan:</label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="setSourceMode('student_invoice')" :class="{'bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black shadow-lg ring-2 ring-emerald-400': sourceMode === 'student_invoice', 'bg-slate-800/80 text-slate-400 font-bold hover:bg-slate-700/80 hover:text-white': sourceMode !== 'student_invoice'}" class="py-3 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2.5">
                        <span class="text-base">🧑‍🎓</span> <span>TAGIHAN SISWA</span>
                    </button>
                    <button type="button" @click="setSourceMode('manual_invoice')" :class="{'bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-black shadow-lg ring-2 ring-emerald-400': sourceMode === 'manual_invoice', 'bg-slate-800/80 text-slate-400 font-bold hover:bg-slate-700/80 hover:text-white': sourceMode !== 'manual_invoice'}" class="py-3 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2.5">
                        <span class="text-base">✏️</span> <span>TAGIHAN MANUAL</span>
                    </button>
                </div>
            </div>

            <!-- STUDENT INVOICE SEARCH & PICKER PANEL -->
            <div x-show="(activeTab === 'invoice' || activeTab === 'kwitansi') && sourceMode === 'student_invoice'" class="bg-slate-950/70 border border-slate-800 p-4 sm:p-5 rounded-2xl space-y-3.5 shadow-inner">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>🔎</span> <span>Cari Tagihan Siswa dari Sistem Keuangan</span>
                    </h3>
                    <template x-if="selectedSourceId">
                        <span class="px-3 py-1 bg-emerald-950 text-emerald-300 border border-emerald-500/80 rounded-xl text-[10px] font-black tracking-wide shadow-xs">
                            ✓ Tagihan Finance Dipilih
                        </span>
                    </template>
                </div>

                <div class="flex gap-2.5">
                    <input type="text" x-model="studentSearchQuery" @keyup.enter="searchStudentInvoices()" placeholder="Cari nama / Student ID / Invoice ID..." class="flex-1 text-xs bg-slate-900 border border-slate-700/80 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 rounded-xl text-white p-3 font-medium transition-all shadow-xs">
                    <button type="button" @click="searchStudentInvoices()" class="px-4 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-xs rounded-xl flex items-center gap-1.5 shrink-0 shadow-md">
                        <span>🔍 Cari</span>
                    </button>
                </div>

                <!-- Searching Loading -->
                <div x-show="isSearching" class="text-center py-4 text-xs text-slate-400 flex items-center justify-center gap-2">
                    <span class="w-3 h-3 bg-emerald-400 rounded-full animate-ping"></span>
                    <span>Mencari tagihan siswa di sistem keuangan...</span>
                </div>

                <!-- Search Results List -->
                <div x-show="!isSearching && studentSearchResults.length > 0" class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                    <template x-for="inv in studentSearchResults" :key="inv.id">
                        <div class="bg-slate-900 p-3.5 rounded-xl border border-slate-700/80 flex items-center justify-between gap-3 hover:border-emerald-500/80 transition-all shadow-sm">
                            <div class="space-y-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-emerald-400 text-xs" x-text="inv.doc_number"></span>
                                    <span class="px-2.5 py-0.5 text-[9.5px] font-black rounded-lg uppercase tracking-wider" :class="{'bg-emerald-950 text-emerald-300 border border-emerald-700': inv.status === 'PAID' || inv.status === 'LUNAS', 'bg-amber-950 text-amber-300 border border-amber-700': inv.status !== 'PAID' && inv.status !== 'LUNAS'}" x-text="inv.status"></span>
                                </div>
                                <p class="text-xs font-black text-white truncate"><span x-text="inv.student_name"></span> (<span class="text-slate-400" x-text="inv.student_id"></span>)</p>
                                <p class="text-[10px] text-slate-400 truncate" x-text="inv.description || 'Tagihan Siswa'"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-black text-emerald-400" x-text="'Rp ' + Number(inv.grand_total || inv.amount || 0).toLocaleString('id-ID')"></p>
                                <button type="button" @click="selectStudentInvoice(inv)" class="mt-1.5 px-3 py-1.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-[11px] rounded-xl transition-all shadow-sm">
                                    PILIH INVOICE
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="!isSearching && hasSearched && studentSearchResults.length === 0" class="text-center py-4 text-xs text-slate-400 bg-slate-900/50 rounded-xl border border-slate-800">
                    Tidak ada tagihan siswa yang cocok.
                </div>

                <!-- Read-Only Banner when invoice selected -->
                <div x-show="selectedSourceId" class="bg-emerald-950/80 border border-emerald-700/80 p-3.5 rounded-xl flex items-center justify-between text-xs mt-2 shadow-sm">
                    <div class="flex items-center gap-3 text-emerald-200">
                        <span class="text-base">🔒</span>
                        <div>
                            <p class="font-black text-white">Tagihan Keuangan: <span class="font-mono text-emerald-300" x-text="selectedSourceId"></span></p>
                            <p class="text-[10px] text-emerald-400 font-medium">Data terhubung langsung dari sistem keuangan (hanya baca).</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 1: PROFIL KOP SURAT FORM -->
            <div x-show="activeTab === 'kop'" class="space-y-6" x-cloak>
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>🖼️</span> <span>Logo Perusahaan & Layout Kop</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Unggah Logo Perusahaan (PNG/JPG)</label>
                            <input type="file" @change="handleFileUpload($event, 'company_logo')" class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-gradient-to-r file:from-emerald-600 file:to-teal-600 file:text-white hover:file:opacity-90 cursor-pointer bg-slate-900 border border-slate-700/80 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Tata Letak Kop Surat (Layout)</label>
                            <select x-model="company.layout_kop" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 p-3 font-medium">
                                <option value="left">Modern Rata Kiri (Samping)</option>
                                <option value="center">Tengah</option>
                                <option value="classic">Kop Formal Klasik</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>🏢</span> <span>Identitas Perusahaan</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nama Perusahaan / Usaha</label>
                            <input type="text" x-model="company.company_name" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Slogan / Tagline Usaha</label>
                            <input type="text" x-model="company.company_tagline" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Alamat Lengkap Usaha</label>
                        <textarea x-model="company.company_address" rows="2" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">No. Telepon / WA</label>
                            <input type="text" x-model="company.company_phone" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Email Resmi Usaha</label>
                            <input type="email" x-model="company.company_email" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Website Perusahaan</label>
                            <input type="text" x-model="company.company_web" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nomor NPWP</label>
                            <input type="text" x-model="company.company_npwp" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                    </div>
                </div>

                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>🏦</span> <span>Informasi Rekening Bank Default</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nama Bank</label>
                            <input type="text" x-model="company.bank_name" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nomor Rekening</label>
                            <input type="text" x-model="company.bank_account" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium font-mono">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Atas Nama Rekening</label>
                            <input type="text" x-model="company.bank_holder" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: INVOICE GENERATOR FORM -->
            <div x-show="activeTab === 'invoice'" class="space-y-6">
                
                <!-- DOCUMENT CONFIG -->
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>⚙️</span> <span>Konfigurasi Dokumen Tagihan</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Tema Visual Dokumen</label>
                            <select x-model="invoice.theme" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                                <option value="emerald">Emerald Clean (Default)</option>
                                <option value="indigo">Indigo Executive</option>
                                <option value="crimson">Crimson Premium</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nomor Tagihan</label>
                            <input type="text" x-model="invoice.doc_number" :readonly="sourceMode === 'student_invoice'" :class="{'bg-slate-950 text-slate-500 cursor-not-allowed': sourceMode === 'student_invoice'}" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Mata Uang</label>
                            <select x-model="invoice.currency" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                                <option value="IDR">IDR (Rupiah)</option>
                                <option value="USD">USD ($)</option>
                                <option value="JPY">JPY (¥)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Tanggal Terbit</label>
                            <input type="date" x-model="invoice.issue_date" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Jatuh Tempo</label>
                            <input type="date" x-model="invoice.due_date" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Status Dokumen</label>
                            <select x-model="invoice.status" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                                <option value="UNPAID">BELUM LUNAS / UNPAID</option>
                                <option value="LUNAS">LUNAS / PAID</option>
                                <option value="OVERDUE">JATUH TEMPO / OVERDUE</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- CLIENT INFO -->
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>👤</span> <span>Informasi Klien / Penerima Tagihan</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nama Perusahaan / Klien</label>
                            <input type="text" x-model="invoice.client_name" :readonly="sourceMode === 'student_invoice'" :class="{'bg-slate-950 text-slate-500 cursor-not-allowed': sourceMode === 'student_invoice'}" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Email Klien (Direct Email)</label>
                            <input type="email" x-model="invoice.client_email" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Alamat Lengkap Klien</label>
                        <textarea x-model="invoice.client_address" rows="2" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium"></textarea>
                    </div>
                </div>

                <!-- ITEMIZED ITEMS -->
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                            <span>📦</span> <span>Rincian Barang / Jasa</span>
                        </h3>
                        <button type="button" x-show="sourceMode === 'manual_invoice'" @click="addItem()" class="px-3.5 py-1.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-md">
                            ➕ Tambah Item
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, idx) in invoice.items" :key="idx">
                            <div class="bg-slate-900/90 p-4 rounded-xl border border-slate-800 space-y-3 shadow-xs">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="w-6 h-6 rounded-lg bg-emerald-950 text-emerald-400 font-mono font-black text-[11px] flex items-center justify-center shrink-0" x-text="idx + 1"></span>
                                    <input type="text" x-model="item.name" :readonly="sourceMode === 'student_invoice'" placeholder="Nama Barang / Layanan" class="flex-1 text-xs bg-slate-950 border border-slate-700/80 rounded-xl text-white p-2.5 font-bold">
                                    <button type="button" x-show="sourceMode === 'manual_invoice'" @click="removeItem(idx)" class="text-rose-400 hover:text-rose-300 p-1.5 rounded-lg hover:bg-rose-950/50 transition-colors" title="Hapus Item">
                                        🗑️
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-3 pl-9">
                                    <div>
                                        <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">Jumlah (Qty)</label>
                                        <input type="number" x-model.number="item.qty" min="1" :readonly="sourceMode === 'student_invoice'" class="w-full text-xs bg-slate-950 border border-slate-700/80 rounded-xl text-white p-2.5 font-bold">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">Harga Satuan (Rp)</label>
                                        <input type="number" x-model.number="item.price" min="0" :readonly="sourceMode === 'student_invoice'" class="w-full text-xs bg-slate-950 border border-slate-700/80 rounded-xl text-white p-2.5 font-bold">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- TOTALS & TAXES -->
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>💰</span> <span>Pengaturan Total & Pajak</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Diskon (Nominal)</label>
                            <input type="number" x-model.number="invoice.discount" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">PPN (%)</label>
                            <input type="number" x-model.number="invoice.ppn_percent" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Ongkir / Lainnya</label>
                            <input type="number" x-model.number="invoice.shipping" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                        </div>
                    </div>
                </div>

                <!-- NOTES -->
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-2">
                    <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider">Catatan & Ketentuan Pembayaran</label>
                    <textarea x-model="invoice.notes" rows="2" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium"></textarea>
                </div>
            </div>

            <!-- TAB 3: KWITANSI GENERATOR FORM -->
            <div x-show="activeTab === 'kwitansi'" class="space-y-6" x-cloak>
                <div class="bg-slate-950/60 p-4 sm:p-5 rounded-2xl border border-slate-800 space-y-4">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>📜</span> <span>Informasi Utama Kwitansi</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Tema Visual Dokumen</label>
                            <select x-model="kwitansi.theme" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                                <option value="emerald">Emerald Clean (Default)</option>
                                <option value="indigo">Indigo Modern</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">No. Kwitansi</label>
                            <input type="text" x-model="kwitansi.doc_number" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Tanggal</label>
                            <input type="date" x-model="kwitansi.issue_date" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Status Dokumen</label>
                            <select x-model="kwitansi.status" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                                <option value="PAID / LUNAS">PAID / LUNAS</option>
                                <option value="PENDING">MENUNGGU</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Telah Diterima Dari</label>
                            <input type="text" x-model="kwitansi.client_name" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Email Pembayar (Penerima Email)</label>
                            <input type="email" x-model="kwitansi.client_email" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Uang Sejumlah (Rp)</label>
                            <input type="number" x-model.number="kwitansi.kwitansi_amount" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Untuk Pembayaran</label>
                        <textarea x-model="kwitansi.payment_for" rows="2" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Kota Terbit</label>
                            <input type="text" x-model="kwitansi.issue_city" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Nama Penandatangan</label>
                            <input type="text" x-model="kwitansi.signer_name" class="w-full text-xs bg-slate-900 border border-slate-700/80 rounded-xl text-white p-3 font-bold">
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: RIWAYAT TABLE -->
            <div x-show="activeTab === 'history'" class="space-y-4" x-cloak>
                <div class="overflow-x-auto border border-slate-800 rounded-2xl bg-slate-950/60 shadow-inner">
                    <table class="w-full text-xs text-left text-slate-300">
                        <thead class="bg-slate-800/80 text-slate-400 font-black uppercase text-[10px] tracking-wider">
                            <tr>
                                <th class="p-3.5">Sumber</th>
                                <th class="p-3.5">No. Dokumen</th>
                                <th class="p-3.5">Tipe</th>
                                <th class="p-3.5">Klien</th>
                                <th class="p-3.5">Tanggal</th>
                                <th class="p-3.5 text-right">Total</th>
                                <th class="p-3.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            <template x-for="item in history" :key="item.id">
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3.5">
                                        <template x-if="item.source_type === 'student_invoice'">
                                            <span class="px-2.5 py-1 bg-emerald-950 text-emerald-300 border border-emerald-700/60 rounded-lg text-[9.5px] font-black">🧑‍🎓 TAGIHAN SISWA</span>
                                        </template>
                                        <template x-if="item.source_type !== 'student_invoice'">
                                            <span class="px-2.5 py-1 bg-slate-800 text-slate-400 border border-slate-700 rounded-lg text-[9.5px] font-black">✏️ MANUAL</span>
                                        </template>
                                    </td>
                                    <td class="p-3.5 font-mono font-bold text-emerald-400" x-text="item.doc_number"></td>
                                    <td class="p-3.5 uppercase font-black text-[10px]" x-text="item.doc_type"></td>
                                    <td class="p-3.5 font-extrabold text-white" x-text="item.client_name"></td>
                                    <td class="p-3.5 text-slate-400" x-text="item.saved_at || item.issue_date"></td>
                                    <td class="p-3.5 text-right font-black text-emerald-400" x-text="'Rp ' + Number(item.grand_total || item.kwitansi_amount || 0).toLocaleString('id-ID')"></td>
                                    <td class="p-3.5 text-center flex items-center justify-center gap-2">
                                        <button @click="loadHistoryItem(item)" class="p-2 bg-emerald-950 hover:bg-emerald-600 text-emerald-300 hover:text-white rounded-xl text-xs transition-colors shadow-xs" title="Muat ke Editor">
                                            👁️
                                        </button>
                                        <button @click="deleteHistoryItem(item.id)" class="p-2 bg-rose-950 hover:bg-rose-600 text-rose-300 hover:text-white rounded-xl text-xs transition-colors shadow-xs" title="Hapus">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="history.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-500 font-bold">
                                    Belum ada riwayat dokumen tersimpan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SIGNATURE & STAMP SECTION (FOR ALL TABS) -->
            <div x-show="activeTab !== 'history'" class="pt-5 border-t border-slate-800/80 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center gap-2">
                        <span>✍️</span> <span>Tanda Tangan & Stempel Digital</span>
                    </h3>
                    <button type="button" @click="clearSignatureCanvas()" class="text-[11px] font-extrabold text-rose-400 hover:text-rose-300 transition-colors">
                        Hapus TTD
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1.5">Bubuhkan tanda tangan di bawah atau unggah stempel PNG:</label>
                        <div class="bg-white rounded-2xl overflow-hidden p-1 border-2 border-dashed border-slate-700 shadow-inner">
                            <canvas id="signatureCanvas" width="400" height="120" class="w-full h-24 bg-white rounded-xl cursor-crosshair"></canvas>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">Unggah Stempel (PNG Transparan)</label>
                            <input type="file" @change="handleFileUpload($event, 'stamp')" class="block w-full text-xs text-slate-400 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-black file:bg-slate-800 file:text-white cursor-pointer bg-slate-900 border border-slate-700/80 rounded-xl">
                        </div>
                        <div>
                            <label class="block text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">Unggah TTD File (PNG Transparan)</label>
                            <input type="file" @change="handleFileUpload($event, 'signature')" class="block w-full text-xs text-slate-400 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-[11px] file:font-black file:bg-slate-800 file:text-white cursor-pointer bg-slate-900 border border-slate-700/80 rounded-xl">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTION BUTTONS BOTTOM -->
            <div x-show="activeTab !== 'history'" class="pt-5 border-t border-slate-800/80 flex flex-wrap items-center gap-3">
                <button type="button" @click="exportPdf()" :disabled="renderState === 'generating' || renderState === 'printing'" class="flex-1 px-5 py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 disabled:cursor-wait text-white font-black text-xs rounded-xl shadow-[0_4px_20px_rgba(16,185,129,0.35)] transition-all flex items-center justify-center gap-2">
                    📥 <span>Ekspor / Unduh PDF Presisi High</span>
                </button>
                <button type="button" @click="printDocument()" :disabled="renderState === 'generating' || renderState === 'printing'" class="px-4 py-3.5 bg-slate-800 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-wait text-slate-200 border border-slate-700 font-extrabold text-xs rounded-xl transition-all flex items-center gap-2 shadow-sm">
                    🖨️ <span>Cetak Dokumen</span>
                </button>
                <button type="button" @click="saveToHistory()" class="px-4 py-3.5 bg-emerald-700 hover:bg-emerald-600 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                    💾 <span>Simpan Ke Riwayat</span>
                </button>
            </div>

        </div>

        <!-- RIGHT LIVE CANVAS PRATINJAU (A4) PANE (6 COLS) -->
        <div class="smart-generator-preview-pane lg:col-span-6 sticky top-6">
            <div class="bg-slate-900/95 backdrop-blur-xl text-slate-200 rounded-2xl p-5 border border-slate-800 shadow-2xl space-y-4">
                <!-- LIVE PREVIEW HEADER -->
                <div class="smart-generator-ui smart-generator-preview-toolbar flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-ping"></span>
                        <h3 class="text-xs font-black text-white uppercase tracking-wider">Live Canvas Pratinjau (A4)</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="exportPdf()" :disabled="renderState === 'generating' || renderState === 'printing'" class="px-3 py-1.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 disabled:cursor-wait text-white text-[11px] font-black rounded-xl shadow-xs transition-all">
                            Ekspor PDF
                        </button>
                        <button type="button" @click="printDocument()" :disabled="renderState === 'generating' || renderState === 'printing'" class="px-3 py-1.5 bg-slate-800 text-slate-300 disabled:opacity-50 disabled:cursor-wait text-[11px] font-bold rounded-xl border border-slate-700 hover:bg-slate-700 transition-all">
                            Cetak
                        </button>
                    </div>
                </div>

                <div x-show="printError" x-cloak role="alert" aria-live="assertive" class="smart-generator-ui rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700" x-text="printError"></div>

                <!-- SCROLLABLE A4 SHEET CANVAS CONTAINER -->
                <div class="smart-generator-preview-viewport overflow-y-auto max-h-[820px] p-4 sm:p-6 bg-slate-950 rounded-2xl border border-slate-800 flex justify-center shadow-inner">
                    
                    <!-- A4 REALISTIC DOCUMENT PAPER SHEET (HIGH CONTRAST & CLEAR FONTS) -->
                    <div id="a4CanvasSheet" data-printable-document="true" class="bg-white text-slate-900 shadow-[0_20px_50px_rgba(0,0,0,0.5)] p-8 sm:p-10 w-full max-w-[210mm] min-h-[297mm] text-[11px] leading-relaxed relative flex flex-col justify-between font-sans transition-all duration-300 border border-slate-200 rounded-xs">
                        
                        <div>
                            <!-- KOP LETTERHEAD -->
                            <div class="border-b-[3px] pb-4 mb-6" :style="'border-color: ' + getThemeColors().primary">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-4">
                                        <template x-if="company.company_logo">
                                            <img :src="company.company_logo" class="w-16 h-16 object-contain shrink-0">
                                        </template>
                                        <div>
                                            <h2 class="text-base font-black text-slate-950 uppercase tracking-tight leading-snug" x-text="company.company_name || 'Nama LPK / Perusahaan'"></h2>
                                            <p class="text-[10px] font-extrabold italic mt-0.5" :style="'color: ' + getThemeColors().primary" x-text="company.company_tagline || 'Growing Together With Integrity'"></p>
                                            <p class="text-[10px] text-slate-800 font-medium leading-snug mt-1" x-text="company.company_address"></p>
                                            <p class="text-[10px] text-slate-800 font-semibold mt-0.5">
                                                Telp: <span x-text="company.company_phone"></span> | Email: <span x-text="company.company_email"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right text-[9.5px] font-bold text-slate-700 shrink-0">
                                        NPWP USAHA<br>
                                        <span class="text-slate-950 font-black text-[11px]" x-text="company.company_npwp"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- INVOICE DOCUMENT CANVAS PREVIEW -->
                            <template x-if="activeTab === 'invoice' || activeTab === 'kop'">
                                <div class="space-y-6" data-document-body="invoice">
                                    <!-- TITLE BAR -->
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h1 class="text-xl font-black text-slate-950 tracking-wider">TAGIHAN RESMI</h1>
                                            <p class="text-xs font-black text-slate-700 mt-0.5">No: <span x-text="invoice.doc_number" class="font-mono text-slate-950"></span></p>
                                        </div>
                                        <div>
                                            <span :class="{'bg-emerald-100 text-emerald-900 border-emerald-500': invoice.status === 'LUNAS' || invoice.status === 'PAID', 'bg-amber-100 text-amber-900 border-amber-600': invoice.status !== 'LUNAS' && invoice.status !== 'PAID'}" class="px-3.5 py-1.5 text-xs font-black rounded-lg border-2 uppercase tracking-wider shadow-sm" x-text="invoice.status"></span>
                                        </div>
                                    </div>

                                    <!-- META CLIENT & DATES -->
                                    <div class="grid grid-cols-2 gap-6 bg-slate-50/80 p-4 rounded-xl border border-slate-200">
                                        <div>
                                            <p class="text-[9.5px] font-black text-slate-500 uppercase tracking-wider">DITUJUAN KEPADA KLIEN:</p>
                                            <p class="text-sm font-black text-slate-950 mt-1" x-text="invoice.client_name || '-'"></p>
                                            <p class="text-[10.5px] text-slate-800 font-medium leading-normal mt-0.5" x-text="invoice.client_address"></p>
                                            <p class="text-[10.5px] text-slate-700 font-bold mt-0.5" x-text="invoice.client_email"></p>
                                        </div>
                                        <div class="text-right space-y-1.5 text-[10.5px]">
                                            <div class="flex justify-between"><span class="text-slate-600 font-bold">Tanggal Terbit:</span> <span class="font-black text-slate-950" x-text="formatDate(invoice.issue_date)"></span></div>
                                            <div class="flex justify-between"><span class="text-slate-600 font-bold">Jatuh Tempo:</span> <span class="font-black text-slate-950" x-text="formatDate(invoice.due_date)"></span></div>
                                            <div class="flex justify-between"><span class="text-slate-600 font-bold">Mata Uang:</span> <span class="font-black text-slate-950" x-text="invoice.currency"></span></div>
                                        </div>
                                    </div>

                                    <!-- ITEMS TABLE -->
                                    <div class="overflow-hidden border border-slate-200 rounded-xl">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-slate-100 text-slate-800 font-black text-[9.5px] uppercase border-b border-slate-300">
                                                <tr>
                                                    <th class="py-3 px-3">DESKRIPSI LAYANAN / PRODUK</th>
                                                    <th class="py-3 text-center px-3">QTY</th>
                                                    <th class="py-3 text-right px-3">HARGA SATUAN</th>
                                                    <th class="py-3 text-right px-3">TOTAL</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-200 text-[10.5px]">
                                                <template x-for="(item, idx) in invoice.items" :key="idx">
                                                    <tr class="hover:bg-slate-50">
                                                        <td class="py-3 px-3 font-bold text-slate-950" x-text="item.name || '-'"></td>
                                                        <td class="py-3 text-center px-3 font-bold text-slate-950" x-text="item.qty || 1"></td>
                                                        <td class="py-3 text-right px-3 font-semibold text-slate-800" x-text="'Rp ' + Number(item.price || 0).toLocaleString('id-ID')"></td>
                                                        <td class="py-3 text-right px-3 font-black text-slate-950 text-xs" x-text="'Rp ' + Number((item.qty || 1) * (item.price || 0)).toLocaleString('id-ID')"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- SUMMARY & BANK INFO -->
                                    <div class="grid grid-cols-12 gap-5 items-start pt-2">
                                        <div class="col-span-6 bg-slate-50 p-4 rounded-xl border border-slate-300 text-[10px] space-y-1">
                                            <p class="font-black text-slate-950 text-[11px] mb-1.5 uppercase tracking-wider">Informasi Pembayaran Bank:</p>
                                            <p class="text-slate-800 font-medium">Bank: <span class="font-black text-slate-950" x-text="company.bank_name"></span></p>
                                            <p class="text-slate-800 font-medium">No. Rek: <span class="font-black text-xs" :style="'color: ' + getThemeColors().primary" x-text="company.bank_account"></span></p>
                                            <p class="text-slate-800 font-medium">A.N: <span class="font-black text-slate-950" x-text="company.bank_holder"></span></p>
                                        </div>
                                        <div class="col-span-6 space-y-2 text-[10.5px] text-right">
                                            <div class="flex justify-between text-slate-700"><span class="font-bold">Subtotal:</span> <span class="font-black text-slate-950" x-text="formatRupiah(calculateSubtotal())"></span></div>
                                            <template x-if="invoice.discount > 0">
                                                <div class="flex justify-between text-rose-700"><span class="font-bold">Diskon:</span> <span class="font-black" x-text="'- ' + formatRupiah(invoice.discount)"></span></div>
                                            </template>
                                            <div class="flex justify-between text-slate-700"><span class="font-bold">PPN (<span x-text="invoice.ppn_percent"></span>%):</span> <span class="font-black text-slate-950" x-text="formatRupiah(calculatePpn())"></span></div>
                                            <template x-if="invoice.shipping > 0">
                                                <div class="flex justify-between text-slate-700"><span class="font-bold">Ongkir/Lainnya:</span> <span class="font-black text-slate-950" x-text="formatRupiah(invoice.shipping)"></span></div>
                                            </template>
                                            <div class="flex justify-between pt-2.5 border-t-2 border-slate-400 font-black text-base text-slate-950">
                                                <span>Grand Total:</span>
                                                <span x-text="formatRupiah(calculateGrandTotal())"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NOTES -->
                                    <template x-if="invoice.notes">
                                        <div class="pt-2 text-[10px] text-slate-800 bg-slate-50 p-3 rounded-xl border border-slate-200">
                                            <p class="font-black text-slate-950 text-[10.5px] mb-0.5">Catatan & Ketentuan:</p>
                                            <p x-text="invoice.notes" class="whitespace-pre-line italic font-semibold text-slate-800"></p>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- KWITANSI DOCUMENT CANVAS PREVIEW -->
                            <template x-if="activeTab === 'kwitansi'">
                                <div class="space-y-6" data-document-body="kwitansi">
                                    <!-- TITLE BAR -->
                                    <div class="flex items-center justify-between border-b-2 border-slate-300 pb-3">
                                        <div>
                                            <h1 class="text-xl font-black text-slate-950 tracking-wider uppercase">KWITANSI PEMBAYARAN</h1>
                                            <p class="text-[10px] font-extrabold text-slate-600 mt-0.5">Bukti Transaksi Resmi Terverifikasi</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs font-black uppercase mr-2.5" :style="'color: ' + getThemeColors().primary" x-text="kwitansi.status"></span>
                                            <span class="text-xs font-black text-slate-800 font-mono" x-text="'No: ' + kwitansi.doc_number"></span>
                                        </div>
                                    </div>

                                    <!-- FIELDS -->
                                    <div class="space-y-3.5 text-[11px] divide-y divide-slate-200">
                                        <div class="grid grid-cols-12 gap-3 items-center pb-3">
                                            <div class="col-span-4 font-black text-slate-700 uppercase tracking-wider text-[10px]">Telah Diterima Dari:</div>
                                            <div class="col-span-8 font-black text-slate-950 text-sm" x-text="kwitansi.client_name || '-'"></div>
                                        </div>

                                        <div class="grid grid-cols-12 gap-3 items-start py-3">
                                            <div class="col-span-4 font-black text-slate-700 uppercase tracking-wider text-[10px] pt-1">Uang Sejumlah:</div>
                                            <div class="col-span-8 bg-slate-50 p-3 rounded-xl border border-slate-300 font-extrabold italic text-slate-950 text-[11px] leading-relaxed" x-text="getTerbilang(kwitansi.kwitansi_amount)"></div>
                                        </div>

                                        <div class="grid grid-cols-12 gap-3 items-start pt-3">
                                            <div class="col-span-4 font-black text-slate-700 uppercase tracking-wider text-[10px]">Untuk Pembayaran:</div>
                                            <div class="col-span-8 text-slate-950 font-bold leading-relaxed" x-text="kwitansi.payment_for || '-'"></div>
                                        </div>
                                    </div>

                                    <!-- AMOUNT CARD -->
                                    <div class="pt-3">
                                        <div class="p-4 rounded-2xl inline-block w-60 shadow-md border-2 transition-all" :style="'background-color: ' + getThemeColors().bgLight + '; border-color: ' + getThemeColors().border">
                                            <p class="text-[9.5px] font-black uppercase tracking-wider" :style="'color: ' + getThemeColors().primary">JUMLAH TOTAL NOMINAL:</p>
                                            <p class="text-xl font-black mt-0.5" :style="'color: ' + getThemeColors().textPrimary" x-text="formatRupiah(kwitansi.kwitansi_amount)"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- SIGNATURE FOOTER FOR ALL DOCS -->
                        <div class="pt-6 mt-6 flex justify-end">
                            <div class="w-56 text-center space-y-1">
                                <p class="text-[10px] text-slate-800 font-bold" x-text="(kwitansi.issue_city || @js(config('branding.document.issue_city'))) + ', ' + formatDate(activeTab === 'kwitansi' ? kwitansi.issue_date : invoice.issue_date)"></p>
                                <div class="h-16 relative flex items-center justify-center my-1">
                                    <template x-if="company.stamp">
                                        <img :src="company.stamp" class="absolute h-16 opacity-85 z-0">
                                    </template>
                                    <template x-if="company.signature">
                                        <img :src="company.signature" class="relative z-10 h-16 object-contain">
                                    </template>
                                </div>
                                <p class="font-black text-slate-950 text-xs border-b-2 border-slate-950 pb-0.5 inline-block" x-text="activeTab === 'kwitansi' ? (kwitansi.signer_name || 'Nama Penandatangan') : (company.company_name || 'Nama LPK / Perusahaan')"></p>
                                <p class="text-[9px] text-slate-700 font-extrabold" x-text="activeTab === 'kwitansi' ? (company.company_name || 'Nama LPK / Perusahaan') : 'Finance & Accounting Dept.'"></p>
                            </div>
                        </div>

                        <!-- WATERMARK FOOTER -->
                        <div class="text-center text-[8.5px] text-slate-500 font-bold pt-4 border-t border-slate-200 mt-4">
                            Dokumen resmi diterbitkan secara sah oleh komputer <span x-text="company.company_name || 'Nama LPK / Perusahaan'"></span>.
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- EMAIL MODAL -->
    <div x-show="openEmailModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4" x-cloak>
        <div class="bg-slate-900 border border-slate-800 text-slate-200 rounded-2xl p-6 w-full max-w-md space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-black text-white flex items-center gap-2">🚀 <span>Kirim Email Dokumen</span></h3>
                <button @click="openEmailModal = false" class="text-slate-400 hover:text-white font-bold p-1">✕</button>
            </div>
            <div>
                <label class="block text-xs font-extrabold text-slate-300 uppercase tracking-wider mb-1.5">Email Klien / Siswa Target</label>
                <input type="email" x-model="invoice.client_email" placeholder="email@siswa.ac.id" class="w-full text-xs bg-slate-950 border border-slate-700/80 rounded-xl text-white p-3 font-medium">
            </div>
            <div class="flex justify-end gap-2.5 pt-3">
                <button @click="openEmailModal = false" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition-all">Batal</button>
                <button @click="sendClientEmail()" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-600/30 transition-all">
                    Kirim Sekarang
                </button>
            </div>
        </div>
    </div>

</div>

<!-- HIDDEN FORM FOR PDF EXPORT -->
<form id="pdfExportForm" action="{{ route('finance.smart_generator.pdf') }}" method="POST" class="hidden">
    @csrf
    <input type="hidden" name="payload" id="pdfPayloadInput">
</form>

<script>
function smartGenerator() {
    return {
        activeTab: 'invoice',
        sourceMode: 'student_invoice', // 'student_invoice' vs 'manual_invoice'
        openEmailModal: false,
        history: [],
        renderState: 'ready',
        printError: '',

        // Student search state
        studentSearchQuery: '',
        studentSearchResults: [],
        isSearching: false,
        hasSearched: false,
        selectedSourceId: null,
        selectedStudentId: null,
        selectedInvoice: null,

        company: {
            company_name: @js(config('branding.organization.name')),
            company_tagline: @js(config('branding.organization.tagline')),
            company_address: @js(config('branding.organization.address')),
            company_phone: @js(config('branding.organization.phone')),
            company_email: @js(config('branding.organization.email')),
            company_web: @js(config('branding.organization.website')),
            company_npwp: @js(config('branding.organization.npwp')),
            bank_name: @js(config('branding.bank.name')),
            bank_account: @js(config('branding.bank.account_number')),
            bank_holder: @js(config('branding.bank.account_holder')),
            layout_kop: 'left',
            company_logo: @js(asset(config('branding.assets.logo'))),
            signature: "",
            stamp: ""
        },

        invoice: {
            theme: 'emerald',
            doc_number: 'INV-WMS-0001',
            currency: 'IDR',
            issue_date: '2026-08-04',
            due_date: '2026-09-02',
            status: 'UNPAID',
            client_name: '',
            client_email: '',
            client_address: '',
            items: [
                { name: 'Sisa Angsuran Biaya Pengurusan Dokumen Ke Jepang', qty: 1, price: 11550000 }
            ],
            discount: 0,
            ppn_percent: 0,
            shipping: 0,
            notes: 'Pembayaran dilakukan ke rekening resmi yang tercantum pada dokumen.'
        },

        kwitansi: {
            theme: 'emerald',
            doc_number: 'KWI-WMS-0001',
            issue_date: '2026-08-04',
            status: 'PAID / LUNAS',
            client_name: '',
            client_email: '',
            kwitansi_amount: 33450000,
            payment_for: 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang',
            issue_city: @js(config('branding.document.issue_city')),
            signer_name: @js(config('branding.document.signer_name'))
        },

        init() {
            this.initSignatureCanvas();
            this.fetchHistory();
            this.searchStudentInvoices();
        },

        setSourceMode(mode) {
            this.sourceMode = mode;
            if (mode === 'manual_invoice') {
                this.selectedSourceId = null;
                this.selectedStudentId = null;
                this.selectedInvoice = null;
            }
        },

        getPaneTitle() {
            if (this.activeTab === 'kop') return 'Pengaturan Profil Kop Surat';
            if (this.activeTab === 'invoice') return 'Pengaturan Invoice';
            if (this.activeTab === 'kwitansi') return 'Pengaturan Kwitansi';
            return 'Riwayat Dokumen Tersimpan';
        },

        searchStudentInvoices() {
            this.isSearching = true;
            this.hasSearched = true;
            fetch("{{ route('finance.smart_generator.search_student_invoices') }}?q=" + encodeURIComponent(this.studentSearchQuery))
                .then(res => res.json())
                .then(data => {
                    this.isSearching = false;
                    if (data.success) {
                        this.studentSearchResults = data.invoices || [];
                    }
                })
                .catch(() => {
                    this.isSearching = false;
                });
        },

        selectStudentInvoice(inv) {
            this.sourceMode = 'student_invoice';
            this.selectedSourceId = inv.id;
            this.selectedStudentId = inv.student_id;
            this.selectedInvoice = inv;

            this.invoice.doc_number = inv.doc_number;
            this.invoice.client_name = inv.student_name;
            this.invoice.client_email = inv.student_email || '';
            this.invoice.client_address = inv.student_address || 'Alamat Siswa';
            this.invoice.issue_date = inv.issue_date || '2026-08-04';
            this.invoice.due_date = inv.due_date || '2026-08-18';
            this.invoice.status = inv.status || 'UNPAID';
            this.invoice.items = inv.items || [{ name: inv.description || 'Tagihan Siswa', qty: 1, price: inv.amount }];
            this.invoice.discount = inv.discount || 0;
            this.invoice.ppn_percent = 0;
            this.invoice.shipping = 0;
            this.invoice.notes = 'Tagihan Resmi Siswa (Finance Engine: ' + inv.doc_number + ')';

            // Also update Kwitansi
            this.kwitansi.doc_number = 'KWI-' + inv.doc_number;
            this.kwitansi.client_name = inv.student_name;
            this.kwitansi.client_email = inv.student_email || '';
            this.kwitansi.kwitansi_amount = inv.grand_total || inv.amount;
            this.kwitansi.payment_for = inv.description || 'Pelunasan Tagihan Siswa';
        },

        addItem() {
            if (this.sourceMode === 'student_invoice') return;
            this.invoice.items.push({ name: 'Rincian Biaya Pengurusan Dokumen', qty: 1, price: 1000000 });
        },

        removeItem(index) {
            if (this.sourceMode === 'student_invoice') return;
            this.invoice.items.splice(index, 1);
        },

        calculateSubtotal() {
            return this.invoice.items.reduce((acc, item) => acc + (Number(item.qty || 1) * Number(item.price || 0)), 0);
        },

        calculatePpn() {
            const sub = this.calculateSubtotal();
            const afterDisc = Math.max(0, sub - Number(this.invoice.discount || 0));
            return (afterDisc * Number(this.invoice.ppn_percent || 0)) / 100;
        },

        calculateGrandTotal() {
            const sub = this.calculateSubtotal();
            const afterDisc = Math.max(0, sub - Number(this.invoice.discount || 0));
            return afterDisc + this.calculatePpn() + Number(this.invoice.shipping || 0);
        },

        formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        },

        formatDate(dateStr) {
            if (!dateStr) return '2026-08-04';
            return dateStr;
        },

        getTerbilang(num) {
            return this.terbilangWord(num);
        },

        terbilangWord(n) {
            n = Math.abs(Number(n) || 0);
            if (n === 0) return '# Nol Rupiah #';
            const units = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
            function convert(num) {
                if (num < 12) return units[num];
                if (num < 20) return convert(num - 10) + ' Belas';
                if (num < 100) return convert(Math.floor(num / 10)) + ' Puluh ' + convert(num % 10);
                if (num < 200) return 'Seratus ' + convert(num - 100);
                if (num < 1000) return convert(Math.floor(num / 100)) + ' Ratus ' + convert(num % 100);
                if (num < 2000) return 'Seribu ' + convert(num - 1000);
                if (num < 1000000) return convert(Math.floor(num / 1000)) + ' Ribu ' + convert(num % 1000);
                if (num < 1000000000) return convert(Math.floor(num / 1000000)) + ' Juta ' + convert(num % 1000000);
                return convert(Math.floor(num / 1000000000)) + ' Miliar ' + convert(num % 1000000000);
            }
            return '# ' + convert(n).trim() + ' Rupiah #';
        },

        loadSampleData() {
            this.sourceMode = 'manual_invoice';
            this.selectedSourceId = null;
            this.invoice.doc_number = 'INV-WMS-0001';
            this.invoice.client_name = '';
            this.invoice.client_email = '';
            this.invoice.client_address = '';
            this.invoice.items = [
                { name: 'Sisa Angsuran Biaya Pengurusan Dokumen Ke Jepang', qty: 1, price: 11550000 }
            ];
            this.invoice.discount = 0;
            this.invoice.ppn_percent = 0;
            this.invoice.shipping = 0;
            this.kwitansi.doc_number = 'KWI-WMS-0001';
            this.kwitansi.client_name = '';
            this.kwitansi.kwitansi_amount = 33450000;
            this.kwitansi.payment_for = 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang';
            this.kwitansi.signer_name = @js(config('branding.document.signer_name'));
        },

        resetForm() {
            this.selectedSourceId = null;
            this.selectedStudentId = null;
            this.selectedInvoice = null;
            this.invoice.doc_number = 'INV-WMS-' + Math.floor(1000 + Math.random() * 9000);
            this.invoice.client_name = '';
            this.invoice.client_email = '';
            this.invoice.client_address = '';
            this.invoice.items = [{ name: 'Deskripsi Layanan / Produk', qty: 1, price: 0 }];
            this.invoice.discount = 0;
            this.invoice.ppn_percent = 0;
            this.invoice.shipping = 0;
            this.invoice.notes = '';
            this.kwitansi.doc_number = 'KWI-WMS-' + Math.floor(1000 + Math.random() * 9000);
            this.kwitansi.client_name = '';
            this.kwitansi.client_email = '';
            this.kwitansi.kwitansi_amount = 0;
            this.kwitansi.payment_for = '';
        },

        handleFileUpload(event, key) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.company[key] = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        initSignatureCanvas() {
            const canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let drawing = false;

            canvas.addEventListener('mousedown', (e) => {
                drawing = true;
                ctx.beginPath();
                ctx.moveTo(e.offsetX, e.offsetY);
            });
            canvas.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                ctx.lineTo(e.offsetX, e.offsetY);
                ctx.strokeStyle = '#0f172a';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.stroke();
            });
            canvas.addEventListener('mouseup', () => {
                drawing = false;
                this.company.signature = canvas.toDataURL();
            });
        },

        clearSignatureCanvas() {
            const canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            this.company.signature = '';
        },

        async waitForPrintableDocument() {
            await new Promise(resolve => this.$nextTick(resolve));
            const printable = document.querySelector('[data-printable-document]');
            const body = printable?.querySelector('[data-document-body]');
            if (!printable || !body || body.textContent.trim() === '') {
                throw new Error('Dokumen belum siap untuk dicetak.');
            }

            if (document.fonts?.ready) {
                await document.fonts.ready.catch(() => {});
            }

            const images = Array.from(printable.querySelectorAll('img'));
            await Promise.all(images.map(image => {
                if (image.complete) {
                    return image.decode ? image.decode().catch(() => {}) : Promise.resolve();
                }
                return new Promise(resolve => {
                    const done = () => resolve();
                    image.addEventListener('load', done, { once: true });
                    image.addEventListener('error', done, { once: true });
                    // A broken optional asset must not deadlock printing.
                    window.setTimeout(done, 3000);
                });
            }));

            // Two frames allow Alpine's x-if/x-text DOM patch and layout pass to
            // settle before print media is evaluated.
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            return printable;
        },

        async exportPdf() {
            if (this.renderState === 'generating' || this.renderState === 'printing') return;
            this.renderState = 'generating';
            this.printError = '';
            try {
                await this.waitForPrintableDocument();
                const payload = this.getPayload();
                const form = document.getElementById('pdfExportForm');
                if (!form) throw new Error('Form ekspor dokumen tidak tersedia.');

                form.replaceChildren();
                const appendHidden = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value ?? '';
                    form.appendChild(input);
                };

                appendHidden('_token', '{{ csrf_token() }}');
                appendHidden('source_type', payload.source_type);
                appendHidden('source_id', payload.source_id || '');
                appendHidden('student_id', payload.student_id || '');
                appendHidden('doc_type', payload.doc_type);
                appendHidden('doc_number', payload.doc_number);
                appendHidden('issue_date', payload.issue_date);
                appendHidden('due_date', payload.due_date);
                appendHidden('status', payload.status);
                appendHidden('client_name', payload.client_name);
                appendHidden('client_email', payload.client_email);
                appendHidden('client_address', payload.client_address);
                appendHidden('company_name', payload.company_name);
                appendHidden('company_tagline', payload.company_tagline);
                appendHidden('company_address', payload.company_address);
                appendHidden('company_phone', payload.company_phone);
                appendHidden('company_email', payload.company_email);
                appendHidden('company_web', payload.company_web);
                appendHidden('company_npwp', payload.company_npwp);
                appendHidden('bank_name', payload.bank_name);
                appendHidden('bank_account', payload.bank_account);
                appendHidden('bank_holder', payload.bank_holder);
                appendHidden('items', JSON.stringify(payload.items));
                appendHidden('discount', payload.discount);
                appendHidden('ppn_percent', payload.ppn_percent);
                appendHidden('shipping', payload.shipping);
                appendHidden('notes', payload.notes);
                appendHidden('kwitansi_amount', payload.kwitansi_amount);
                appendHidden('payment_for', payload.payment_for);
                appendHidden('issue_city', payload.issue_city);
                appendHidden('signer_name', payload.signer_name);
                appendHidden('company_logo', payload.company_logo || '');
                appendHidden('signature', payload.signature || '');
                appendHidden('stamp', payload.stamp || '');
                appendHidden('theme', payload.theme || 'emerald');
                form.submit();
            } catch (error) {
                this.renderState = 'failed';
                this.printError = 'Dokumen belum siap untuk diekspor.';
                console.warn('Smart Generator export preparation failed', error?.message || 'unknown');
            }
        },

        async printDocument() {
            if (this.renderState === 'printing' || this.renderState === 'generating') return;
            this.renderState = 'printing';
            this.printError = '';
            let finished = false;
            const finish = () => {
                if (finished) return;
                finished = true;
                document.body.classList.remove('smart-generator-printing');
                this.renderState = 'ready';
            };

            try {
                await this.waitForPrintableDocument();
                document.body.classList.add('smart-generator-printing');
                window.addEventListener('afterprint', finish, { once: true });
                window.print();
                // Chromium/Edge normally dispatch afterprint. The fallback
                // prevents a stale print mode in embedded/mobile browsers.
                window.setTimeout(finish, 1500);
            } catch (error) {
                finish();
                this.renderState = 'failed';
                this.printError = 'Dokumen belum siap untuk dicetak.';
                console.warn('Smart Generator print preparation failed', error?.message || 'unknown');
            }
        },

        getThemeColors() {
            const theme = (this.activeTab === 'kwitansi') ? (this.kwitansi.theme || 'emerald') : (this.invoice.theme || 'emerald');
            if (theme === 'indigo') {
                return {
                    primary: '#4338ca',
                    secondary: '#4f46e5',
                    bgLight: '#eef2ff',
                    border: '#4f46e5',
                    textPrimary: '#1e1b4b',
                    textDark: '#312e81',
                    badgeBg: 'bg-indigo-100 text-indigo-900 border-indigo-500'
                };
            }
            if (theme === 'crimson') {
                return {
                    primary: '#be123c',
                    secondary: '#e11d48',
                    bgLight: '#fff1f2',
                    border: '#e11d48',
                    textPrimary: '#881337',
                    textDark: '#9f1239',
                    badgeBg: 'bg-rose-100 text-rose-900 border-rose-500'
                };
            }
            // Default Emerald
            return {
                primary: '#047857',
                secondary: '#059669',
                bgLight: '#ecfdf5',
                border: '#059669',
                textPrimary: '#064e3b',
                textDark: '#065f46',
                badgeBg: 'bg-emerald-100 text-emerald-900 border-emerald-500'
            };
        },

        saveToHistory() {
            const payload = this.getPayload();
            fetch("{{ route('finance.smart_generator.save') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.history = data.history;
                    alert('Dokumen ' + payload.doc_number + ' berhasil disimpan ke riwayat!');
                }
            });
        },

        fetchHistory() {
            fetch("{{ route('finance.smart_generator.history_api') }}")
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.history = data.history;
                    }
                });
        },

        deleteHistoryItem(id) {
            if (!confirm('Hapus dokumen ini dari riwayat?')) return;
            fetch("{{ url('/finance/smart-generator/history') }}/" + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.history = data.history;
                }
            });
        },

        loadHistoryItem(item) {
            if (item.source_type) {
                this.sourceMode = item.source_type;
                this.selectedSourceId = item.source_id || null;
                this.selectedStudentId = item.student_id || null;
            }

            if (item.doc_type === 'kwitansi') {
                this.activeTab = 'kwitansi';
                this.kwitansi.doc_number = item.doc_number;
                this.kwitansi.client_name = item.client_name;
                this.kwitansi.kwitansi_amount = item.kwitansi_amount;
                this.kwitansi.payment_for = item.payment_for;
                this.kwitansi.signer_name = item.signer_name || @js(config('branding.document.signer_name'));
                if (item.theme) this.kwitansi.theme = item.theme;
            } else {
                this.activeTab = 'invoice';
                this.invoice.doc_number = item.doc_number;
                this.invoice.client_name = item.client_name;
                this.invoice.client_email = item.client_email;
                this.invoice.client_address = item.client_address;
                this.invoice.items = item.items || [];
                this.invoice.discount = item.discount || 0;
                this.invoice.ppn_percent = item.ppn_percent || 0;
                this.invoice.shipping = item.shipping || 0;
                if (item.theme) this.invoice.theme = item.theme;
            }
        },

        sendClientEmail() {
            const payload = this.getPayload();
            fetch("{{ route('finance.smart_generator.send_email') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    client_email: payload.client_email,
                    doc_number: payload.doc_number,
                    source_type: payload.source_type,
                    source_id: payload.source_id,
                    student_id: payload.student_id
                })
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.message || 'Gagal mengirim email.'); });
                }
                return res.json();
            })
            .then(data => {
                alert(data.message);
                this.openEmailModal = false;
            })
            .catch(err => {
                alert(err.message);
            });
        },

        getPayload() {
            const isInvoice = (this.activeTab === 'invoice' || this.activeTab === 'kop');
            return {
                source_type: this.sourceMode,
                source_id: this.selectedSourceId,
                student_id: this.selectedStudentId,
                theme: isInvoice ? (this.invoice.theme || 'emerald') : (this.kwitansi.theme || 'emerald'),

                doc_type: isInvoice ? 'invoice' : 'kwitansi',
                doc_number: isInvoice ? this.invoice.doc_number : this.kwitansi.doc_number,
                issue_date: isInvoice ? this.invoice.issue_date : this.kwitansi.issue_date,
                due_date: this.invoice.due_date,
                status: isInvoice ? this.invoice.status : this.kwitansi.status,
                client_name: isInvoice ? this.invoice.client_name : this.kwitansi.client_name,
                client_email: isInvoice ? this.invoice.client_email : this.kwitansi.client_email,
                client_address: this.invoice.client_address,
                company_name: this.company.company_name,
                company_tagline: this.company.company_tagline,
                company_address: this.company.company_address,
                company_phone: this.company.company_phone,
                company_email: this.company.company_email,
                company_web: this.company.company_web,
                company_npwp: this.company.company_npwp,
                bank_name: this.company.bank_name,
                bank_account: this.company.bank_account,
                bank_holder: this.company.bank_holder,
                items: this.invoice.items,
                subtotal: this.calculateSubtotal(),
                discount: this.invoice.discount,
                ppn_percent: this.invoice.ppn_percent,
                shipping: this.invoice.shipping,
                grand_total: this.calculateGrandTotal(),
                notes: this.invoice.notes,
                kwitansi_amount: this.kwitansi.kwitansi_amount,
                payment_for: this.kwitansi.payment_for,
                issue_city: this.kwitansi.issue_city,
                signer_name: this.kwitansi.signer_name,
                company_logo: this.company.company_logo,
                signature: this.company.signature,
                stamp: this.company.stamp
            };
        }
    }
}
</script>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }

    html,
    body {
        width: 210mm !important;
        min-height: 297mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    body.smart-generator-printing {
        overflow: visible !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Keep the application shell out of the print tree. Do not use a global
       body * visibility rule: inherited visibility plus scroll parents was
       the source of the blank/clipped print output. */
    body.smart-generator-printing #sidebar,
    body.smart-generator-printing #mobile-sidebar-overlay,
    body.smart-generator-printing .wms-mobile-header,
    body.smart-generator-printing .mobile-bottom-nav,
    body.smart-generator-printing #floating-qr-button,
    body.smart-generator-printing > .fixed,
    body.smart-generator-printing #toast-container,
    body.smart-generator-printing #confirm-dialog,
    body.smart-generator-printing .smart-generator-ui,
    body.smart-generator-printing #pdfExportForm,
    body.smart-generator-printing .main-content > *:not(#main-content) {
        display: none !important;
    }

    body.smart-generator-printing .wms-shell,
    body.smart-generator-printing .main-content,
    body.smart-generator-printing #main-content,
    body.smart-generator-printing #smart-generator-app {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        min-height: 0 !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
    }

    body.smart-generator-printing .smart-generator-preview-pane {
        display: block !important;
        position: static !important;
        width: 210mm !important;
        max-width: 210mm !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body.smart-generator-printing .smart-generator-preview-pane > div,
    body.smart-generator-printing .smart-generator-preview-viewport {
        display: block !important;
        width: 210mm !important;
        max-width: 210mm !important;
        max-height: none !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
        border: 0 !important;
        box-shadow: none !important;
    }

    #a4CanvasSheet {
        display: flex !important;
        visibility: visible !important;
        position: relative !important;
        box-sizing: border-box !important;
        width: 210mm !important;
        min-width: 210mm !important;
        max-width: 210mm !important;
        min-height: 297mm !important;
        height: auto !important;
        margin: 0 !important;
        padding: 10mm !important;
        overflow: visible !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        color: #0f172a !important;
        background: #fff !important;
    }

    #a4CanvasSheet * {
        visibility: visible !important;
    }

    #a4CanvasSheet table {
        width: 100% !important;
        max-width: 100% !important;
        page-break-inside: auto;
        break-inside: auto;
    }

    #a4CanvasSheet thead {
        display: table-header-group;
    }

    #a4CanvasSheet tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    #a4CanvasSheet td,
    #a4CanvasSheet th,
    #a4CanvasSheet p,
    #a4CanvasSheet span {
        overflow-wrap: anywhere;
        word-break: normal;
    }

    #a4CanvasSheet img,
    #a4CanvasSheet svg,
    #a4CanvasSheet canvas {
        max-width: 100%;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
@endsection
