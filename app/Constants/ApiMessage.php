<?php

declare(strict_types=1);

namespace App\Constants;

class ApiMessage
{
    public const SUCCESS = 'Operación exitosa.';

    public const CREATED = 'Registro guardado exitosamente.';

    public const BATCH_PROCESSED = 'Bache procesado.';

    public const SYNC_PULL_SUCCESS = 'Datos sincronizados exitosamente.';

    public const UNAUTHORIZED = 'Acceso denegado. Token inválido o ausente.';

    public const FORBIDDEN = 'No tienes permisos para realizar esta acción.';

    public const NOT_FOUND = 'El recurso que buscas no existe o fue eliminado.';

    public const VALIDATION_ERROR = 'Los datos enviados son inválidos.';

    public const SERVER_ERROR = 'Ocurrió un error inesperado en el servidor.';

    public const INVALID_CREDENTIALS = 'Las credenciales son incorrectas.';

    public const LIMIT_ACCOUNTS = 'Límite de cuentas alcanzado para tu plan actual.';

    public const MISSING_ID = 'Falta el identificador del registro.';

    public const ACCOUNT_DEFAULT_PROTECTED = 'La cuenta principal por defecto no puede ser modificada ni eliminada.';

    public const ACCOUNT_NAME_DUPLICATE = 'Ya existe una cuenta con este nombre.';

    public const ACCOUNT_INACTIVE = 'La cuenta seleccionada se encuentra desactivada.';

    public const ACCOUNT_TYPE_MISMATCH = 'El método de pago no coincide con el tipo de cuenta.';

    public const HARD_DELETE_REQUIRES_SOFT_DELETE = 'Un movimiento debe estar desactivado antes de ser eliminado permanentemente.';

    public const RELOCATE_REASON_REQUIRED = 'El motivo del traslado es obligatorio.';

    public const TRANSACTION_ALREADY_INACTIVE = 'No se puede trasladar un movimiento desactivado.';

    public const RELOCATE_SAME_ACCOUNT = 'La cuenta destino debe ser diferente a la cuenta origen.';

    public const SESSION_LIMIT_REACHED = 'Ya tienes una sesión activa en otro dispositivo.';

    public const SESSION_REVOKED_REMOTE = 'Sesión cerrada desde otro dispositivo.';

    public const INVALID_REFRESH_TOKEN = 'El token de actualización es inválido o ha expirado.';

    public const ACCOUNTS_FETCHED = 'Cuentas obtenidas exitosamente.';

    public const TRANSACTIONS_FETCHED = 'Movimientos obtenidos exitosamente.';

    public const USERS_FETCHED = 'Lista de clientes obtenida exitosamente.';

    public const PLAN_UPDATED = 'Plan de usuario actualizado exitosamente.';

    public const CATEGORIES_FETCHED = 'Categorías obtenidas exitosamente.';

    public const PREMIUM_REQUEST_SUBMITTED = 'Tu solicitud fue recibida. La revisaremos en un plazo máximo de 24 horas.';

    public const PREMIUM_REQUESTS_FETCHED = 'Solicitudes Premium obtenidas exitosamente.';

    public const PREMIUM_REQUEST_APPROVED = 'Solicitud Premium aprobada exitosamente.';

    public const PREMIUM_REQUEST_REJECTED = 'Solicitud Premium rechazada exitosamente.';

    public const PREMIUM_REQUIRED = 'Esta funcionalidad requiere una suscripción Premium activa.';

    public const UNSUPPORTED_ENTITY = 'La entidad enviada no está soportada para sincronización.';

    public const UNSUPPORTED_OPERATION = 'La operación enviada no está soportada para esta entidad.';

    public const SYNC_INTERNAL_ERROR = 'Error interno al sincronizar los datos.';

    public const VALIDATION_EMAIL_UNIQUE = 'Este correo electrónico ya está registrado. Intenta iniciar sesión.';

    public const VALIDATION_EMAIL_REQUIRED = 'El correo electrónico es obligatorio.';

    public const VALIDATION_EMAIL_INVALID = 'Debes ingresar un formato de correo válido.';

    public const VALIDATION_NAME_REQUIRED = 'El nombre completo es obligatorio.';

    public const VALIDATION_NAME_OR_ALIAS_REQUIRED = 'El nombre o alias es obligatorio.';

    public const VALIDATION_NAME_MAX = 'El nombre no puede tener más de 255 caracteres.';

    public const VALIDATION_PASSWORD_REQUIRED = 'La contraseña es obligatoria.';

    public const VALIDATION_PASSWORD_MIN = 'La contraseña debe tener al menos 6 caracteres.';
}
