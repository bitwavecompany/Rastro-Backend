<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud Premium - Rastro</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; color: #1f2937; line-height: 1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 24px 32px; text-align: left;">
                            <span style="color: #38bdf8; font-size: 20px; font-weight: 700; letter-spacing: 0.5px;">RASTRO</span>
                            <span style="color: #94a3b8; font-size: 13px; margin-left: 8px;">| Panel de Notificaciones</span>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <div style="display: inline-block; background-color: #fef3c7; color: #92400e; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 9999px; text-transform: uppercase; margin-bottom: 16px;">
                                Solicitud Pendiente de Verificación
                            </div>
                            
                            <h1 style="color: #0f172a; font-size: 22px; font-weight: 700; margin: 0 0 12px 0;">
                                Nuevo comprobante de pago recibido
                            </h1>
                            
                            <p style="color: #4b5563; font-size: 15px; margin: 0 0 24px 0;">
                                El usuario <strong>{{ $user->name ?: 'Sin alias' }}</strong> (<a href="mailto:{{ $user->email }}" style="color: #0284c7; text-decoration: none;">{{ $user->email }}</a>) ha enviado un comprobante de transferencia bancaria y solicita subir a plan <strong>Premium</strong>.
                            </p>

                            <!-- Data Card -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding-bottom: 10px; color: #64748b; font-size: 13px; font-weight: 600; width: 40%;">Método:</td>
                                                <td style="padding-bottom: 10px; color: #0f172a; font-size: 14px; font-weight: 500;">
                                                    {{ ($methodValue ?? 'transfer') === 'transfer' ? 'Transferencia Bancaria' : 'Efectivo' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 10px; color: #64748b; font-size: 13px; font-weight: 600;">Banco de Origen:</td>
                                                <td style="padding-bottom: 10px; color: #0f172a; font-size: 14px; font-weight: 600;">
                                                    {{ $bank ?? $user->payment_bank ?? 'No especificado' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-bottom: 10px; color: #64748b; font-size: 13px; font-weight: 600;">N° de Comprobante:</td>
                                                <td style="padding-bottom: 10px; color: #0369a1; font-size: 15px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;">
                                                    {{ $receipt ?? $user->payment_receipt ?? 'S/N' }}
                                                </td>
                                            </tr>
                                            @if(!empty($dateFormatted))
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 600;">Fecha de Solicitud:</td>
                                                <td style="color: #0f172a; font-size: 14px; font-weight: 500;">
                                                    {{ $dateFormatted }}
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin: 32px 0 16px 0;">
                                <a href="{{ $adminUrl }}" target="_blank" style="display: inline-block; background-color: #0284c7; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);">
                                    Revisar y Validar en Rastro Admin &rarr;
                                </a>
                            </div>

                            <p style="color: #94a3b8; font-size: 13px; text-align: center; margin: 16px 0 0 0;">
                                Recuerda cumplir con el SLA máximo de 24 horas para aprobación o rechazo.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 32px; text-align: center; color: #94a3b8; font-size: 12px;">
                            Este es un correo transaccional generado automáticamente por Rastro Backend.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>