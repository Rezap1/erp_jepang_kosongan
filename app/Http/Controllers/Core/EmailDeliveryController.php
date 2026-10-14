<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SystemSettingService;
use App\Services\Core\EmailDeliveryService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class EmailDeliveryController extends Controller
{
    protected $settingService;
    protected $emailDeliveryService;

    public function __construct(SystemSettingService $settingService, EmailDeliveryService $emailDeliveryService)
    {
        $this->settingService = $settingService;
        $this->emailDeliveryService = $emailDeliveryService;
    }

    /**
     * Initiate OAuth Authorization flow for Google or Microsoft with Account Chooser.
     */
    public function connectProvider(Request $request, string $provider)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['google', 'microsoft'])) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Provider email tidak didukung.']);
        }

        Session::forget('oauth_pending_preview');

        $state = Str::random(32);
        Session::put('oauth_email_state', $state);
        Session::put('oauth_email_provider', $provider);

        $oauth = $this->oauthProviderConfig($provider);
        if (!$oauth) {
            Session::forget(['oauth_email_state', 'oauth_email_provider']);
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Konfigurasi OAuth provider belum lengkap. Hubungi administrator sistem.']);
        }

        $params = [
            'client_id' => $oauth['client_id'],
            'redirect_uri' => $oauth['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $oauth['scopes']),
            'state' => $state,
            'prompt' => 'select_account',
        ];
        if ($provider === 'google') {
            $params['access_type'] = 'offline';
        }

        return redirect()->away($oauth['authorize_url'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * Handle OAuth Callback for Google or Microsoft.
     */
    public function oauthCallback(Request $request, string $provider)
    {
        $provider = strtolower(trim($provider));
        if (!in_array($provider, ['google', 'microsoft'])) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Provider OAuth tidak valid.']);
        }

        $expectedState = (string) Session::pull('oauth_email_state', '');
        $expectedProvider = (string) Session::pull('oauth_email_provider', '');
        $receivedState = (string) $request->input('state', '');
        if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)
            || !hash_equals($expectedProvider, $provider)) {
            Session::forget('oauth_pending_preview');
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Validasi keamanan OAuth gagal. Silakan mulai ulang proses koneksi email.']);
        }

        $code = $request->input('code');
        if (empty($code)) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Otorisasi OAuth dibatalkan oleh pengguna.']);
        }

        $oauth = $this->oauthProviderConfig($provider);
        if (!$oauth) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Konfigurasi OAuth provider belum lengkap.']);
        }

        try {
            $tokenResponse = Http::asForm()->acceptJson()->timeout(15)->post($oauth['token_url'], [
                'client_id' => $oauth['client_id'],
                'client_secret' => $oauth['client_secret'],
                'redirect_uri' => $oauth['redirect_uri'],
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);
            if (!$tokenResponse->successful() || empty($tokenResponse->json('access_token'))) {
                throw new \RuntimeException('Provider menolak pertukaran authorization code.');
            }

            $accessToken = (string) $tokenResponse->json('access_token');
            $identityResponse = Http::withToken($accessToken)->acceptJson()->timeout(15)->get($oauth['userinfo_url']);
            if (!$identityResponse->successful()) {
                throw new \RuntimeException('Identitas akun provider tidak dapat diverifikasi.');
            }

            $identity = $identityResponse->json();
            $accountEmail = trim((string) ($identity['email'] ?? $identity['mail'] ?? $identity['userPrincipalName'] ?? ''));
            if (!filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Provider tidak mengembalikan alamat email yang valid.');
            }
            if ($provider === 'google' && array_key_exists('email_verified', $identity) && !$identity['email_verified']) {
                throw new \RuntimeException('Alamat email Google belum terverifikasi.');
            }
        } catch (\Throwable $e) {
            Log::warning('OAuth email verification failed', ['provider' => $provider, 'error' => $e->getMessage()]);
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Verifikasi OAuth gagal. Silakan ulangi koneksi dari awal.']);
        }

        $company = $this->settingService->getCompanyProfile();
        $userEmail = auth()->user()->email ?? auth()->user()->Email ?? null;
        if (!$userEmail) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Identitas administrator tidak valid.']);
        }

        // GOOGLE WORKSPACE DOMAIN VALIDATION
        $allowedDomain = config('mail.allowed_domain');
        $emailDomain = strtolower(substr(strrchr($accountEmail, "@"), 1));

        if ($provider === 'google' && !empty($allowedDomain) && $emailDomain !== strtolower($allowedDomain)) {
            Log::warning("OAuth Google domain validation failed for account: {$accountEmail}");
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => "Akun Google harus menggunakan email perusahaan @{$allowedDomain}."]);
        }

        $providerName = ($provider === 'google') ? 'Google Workspace' : 'Microsoft 365 / Outlook';

        $expiresIn = max(1, (int) $tokenResponse->json('expires_in', 3600));
        $tokenPayload = [
            'provider' => $provider,
            'provider_name' => $providerName,
            'account' => $accountEmail,
            'access_token' => $accessToken,
            'refresh_token' => (string) $tokenResponse->json('refresh_token', ''),
            'token_type' => (string) $tokenResponse->json('token_type', 'Bearer'),
            'expires_at' => time() + $expiresIn,
            'prompt_used' => 'select_account',
            'connected_by' => $userEmail,
            'created_at' => now()->toDateTimeString()
        ];

        // Store pending preview payload in session for Account Preview screen
        Session::put('oauth_pending_preview', $tokenPayload);

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery', 'preview' => '1'])
            ->with('info', "Akun {$accountEmail} berhasil diverifikasi. Silakan klik Lanjutkan untuk menyelesaikan tautan.");
    }

    /**
     * Finalize connection after Account Preview Verification.
     */
    public function confirmConnection(Request $request)
    {
        $pending = Session::get('oauth_pending_preview');
        if (!$pending) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->withErrors(['error' => 'Sesi verifikasi OAuth telah berakhir. Silakan hubungkan ulang.']);
        }

        $company = $this->settingService->getCompanyProfile();
        $userEmail = auth()->user()->email ?? ($company['company']['email'] ?? config('branding.organization.email'));

        $provider = $pending['provider'];
        $accountEmail = $pending['account'];
        $providerName = $pending['provider_name'] ?? (($provider === 'google') ? 'Google Workspace' : 'Microsoft 365 / Outlook');

        $encryptedCredentials = Crypt::encryptString(json_encode($pending));

        // Persist through SystemSettingService -> SystemSettingRepository -> MASTER_SYSTEM_SETTING
        $this->settingService->set('EMAIL_PROVIDER', $provider, $userEmail);
        $this->settingService->set('SET_EMAIL_PROVIDER', $provider, $userEmail);
        $this->settingService->set('EMAIL_CONNECTION_STATUS', 'connected', $userEmail);
        $this->settingService->set('SET_EMAIL_STATUS', 'connected', $userEmail);
        $this->settingService->set('SET_EMAIL_CONNECTED_AT', now()->format('d M Y, H:i'), $userEmail);
        $this->settingService->set('SET_EMAIL_CONNECTED_ACCOUNT', $accountEmail, $userEmail);
        $this->settingService->set('SET_EMAIL_FROM_ADDRESS', $accountEmail, $userEmail);
        $this->settingService->set('EMAIL_FROM_ADDRESS', $accountEmail, $userEmail);
        $this->settingService->set('SET_EMAIL_FROM_NAME', $company['company']['name'] ?? config('branding.application.name'), $userEmail);
        $this->settingService->set('EMAIL_FROM_NAME', $company['company']['name'] ?? config('branding.application.name'), $userEmail);
        $this->settingService->set('SET_EMAIL_CREDENTIAL_DATA', $encryptedCredentials, $userEmail);

        $this->settingService->reloadCache();
        $this->emailDeliveryService->applyDynamicMailConfig();

        Session::forget('oauth_pending_preview');

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->with('success', "🟢 EMAIL TERHUBUNG: Akun {$providerName} ({$accountEmail}) telah aktif.");
    }

    /**
     * Cancel pending OAuth verification and return to provider connection dashboard.
     */
    public function cancelPreview(Request $request)
    {
        Session::forget('oauth_pending_preview');
        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->with('info', 'Otorisasi email dibatalkan.');
    }

    private function oauthProviderConfig(string $provider): ?array
    {
        if ($provider === 'google') {
            $config = [
                'client_id' => trim((string) config('services.google.oauth_client_id')),
                'client_secret' => trim((string) config('services.google.oauth_client_secret')),
                'redirect_uri' => trim((string) (config('services.google.oauth_redirect_uri') ?: route('settings.email.callback', 'google'))),
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'userinfo_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scopes' => ['openid', 'email', 'profile', 'https://www.googleapis.com/auth/gmail.send'],
            ];
        } elseif ($provider === 'microsoft') {
            $tenant = trim((string) config('services.microsoft.tenant', 'common')) ?: 'common';
            $baseUrl = 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0';
            $config = [
                'client_id' => trim((string) config('services.microsoft.oauth_client_id')),
                'client_secret' => trim((string) config('services.microsoft.oauth_client_secret')),
                'redirect_uri' => trim((string) (config('services.microsoft.oauth_redirect_uri') ?: route('settings.email.callback', 'microsoft'))),
                'authorize_url' => $baseUrl . '/authorize',
                'token_url' => $baseUrl . '/token',
                'userinfo_url' => 'https://graph.microsoft.com/v1.0/me',
                'scopes' => ['openid', 'email', 'profile', 'offline_access', 'Mail.Send'],
            ];
        } else {
            return null;
        }

        if ($config['client_id'] === '' || $config['client_secret'] === '' || $config['redirect_uri'] === '') {
            return null;
        }

        return $config;
    }

    /**
     * Connect Custom SMTP transport with validated credentials.
     */
    public function connectSmtp(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|numeric|min:1|max:65535',
            'encryption' => 'required|string|in:ssl,tls,starttls,none',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'sender_name' => 'required|string|max:255',
            'reply_to' => 'nullable|email|max:255',
        ]);

        $userEmail = auth()->user()->email ?? config('branding.organization.email');

        $smtpPayload = [
            'provider' => 'smtp',
            'host' => trim($validated['host']),
            'port' => (int)$validated['port'],
            'encryption' => strtolower(trim($validated['encryption'])),
            'username' => trim($validated['username']),
            'password' => $validated['password'],
            'sender_name' => trim($validated['sender_name']),
            'created_at' => now()->toDateTimeString()
        ];

        // Encrypt sensitive SMTP credentials
        $encryptedCredentials = Crypt::encryptString(json_encode($smtpPayload));

        // Persist through SystemSettingService -> SystemSettingRepository -> MASTER_SYSTEM_SETTING
        $this->settingService->set('SET_EMAIL_PROVIDER', 'smtp', $userEmail);
        $this->settingService->set('SET_EMAIL_STATUS', 'connected', $userEmail);
        $this->settingService->set('SET_EMAIL_CONNECTED_AT', now()->format('d M Y, H:i'), $userEmail);
        $this->settingService->set('SET_EMAIL_CONNECTED_ACCOUNT', $validated['username'], $userEmail);
        $this->settingService->set('SET_EMAIL_FROM_ADDRESS', $validated['username'], $userEmail);
        $this->settingService->set('SET_EMAIL_FROM_NAME', $validated['sender_name'], $userEmail);
        if (!empty($validated['reply_to'])) {
            $this->settingService->set('SET_EMAIL_REPLY_TO', $validated['reply_to'], $userEmail);
        }
        $this->settingService->set('SET_EMAIL_CREDENTIAL_DATA', $encryptedCredentials, $userEmail);

        $this->settingService->reloadCache();

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->with('success', "✓ Server SMTP custom ({$validated['host']}) berhasil terhubung ke WMS.");
    }

    /**
     * Disconnect active email provider and clear credentials.
     */
    public function disconnect(Request $request)
    {
        $userEmail = auth()->user()->email ?? config('branding.organization.email');

        $this->settingService->set('EMAIL_PROVIDER', 'none', $userEmail);
        $this->settingService->set('SET_EMAIL_PROVIDER', 'none', $userEmail);
        $this->settingService->set('EMAIL_CONNECTION_STATUS', 'disconnected', $userEmail);
        $this->settingService->set('SET_EMAIL_STATUS', 'disconnected', $userEmail);
        $this->settingService->set('SET_EMAIL_CREDENTIAL_DATA', '', $userEmail);

        $this->settingService->reloadCache();

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->with('success', '✓ Koneksi email berhasil diputuskan.');
    }

    /**
     * Reconnect / Re-verify active provider.
     */
    public function reconnect(Request $request)
    {
        $config = $this->settingService->getEmailDeliveryConfig();
        $provider = $config['provider'];

        if (in_array($provider, ['google', 'microsoft'])) {
            return $this->connectProvider($request, $provider);
        }

        $userEmail = auth()->user()->email ?? config('branding.organization.email');
        $this->settingService->set('SET_EMAIL_STATUS', 'connected', $userEmail);
        $this->settingService->set('SET_EMAIL_CONNECTED_AT', now()->format('d M Y, H:i'), $userEmail);
        $this->settingService->reloadCache();

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->with('success', '✓ Koneksi email telah diverifikasi ulang dan berstatus aktif.');
    }

    /**
     * Update Sender Information (From Address, From Name, Reply-To).
     */
    public function updateSender(Request $request)
    {
        $validated = $request->validate([
            'from_address' => 'required|email',
            'from_name' => 'required|string|max:255',
            'reply_to' => 'nullable|email',
        ]);

        $userEmail = auth()->user()->email ?? config('branding.organization.email');

        $this->settingService->set('SET_EMAIL_FROM_ADDRESS', $validated['from_address'], $userEmail);
        $this->settingService->set('EMAIL_FROM_ADDRESS', $validated['from_address'], $userEmail);
        $this->settingService->set('SET_EMAIL_FROM_NAME', $validated['from_name'], $userEmail);
        $this->settingService->set('EMAIL_FROM_NAME', $validated['from_name'], $userEmail);
        if (!empty($validated['reply_to'])) {
            $this->settingService->set('SET_EMAIL_REPLY_TO', $validated['reply_to'], $userEmail);
            $this->settingService->set('EMAIL_REPLY_TO', $validated['reply_to'], $userEmail);
        }

        $this->settingService->reloadCache();

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->with('success', '✓ Identitas pengirim email berhasil diperbarui.');
    }

    /**
     * Dispatch Test Email via EmailDeliveryService.
     */
    public function sendTestEmail(Request $request)
    {
        $recipientEmail = trim($request->input('recipient_email', $request->input('settings.SET_EMAIL_FROM_ADDRESS', '')));

        if (empty($recipientEmail)) {
            $response = ['success' => false, 'message' => 'Alamat email penerima tidak boleh kosong.'];
            return $request->expectsJson() ? response()->json($response, 422) : back()->withErrors(['error' => $response['message']]);
        }

        $result = $this->emailDeliveryService->sendTestEmail($recipientEmail);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ]);
        }

        if ($result['success']) {
            return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
                ->with('success', $result['message']);
        }

        return redirect()->route('settings.index', ['tab' => 'Email_Delivery'])
            ->withErrors(['error' => $result['message']]);
    }
}
