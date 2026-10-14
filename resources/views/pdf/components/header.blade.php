{{-- 
  SHARED OFFICIAL PDF HEADER COMPONENT (H8.4)
  Reads dynamic company profile configuration from SystemSettingService::getCompanyProfile()['company'].
  DomPDF compatible (table layout, inline styles, base64 image encoding, zero JS).
--}}
@props(['company' => []])

@php
    $companyName    = !empty($company['name'])    ? $company['name']    : config('branding.organization.name');
    $companyTagline = $company['tagline'] ?? '';
    $companyAddress = $company['address'] ?? '';
    $companyPhone   = $company['phone']   ?? '';
    $companyWa      = $company['whatsapp'] ?? '';
    $companyEmail   = $company['email']   ?? '';
    $companyWeb     = $company['website'] ?? '';
    $companyNpwp    = $company['npwp']    ?? '';
    $companyLogo    = $company['logo']    ?? '';

    // Convert local logo path to base64 data URI for safe DomPDF rendering
    $logoSrc = null;
    if (!empty($companyLogo)) {
        $logoFullPath = public_path($companyLogo);
        if (file_exists($logoFullPath) && is_file($logoFullPath)) {
            $ext = strtolower(pathinfo($logoFullPath, PATHINFO_EXTENSION));
            $ext = ($ext === 'jpg') ? 'jpeg' : $ext;
            $imgData = @file_get_contents($logoFullPath);
            if ($imgData !== false) {
                $logoSrc = 'data:image/' . $ext . ';base64,' . base64_encode($imgData);
            }
        }
    }

    // Build contacts string dynamically
    $contacts = [];
    if (!empty($companyPhone))   { $contacts[] = 'Telp: ' . $companyPhone; }
    if (!empty($companyWa))      { $contacts[] = 'WA: ' . $companyWa; }
    if (!empty($companyEmail))   { $contacts[] = 'Email: ' . $companyEmail; }
    if (!empty($companyWeb))     { $contacts[] = 'Web: ' . $companyWeb; }
    $contactString = implode(' | ', $contacts);
@endphp

<div class="pdf-header-wrapper" style="width: 100%; margin-bottom: 20px;">
    <table style="width: 100%; border-collapse: collapse; border-bottom: 3px double #0f172a; padding-bottom: 10px;">
        <tr>
            {{-- Logo Column --}}
            @if(!empty($logoSrc))
                <td style="width: 75px; vertical-align: middle; padding-right: 15px;">
                    <img src="{{ $logoSrc }}" alt="Logo" style="max-width: 70px; max-height: 70px; object-fit: contain;">
                </td>
            @endif

            {{-- Company Information Column --}}
            <td style="vertical-align: top; text-align: left;">
                <div style="font-size: 18px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.2;">
                    {{ $companyName }}
                </div>
                @if(!empty($companyTagline))
                    <div style="font-size: 10px; font-weight: 700; color: #475569; margin-top: 2px;">
                        {{ $companyTagline }}
                    </div>
                @endif
                @if(!empty($companyAddress))
                    <div style="font-size: 9px; color: #64748b; margin-top: 3px; line-height: 1.3;">
                        {{ $companyAddress }}
                    </div>
                @endif
                @if(!empty($contactString))
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">
                        {{ $contactString }}
                    </div>
                @endif
            </td>

            {{-- NPWP / Top Right Metadata Column --}}
            @if(!empty($companyNpwp))
                <td style="width: 180px; vertical-align: top; text-align: right;">
                    <div style="font-size: 9px; font-weight: 700; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                        NPWP: {{ $companyNpwp }}
                    </div>
                </td>
            @endif
        </tr>
    </table>
</div>
