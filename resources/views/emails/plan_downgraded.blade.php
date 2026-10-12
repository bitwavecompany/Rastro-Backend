<!DOCTYPE html>
<html>
<head>
    <title>Actualización de tu plan en Rastro</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Hola, {{ $user->name }}</h2>
    <p>Te informamos que tu suscripción ha sido cambiada al plan <strong>Free</strong>.</p>
    @if(isset($dateFormatted) && $dateFormatted)
    <p>Fecha efectiva del cambio: <strong>{{ $dateFormatted }}</strong></p>
    @endif
    <p>Ten en cuenta las características de tu plan actual:</p>
    <ul>
        <li>Máximo <strong>3 cuentas activas</strong> permitidas. Si tienes cuentas adicionales activas, deberás desactivar el exceso en la aplicación.</li>
        <li>Historial visible de los <strong>últimos 6 meses</strong> (tus movimientos más antiguos continúan archivados y seguros en el servidor).</li>
        <li>Movimientos ilimitados.</li>
    </ul>
    <p>Si deseas reactivar todos los beneficios ilimitados y la exportación de reportes, puedes solicitar una mejora a Premium en cualquier momento desde la app.</p>
    <p>Atentamente,<br>El equipo de Rastro</p>
</body>
</html>
