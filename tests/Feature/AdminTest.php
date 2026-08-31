<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_normal_no_puede_acceder_al_panel_admin()
    {
        /** @var \App\Models\User $client */
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_un_admin_puede_ver_la_lista_de_clientes()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'client', 'name' => 'Cliente Uno']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.name', 'Cliente Uno');
    }

    public function test_un_admin_puede_cambiar_el_plan_a_premium_con_pago()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        
        /** @var \App\Models\User $client */
        $client = User::factory()->create(['role' => 'client', 'plan' => 'free']);

        $payload = [
            'plan' => 'premium',
            'payment_method' => 'transfer',
            'payment_bank' => 'Pichincha',
            'payment_receipt' => '123456789'
        ];

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$client->id}/plan", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.plan', 'premium');

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'plan' => 'premium',
            'payment_method' => 'transfer',
            'payment_bank' => 'Pichincha',
            'payment_receipt' => '123456789'
        ]);
    }

    public function test_el_plan_premium_falla_si_no_se_envia_metodo_de_pago()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        
        /** @var \App\Models\User $client */
        $client = User::factory()->create(['role' => 'client', 'plan' => 'free']);

        $payload = [
            'plan' => 'premium'
        ];

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$client->id}/plan", $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['payment_method']);
    }
}