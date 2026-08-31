<?php

namespace App\Traits;

trait ApiResponse
{
    protected function successResponse($data, string $message = 'Operación exitosa', int $code = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function createdResponse($data, string $message = 'Registro creado exitosamente')
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function errorResponse(string $message, int $code = 400)
    {
        return response()->json([
            'message' => $message
        ], $code);
    }
}