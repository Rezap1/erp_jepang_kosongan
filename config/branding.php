<?php

$configuredAppName = env('APP_NAME', 'ERP LPK Jepang');
$defaultAppName = $configuredAppName === 'Laravel' ? 'ERP LPK Jepang' : $configuredAppName;

return [
    'application' => [
        'name' => env('BRAND_APP_NAME', $defaultAppName),
        'short_name' => env('BRAND_SHORT_NAME', 'ERP LPK Jepang'),
        'version' => env('BRAND_VERSION', '1.0'),
    ],

    'organization' => [
        'name' => env('BRAND_ORGANIZATION_NAME', 'Nama LPK / Perusahaan'),
        'tagline' => env('BRAND_TAGLINE', 'Sistem Manajemen LPK Jepang'),
        'address' => env('BRAND_ADDRESS', 'Alamat LPK / perusahaan'),
        'phone' => env('BRAND_PHONE', ''),
        'whatsapp' => env('BRAND_WHATSAPP', ''),
        'email' => env('BRAND_EMAIL', 'admin@localhost.test'),
        'website' => env('BRAND_WEBSITE', ''),
        'npwp' => env('BRAND_NPWP', ''),
    ],

    'assets' => [
        'logo' => env('BRAND_LOGO', 'img/erp-lpk-jepang-logo.png'),
        'favicon' => env('BRAND_FAVICON', 'img/erp-lpk-jepang-favicon.png'),
        'login_background' => env('BRAND_LOGIN_BACKGROUND', 'img/erp-lpk-jepang-login-bg.png'),
    ],

    'colors' => [
        'primary' => env('BRAND_PRIMARY_COLOR', '#DC2626'),
        'secondary' => env('BRAND_SECONDARY_COLOR', '#0F172A'),
        'accent' => env('BRAND_ACCENT_COLOR', '#F59E0B'),
        'sidebar_bg' => env('BRAND_SIDEBAR_BG', '#0F172A'),
        'sidebar_text' => env('BRAND_SIDEBAR_TEXT', '#CBD5E1'),
        'sidebar_active_bg' => env('BRAND_SIDEBAR_ACTIVE_BG', '#7F1D1D'),
        'sidebar_active_text' => env('BRAND_SIDEBAR_ACTIVE_TEXT', '#FFFFFF'),
        'topbar_bg' => env('BRAND_TOPBAR_BG', '#FFFFFF'),
        'topbar_text' => env('BRAND_TOPBAR_TEXT', '#0F172A'),
        'page_bg' => env('BRAND_PAGE_BG', '#F1F5F9'),
        'card_bg' => env('BRAND_CARD_BG', '#FFFFFF'),
    ],

    'bank' => [
        'name' => env('BRAND_BANK_NAME', ''),
        'account_number' => env('BRAND_BANK_ACCOUNT', ''),
        'account_holder' => env('BRAND_BANK_HOLDER', 'Nama LPK / Perusahaan'),
        'branch' => env('BRAND_BANK_BRANCH', ''),
    ],

    'document' => [
        'signer_name' => env('BRAND_SIGNER_NAME', 'Nama Penandatangan'),
        'signer_title' => env('BRAND_SIGNER_TITLE', 'Pimpinan LPK'),
        'issue_city' => env('BRAND_ISSUE_CITY', 'Kota penerbit'),
        'prefix' => env('BRAND_DOCUMENT_PREFIX', 'DOC/LPK/'),
    ],

    'social' => [
        'instagram' => env('BRAND_INSTAGRAM_URL', ''),
        'tiktok' => env('BRAND_TIKTOK_URL', ''),
    ],
];
