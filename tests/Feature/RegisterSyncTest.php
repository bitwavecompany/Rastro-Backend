<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_registrarse_y_sincronizar_datos_locales()
    {
        $payload = [
            'name' => 'Mi Negocio',
            'email' => 'prueba@ejemplo.com',
            'password' => 'secreta123',
            'localData' => [
                'accounts' => [
                    [
                        'id' => 'usr_acc_123',
                        'name' => 'Efectivo',
                        'type' => 'cash',
                        'amount' => 150.00,
                    ]
                ],
                'transactions' => [
                    [
                        'id' => 'usr_tx_999',
                        'accountId' => 'usr_acc_123',
                        'kind' => 'income',
                        'amount' => 150.00,
                        'reason' => 'Saldo inicial',
                        'paymentType' => 'cash',
                    ]
                ]
            ]
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
                             'transactions'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'prueba@ejemplo.com',
            'plan' => 'free'
        ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Efectivo',
            'amount' => 150.00
        ]);

        $this->assertDatabaseHas('transactions', [
            'kind' => 'income',
            'amount' => 150.00,
            'reason' => 'Saldo inicial'
        ]);
    }

    public function test_un_usuario_puede_registrarse_sin_datos_locales_y_recibe_cuenta_por_defecto()
    {
        $payload = [
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'secreta123',
        ];

        $response = $this->postJson('/api/auth/register-sync', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@ejemplo.com'
        ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'Efectivo',
            'type' => 'cash',
            'amount' => 0.00
        ]);
    }
}