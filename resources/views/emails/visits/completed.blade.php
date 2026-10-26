<!DOCTYPE html>
<html lang="en">
<body>
    <p>Hello {{ $visit->property->customer->name }},</p>
    <p>Your service visit at {{ $visit->property->address_line_1 }} has been completed.</p>
    <p>The service report is attached for your records.</p>
    <p>Thank you,<br>{{ $visit->tenant->name }}</p>
</body>
</html>
