<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Helpers\UserResolverHelper;
use App\Helpers\TerbilangHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SmartGeneratorController extends Controller
{
    protected $settingService;
    protected $invoiceService;

    public function __construct(SystemSettingService $settingService, InvoiceService $invoiceService)
    {
        $this->settingService = $settingService;
        $this->invoiceService = $invoiceService;
    }

    public function index(Request $request)
    {
        $company = $this->settingService->getCompanyProfile();
        $history = $this->getHistory();

        return view('finance.smart_generator', compact('company', 'history'));
    }

    public function searchStudentInvoices(Request $request)
    {
        $query = trim($request->input('q', ''));
        $studentRepo = app(StudentRepositoryInterface::class);
        $userRepo = app(UserRepositoryInterface::class);

        $allInvoices = $this->invoiceService->getAll();
        $students = collect($studentRepo->fetchAll());
        $users = collect($userRepo->fetchAll());

        $results = [];

        foreach ($allInvoices as $inv) {
            $studentId = $inv['Student_ID'] ?? '';
            $invType = strtoupper($inv['Invoice_Type'] ?? 'STUDENT');

            // Only include Student invoices
            if (empty($studentId) && $invType !== 'STUDENT') {
                continue;
            }

            $student = $students->firstWhere('Student_ID', $studentId);
            $studentName = $student['Full_Name'] ?? UserResolverHelper::getName($studentId);
            if ($studentName === '-' || empty($studentName)) {
                $studentName = $studentId ?: 'Siswa';
            }

            // Find email
            $studentEmail = $student['Email'] ?? '';
            if (empty($studentEmail) && !empty($student['User_ID'])) {
                $userRec = $users->firstWhere('User_ID', $student['User_ID']);
                $studentEmail = $userRec['Email'] ?? '';
            }

            $docNumber = $inv['Invoice_ID'] ?? ($inv['Invoice_Number'] ?? '');
            $description = $inv['Description'] ?? ($inv['Category'] ?? 'Tagihan Siswa');
            $grandTotal = (float)($inv['Grand_Total'] ?? ($inv['Amount'] ?? 0));
            $status = strtoupper($inv['Display_Status'] ?? ($inv['Status'] ?? 'UNPAID'));
            $issueDate = $inv['Invoice_Date'] ?? date('Y-m-d');
            $dueDate = $inv['Due_Date'] ?? date('Y-m-d', strtotime('+14 days'));
            $address = $student['Address'] ?? '';

            $parsedItems = $inv['Parsed_Line_Items'] ?? [];
            $items = [];
            if (!empty($parsedItems) && is_array($parsedItems)) {
                foreach ($parsedItems as $pi) {
                    $items[] = [
                        'name' => $pi['description'] ?? $description,
                        'qty' => (float)($pi['qty'] ?? 1),
                        'price' => (float)($pi['unit_price'] ?? 0),
                        'total' => (float)($pi['subtotal'] ?? 0)
                    ];
                }
            }

            if (empty($items)) {
                $items[] = [
                    'name' => $description,
                    'qty' => 1,
                    'price' => $grandTotal,
                    'total' => $grandTotal
                ];
            }

            // Filter by search query if provided
            if (!empty($query)) {
                $searchableText = strtolower("{$docNumber} {$studentId} {$studentName} {$studentEmail} {$description}");
                if (!str_contains($searchableText, strtolower($query))) {
                    continue;
                }
            }

            $results[] = [
                'id' => $docNumber,
                'doc_number' => $docNumber,
                'student_id' => $studentId,
                'student_name' => $studentName,
                'student_email' => $studentEmail,
                'student_address' => $address,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => $status,
                'amount' => $grandTotal,
                'subtotal' => (float)($inv['Subtotal_Amount'] ?? $grandTotal),
                'discount' => (float)($inv['Total_Discount'] ?? 0),
                'ppn_amount' => (float)($inv['Total_Tax'] ?? 0),
                'grand_total' => $grandTotal,
                'description' => $description,
                'items' => $items
            ];
        }

        return response()->json([
            'success' => true,
            'invoices' => array_values($results)
        ]);
    }

    public function exportPdf(Request $request)
    {
        try {
            $docType = $request->input('doc_type', 'invoice');
            $data = $this->prepareDocumentData($request);

            $viewName = ($docType === 'kwitansi')
                ? 'pdf.smart_generator_kwitansi'
                : 'pdf.smart_generator_invoice';

            $filename = ($docType === 'kwitansi' ? 'Kwitansi_' : 'Invoice_') . ($data['doc_number'] ?? time()) . '.pdf';

            $pdf = Pdf::loadView($viewName, ['data' => $data]);
            $pdf->setPaper('A4', 'portrait');
            $pdf->getDomPDF()->getOptions()->set([
                'defaultFont' => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);

            return $pdf->download($filename);
        } catch (Throwable $e) {
            Log::error('Smart Generator PDF export failed', [
                'stage' => 'prepare_render_download',
                'exception' => get_class($e),
                'doc_type' => $request->input('doc_type', 'invoice'),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen gagal diekspor. Periksa data lalu coba lagi.',
                ], 422);
            }

            return back()
                ->with('error', 'Dokumen gagal diekspor. Periksa data lalu coba lagi.')
                ->withInput();
        }
    }

    public function saveHistory(Request $request)
    {
        $data = $this->prepareDocumentData($request);
        $data['id'] = $request->input('id', 'HIST-' . date('YmdHis') . '-' . Str::random(4));
        $data['saved_at'] = now()->format('d M Y, H:i');

        $history = $this->mutateHistory(function (array $history) use ($data) {
            $existingIndex = null;
            foreach ($history as $index => $item) {
                if (isset($item['doc_number']) && $item['doc_number'] === $data['doc_number']) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $history[$existingIndex] = $data;
            } else {
                array_unshift($history, $data);
            }

            return $history;
        });

        return response()->json([
            'success' => true,
            'message' => 'Dokumen ' . ($data['doc_number'] ?? '') . ' berhasil disimpan ke riwayat.',
            'history' => $history
        ]);
    }

    public function getHistoryApi()
    {
        return response()->json([
            'success' => true,
            'history' => $this->getHistory()
        ]);
    }

    public function deleteHistory($id)
    {
        $filtered = $this->mutateHistory(function (array $history) use ($id) {
            return array_values(array_filter($history, function ($item) use ($id) {
                return ($item['id'] ?? '') !== $id && ($item['doc_number'] ?? '') !== $id;
            }));
        });

        return response()->json([
            'success' => true,
            'message' => 'Riwayat dokumen berhasil dihapus.',
            'history' => $filtered
        ]);
    }

    public function sendEmail(Request $request)
    {
        $clientEmail = trim($request->input('client_email', ''));
        $docNumber = $request->input('doc_number');
        $sourceType = $request->input('source_type', 'manual_invoice');
        $sourceId = $request->input('source_id');
        $studentId = $request->input('student_id');

        // For student invoices, if email is empty, try to resolve from Student/User record
        if ($sourceType === 'student_invoice' && empty($clientEmail) && (!empty($sourceId) || !empty($studentId))) {
            try {
                $studentRepo = app(StudentRepositoryInterface::class);
                $userRepo = app(UserRepositoryInterface::class);
                $sId = $studentId;
                if (empty($sId) && !empty($sourceId)) {
                    $inv = $this->invoiceService->getById($sourceId);
                    $sId = $inv['Student_ID'] ?? '';
                }
                if (!empty($sId)) {
                    $student = $studentRepo->findById($sId);
                    $clientEmail = $student['Email'] ?? '';
                    if (empty($clientEmail) && !empty($student['User_ID'])) {
                        $userRec = $userRepo->findById($student['User_ID']);
                        $clientEmail = $userRec['Email'] ?? '';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore error
            }
        }

        if (empty($clientEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'Email siswa tidak tersedia.'
            ], 422);
        }

        $payload = $this->prepareDocumentData($request);
        $payload['client_email'] = $clientEmail;

        $emailDeliveryService = app(\App\Services\Core\EmailDeliveryService::class);
        $result = $emailDeliveryService->sendDocumentEmail($payload);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 422);
        }

        return response()->json($result, 200);
    }

    private function prepareDocumentData(Request $request)
    {
        if ($request->input('source_type') === 'student_invoice') {
            return $this->prepareStudentInvoiceDocumentData($request);
        }

        $items = $request->input('items', []);
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        $subtotal = 0;
        $formattedItems = [];
        foreach ($items as $item) {
            $qty = (float)($item['qty'] ?? 1);
            $price = (float)($item['price'] ?? 0);
            $total = $qty * $price;
            $subtotal += $total;
            $formattedItems[] = [
                'name' => $item['name'] ?? 'Item Layanan / Produk',
                'qty' => $qty,
                'price' => $price,
                'total' => $total
            ];
        }

        $discount = (float)$request->input('discount', 0);
        $ppnPercent = (float)$request->input('ppn_percent', 0);
        $shipping = (float)$request->input('shipping', 0);

        $afterDiscount = max(0, $subtotal - $discount);
        $ppnAmount = ($afterDiscount * $ppnPercent) / 100;
        $grandTotal = $afterDiscount + $ppnAmount + $shipping;

        // Convert base64 / path images for PDF compatibility
        $logo = $request->input('company_logo');
        $signature = $request->input('signature');
        $stamp = $request->input('stamp');

        $logoBase64 = $this->encodeImageToBase64($logo);
        $signatureBase64 = $this->encodeImageToBase64($signature);
        $stampBase64 = $this->encodeImageToBase64($stamp);

        $kwitansiAmount = (float)$request->input('kwitansi_amount', $grandTotal > 0 ? $grandTotal : $subtotal);

        return [
            'source_type' => $request->input('source_type', 'manual_invoice'),
            'source_id' => $request->input('source_id', null),
            'student_id' => $request->input('student_id', null),

            'doc_type' => $request->input('doc_type', 'invoice'),
            'theme' => $request->input('theme', 'emerald'),
            'doc_number' => $request->input('doc_number', 'INV-WMS-' . rand(1000, 9999)),
            'currency' => $request->input('currency', 'IDR'),
            'issue_date' => $request->input('issue_date', date('Y-m-d')),
            'due_date' => $request->input('due_date', date('Y-m-d', strtotime('+14 days'))),
            'status' => $request->input('status', 'UNPAID'),
            
            // Client info
            'client_name' => $request->input('client_name', ''),
            'client_email' => $request->input('client_email', ''),
            'client_address' => $request->input('client_address', ''),

            // Company Profile Info
            'company_name' => $request->input('company_name', config('branding.organization.name')),
            'company_tagline' => $request->input('company_tagline', config('branding.organization.tagline')),
            'company_address' => $request->input('company_address', config('branding.organization.address')),
            'company_phone' => $request->input('company_phone', config('branding.organization.phone')),
            'company_email' => $request->input('company_email', config('branding.organization.email')),
            'company_web' => $request->input('company_web', config('branding.organization.website')),
            'company_npwp' => $request->input('company_npwp', config('branding.organization.npwp')),
            'layout_kop' => $request->input('layout_kop', 'left'),

            // Bank Info
            'bank_name' => $request->input('bank_name', config('branding.bank.name')),
            'bank_account' => $request->input('bank_account', config('branding.bank.account_number')),
            'bank_holder' => $request->input('bank_holder', config('branding.bank.account_holder')),

            // Items & Totals
            'items' => $formattedItems,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'ppn_percent' => $ppnPercent,
            'ppn_amount' => $ppnAmount,
            'shipping' => $shipping,
            'grand_total' => $grandTotal,

            // Kwitansi specific
            'kwitansi_amount' => $kwitansiAmount,
            'terbilang' => TerbilangHelper::convert($kwitansiAmount),
            'payment_for' => $request->input('payment_for', 'Total Angsuran Keempat Biaya Pengurusan Dokumen Ke Jepang'),
            'issue_city' => $request->input('issue_city', config('branding.document.issue_city')),
            'signer_name' => $request->input('signer_name', config('branding.document.signer_name')),

            // Terms
            'notes' => $request->input('notes', 'Pembayaran dilakukan ke rekening resmi yang tercantum pada dokumen.'),

            // Images
            'company_logo' => $logoBase64,
            'signature' => $signatureBase64,
            'stamp' => $stampBase64
        ];
    }

    private function prepareStudentInvoiceDocumentData(Request $request): array
    {
        $sourceId = trim((string) $request->input('source_id', ''));
        if ($sourceId === '') {
            throw new \InvalidArgumentException('Source invoice wajib dipilih untuk dokumen invoice siswa.');
        }

        $invoice = $this->invoiceService->getById($sourceId);
        if (!$invoice) {
            throw new \InvalidArgumentException("Invoice siswa #{$sourceId} tidak ditemukan.");
        }

        if (strtoupper((string) ($invoice['Invoice_Type'] ?? 'STUDENT')) !== 'STUDENT' || empty($invoice['Student_ID'])) {
            throw new \InvalidArgumentException("Invoice #{$sourceId} bukan invoice siswa yang valid.");
        }

        $studentRepo = app(StudentRepositoryInterface::class);
        $userRepo = app(UserRepositoryInterface::class);
        $student = $studentRepo->findById($invoice['Student_ID']);

        $studentName = $student['Full_Name'] ?? 'Data siswa tidak ditemukan';
        $studentEmail = $student['Email'] ?? '';
        if (empty($studentEmail) && !empty($student['User_ID'])) {
            $user = $userRepo->findById($student['User_ID']);
            $studentEmail = $user['Email'] ?? '';
        }

        $description = $invoice['Description'] ?? ($invoice['Category'] ?? 'Tagihan Siswa');
        $items = $this->trustedInvoiceItems($invoice, $description);
        $subtotal = (float) ($invoice['Subtotal_Amount'] ?? collect($items)->sum('total'));
        $discount = (float) ($invoice['Total_Discount'] ?? 0);
        $ppnAmount = (float) ($invoice['Total_Tax'] ?? 0);
        $grandTotal = (float) ($invoice['Grand_Total'] ?? $invoice['Amount'] ?? collect($items)->sum('total'));
        $docType = $request->input('doc_type') === 'kwitansi' ? 'kwitansi' : 'invoice';
        $docNumber = $docType === 'kwitansi' ? 'KWI-' . $invoice['Invoice_ID'] : $invoice['Invoice_ID'];
        $status = $invoice['Display_Status'] ?? ($invoice['Status'] ?? 'Waiting Payment');
        $logoBase64 = $this->encodeImageToBase64($request->input('company_logo'));
        $signatureBase64 = $this->encodeImageToBase64($request->input('signature'));
        $stampBase64 = $this->encodeImageToBase64($request->input('stamp'));
        $kwitansiAmount = $grandTotal;

        return [
            'source_type' => 'student_invoice',
            'source_id' => $invoice['Invoice_ID'],
            'student_id' => $invoice['Student_ID'],

            'doc_type' => $docType,
            'theme' => $this->normalizeTheme($request->input('theme', 'emerald')),
            'doc_number' => $docNumber,
            'currency' => 'IDR',
            'issue_date' => $invoice['Invoice_Date'] ?? ($invoice['Created_At'] ?? date('Y-m-d')),
            'due_date' => $invoice['Due_Date'] ?? date('Y-m-d', strtotime('+14 days')),
            'status' => $status,

            'client_name' => $studentName,
            'client_email' => $studentEmail,
            'client_address' => $student['Address'] ?? '',

            'company_name' => $request->input('company_name', config('branding.organization.name')),
            'company_tagline' => $request->input('company_tagline', config('branding.organization.tagline')),
            'company_address' => $request->input('company_address', config('branding.organization.address')),
            'company_phone' => $request->input('company_phone', config('branding.organization.phone')),
            'company_email' => $request->input('company_email', config('branding.organization.email')),
            'company_web' => $request->input('company_web', config('branding.organization.website')),
            'company_npwp' => $request->input('company_npwp', config('branding.organization.npwp')),
            'layout_kop' => $request->input('layout_kop', 'left'),

            'bank_name' => $request->input('bank_name', config('branding.bank.name')),
            'bank_account' => $request->input('bank_account', config('branding.bank.account_number')),
            'bank_holder' => $request->input('bank_holder', config('branding.bank.account_holder')),

            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'ppn_percent' => 0,
            'ppn_amount' => $ppnAmount,
            'shipping' => 0,
            'grand_total' => $grandTotal,

            'kwitansi_amount' => $kwitansiAmount,
            'terbilang' => TerbilangHelper::convert($kwitansiAmount),
            'payment_for' => $description,
            'issue_city' => $request->input('issue_city', config('branding.document.issue_city')),
            'signer_name' => $request->input('signer_name', config('branding.document.signer_name')),
            'notes' => $invoice['Notes'] ?? $request->input('notes', 'Pembayaran dilakukan ke rekening resmi yang tercantum pada dokumen.'),
            'company_logo' => $logoBase64,
            'signature' => $signatureBase64,
            'stamp' => $stampBase64
        ];
    }

    private function trustedInvoiceItems(array $invoice, string $description): array
    {
        $lineItems = $invoice['Parsed_Line_Items'] ?? ($invoice['Line_Items'] ?? []);
        if (is_string($lineItems)) {
            $lineItems = json_decode($lineItems, true) ?: [];
        }

        $items = [];
        if (is_array($lineItems)) {
            foreach ($lineItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $qty = (float) ($item['qty'] ?? 1);
                $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                $total = (float) ($item['subtotal'] ?? ($qty * $price));
                $items[] = [
                    'name' => $item['description'] ?? ($item['name'] ?? $description),
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $total,
                ];
            }
        }

        if (empty($items)) {
            $amount = (float) ($invoice['Grand_Total'] ?? $invoice['Amount'] ?? 0);
            $items[] = [
                'name' => $description,
                'qty' => 1,
                'price' => $amount,
                'total' => $amount,
            ];
        }

        return $items;
    }

    private function normalizeTheme(string $theme): string
    {
        return in_array($theme, ['emerald', 'indigo', 'crimson'], true) ? $theme : 'emerald';
    }

    private function encodeImageToBase64($pathOrBase64)
    {
        if (empty($pathOrBase64)) {
            return null;
        }

        if (is_string($pathOrBase64) && preg_match('#^data:image/(png|jpe?g|webp|gif);base64,[A-Za-z0-9+/=\s]+$#i', $pathOrBase64)) {
            return $pathOrBase64;
        }

        // Extract relative URL path if a full URL (http/https) was passed
        $urlPath = parse_url((string) $pathOrBase64, PHP_URL_PATH) ?? (string) $pathOrBase64;
        $cleanPath = str_replace('\\', '/', ltrim(rawurldecode($urlPath), '/'));

        if (
            $cleanPath === ''
            || str_contains($cleanPath, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $cleanPath)
            || preg_match('/^[A-Za-z]:/', $cleanPath)
            || !preg_match('#^(storage|img|images|assets)/#', $cleanPath)
        ) {
            return null;
        }

        // Check in public_path (e.g. img/logo.png.jpeg, storage/...)
        $fullPath = $this->resolveSafePath(public_path($cleanPath), public_path());

        // Fallback: check storage/app/public if path starts with storage/
        if (!$fullPath && str_starts_with($cleanPath, 'storage/')) {
            $storageRelative = str_replace('storage/', 'app/public/', $cleanPath);
            $fullPath = $this->resolveSafePath(storage_path($storageRelative), storage_path('app/public'));
        }

        if ($fullPath && is_file($fullPath)) {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            if ($ext === 'jpg') {
                $ext = 'jpeg';
            }
            if (!in_array($ext, ['jpeg', 'png', 'webp', 'gif'], true)) {
                return null;
            }
            $imgData = @file_get_contents($fullPath);
            if ($imgData !== false) {
                return 'data:image/' . $ext . ';base64,' . base64_encode($imgData);
            }
        }

        return null;
    }

    private function resolveSafePath(string $candidatePath, string $rootPath): ?string
    {
        $realCandidate = realpath($candidatePath);
        $realRoot = realpath($rootPath);

        if (!$realCandidate || !$realRoot) {
            return null;
        }

        $rootPrefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($realCandidate, $rootPrefix) ? $realCandidate : null;
    }

    private function getHistory()
    {
        $filePath = storage_path('app/smart_generator_history.json');
        if (file_exists($filePath)) {
            $json = file_get_contents($filePath);
            if ($json === false) {
                throw new \RuntimeException('Riwayat Smart Generator tidak dapat dibaca.');
            }

            $decoded = json_decode($json, true);
            if (!is_array($decoded) && trim($json) !== '') {
                throw new \RuntimeException('Format riwayat Smart Generator tidak valid.');
            }

            return $decoded ?: [];
        }
        return [];
    }

    private function saveHistoryFile(array $history)
    {
        $filePath = storage_path('app/smart_generator_history.json');
        $json = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($filePath, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Riwayat Smart Generator gagal disimpan.');
        }
    }

    private function mutateHistory(callable $mutation): array
    {
        return \Illuminate\Support\Facades\Cache::lock('smart_generator_history_write', 10)
            ->block(3, function () use ($mutation) {
                $history = $mutation($this->getHistory());
                $this->saveHistoryFile($history);

                return $history;
            });
    }
}
