<?php

namespace App\Services\Core;

use App\Services\Core\SystemSettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailDeliveryService
{
    protected $settingService;

    public function __construct(SystemSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Dynamically apply current Email Delivery Connection config to Laravel Mail.
     */
    public function applyDynamicMailConfig(): array
    {
        $config = $this->settingService->getEmailDeliveryConfig();
        $provider = $config['provider'];

        $encryptedPayload = $this->settingService->get('SET_EMAIL_CREDENTIAL_DATA', $this->settingService->get('EMAIL_CREDENTIAL_DATA', null));
        $credentials = [];
        if ($encryptedPayload) {
            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($encryptedPayload);
                $credentials = json_decode($decrypted, true) ?: [];
            } catch (\Throwable $e) {
                $credentials = [];
            }
        }

        $currentDriver = config('mail.default');
        $targetDriver = ($currentDriver === 'array' || app()->environment('testing')) ? 'array' : 'smtp';

        if ($provider === 'smtp' && !empty($credentials)) {
            config([
                'mail.default' => $targetDriver,
                'mail.mailers.smtp.host' => $credentials['host'] ?? config('mail.mailers.smtp.host', '127.0.0.1'),
                'mail.mailers.smtp.port' => (int)($credentials['port'] ?? config('mail.mailers.smtp.port', 587)),
                'mail.mailers.smtp.encryption' => (isset($credentials['encryption']) && strtolower($credentials['encryption']) !== 'none') ? strtolower($credentials['encryption']) : null,
                'mail.mailers.smtp.username' => $credentials['username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $credentials['password'] ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => $config['from_address'],
                'mail.from.name' => $config['from_name'],
            ]);
        } elseif (in_array($provider, ['google', 'microsoft']) && !empty($credentials)) {
            $smtpHost = ($provider === 'google') ? 'smtp.gmail.com' : 'smtp.office365.com';
            config([
                'mail.default' => $targetDriver,
                'mail.mailers.smtp.host' => $smtpHost,
                'mail.mailers.smtp.port' => 587,
                'mail.mailers.smtp.encryption' => 'tls',
                'mail.mailers.smtp.username' => $config['from_address'],
                'mail.mailers.smtp.password' => $credentials['access_token'] ?? ($credentials['password'] ?? ''),
                'mail.from.address' => $config['from_address'],
                'mail.from.name' => $config['from_name'],
            ]);
        } else {
            config([
                'mail.from.address' => $config['from_address'],
                'mail.from.name' => $config['from_name'],
            ]);
        }

        try {
            Mail::alwaysFrom($config['from_address'], $config['from_name']);
        } catch (\Throwable $e) {
            // Ignore if method unavailable
        }

        return $config;
    }

    /**
     * Get configured sender address and name.
     */
    public function getSenderConfig(): array
    {
        $config = $this->applyDynamicMailConfig();

        return [
            'from_address' => $config['from_address'] ?: config('branding.organization.email'),
            'from_name' => $config['from_name'] ?: config('branding.application.name'),
            'reply_to' => $config['reply_to'] ?: $config['from_address'],
            'provider' => $config['provider'],
            'status' => $config['is_healthy'] ? '🟢 Connected' : '🔴 Disconnected',
        ];
    }

    /**
     * Send test email to specified recipient (e.g. rezagaming800@gmail.com).
     */
    public function sendTestEmail(string $recipientEmail): array
    {
        $recipientEmail = trim($recipientEmail);

        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Alamat email penerima test tidak valid.'
            ];
        }

        $sender = $this->getSenderConfig();
        $subject = 'WMS Email Delivery Test';
        $timestamp = now()->format('d M Y H:i:s T');

        $bodyText = config('branding.application.name')."\n\n" .
            "Email delivery test berhasil dikirim.\n" .
            "Pengirim: {$sender['from_name']} <{$sender['from_address']}>\n" .
            "Waktu: {$timestamp}\n";

        try {
            Mail::raw($bodyText, function ($message) use ($recipientEmail, $sender, $subject) {
                $message->to($recipientEmail)
                        ->from($sender['from_address'], $sender['from_name'])
                        ->subject($subject);
            });

            return [
                'success' => true,
                'message' => "Email delivery test berhasil dikirim ke {$recipientEmail}.",
                'recipient' => $recipientEmail,
                'sender' => [
                    'from_address' => $sender['from_address'],
                    'from_name' => $sender['from_name']
                ]
            ];
        } catch (\Throwable $e) {
            // Log error without exposing raw credentials
            Log::error('EmailDeliveryService Test Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim email percobaan. Silakan periksa konfigurasi email atau hubungi administrator.'
            ];
        }
    }

    /**
     * Send Document Invoice / Kwitansi email with PDF attachment.
     */
    public function sendDocumentEmail(array $payload): array
    {
        $recipientEmail = trim($payload['client_email'] ?? '');
        $docNumber = $payload['doc_number'] ?? 'INV-DOCUMENT';
        $sourceType = $payload['source_type'] ?? 'manual_invoice';

        if (empty($recipientEmail)) {
            return [
                'success' => false,
                'message' => ($sourceType === 'student_invoice') ? 'Email siswa tidak tersedia.' : 'Email client tidak boleh kosong.'
            ];
        }

        $sender = $this->getSenderConfig();
        $subject = "Tagihan Invoice {$docNumber} — " . $sender['from_name'];

        $clientName = $payload['client_name'] ?? 'Pelanggan';
        $issueDate = $payload['issue_date'] ?? date('Y-m-d');
        $dueDate = $payload['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
        $grandTotal = 'Rp ' . number_format((float)($payload['grand_total'] ?? $payload['subtotal'] ?? 0), 0, ',', '.');

        $bodyText = "Yth. {$clientName},\n\n" .
            "Berikut kami sampaikan dokumen tagihan:\n\n" .
            "Invoice : {$docNumber}\n" .
            "Tanggal : {$issueDate}\n" .
            "Jatuh Tempo : {$dueDate}\n" .
            "Total : {$grandTotal}\n\n" .
            "Silakan melihat lampiran PDF untuk rincian lengkap.\n\n" .
            "Hormat kami,\n" .
            "{$sender['from_name']}\n";

        // Generate PDF Binary
        try {
            $viewName = (($payload['doc_type'] ?? 'invoice') === 'kwitansi')
                ? 'pdf.smart_generator_kwitansi'
                : 'pdf.smart_generator_invoice';

            $pdf = Pdf::loadView($viewName, ['data' => $payload]);
            $pdf->setPaper('A4', 'portrait');
            $pdf->getDomPDF()->getOptions()->set([
                'defaultFont' => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);

            $pdfContent = $pdf->output();
            $filename = (($payload['doc_type'] ?? 'invoice') === 'kwitansi' ? 'Kwitansi_' : 'Invoice_') . $docNumber . '.pdf';

            Mail::raw($bodyText, function ($message) use ($recipientEmail, $sender, $subject, $pdfContent, $filename) {
                $message->to($recipientEmail)
                        ->from($sender['from_address'], $sender['from_name'])
                        ->subject($subject)
                        ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
            });

            return [
                'success' => true,
                'message' => "Email pemberitahuan dokumen {$docNumber} telah dikirim ke {$recipientEmail}."
            ];
        } catch (\Throwable $e) {
            Log::error('EmailDeliveryService Document Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim email dokumen. Silakan periksa konfigurasi email atau hubungi administrator.'
            ];
        }
    }
}
