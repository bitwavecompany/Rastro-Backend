<!DOCTYPE html>
<html>
<head>
    <title>Actualización de tu solicitud Rastro Premium</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Hola, {{ $user->name }}</h2>
    <p>Lamentamos informarte que tu solicitud para el plan <strong>Rastro Premium</strong> no pudo ser aprobada en esta ocasión.</p>
    @if(isset($dateFormatted) && $dateFormatted)
    <p>Fecha de revisión: <strong>{{ $dateFormatted }}</strong></p>
    @endif
    <p><strong>Motivo del rechazo:</strong></p>
    <blockquote style="border-left: 4px solid #f44336; padding-left: 10px; margin: 15px 0; color: #555;">
        {{ $reason }}
    </blockquote>
    <p>Puedes corregir la información y enviar una nueva solicitud desde la aplicación en cualquier momento.</p>
</body>
</html>
