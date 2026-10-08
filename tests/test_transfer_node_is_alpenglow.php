<?php

require_once __DIR__ . '/../classes/TransferNodeInterface.php';
require_once __DIR__ . '/../classes/Log.php';
require_once __DIR__ . '/../classes/TransferNode.php';

// Test decoded RPC responses directly, without curl, network access or delays.
$active = [
    'jsonrpc' => '2.0', 'id' => 1,
    'result' => [
        'block' => ['slot' => 345, 'blockId' => array_fill(0, 32, 1)],
        'signature' => ['signature' => array_fill(0, 192, 1), 'bitmap' => [1]],
    ],
];
$cases = [
    ['Alpenglow active', $active, true],
    ['Tower active', ['jsonrpc' => '2.0', 'id' => 1, 'result' => null], false],
    ['null response', null, true],
    ['invalid JSON decoded', json_decode('invalid JSON', true), true],
    ['empty response', [], true],
    ['missing result', ['jsonrpc' => '2.0', 'id' => 1], true],
    ['missing genesis fields', ['result' => []], true],
    ['invalid result type', ['result' => false], true],
    ['unexpected string', 'tower', true],
    ['method not found', ['error' => ['code' => -32601, 'message' => 'Method not found']], true],
    ['error overrides null result', ['error' => ['code' => -32603], 'result' => null], true],
    ['null error is not a confirmed Tower response', ['error' => null, 'result' => null], true],
];

// The helper must not depend on the global error from an unrelated JSON decode.
json_decode('invalid JSON', true);
foreach ($cases as [$name, $data, $expected]) {
    ob_start();
    try {
        $actual = TransferNode::isAlpenglowByJsonData($data);
    } finally {
        ob_end_clean();
    }
    $expected_str = $expected ? "true" : "false";
    echo "'{$name}' {$expected_str}\n";
    if ($actual !== $expected) {
        throw new RuntimeException("Failed: {$name}");
    }
}
echo 'PASS: ' . count($cases) . " Alpenglow response cases\n";
