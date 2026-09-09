<?php
/**
 * inventory-api / public / health.php
 *
 * Open this in a browser (e.g. https://yourname.synology.me/inventory/health.php)
 * to sanity-check the setup WITHOUT needing the API key. It never reveals
 * data or the secret — just confirms PHP is running and the data folder
 * is writable, which is 90% of setup problems.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$dataDirExists   = is_dir(DATA_DIR);
$dataDirWritable = $dataDirExists && is_writable(DATA_DIR);

if (!$dataDirExists) {
    @mkdir(DATA_DIR, 0750, true);
    $dataDirExists   = is_dir(DATA_DIR);
    $dataDirWritable = $dataDirExists && is_writable(DATA_DIR);
}

$keyIsDefault = (API_KEY === 'CHANGE-THIS-TO-A-LONG-RANDOM-SECRET-KEY');

echo json_encode([
    'status'            => 'ok',
    'php_version'       => phpversion(),
    'data_dir_exists'   => $dataDirExists,
    'data_dir_writable' => $dataDirWritable,
    'api_key_still_default' => $keyIsDefault,
    'allowed_origin'    => ALLOWED_ORIGIN,
    'message'           => $keyIsDefault
        ? '⚠️  You have not changed the default API key yet — edit config.php'
        : '✅ Looks good. Point the app at api.php (not this file).',
], JSON_PRETTY_PRINT);
