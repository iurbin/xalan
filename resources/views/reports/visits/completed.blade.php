<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service report #{{ $visit->id }}</title>
    <style>
        body { color: #222; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { border-bottom: 2px solid #333; padding-bottom: 8px; }
        h2 { margin-top: 24px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h1>Service report</h1>
    <p class="muted">{{ $visit->tenant->name }} · Visit #{{ $visit->id }}</p>

    <h2>Customer and property</h2>
    <table>
        <tr><th>Customer</th><td>{{ $visit->property->customer->name }}</td></tr>
        <tr><th>Address</th><td>{{ $visit->property->address_line_1 }}{{ $visit->property->address_line_2 ? ', '.$visit->property->address_line_2 : '' }}, {{ $visit->property->city }}{{ $visit->property->state ? ', '.$visit->property->state : '' }} {{ $visit->property->postal_code }}</td></tr>
        <tr><th>Technician</th><td>{{ $visit->technician?->name ?? 'Unassigned' }}</td></tr>
        <tr><th>Completed</th><td>{{ $visit->completed_at?->toDateTimeString() }}</td></tr>
    </table>

    <h2>Readings</h2>
    <table>
        <tr><th>Chlorine</th><td>{{ $visit->chlorine_reading ?? 'Not recorded' }}</td></tr>
        <tr><th>pH</th><td>{{ $visit->ph_reading ?? 'Not recorded' }}</td></tr>
    </table>

    <h2>Services requested</h2>
    @if ($visit->requested_services)
        <ul>
            @foreach ($visit->requested_services as $service)
                <li>{{ $service }}</li>
            @endforeach
        </ul>
    @else
        <p class="muted">No services were requested in advance.</p>
    @endif

    <h2>Services performed</h2>
    @if ($visit->services_performed)
        <ul>
            @foreach ($visit->services_performed as $service)
                <li>{{ $service }}</li>
            @endforeach
        </ul>
    @else
        <p class="muted">No services recorded.</p>
    @endif

    <h2>Technician notes</h2>
    <p>{{ $visit->technician_notes ?: 'No notes recorded.' }}</p>
</body>
</html>
