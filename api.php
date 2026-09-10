<?php
/**
 * inventory-api / public / api.php
 *
 * A minimal REST endpoint for the Urban Roti Inventory PWA.
 * Stores the entire dataset (vendors, items, notes, entries) as a single
 * JSON file on the NAS's disk. No database needed — this app is single-
 * business scale, so a flat file with atomic writes is simple and reliable.
 *
 * Endpoints:
 *   GET   /api.php   -> returns { vendors, items, notes, entries }
 *   POST  /api.php   -> body is { vendors, items, notes, entries }, overwrites the store
 *
 * Auth: every request must include header  X-API-Key: <your secret>
 * matching the value of API_KEY in config.php (one directory above this file).
 */

require_once __DIR__ . '/../config.php';

// ── CORS ──────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Access-Control-Max-Age: 86400');

// Preflight requests end here
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals(API_KEY, $providedKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

// ── Ensure data directory + default file exist ──────────────────
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0750, true);
}
$storeFile = DATA_DIR . '/store.json';

$defaultStore = [
    'vendors' => [],
    'items'   => [],
    'notes'   => '',
    'entries' => [],
    'updatedAt' => null,
];

if (!file_exists($storeFile)) {
    file_put_contents($storeFile, json_encode($defaultStore, JSON_PRETTY_PRINT));
}

// ── Handle GET (read) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $raw = file_get_contents($storeFile);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $defaultStore;
    }
    echo json_encode($data);
    exit;
}

// ── Handle POST (write) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $incoming = json_decode($body, true);

    if (!is_array($incoming)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body']);
        exit;
    }

    // Basic shape validation — keep whatever keys are recognised,
    // default anything missing so a partial payload can't wipe other data.
    $existingRaw = file_get_contents($storeFile);
    $existing = json_decode($existingRaw, true);
    if (!is_array($existing)) {
        $existing = $defaultStore;
    }

    $merged = [
        'vendors' => array_key_exists('vendors', $incoming) ? $incoming['vendors'] : $existing['vendors'],
        'items'   => array_key_exists('items', $incoming)   ? $incoming['items']   : $existing['items'],
        'notes'   => array_key_exists('notes', $incoming)   ? $incoming['notes']   : $existing['notes'],
        'entries' => array_key_exists('entries', $incoming) ? $incoming['entries'] : $existing['entries'],
        'updatedAt' => date('c'),
    ];

    // Atomic write: write to a temp file then rename, so a request that
    // fails mid-write (e.g. connection drop) can never corrupt the store.
    $tmpFile = $storeFile . '.tmp';
    $written = file_put_contents($tmpFile, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not write data file — check folder permissions']);
        exit;
    }
    rename($tmpFile, $storeFile);

    echo json_encode(['ok' => true, 'updatedAt' => $merged['updatedAt']]);
    exit;
}

// ── Anything else ──────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
