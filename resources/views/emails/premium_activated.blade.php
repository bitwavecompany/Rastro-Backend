<!DOCTYPE html>
<html>
<head>
    <title>¡Tu cuenta Rastro Premium está activa!</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>¡Hola, {{ $user->name }}!</h2>
    <p>Nos complace informarte que tu suscripción a <strong>Rastro Premium</strong> ha sido activada exitosamente.</p>
    @if(isset($dateFormatted) && $dateFormatted)
    <p>Fecha de activación: <strong>{{ $dateFormatted }}</strong></p>
    @endif
    <p>Ahora tienes acceso a:</p>
    <ul>
        <li>Cuentas ilimitadas</li>
        <li>Historial completo sin restricciones</li>
        <li>Exportación de transacciones en CSV</li>
        <li>Sincronización en la nube ilimitada</li>
    </ul>
    <p>¡Gracias por confiar en Rastro para tu gestión financiera!</p>
</body>
</html>
