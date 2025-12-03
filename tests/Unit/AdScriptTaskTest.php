<?php

use App\Enums\TaskStatus;
use App\Models\AdScriptTask;

test('task has correct default status', function () {
    $task = new AdScriptTask([
        'reference_script' => 'Test script',
        'outcome_description' => 'Test outcome',
    ]);

    expect($task->status)->toBeNull();
});

test('task status enum is correctly cast', function () {
    $task = new AdScriptTask([
        'reference_script' => 'Test script',
        'outcome_description' => 'Test outcome',
        'status' => 'pending',
    ]);

    expect($task->status)->toBe(TaskStatus::Pending);
});

test('isPending returns true for pending status', function () {
    $task = new AdScriptTask(['status' => TaskStatus::Pending]);

    expect($task->isPending())->toBeTrue();
    expect($task->isProcessing())->toBeFalse();
    expect($task->isCompleted())->toBeFalse();
    expect($task->isFailed())->toBeFalse();
});

test('isProcessing returns true for processing status', function () {
    $task = new AdScriptTask(['status' => TaskStatus::Processing]);

    expect($task->isPending())->toBeFalse();
    expect($task->isProcessing())->toBeTrue();
    expect($task->isCompleted())->toBeFalse();
    expect($task->isFailed())->toBeFalse();
});

test('isCompleted returns true for completed status', function () {
    $task = new AdScriptTask(['status' => TaskStatus::Completed]);

    expect($task->isPending())->toBeFalse();
    expect($task->isProcessing())->toBeFalse();
    expect($task->isCompleted())->toBeTrue();
    expect($task->isFailed())->toBeFalse();
});

test('isFailed returns true for failed status', function () {
    $task = new AdScriptTask(['status' => TaskStatus::Failed]);

    expect($task->isPending())->toBeFalse();
    expect($task->isProcessing())->toBeFalse();
    expect($task->isCompleted())->toBeFalse();
    expect($task->isFailed())->toBeTrue();
});

test('TaskStatus isTerminal returns true for completed and failed', function () {
    expect(TaskStatus::Pending->isTerminal())->toBeFalse();
    expect(TaskStatus::Processing->isTerminal())->toBeFalse();
    expect(TaskStatus::Completed->isTerminal())->toBeTrue();
    expect(TaskStatus::Failed->isTerminal())->toBeTrue();
});

test('TaskStatus label returns human readable string', function () {
    expect(TaskStatus::Pending->label())->toBe('Pending');
    expect(TaskStatus::Processing->label())->toBe('Processing');
    expect(TaskStatus::Completed->label())->toBe('Completed');
    expect(TaskStatus::Failed->label())->toBe('Failed');
});
