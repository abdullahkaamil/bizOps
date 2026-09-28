<!DOCTYPE html>
<html lang="{{ $settings->locale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $number }} — {{ $branding['company_name'] }}</title>
    <style>
        @page { margin: 1.6cm 1.4cm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111827; margin: 0; }
        .header { border-bottom: 3px solid {{ $branding['primary_color'] }}; padding-bottom: 10px; margin-bottom: 16px; }
        .header table { width: 100%; }
        .logo { max-height: 56px; max-width: 200px; }
        .company { font-size: 15px; font-weight: bold; color: {{ $branding['primary_color'] }}; }
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
        .labels { margin-top: 24px; }
        .labels td { border: 1px solid #d1d5db; padding: 10px; width: 50%; height: 60px; vertical-align: top; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #eef2ff; color: {{ $branding['primary_color'] }}; }
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
                        <div class="company">{{ $branding['company_name'] }}</div>
                    @endif
                    <div class="muted">
                        @if ($branding['legal_name']){{ $branding['legal_name'] }}<br>@endif
                        @if ($branding['address']){{ $branding['address'] }}<br>@endif
                        @if ($branding['email']){{ $branding['email'] }} @endif
                        @if ($branding['phone']) · {{ $branding['phone'] }}@endif
                    </div>
                </td>
                <td style="width:40%">
                    <div class="doc-title">{{ $variant === 'delivery' ? __('Delivery Report') : __('Repair Report') }}</div>
                    <div class="doc-number"><strong>{{ $number }}</strong></div>
                    <div class="doc-number muted">{{ $date }}</div>
                    @if ($job_number)<div class="doc-number">{{ __('Job') }}: <span class="badge">{{ $job_number }}</span></div>@endif
                </td>
            </tr>
        </table>
    </div>

    <table class="grid section">
        <tr>
            <td>
                <h2>{{ __('Customer') }}</h2>
                <div class="field">{{ $customer?->company_name ?? '—' }}</div>
                @if ($customer?->email)<div class="field muted">{{ $customer->email }}</div>@endif
                @if ($customer?->phone)<div class="field muted">{{ $customer->phone }}</div>@endif
            </td>
            <td>
                <h2>{{ __('Device') }}</h2>
                <div class="field">{{ $device?->brand }} {{ $device?->model }}</div>
                <div class="field"><span class="label">{{ __('Serial') }}</span>
                    {{ $device?->serial_number ?? __('Not available') }}</div>
            </td>
        </tr>
    </table>

    <table class="grid section">
        <tr>
            <td>
                <h2>{{ __('Timeline') }}</h2>
                <div class="field"><span class="label">{{ __('Received') }}</span> {{ $received_at ?? '—' }}</div>
                <div class="field"><span class="label">{{ __('Completed') }}</span> {{ $completed_at ?? '—' }}</div>
                @if ($variant === 'delivery')
                    <div class="field"><span class="label">{{ __('Delivered') }}</span> {{ $delivered_at ?? '—' }}</div>
                @endif
            </td>
            <td>
                <h2>{{ __('Technician') }}</h2>
                <div class="field">{{ $assignee?->name ?? '—' }}</div>
                <div class="field"><span class="label">{{ __('Status') }}</span> {{ $ticket->status->label() }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>{{ __('Reported Issue') }}</h2>
        <div class="notes">{{ $issue ?: '—' }}</div>
    </div>

    <div class="section">
        <h2>{{ __('Repair Notes') }}</h2>
        <div class="notes">{{ $repair_notes ?: '—' }}</div>
    </div>

    @if (count($images))
        <div>
            <h2>{{ __('Photos') }}</h2>
            <table class="images">
                @foreach (array_chunk($images, 3) as $row)
                    <tr>@foreach ($row as $img)<td><img src="{{ $img }}" alt="photo"></td>@endforeach</tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($variant === 'delivery')
        <table class="labels section">
            <tr>
                <td><strong>{{ __('Delivered by') }}</strong><br><span class="muted">{{ $assignee?->name ?? '' }}</span></td>
                <td><strong>{{ __('Received by') }}</strong><br><span class="muted"></span></td>
            </tr>
        </table>
    @endif

    <div class="footer">
        {{ $branding['company_name'] }} · {{ $number }} · {{ __('Generated') }} {{ $date }}
    </div>
</body>
</html>
