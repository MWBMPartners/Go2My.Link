<?php
/**
 * ============================================================================
 * 🤖 GoToMyLink — Dynamic robots.txt Handler (Component B)
 * ============================================================================
 *
 * Generates robots.txt dynamically based on system settings. Short URL
 * domains should generally block crawlers (short codes are not content)
 * while allowing the homepage.
 *
 * Settings:
 *   - indexer.allow_robots_txt  — Whether to serve robots.txt (else 404)
 *   - indexer.block_suspicious  — Whether to explicitly block bad bots
 *
 * 📖 Reference: https://www.robotstxt.org/robotstxt.html
 *
 * @package    GoToMyLink
 * @subpackage ComponentB
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

// ============================================================================
// 📦 Bootstrap — Minimal application loading
// ============================================================================

$componentAuthPath = dirname(__DIR__)
    . DIRECTORY_SEPARATOR . '_auth_keys'
    . DIRECTORY_SEPARATOR . 'auth_creds.php';

if (file_exists($componentAuthPath))
{
    require_once $componentAuthPath;
}

define('G2ML_COMPONENT',        'B');
define('G2ML_COMPONENT_NAME',   'Shortlink Redirect');
define('G2ML_COMPONENT_DOMAIN', 'g2my.link');
define('G2ML_ROOT', dirname(__DIR__, 2));

require_once G2ML_ROOT
    . DIRECTORY_SEPARATOR . '_includes'
    . DIRECTORY_SEPARATOR . 'page_init.php';

// ============================================================================
// 🤖 Generate robots.txt
// ============================================================================

// Check if robots.txt serving is enabled
$allowRobots = getSetting('indexer.allow_robots_txt', true);

if (!$allowRobots)
{
    http_response_code(404);
    exit;
}

// Set content type for plain text
// 📖 Reference: https://www.php.net/manual/en/function.header.php
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours

// ============================================================================
// 📋 Default rules — block crawling of short code paths
// ============================================================================
// Short codes are redirect endpoints, not crawlable content.
// Allow the homepage only.
// ============================================================================

echo 'User-agent: *' . PHP_EOL;
echo 'Disallow: /' . PHP_EOL;
echo 'Allow: /$' . PHP_EOL;
echo PHP_EOL;

// ============================================================================
// 🚫 Block suspicious bots (if enabled)
// ============================================================================
// Known aggressive crawlers that can generate excessive load on redirect
// engines. These bots typically ignore Disallow rules, but explicit blocking
// provides an additional signal and may help with log analysis.
//
// 📖 Reference: https://www.robotstxt.org/db.html
// ============================================================================

$blockSuspicious = getSetting('indexer.block_suspicious', false);

if ($blockSuspicious)
{
    $suspiciousBots = [
        'AhrefsBot',
        'SemrushBot',
        'MJ12bot',
        'DotBot',
        'BLEXBot',
        'PetalBot',
        'YandexBot',
        'Bytespider',
    ];

    echo '# Block suspicious/aggressive bots' . PHP_EOL;

    foreach ($suspiciousBots as $bot)
    {
        echo 'User-agent: ' . $bot . PHP_EOL;
        echo 'Disallow: /' . PHP_EOL;
        echo PHP_EOL;
    }
}

// ============================================================================
// 🗺️ Sitemap reference (Component A, not B)
// ============================================================================
echo '# Sitemap on the main website' . PHP_EOL;
echo 'Sitemap: https://go2my.link/sitemap.xml' . PHP_EOL;

exit;
