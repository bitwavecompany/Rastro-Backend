<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BatchSyncPostgresSavepointTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_sync_continues_when_an_individual_operation_fails(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Premium]);

        $opId1 = (string) Str::uuid();
        $opId2 = (string) Str::uuid();
        $opId3 = (string) Str::uuid();

        $id1 = (string) Str::uuid();
        $id2 = (string) Str::uuid();
        $id3 = (string) Str::uuid();

        $payload = [
            'operations' => [
                // 1. Valid operation
                [
                    'opId' => $opId1,
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => $id1,
                        'name' => 'Cuenta Valida Uno',
                        'type' => 'bank',
                        'amount' => 100.00,
                    ],
                ],
                // 2. Invalid operation (duplicate name violates validation)
                [
                    'opId' => $opId2,
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => $id2,
                        'name' => 'Cuenta Valida Uno', // duplicate name
                        'type' => 'cash',
                        'amount' => 50.00,
                    ],
                ],
                // 3. Subsequent valid operation (should NOT be aborted by Postgres 25P02)
                [
                    'opId' => $opId3,
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => $id3,
                        'name' => 'Cuenta Valida Dos',
                        'type' => 'cash',
                        'amount' => 200.00,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200);

        $results = $response->json('data.results');
        $this->assertCount(3, $results);

        $this->assertEquals($opId1, $results[0]['opId']);
        $this->assertEquals('success', $results[0]['status']);

        $this->assertEquals($opId2, $results[1]['opId']);
        $this->assertEquals('error', $results[1]['status']);

        $this->assertEquals($opId3, $results[2]['opId']);
        $this->assertEquals('success', $results[2]['status']);

        // Verify database state: 1 and 3 are persisted, 2 is not
        $this->assertDatabaseHas('accounts', ['id' => $id1, 'name' => 'Cuenta Valida Uno']);
        $this->assertDatabaseMissing('accounts', ['id' => $id2]);
        $this->assertDatabaseHas('accounts', ['id' => $id3, 'name' => 'Cuenta Valida Dos']);
    }

    public function test_batch_sync_handles_database_query_exception_isolation(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Premium]);

        $opId1 = (string) Str::uuid();
        $opId2 = (string) Str::uuid();
        $opId3 = (string) Str::uuid();

        $id1 = (string) Str::uuid();
        $id3 = (string) Str::uuid();

        // Pre-create an account with $id1 to force a duplicate primary key error on Op 2
        $user->accounts()->create([
            'id' => $id1,
            'name' => 'Cuenta Existente',
            'type' => 'bank',
            'amount' => 100.00,
        ]);

        $payload = [
            'operations' => [
                // 1. Valid operation
                [
                    'opId' => $opId1,
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'name' => 'Nueva Cuenta A',
                        'type' => 'bank',
                        'amount' => 100.00,
                    ],
                ],
                // 2. Database level collision (duplicate UUID pk with different name)
                [
                    'opId' => $opId2,
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => $id1, // PK Collision in Postgres
                        'name' => 'Nombre Diferente Pero Mismo ID',
                        'type' => 'cash',
                        'amount' => 50.00,
                    ],
                ],
                // 3. Subsequent valid operation: must succeed despite Postgres QueryException on Op 2
                [
                    'opId' => $opId3,
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => $id3,
                        'name' => 'Nueva Cuenta B',
                        'type' => 'cash',
                        'amount' => 200.00,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200);

        $results = $response->json('data.results');
        $this->assertCount(3, $results);

        $this->assertEquals($opId1, $results[0]['opId']);
        $this->assertEquals('success', $results[0]['status']);

        $this->assertEquals($opId2, $results[1]['opId']);
        $this->assertEquals('error', $results[1]['status']);

        $this->assertEquals($opId3, $results[2]['opId']);
        $this->assertEquals('success', $results[2]['status']);

        $this->assertDatabaseHas('accounts', ['name' => 'Nueva Cuenta A']);
        $this->assertDatabaseMissing('accounts', ['name' => 'Nombre Diferente Pero Mismo ID']);
        $this->assertDatabaseHas('accounts', ['id' => $id3, 'name' => 'Nueva Cuenta B']);
    }
}
