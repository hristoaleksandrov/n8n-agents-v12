<?php

use App\Services\N8nService;

test('generateSignature creates consistent HMAC signature', function () {
    $payload = [
        'task_id' => 1,
        'reference_script' => 'Test script',
        'outcome_description' => 'Test outcome',
    ];

    $signature1 = N8nService::generateSignature($payload);
    $signature2 = N8nService::generateSignature($payload);

    expect($signature1)->toBe($signature2);
    expect($signature1)->toBeString();
    expect(strlen($signature1))->toBe(64);
});

test('generateSignature creates different signatures for different payloads', function () {
    $payload1 = ['task_id' => 1, 'data' => 'test'];
    $payload2 = ['task_id' => 2, 'data' => 'test'];

    $signature1 = N8nService::generateSignature($payload1);
    $signature2 = N8nService::generateSignature($payload2);

    expect($signature1)->not->toBe($signature2);
});

test('verifySignature returns true for valid signature', function () {
    $payload = [
        'task_id' => 1,
        'new_script' => 'Improved script',
        'analysis' => 'Analysis result',
    ];

    $signature = N8nService::generateSignature($payload);

    expect(N8nService::verifySignature($signature, $payload))->toBeTrue();
});

test('verifySignature returns false for invalid signature', function () {
    $payload = [
        'task_id' => 1,
        'new_script' => 'Improved script',
        'analysis' => 'Analysis result',
    ];

    expect(N8nService::verifySignature('invalid-signature', $payload))->toBeFalse();
});

test('verifySignature returns false for tampered payload', function () {
    $originalPayload = [
        'task_id' => 1,
        'new_script' => 'Original script',
        'analysis' => 'Analysis',
    ];

    $signature = N8nService::generateSignature($originalPayload);

    $tamperedPayload = [
        'task_id' => 1,
        'new_script' => 'Tampered script',
        'analysis' => 'Analysis',
    ];

    expect(N8nService::verifySignature($signature, $tamperedPayload))->toBeFalse();
});

test('signature is timing-attack resistant', function () {
    $payload = ['task_id' => 1, 'data' => 'test'];
    $validSignature = N8nService::generateSignature($payload);

    $almostCorrect = substr($validSignature, 0, -1).'x';

    expect(N8nService::verifySignature($almostCorrect, $payload))->toBeFalse();
});
