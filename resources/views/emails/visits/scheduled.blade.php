<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $visit->property->customer->name }},</p>

    @if ($notificationType === 'scheduled')
        <p>Your service visit has been scheduled.</p>
    @elseif ($notificationType === 'rescheduled')
        <p>Your service visit has been updated.</p>
    @else
        <p>This is a reminder about your upcoming service visit.</p>
    @endif

    <p>
        <strong>When:</strong> {{ $scheduledAt }}<br>
        <strong>Address:</strong>
        {{ $visit->property->address_line_1 }}{{ $visit->property->address_line_2 ? ', '.$visit->property->address_line_2 : '' }},
        {{ $visit->property->city }}{{ $visit->property->state ? ', '.$visit->property->state : '' }}
    </p>

    @if ($visit->technician)
        <p><strong>Technician:</strong> {{ $visit->technician->name }}</p>
    @endif

    <p>Thank you,<br>{{ $visit->tenant->name }}</p>
</body>
</html>
