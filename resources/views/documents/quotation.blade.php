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
        .doc-title { font-size: 20px; font-weight: bold; text-align: right; color: {{ $branding['primary_color'] }}; }
        .doc-number { text-align: right; }
        h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: {{ $branding['primary_color'] }};
             border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin: 16px 0 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { text-align: left; border-bottom: 2px solid {{ $branding['primary_color'] }}; padding: 6px 4px; font-size: 10px; text-transform: uppercase; }
        table.lines td { padding: 6px 4px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        .num { text-align: right; }
        .totals { width: 40%; margin-left: 60%; margin-top: 12px; }
        .totals td { padding: 4px 4px; }
        .totals .grand td { border-top: 2px solid {{ $branding['primary_color'] }}; font-weight: bold; font-size: 13px; }
        .notes { margin-top: 16px; white-space: pre-line; }
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
                        @if ($branding['address']){{ $branding['address'] }}<br>@endif
                        @if ($branding['email']){{ $branding['email'] }}@endif
                        @if ($branding['phone']) · {{ $branding['phone'] }}@endif
                    </div>
                </td>
                <td style="width:40%">
                    <div class="doc-title">{{ __('Quotation') }}</div>
                    <div class="doc-number"><strong>{{ $number }}</strong></div>
                    <div class="doc-number muted"{{ __('Issued') }} {{ $issue_date }}</div>
                    @if ($valid_until)<div class="doc-number muted"{{ __('Valid until') }} {{ $valid_until }}</div>@endif
                </td>
            </tr>
        </table>
    </div>

    <h2>{{ __('Prepared for') }}</h2>
    <div>{{ $customer?->company_name }}</div>
    @if ($contact)<div class="muted">{{ trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) }}</div>@endif
    @if ($customer?->email)<div class="muted">{{ $customer->email }}</div>@endif

    <table class="lines">
        <thead>
            <tr>
                <th style="width:44%">{{ __('Item') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Unit price') }}</th>
                <th class="num">{{ __('Tax %') }}</th>
                <th class="num">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>
                        <strong>{{ $line['alias'] }}</strong>
                        @if ($line['description'])<div class="muted">{{ $line['description'] }}</div>@endif
                    </td>
                    <td class="num">{{ $line['quantity'] }} {{ $line['unit'] }}</td>
                    <td class="num">{{ $currency }} {{ $line['unit_price'] }}</td>
                    <td class="num">{{ $line['tax_rate'] }}</td>
                    <td class="num">{{ $currency }} {{ $line['line_total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ __('Subtotal') }}</td><td class="num">{{ $currency }} {{ $subtotal }}</td></tr>
        <tr><td>{{ __('Discount') }}</td><td class="num">-{{ $currency }} {{ $discount_total }}</td></tr>
        <tr><td>{{ __('Tax') }}</td><td class="num">{{ $currency }} {{ $tax_total }}</td></tr>
        <tr class="grand"><td>{{ __('Total') }}</td><td class="num">{{ $currency }} {{ $grand_total }}</td></tr>
    </table>

    @if ($notes)<div class="notes"><h2>{{ __('Notes') }}</h2>{{ $notes }}</div>@endif
    @if ($terms)<div class="notes"><h2>{{ __('Terms') }}</h2>{{ $terms }}</div>@endif

    <div class="footer">{{ $branding['company_name'] }} · {{ $number }} · {{ __('Generated') }} {{ $date }}</div>
</body>
</html>
