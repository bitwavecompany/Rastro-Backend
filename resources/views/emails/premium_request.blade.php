<!DOCTYPE html>
<html>
<head>
    <title>Nueva Solicitud Premium</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <h2>¡Tienes una nueva solicitud de suscripción!</h2>
    <p>El cliente <strong>{{ $user->name }}</strong> ({{ $user->email }}) ha enviado un comprobante de pago y está a la espera de validación.</p>
    
    <div style="background-color: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
        <ul style="list-style-type: none; padding: 0; margin: 0;">
            <li><strong>Método:</strong> {{ ($user->payment_method?->value ?? $user->payment_method) === 'transfer' ? 'Transferencia' : 'Efectivo' }}</li>
            <li><strong>Banco:</strong> {{ $user->payment_bank ?? 'No especificado' }}</li>
            <li><strong>N° de Comprobante:</strong> <span style="font-family: monospace; font-size: 16px;">{{ $user->payment_receipt }}</span></li>
            @if(isset($dateFormatted) && $dateFormatted)
            <li><strong>Fecha de Solicitud:</strong> {{ $dateFormatted }}</li>
            @endif
        </ul>
    </div>

    <p>Por favor, ingresa a tu panel de administración (Rastro Admin) para verificar el pago y aprobar la cuenta.</p>
</body>
</html>