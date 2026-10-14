{{-- 
  SHARED OFFICIAL PDF FOOTER COMPONENT (H8.5)
  Reads dynamic document configuration from SystemSettingService::getCompanyProfile()['document'].
  Supports Digital Signature, Company Stamp, Authorized Signer Name & Title, and optional QR Verification.
  DomPDF compatible (table layout, inline styles, base64 image encoding, zero JS).
--}}
@props([
    'document'       => [],
    'verificationUrl'=> null,
    'qrCodeSvg'      => null,
    'notice'         => null
])

@php
    $signerName  = !empty($document['signer_name'])  ? $document['signer_name']  : 'Pejabat Berwenang';
    $signerTitle = !empty($document['signer_title']) ? $document['signer_title'] : 'Authorized Signatory';
    $sigUrl      = $document['signature_url'] ?? '';
    $stampUrl    = $document['stamp_url']     ?? '';

    // Convert signature image to base64 for safe DomPDF rendering
    $sigSrc = null;
    if (!empty($sigUrl)) {
        $sigFullPath = public_path($sigUrl);
        if (file_exists($sigFullPath) && is_file($sigFullPath)) {
            $ext = strtolower(pathinfo($sigFullPath, PATHINFO_EXTENSION));
            $ext = ($ext === 'jpg') ? 'jpeg' : $ext;
            $imgData = @file_get_contents($sigFullPath);
            if ($imgData !== false) {
                $sigSrc = 'data:image/' . $ext . ';base64,' . base64_encode($imgData);
            }
        }
    }

    // Convert stamp image to base64 for safe DomPDF rendering
    $stampSrc = null;
    if (!empty($stampUrl)) {
        $stampFullPath = public_path($stampUrl);
        if (file_exists($stampFullPath) && is_file($stampFullPath)) {
            $ext = strtolower(pathinfo($stampFullPath, PATHINFO_EXTENSION));
            $ext = ($ext === 'jpg') ? 'jpeg' : $ext;
            $imgData = @file_get_contents($stampFullPath);
            if ($imgData !== false) {
                $stampSrc = 'data:image/' . $ext . ';base64,' . base64_encode($imgData);
            }
        }
    }
@endphp

<div class="pdf-footer-wrapper" style="width: 100%; margin-top: 30px;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            {{-- Left Side: QR Code Verification Box (Optional) --}}
            <td style="vertical-align: bottom; width: 50%;">
                @if(!empty($qrCodeSvg) || !empty($verificationUrl))
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; width: 220px; text-align: center;">
                        <div style="font-size: 8px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">
                            Verifikasi Keabsahan Dokumen
                        </div>
                        @if(!empty($qrCodeSvg))
                            <div style="margin: 0 auto; display: inline-block;">
                                {!! $qrCodeSvg !!}
                            </div>
                        @elseif(!empty($verificationUrl))
                            <div style="font-size: 8px; color: #2563eb; font-weight: bold; word-break: break-all; margin: 4px 0;">
                                {{ $verificationUrl }}
                            </div>
                        @endif
                        <div style="font-size: 7.5px; color: #94a3b8; margin-top: 3px;">
                            Pindai QR Code untuk memverifikasi keaslian dokumen resmi ini secara publik.
                        </div>
                    </div>
                @endif
            </td>

            {{-- Right Side: Signature, Stamp, Signer Name & Title --}}
            <td style="vertical-align: top; width: 50%; text-align: center;">
                <div style="display: inline-block; text-align: center; min-width: 200px;">
                    <div style="font-size: 10px; font-weight: 700; color: #475569;">
                        Hormat Kami,
                    </div>
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                        {{ $signerTitle }}
                    </div>

                    {{-- Signature & Stamp Container --}}
                    <div style="position: relative; height: 70px; margin: 5px auto; width: 180px;">
                        @if(!empty($stampSrc))
                            <img src="{{ $stampSrc }}" alt="Stempel" style="position: absolute; left: 10px; top: 0px; height: 65px; opacity: 0.85; z-index: 1;">
                        @endif
                        @if(!empty($sigSrc))
                            <img src="{{ $sigSrc }}" alt="TTD" style="position: absolute; left: 30px; top: 10px; height: 50px; z-index: 2;">
                        @endif
                    </div>

                    <div style="border-bottom: 1px solid #0f172a; width: 180px; margin: 0 auto;"></div>
                    <div style="font-size: 11px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        {{ $signerName }}
                    </div>
                    <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">
                        Tanggal: {{ date('d M Y') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Verification Notice / Footer Disclaimer --}}
    @if(!empty($notice))
        <div style="margin-top: 20px; padding-top: 8px; border-top: 1px dashed #cbd5e1; font-size: 8.5px; color: #94a3b8; text-align: center;">
            {{ $notice }}
        </div>
    @else
        <div style="margin-top: 20px; padding-top: 8px; border-top: 1px dashed #cbd5e1; font-size: 8.5px; color: #94a3b8; text-align: center;">
            Dokumen ini diterbitkan secara elektronik oleh {{ config('branding.application.name') }} dan memiliki kekuatan verifikasi yang sah.
        </div>
    @endif
</div>
