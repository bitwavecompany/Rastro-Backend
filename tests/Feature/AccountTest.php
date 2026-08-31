<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_listar_sus_cuentas()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $user->accounts()->create([
            'name' => 'Mi Billetera',
            'type' => 'cash',
            'amount' => 100.00
        ]);

        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.name', 'Mi Billetera');
    }

    public function test_un_usuario_puede_crear_una_cuenta()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $payload = [
            'name' => 'Banco Pichincha',
            'type' => 'bank',
            'amount' => 500.00,
            'description' => 'Ahorros'
        ];

        $response = $this->actingAs($user)->postJson('/api/accounts', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Banco Pichincha');

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Banco Pichincha',
            'amount' => 500.00
        ]);
    }

    public function test_un_usuario_no_puede_ver_las_cuentas_de_otros_usuarios()
    {
        /** @var \App\Models\User $user1 */
        $user1 = User::factory()->create();
        
        $user1->accounts()->create([
            'name' => 'Cuenta Secreta',
            'type' => 'bank',
            'amount' => 1000.00
        ]);

        /** @var \App\Models\User $user2 */
        $user2 = User::factory()->create();

        $response = $this->actingAs($user2)->getJson('/api/accounts');

        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');
    }
}