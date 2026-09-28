<!DOCTYPE html>
<html lang="{{ $settings->locale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $number }} — {{ $branding['company_name'] }}</title>
    <style>
        @page { margin: 1.6cm 1.4cm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111827; margin: 0; }
        .accent { color: {{ $branding['primary_color'] }}; }
        .header { border-bottom: 3px solid {{ $branding['primary_color'] }}; padding-bottom: 10px; margin-bottom: 16px; }
        .header table { width: 100%; }
        .logo { max-height: 56px; max-width: 200px; }
        .company { font-size: 15px; font-weight: bold; }
        .muted { color: #6b7280; }
        .doc-title { font-size: 18px; font-weight: bold; text-align: right; }
        .doc-number { text-align: right; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: {{ $branding['primary_color'] }};
             border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin: 18px 0 8px; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; width: 50%; padding-right: 12px; }
        .field { margin-bottom: 4px; }
        .field .label { color: #6b7280; display: inline-block; min-width: 90px; }
        .notes { white-space: pre-line; line-height: 1.5; }
        .section { page-break-inside: avoid; }
        .images td { width: 33%; padding: 4px; vertical-align: top; }
        .images img { width: 100%; height: 120px; object-fit: cover; border: 1px solid #e5e7eb; border-radius: 4px; }
        .signature-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; width: 280px; }
        .signature-box img { max-height: 90px; max-width: 260px; }
        .labels { margin-top: 24px; }
        .labels td { border: 1px solid #d1d5db; padding: 10px; width: 50%; height: 60px; vertical-align: top; }
        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="width:60%">
                    @if ($branding['logo'])
                        <img class="logo" src="{{ $branding['logo'] }}" alt="logo">
                    @else
                        <div class="company accent">{{ $branding['company_name'] }}</div>
                    @endif
                    <div class="muted">
                        @if ($branding['legal_name']){{ $branding['legal_name'] }}<br>@endif
                        @if ($branding['address']){{ $branding['address'] }}<br>@endif
                        @if ($branding['email']){{ $branding['email'] }} @endif
                        @if ($branding['phone']) · {{ $branding['phone'] }}@endif
                        @if ($branding['tax_number'])<br>{{ __('Tax') }}: {{ $branding['tax_number'] }}@endif
                    </div>
                </td>
                <td style="width:40%">
                    <div class="doc-title">{{ __('Service Report') }}</div>
                    <div class="doc-number"><strong>{{ $number }}</strong></div>
                    <div class="doc-number muted">{{ $date }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid section">
        <tr>
            <td>
                <h2>{{ __('Customer') }}</h2>
                <div class="field">{{ $customer?->company_name ?? '—' }}</div>
                @if ($contact)
                    <div class="field muted">{{ trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) }}</div>
                    @if ($contact->phone)<div class="field muted">{{ $contact->phone }}</div>@endif
                @endif
                @if ($customer?->email)<div class="field muted">{{ $customer->email }}</div>@endif
            </td>
            <td>
                <h2>{{ __('Service Address') }}</h2>
                @if ($address)
                    <div class="field">{{ $address->address_line_1 }}</div>
                    @if ($address->address_line_2)<div class="field">{{ $address->address_line_2 }}</div>@endif
                    <div class="field">{{ trim(($address->postal_code ?? '').' '.($address->city ?? '')) }}</div>
                    @if ($address->country_code)<div class="field">{{ $address->country_code }}</div>@endif
                @else
                    <div class="field muted">—</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="grid section">
        <tr>
            <td>
                <h2>{{ __('Schedule') }}</h2>
                <div class="field"><span class="label">{{ __('Planned') }}</span> {{ $planned_at ?? '—' }}</div>
                <div class="field"><span class="label">{{ __('Started') }}</span> {{ $actual_start_at ?? '—' }}</div>
                <div class="field"><span class="label">{{ __('Ended') }}</span> {{ $actual_end_at ?? '—' }}</div>
                <div class="field"><span class="label">{{ __('Duration') }}</span>
                    {{ $duration_minutes !== null ? $duration_minutes.' '.__('min') : '—' }}</div>
            </td>
            <td>
                <h2>{{ __('Assignment') }}</h2>
                <div class="field"><span class="label">{{ __('Technician') }}</span> {{ $assignee?->name ?? '—' }}</div>
                <div class="field"><span class="label">{{ __('Status') }}</span> {{ $job->status->label() }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>{{ __('Service Notes') }}</h2>
        <div class="notes">{{ $service_notes ?: '—' }}</div>
    </div>

    @if (count($images))
        <div>
            <h2>{{ __('Photos') }}</h2>
            <table class="images">
                @foreach (array_chunk($images, 3) as $row)
                    <tr>
                        @foreach ($row as $img)
                            <td><img src="{{ $img }}" alt="photo"></td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if (count($workshop_devices))
        <div class="section">
            <h2>{{ __('Related Workshop Devices') }}</h2>
            @foreach ($workshop_devices as $device)
                <div class="field">{{ $device }}</div>
            @endforeach
        </div>
    @endif

    <div class="section">
        <h2>{{ __('Signature') }}</h2>
        @if ($signature)
            <div class="signature-box">
                <img src="{{ $signature }}" alt="signature">
                <div class="muted">{{ $signed_by ?? __('Signed on site') }}</div>
            </div>
        @else
            <div class="muted">{{ __('No signature captured.') }}</div>
        @endif
    </div>

    <table class="labels section">
        <tr>
            <td><strong>{{ __('Delivered by') }}</strong><br><span class="muted">{{ $assignee?->name ?? '' }}</span></td>
            <td><strong>{{ __('Accepted by') }}</strong><br><span class="muted">{{ $signed_by ?? '' }}</span></td>
        </tr>
    </table>

    <div class="footer">
        {{ $branding['company_name'] }} · {{ $number }} · {{ __('Generated') }} {{ $date }}
    </div>
</body>
</html>
