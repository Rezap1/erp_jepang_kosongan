<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAYAR DYNAMIC QR PRESENSI PEGAWAI - {{ $session['Session_ID'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .qr-card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between p-6 md:p-12">

    <!-- TOP HEADER -->
    <header class="flex justify-between items-center border-b border-slate-800 pb-6">
        <div>
            <div class="flex items-center gap-3">
                <span class="w-4 h-4 rounded-full bg-emerald-500 animate-ping"></span>
                <h1 class="text-2xl font-black tracking-wider uppercase text-white">PRESENSI QR DINAMIS SDM {{ $companyProfile['company']['name'] ?? $brand['organization']['name'] }}</h1>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ $session['Title'] ?? 'Presensi Kehadiran Pegawai' }} &bull; Sesi ID: <strong class="text-blue-400 font-mono">{{ $session['Session_ID'] }}</strong></p>
        </div>
        <div class="text-right">
            <div id="liveClock" class="text-3xl font-black font-mono text-emerald-400 tracking-wider">00:00:00</div>
            <div class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-0.5">{{ date('l, d F Y') }}</div>
        </div>
    </header>

    <!-- CENTER DYNAMIC QR DISPLAY -->
    <main class="my-auto py-8">
        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            
            <!-- QR CODE BOX -->
            <div class="qr-card p-8 rounded-3xl shadow-2xl text-center space-y-6">
                <div class="relative inline-block p-4 bg-white rounded-2xl shadow-inner border-4 border-slate-700">
                    <canvas id="qrCanvas" class="w-64 h-64 mx-auto"></canvas>
                    <div id="loadingOverlay" class="absolute inset-0 bg-slate-900/80 rounded-2xl flex items-center justify-center text-xs font-bold text-slate-300">
                        🔄 Memuat QR Token...
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-center gap-2 text-xs font-bold text-slate-300 uppercase tracking-wider">
                        <span>Rotasi Token Otomatis:</span>
                        <span id="countdown" class="px-2.5 py-1 bg-emerald-500/20 text-emerald-400 rounded-lg border border-emerald-500/30 font-mono text-sm font-black">00:00</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2">Pindai QR ini menggunakan kamera scanner pada aplikasi mobile pegawai WMS.</p>
                </div>
            </div>

            <!-- SESSION METRICS & LIVE ATTENDANCE LOG -->
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="qr-card p-5 rounded-2xl border border-slate-800">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Jam Mulai Sesi</span>
                        <span class="text-xl font-black text-white mt-1 block font-mono">{{ $session['Start_Time'] ?? '08:00' }} WIB</span>
                    </div>
                    <div class="qr-card p-5 rounded-2xl border border-slate-800">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Toleransi Terlambat</span>
                        <span class="text-xl font-black text-amber-400 mt-1 block font-mono">+{{ $session['Grace_Period'] ?? 30 }} Menit</span>
                    </div>
                </div>

                <div class="qr-card p-6 rounded-3xl space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                        <h3 class="text-xs font-black uppercase text-slate-300 tracking-wider">Total Pegawai Presensi Hari Ini</h3>
                        <span id="totalScanned" class="px-3 py-1 bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded-xl font-black text-sm">0 Orang</span>
                    </div>

                    <div class="space-y-2 max-h-48 overflow-y-auto pr-2" id="scannedList">
                        <div class="text-center py-6 text-xs text-slate-500 italic">Belum ada aktivitas presensi pada sesi ini.</div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- FOOTER STATUS -->
    <footer class="border-t border-slate-800 pt-4 text-center text-xs text-slate-500">
        {{ $brand['application']['name'] }} &bull; Enterprise Dynamic QR Security Engine EPS Rev.1.0 &bull; Nonce Single-Use Protection Active
    </footer>

    <script>
        const sessionId = "{{ $session['Session_ID'] }}";
        const tokenUrl = "/hr/attendance/qr/session/" + sessionId + "/token";
        const summaryUrl = "/hr/attendance/qr/session/" + sessionId + "/summary";

        let countdownSec = 0;
        let countdownInterval = null;
        let tokenFetchInFlight = false;

        // Live Clock
        setInterval(() => {
            const now = new Date();
            document.getElementById('liveClock').innerText = now.toLocaleTimeString('id-ID');
        }, 1000);

        // Fetch Dynamic Token
        async function fetchToken() {
            if (tokenFetchInFlight) return;
            tokenFetchInFlight = true;
            try {
                const response = await fetch(tokenUrl);
                const result = await response.json();
                
                if (result.success && result.data && result.data.token) {
                    const token = result.data.token;
                    QRCode.toCanvas(document.getElementById('qrCanvas'), token, {
                        width: 256,
                        margin: 1,
                        color: { dark: '#0f172a', light: '#ffffff' }
                    }, function (error) {
                        if (error) console.error(error);
                        document.getElementById('loadingOverlay').style.display = 'none';
                    });

                    countdownSec = Number(result.data.expires_in) || 0;
                } else {
                    console.error("Token error:", result.message);
                }
            } catch (err) {
                console.error("Failed to fetch token:", err);
            } finally {
                tokenFetchInFlight = false;
            }
        }

        // Countdown Timer
        function startCountdown() {
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                countdownSec--;
                if (countdownSec <= 0) {
                    fetchToken();
                    countdownSec = 0;
                }
                document.getElementById('countdown').innerText = formatCountdown(countdownSec);
            }, 1000);
        }

        function formatCountdown(totalSeconds) {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }

        // Live Attendance Summary Polling
        async function fetchSummary() {
            try {
                const response = await fetch(summaryUrl);
                const result = await response.json();
                
                if (result.success && result.data) {
                    const data = result.data;
                    document.getElementById('totalScanned').innerText = data.total_scanned + " Orang";

                    const listEl = document.getElementById('scannedList');
                    if (data.attendances && data.attendances.length > 0) {
                        listEl.innerHTML = data.attendances.map(a => `
                            <div class="flex justify-between items-center bg-slate-800/50 p-2.5 rounded-xl text-xs border border-slate-700/50">
                                <div>
                                    <span class="font-bold text-slate-200 block">${escapeHtml(a.Employee_Name)}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">${escapeHtml(a.Check_In_Time)} WIB</span>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase ${a.Status === 'PRESENT' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'}">
                                    ${a.Status === 'PRESENT' ? '✅ TEPAT WAKTU' : '⚠️ TERLAMBAT'}
                                </span>
                            </div>
                        `).join('');
                    }
                }
            } catch (err) {
                console.error("Failed to fetch summary:", err);
            }
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'\"]/g, ch => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[ch]));
        }

        // Initial Load
        fetchToken();
        startCountdown();
        fetchSummary();
        setInterval(fetchSummary, 4000);
    </script>
</body>
</html>
