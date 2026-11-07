@php
    $isRtl = $dir === 'rtl';
    $alignEnd = $isRtl ? 'left' : 'right';
    $alignStart = $isRtl ? 'right' : 'left';
    $money = static fn ($value): string => number_format((float) $value, 2).' '.$transaction->currency;
    $shortId = \Illuminate\Support\Str::of((string) $transaction->public_id)->substr(-8)->upper();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ $locale === 'en' ? 'Invoice' : 'فاتورة' }} {{ $transaction->public_id }}</title>
    <style>
        body {
            font-family: xbriyaz, dejavusans, sans-serif;
            font-size: 10.5pt;
            color: #203b41;
            direction: {{ $dir }};
            text-align: {{ $alignStart }};
            line-height: 1.45;
        }
        table { border-collapse: collapse; }
        td, th { vertical-align: top; }
        .top-rule {
            width: 100%;
            height: 3.5pt;
            background-color: #176b60;
            margin: 0 0 12pt;
        }
        .header {
            width: 100%;
            margin-bottom: 12pt;
            border-bottom: 1px solid #e0e9e9;
            padding-bottom: 10pt;
        }
        .header td { padding: 0; border: 0; vertical-align: middle; }
        .brand-logo {
            width: 42px;
            height: auto;
        }
        .brand-fallback {
            font-size: 11pt;
            font-weight: bold;
            color: #176b60;
        }
        .brand-meta {
            padding-{{ $isRtl ? 'right' : 'left' }}: 8pt;
        }
        .brand-name {
            font-size: 10pt;
            font-weight: bold;
            color: #176b60;
            margin: 0;
        }
        .brand-tag {
            font-size: 8pt;
            color: #64787c;
            margin: 1pt 0 0;
        }
        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            color: #10544c;
            margin: 0 0 2pt;
            line-height: 1.15;
            text-align: {{ $alignEnd }};
        }
        .doc-id {
            font-size: 9pt;
            color: #64787c;
            text-align: {{ $alignEnd }};
            direction: ltr;
            unicode-bidi: embed;
        }
        .doc-id strong {
            color: #10544c;
            font-size: 11pt;
        }
        .business-block {
            width: 100%;
            margin: 0 0 12pt;
        }
        .business-block td { padding: 0; border: 0; }
        .business-name {
            font-size: 12.5pt;
            font-weight: bold;
            color: #203b41;
            margin: 0 0 2pt;
        }
        .muted {
            color: #64787c;
            font-size: 9pt;
        }
        .meta-card {
            width: 100%;
            background-color: #f3f5f5;
            border: 1px solid #e0e9e9;
            margin: 0 0 14pt;
        }
        .meta-card td {
            padding: 9pt 10pt;
            width: 25%;
            border-{{ $isRtl ? 'left' : 'right' }}: 1px solid #e0e9e9;
        }
        .meta-card td:last-child {
            border-{{ $isRtl ? 'left' : 'right' }}: 0;
        }
        .meta-label {
            display: block;
            color: #64787c;
            font-size: 8pt;
            margin-bottom: 2pt;
        }
        .meta-value {
            font-size: 10pt;
            font-weight: bold;
            color: #203b41;
        }
        .party {
            width: 100%;
            margin: 0 0 14pt;
            border: 1px solid #e0e9e9;
        }
        .party td {
            padding: 11pt 12pt;
            border: 0;
        }
        .party-label {
            color: #176b60;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: none;
            margin: 0 0 4pt;
        }
        .party-name {
            font-size: 13pt;
            font-weight: bold;
            color: #203b41;
            margin: 0;
        }
        .party-note {
            margin-top: 5pt;
            color: #64787c;
            font-size: 9.5pt;
        }
        .issuer {
            color: #64787c;
            font-size: 9pt;
            line-height: 1.55;
        }
        .items {
            width: 100%;
            margin: 0 0 12pt;
        }
        .items th {
            background-color: #176b60;
            color: #ffffff;
            font-size: 9pt;
            font-weight: bold;
            padding: 8pt 8pt;
            border: 1px solid #176b60;
            text-align: {{ $alignStart }};
        }
        .items th.num,
        .items td.num {
            text-align: {{ $alignEnd }};
            white-space: nowrap;
        }
        .items td {
            padding: 8pt;
            border: 1px solid #e0e9e9;
            font-size: 10pt;
        }
        .items tr.alt td {
            background-color: #f7faf9;
        }
        .items .empty-line {
            color: #64787c;
        }
        .bottom {
            width: 100%;
            margin-top: 4pt;
        }
        .bottom td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }
        .notes {
            padding-{{ $isRtl ? 'left' : 'right' }}: 14pt;
        }
        .notes-title {
            font-size: 9pt;
            font-weight: bold;
            color: #10544c;
            margin: 0 0 4pt;
        }
        .notes-body {
            font-size: 9pt;
            color: #64787c;
            line-height: 1.55;
        }
        .totals {
            width: 100%;
            border: 1px solid #e0e9e9;
            background-color: #f7faf9;
        }
        .totals td {
            padding: 7pt 10pt;
            border: 0;
            font-size: 10pt;
        }
        .totals .label { color: #64787c; }
        .totals .value {
            text-align: {{ $alignEnd }};
            font-weight: bold;
            color: #203b41;
        }
        .totals .divider td {
            border-top: 1px solid #e0e9e9;
            padding-top: 9pt;
        }
        .totals .grand .label,
        .totals .grand .value {
            color: #10544c;
            font-size: 12pt;
            font-weight: bold;
        }
        .status-paid { color: #217152; }
        .status-partial { color: #916019; }
        .status-unpaid { color: #b43d4d; }
        .sign-wrap {
            width: 100%;
            margin-top: 26pt;
        }
        .sign-box {
            width: 48%;
            border-top: 1px solid #c8d5d2;
            padding-top: 8pt;
        }
        .sign-label {
            font-size: 8.5pt;
            color: #64787c;
            margin: 0 0 6pt;
        }
        .sign-img {
            max-height: 52pt;
            max-width: 170pt;
            margin-bottom: 4pt;
        }
        .sign-name {
            font-size: 10pt;
            font-weight: bold;
            color: #203b41;
        }
        .sign-line {
            height: 36pt;
        }
    </style>
</head>
<body>
    <div class="top-rule"></div>

    <table class="header" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="54%">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="48" style="vertical-align: middle;">
                            @if (! empty($logo))
                                <img class="brand-logo" src="{{ $logo }}" alt="Beacon" width="42" height="23">
                            @else
                                <span class="brand-fallback">Beacon</span>
                            @endif
                        </td>
                        <td class="brand-meta" style="vertical-align: middle;">
                            <div class="brand-name">Beacon</div>
                            <div class="brand-tag">{{ $locale === 'en' ? 'Financial document' : 'مستند مالي' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="46%" style="text-align: {{ $alignEnd }}; vertical-align: middle;">
                <div class="doc-title">{{ $locale === 'en' ? 'Invoice' : 'فاتورة' }}</div>
                <div class="doc-id">
                    {{ $locale === 'en' ? 'No.' : 'رقم' }}
                    <strong>{{ $shortId }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <table class="business-block" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="58%">
                <div class="business-name">{{ $enterprise->business_name }}</div>
                <div class="issuer">
                    @if ($enterprise->location)
                        {{ $enterprise->location }}
                        @if ($enterprise->contact_phone || $enterprise->contact_email)<br>@endif
                    @endif
                    @if ($enterprise->contact_phone)
                        {{ $enterprise->contact_phone }}
                        @if ($enterprise->contact_email) · @endif
                    @endif
                    @if ($enterprise->contact_email)
                        {{ $enterprise->contact_email }}
                    @endif
                </div>
            </td>
            <td width="42%" style="text-align: {{ $alignEnd }};">
                <div class="muted" style="font-size: 7.5pt; direction: ltr; unicode-bidi: embed;">
                    {{ $transaction->public_id }}
                </div>
            </td>
        </tr>
    </table>

    <table class="meta-card" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <span class="meta-label">{{ $locale === 'en' ? 'Date' : 'التاريخ' }}</span>
                <span class="meta-value">{{ $transaction->transaction_date?->toDateString() ?: '—' }}</span>
            </td>
            <td>
                <span class="meta-label">{{ $locale === 'en' ? 'Type' : 'النوع' }}</span>
                <span class="meta-value">{{ $transaction->type->label() }}</span>
            </td>
            <td>
                <span class="meta-label">{{ $locale === 'en' ? 'Category' : 'التصنيف' }}</span>
                <span class="meta-value">{{ $categoryLabel }}</span>
            </td>
            <td>
                <span class="meta-label">{{ $locale === 'en' ? 'Currency' : 'العملة' }}</span>
                <span class="meta-value">{{ $transaction->currency }}</span>
            </td>
        </tr>
    </table>

    <table class="party" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="55%">
                <div class="party-label">{{ $locale === 'en' ? 'Bill to / From' : 'الجهة' }}</div>
                <div class="party-name">{{ $transaction->counterparty ?: '—' }}</div>
                @if ($transaction->description)
                    <div class="party-note">{{ $transaction->description }}</div>
                @endif
            </td>
            <td width="45%" style="text-align: {{ $alignEnd }}; background-color: #eef4f2;">
                <div class="party-label">{{ $locale === 'en' ? 'Payment status' : 'حالة الدفع' }}</div>
                <div class="party-name {{ $paymentStatusClass }}">{{ $transaction->payment_status->label() }}</div>
                <div class="party-note">
                    {{ $locale === 'en' ? 'Outstanding' : 'المتبقي' }}:
                    <strong>{{ $money($outstanding) }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <table class="items" width="100%" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="8%">#</th>
                <th width="44%">{{ $locale === 'en' ? 'Description' : 'الوصف' }}</th>
                <th class="num" width="12%">{{ $locale === 'en' ? 'Qty' : 'الكمية' }}</th>
                <th class="num" width="18%">{{ $locale === 'en' ? 'Unit price' : 'سعر الوحدة' }}</th>
                <th class="num" width="18%">{{ $locale === 'en' ? 'Line total' : 'الإجمالي' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr class="{{ $index % 2 === 1 ? 'alt' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $money($item->unit_price) }}</td>
                    <td class="num">{{ $money($item->total) }}</td>
                </tr>
            @empty
                <tr>
                    <td>1</td>
                    <td class="empty-line">{{ $categoryLabel }}</td>
                    <td class="num">1</td>
                    <td class="num">{{ $money($transaction->amount) }}</td>
                    <td class="num">{{ $money($transaction->amount) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="bottom" width="100%">
        <tr>
            <td width="52%" class="notes">
                <div class="notes-title">{{ $locale === 'en' ? 'Notes' : 'ملاحظات' }}</div>
                <div class="notes-body">
                    @if ($transaction->description)
                        {{ $transaction->description }}
                    @else
                        {{ $locale === 'en'
                            ? 'This document summarizes a recorded financial transaction in Beacon.'
                            : 'يلخّص هذا المستند معاملة مالية مسجّلة في منصة بيكون.' }}
                    @endif
                    @if ($creatorName)
                        <br>{{ $locale === 'en' ? 'Recorded by' : 'سجّلها' }}: {{ $creatorName }}
                    @endif
                </div>
            </td>
            <td width="48%">
                <table class="totals" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="label">{{ $locale === 'en' ? 'Subtotal' : 'المجموع الفرعي' }}</td>
                        <td class="value">{{ $money($transaction->amount) }}</td>
                    </tr>
                    <tr>
                        <td class="label">{{ $locale === 'en' ? 'Outstanding' : 'المتبقي' }}</td>
                        <td class="value">{{ $money($outstanding) }}</td>
                    </tr>
                    <tr class="divider grand">
                        <td class="label">{{ $locale === 'en' ? 'Total' : 'الإجمالي' }}</td>
                        <td class="value">{{ $money($transaction->amount) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="sign-wrap" width="100%">
        <tr>
            <td class="sign-box" style="{{ $isRtl ? 'margin-right:auto' : '' }}">
                <div class="sign-label">{{ $locale === 'en' ? 'Authorized signature' : 'التوقيع المعتمد' }}</div>
                @if ($signature)
                    <img class="sign-img" src="{{ $signature }}" alt="">
                @else
                    <div class="sign-line"></div>
                @endif
                @if ($signerName)
                    <div class="sign-name">{{ $signerName }}</div>
                @endif
            </td>
            <td width="4%"></td>
            <td class="sign-box">
                <div class="sign-label">{{ $locale === 'en' ? 'Date / stamp' : 'التاريخ / الختم' }}</div>
                <div class="sign-line"></div>
                <div class="muted">{{ $transaction->transaction_date?->toDateString() }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
