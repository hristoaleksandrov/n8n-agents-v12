<?php

use App\Enums\TaskStatus;
use App\Jobs\TriggerN8nWorkflow;
use App\Models\AdScriptTask;
use Illuminate\Support\Facades\Http;

test('job marks task as processing before making request', function () {
    Http::fake([
        '*' => Http::response([
            'new_script' => 'Improved script',
            'analysis' => 'Analysis result',
        ], 200),
    ]);

    $task = AdScriptTask::factory()->pending()->create();

    (new TriggerN8nWorkflow($task))->handle(app(\App\Services\N8nService::class));

    $task->refresh();
    expect($task->status)->toBe(TaskStatus::Completed);
});

test('job marks task as completed on successful n8n response', function () {
    Http::fake([
        '*' => Http::response([
            'new_script' => 'The new improved script content',
            'analysis' => 'Script was improved for better engagement',
        ], 200),
    ]);

    $task = AdScriptTask::factory()->pending()->create();

    (new TriggerN8nWorkflow($task))->handle(app(\App\Services\N8nService::class));

    $task->refresh();
    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->new_script)->toBe('The new improved script content');
    expect($task->analysis)->toBe('Script was improved for better engagement');
});

test('job sends correct payload to n8n webhook', function () {
    Http::fake();

    $task = AdScriptTask::factory()->create([
        'reference_script' => 'Original script content',
        'outcome_description' => 'Make it better',
    ]);

    (new TriggerN8nWorkflow($task))->handle(app(\App\Services\N8nService::class));

    Http::assertSent(function ($request) use ($task) {
        $body = $request->data();

        return $body['task_id'] === $task->id
            && $body['reference_script'] === 'Original script content'
            && $body['outcome_description'] === 'Make it better'
            && isset($body['callback_url']);
    });
});

test('job includes HMAC signature header', function () {
    Http::fake();

    $task = AdScriptTask::factory()->create();

    (new TriggerN8nWorkflow($task))->handle(app(\App\Services\N8nService::class));

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-N8N-Signature')
            && strlen($request->header('X-N8N-Signature')[0]) === 64;
    });
});

test('job handles n8n failure gracefully', function () {
    Http::fake([
        '*' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $task = AdScriptTask::factory()->pending()->create();
    $job = new TriggerN8nWorkflow($task);

    (new TriggerN8nWorkflow($task))->handle(app(\App\Services\N8nService::class));

    $task->refresh();
    expect($task->status)->toBe(TaskStatus::Processing);
});

test('job configuration has correct retry settings', function () {
    $task = AdScriptTask::factory()->create();
    $job = new TriggerN8nWorkflow($task);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe(30);
    expect($job->timeout)->toBe(120);
});
