<?php
/**
 * inventory-api / config.php
 *
 * Kept OUTSIDE the public web root (one level above /public) so it can
 * never be downloaded directly by URL — only api.php can read it via
 * require_once from the filesystem.
 */

// ── Set your own secret key here ────────────────────────────────
// Use a long random string. This must match exactly what you enter
// into the app's "Secret key" field under Settings → NAS Sync.
// Generate one easily at: https://www.random.org/strings/  (32 chars, letters+digits)
define('API_KEY', 'CHANGE-THIS-TO-A-LONG-RANDOM-SECRET-KEY');

// ── CORS: only allow requests from your app's domain ────────────
// This should be the exact origin GitHub Pages serves your app from.
// No trailing slash.
define('ALLOWED_ORIGIN', 'https://swam90.github.io');

// ── Where data files are stored (outside the public web root) ───
define('DATA_DIR', __DIR__ . '/data');
