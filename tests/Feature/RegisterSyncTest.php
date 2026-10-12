<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_registrarse_y_sincronizar_datos_locales(): void
    {
        $accId = (string) Str::uuid();
        $txId = (string) Str::uuid();

        $payload = [
            'name' => 'Mi Negocio',
            'email' => 'prueba@ejemplo.com',
            'password' => 'secreta123',
            'localData' => [
                'accounts' => [
                    [
                        'id' => $accId,
                        'name' => 'Efectivo',
                        'type' => 'cash',
                        'amount' => 150.00,
                        'is_default' => true,
                    ],
                ],
                'transactions' => [
                    [
                        'id' => $txId,
                        'accountId' => $accId,
                        'kind' => 'income',
                        'amount' => 150.00,
                        'reason' => 'Saldo inicial',
                        'paymentType' => 'cash',
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/auth/register-sync', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'plan'],
                    'syncedData' => [
                        'accounts',
                        'transactions',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'prueba@ejemplo.com',
            'plan' => 'free',
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $accId,
            'name' => 'Efectivo',
            'initial_balance' => 150.00,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'type' => 'income',
            'amount' => 150.00,
            'reason' => 'Saldo inicial',
        ]);
    }

    public function test_un_usuario_puede_registrarse_sin_datos_locales_y_recibe_cuenta_por_defecto(): void
    {
        $payload = [
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'secreta123',
        ];

        $response = $this->postJson('/api/auth/register-sync', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@ejemplo.com',
        ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Efectivo',
            'type' => 'cash',
            'initial_balance' => 0.00,
            'is_default' => true,
        ]);
    }

    public function test_un_usuario_puede_registrarse_enviando_initial_balance_en_cuentas_locales(): void
    {
        $accId = (string) Str::uuid();

        $payload = [
            'name' => 'Usuario Balance',
            'email' => 'balance@ejemplo.com',
            'password' => 'secreta123',
            'localData' => [
                'accounts' => [
                    [
                        'id' => $accId,
                        'name' => 'Efectivo',
                        'type' => 'cash',
                        'initial_balance' => 250.00,
                        'is_default' => true,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/auth/register-sync', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('accounts', [
            'id' => $accId,
            'name' => 'Efectivo',
            'initial_balance' => 250.00,
            'is_default' => true,
        ]);
    }
}
