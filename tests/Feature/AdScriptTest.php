<?php

use App\Enums\TaskStatus;
use App\Jobs\TriggerN8nWorkflow;
use App\Models\AdScriptTask;
use App\Services\N8nService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

describe('POST /api/ad-scripts', function () {
    test('creates a new task with valid data', function () {
        $response = $this->postJson('/api/ad-scripts', [
            'reference_script' => 'This is a sample advertising script that needs improvement.',
            'outcome_description' => 'Make it more engaging and persuasive',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Task created successfully.',
                'data' => [
                    'status' => 'pending',
                ],
            ])
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'status'],
            ]);

        $this->assertDatabaseHas('ad_script_tasks', [
            'reference_script' => 'This is a sample advertising script that needs improvement.',
            'outcome_description' => 'Make it more engaging and persuasive',
            'status' => 'pending',
        ]);
    });

    test('dispatches TriggerN8nWorkflow job after task creation', function () {
        $this->postJson('/api/ad-scripts', [
            'reference_script' => 'Sample script for testing job dispatch.',
            'outcome_description' => 'Test outcome description',
        ]);

        Queue::assertPushed(TriggerN8nWorkflow::class, function ($job) {
            return $job->task->reference_script === 'Sample script for testing job dispatch.';
        });
    });

    test('fails validation with missing reference_script', function () {
        $response = $this->postJson('/api/ad-scripts', [
            'outcome_description' => 'Make it better',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference_script']);
    });

    test('fails validation with missing outcome_description', function () {
        $response = $this->postJson('/api/ad-scripts', [
            'reference_script' => 'This is a sample script.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outcome_description']);
    });

    test('fails validation with reference_script too short', function () {
        $response = $this->postJson('/api/ad-scripts', [
            'reference_script' => 'Short',
            'outcome_description' => 'Make it better',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference_script']);
    });

    test('fails validation with outcome_description too short', function () {
        $response = $this->postJson('/api/ad-scripts', [
            'reference_script' => 'This is a sufficiently long reference script.',
            'outcome_description' => 'Hi',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['outcome_description']);
    });
});

describe('GET /api/ad-scripts/{task}', function () {
    test('returns task details', function () {
        $task = AdScriptTask::factory()->create([
            'reference_script' => 'Original script content',
            'outcome_description' => 'Make it engaging',
        ]);

        $response = $this->getJson("/api/ad-scripts/{$task->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $task->id,
                    'reference_script' => 'Original script content',
                    'outcome_description' => 'Make it engaging',
                    'status' => 'pending',
                ],
            ]);
    });

    test('returns 404 for non-existent task', function () {
        $response = $this->getJson('/api/ad-scripts/99999');

        $response->assertNotFound();
    });

    test('shows completed task with results', function () {
        $task = AdScriptTask::factory()->completed()->create();

        $response = $this->getJson("/api/ad-scripts/{$task->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'reference_script',
                    'outcome_description',
                    'new_script',
                    'analysis',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'status' => 'completed',
                ],
            ]);

        expect($response->json('data.new_script'))->not->toBeNull();
        expect($response->json('data.analysis'))->not->toBeNull();
    });
});

describe('POST /api/ad-scripts/{task}/result', function () {
    test('updates task with successful result when signature is valid', function () {
        $task = AdScriptTask::factory()->processing()->create();

        $payload = [
            'task_id' => $task->id,
            'new_script' => 'This is the improved script.',
            'analysis' => 'Made the tone more engaging.',
        ];

        $signature = N8nService::generateSignature($payload);

        $response = $this->postJson(
            "/api/ad-scripts/{$task->id}/result",
            $payload,
            ['X-N8N-Signature' => $signature]
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Task completed successfully.',
                'status' => 'completed',
            ]);

        $task->refresh();
        expect($task->status)->toBe(TaskStatus::Completed);
        expect($task->new_script)->toBe('This is the improved script.');
        expect($task->analysis)->toBe('Made the tone more engaging.');
    });

    test('marks task as failed when error is provided', function () {
        $task = AdScriptTask::factory()->processing()->create();

        $payload = [
            'task_id' => $task->id,
            'error' => 'AI model timeout occurred',
        ];

        $signature = N8nService::generateSignature($payload);

        $response = $this->postJson(
            "/api/ad-scripts/{$task->id}/result",
            $payload,
            ['X-N8N-Signature' => $signature]
        );

        $response->assertOk()
            ->assertJson([
                'message' => 'Task marked as failed.',
                'status' => 'failed',
            ]);

        $task->refresh();
        expect($task->status)->toBe(TaskStatus::Failed);
        expect($task->error_message)->toBe('AI model timeout occurred');
    });

    test('rejects request with missing signature', function () {
        $task = AdScriptTask::factory()->processing()->create();

        $response = $this->postJson("/api/ad-scripts/{$task->id}/result", [
            'task_id' => $task->id,
            'new_script' => 'Improved script',
            'analysis' => 'Analysis here',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Missing signature header.',
            ]);
    });

    test('rejects request with invalid signature', function () {
        $task = AdScriptTask::factory()->processing()->create();

        $response = $this->postJson(
            "/api/ad-scripts/{$task->id}/result",
            [
                'task_id' => $task->id,
                'new_script' => 'Improved script',
                'analysis' => 'Analysis here',
            ],
            ['X-N8N-Signature' => 'invalid-signature']
        );

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid signature.',
            ]);
    });

    test('rejects update for already completed task', function () {
        $task = AdScriptTask::factory()->completed()->create();

        $payload = [
            'task_id' => $task->id,
            'new_script' => 'Another improved script',
            'analysis' => 'Another analysis',
        ];

        $signature = N8nService::generateSignature($payload);

        $response = $this->postJson(
            "/api/ad-scripts/{$task->id}/result",
            $payload,
            ['X-N8N-Signature' => $signature]
        );

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Task already processed.',
                'status' => 'completed',
            ]);
    });

    test('rejects update for already failed task', function () {
        $task = AdScriptTask::factory()->failed()->create();

        $payload = [
            'task_id' => $task->id,
            'new_script' => 'Improved script',
            'analysis' => 'Analysis',
        ];

        $signature = N8nService::generateSignature($payload);

        $response = $this->postJson(
            "/api/ad-scripts/{$task->id}/result",
            $payload,
            ['X-N8N-Signature' => $signature]
        );

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Task already processed.',
                'status' => 'failed',
            ]);
    });
});
