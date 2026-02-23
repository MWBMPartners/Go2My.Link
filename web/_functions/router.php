<?php
/**
 * ============================================================================
 * 🔀 Go2My.Link — File-Based Router
 * ============================================================================
 *
 * Maps URL route segments to PHP files in a pages/ directory.
 * Used by Component A (Main Website) and Admin Dashboard.
 *
 * Components B (g2my.link) and C (lnks.page) do NOT use this router —
 * they handle routing directly in their index.php entry points.
 *
 * Routing examples:
 *   /              → pages/home.php
 *   /about         → pages/about.php  OR  pages/about/index.php
 *   /legal/terms   → pages/legal/terms.php  OR  pages/legal/terms/index.php
 *   /dashboard     → pages/dashboard.php  OR  pages/dashboard/index.php
 *
 * Dependencies: None (standalone function)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - .htaccess routing: web/Go2My.Link/public_html/.htaccess
 *     - DIRECTORY_SEPARATOR: https://www.php.net/manual/en/dir.constants.php
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

/**
 * Resolve a URL route to a PHP file path.
 *
 * Takes a route string (from ?route= parameter, set by .htaccess) and maps
 * it to a PHP file within the specified pages directory.
 *
 * Security: Route segments are sanitised to prevent directory traversal.
 * Only alphanumeric characters, hyphens, and underscores are allowed.
 *
 * @param  string $route     The route string (e.g., '', 'about', 'legal/terms')
 * @param  string $pagesDir  Absolute path to the pages directory
 * @return array             ['file' => string|null, 'status' => int, 'segments' => array]
 *                           file: Absolute path to the PHP file (or null if not found)
 *                           status: HTTP status code (200 or 404)
 *                           segments: Array of sanitised route segments
 *
 * Usage example:
 *   $resolved = resolveRoute($_GET['route'] ?? '', __DIR__ . '/pages');
 *   if ($resolved['file'] !== null) {
 *       require $resolved['file'];
 *   } else {
 *       http_response_code(404);
 *       require __DIR__ . '/pages/errors/404.php';
 *   }
 */
function resolveRoute(string $route, string $pagesDir): array
{
    // Clean the route
    $route = trim($route, '/ ');

    // Empty route = homepage
    if ($route === '')
    {
        $homeFile = $pagesDir . DIRECTORY_SEPARATOR . 'home.php';

        if (file_exists($homeFile))
        {
            return [
                'file'     => $homeFile,
                'status'   => 200,
                'segments' => [],
            ];
        }

        // Fall back to index.php in the pages directory
        $indexFile = $pagesDir . DIRECTORY_SEPARATOR . 'index.php';

        if (file_exists($indexFile))
        {
            return [
                'file'     => $indexFile,
                'status'   => 200,
                'segments' => [],
            ];
        }

        return [
            'file'     => null,
            'status'   => 404,
            'segments' => [],
        ];
    }

    // Split route into segments
    $segments = explode('/', $route);

    // Sanitise each segment to prevent directory traversal
    // 📖 Reference: https://www.php.net/manual/en/function.preg-replace.php
    $sanitisedSegments = [];

    foreach ($segments as $segment)
    {
        // Only allow alphanumeric, hyphens, and underscores
        $clean = preg_replace('/[^a-zA-Z0-9\-_]/', '', $segment);

        // Prevent empty segments and directory traversal
        if ($clean === '' || $clean === '.' || $clean === '..')
        {
            return [
                'file'     => null,
                'status'   => 404,
                'segments' => [],
            ];
        }

        $sanitisedSegments[] = $clean;
    }

    // Build the file path from segments
    $relativePath = implode(DIRECTORY_SEPARATOR, $sanitisedSegments);

    // Try as a direct .php file first (e.g., pages/about.php)
    $directFile = $pagesDir . DIRECTORY_SEPARATOR . $relativePath . '.php';

    if (file_exists($directFile))
    {
        return [
            'file'     => $directFile,
            'status'   => 200,
            'segments' => $sanitisedSegments,
        ];
    }

    // Try as a directory with index.php (e.g., pages/about/index.php)
    $indexFile = $pagesDir . DIRECTORY_SEPARATOR . $relativePath . DIRECTORY_SEPARATOR . 'index.php';

    if (file_exists($indexFile))
    {
        return [
            'file'     => $indexFile,
            'status'   => 200,
            'segments' => $sanitisedSegments,
        ];
    }

    // Not found
    return [
        'file'     => null,
        'status'   => 404,
        'segments' => $sanitisedSegments,
    ];
}

/**
 * Get the current route string from the request.
 *
 * @return string  The route string (empty for homepage)
 */
function getCurrentRoute(): string
{
    return trim($_GET['route'] ?? '', '/ ');
}

/**
 * Check if the current route matches a given pattern.
 *
 * @param  string $pattern  Route pattern to match (e.g., 'about', 'legal/*')
 * @return bool             True if the current route matches
 */
function isRoute(string $pattern): bool
{
    $current = getCurrentRoute();

    // Exact match
    if ($pattern === $current)
    {
        return true;
    }

    // Wildcard match (e.g., 'legal/*' matches 'legal/terms', 'legal/privacy')
    if (str_ends_with($pattern, '/*'))
    {
        $prefix = substr($pattern, 0, -2);
        return str_starts_with($current, $prefix . '/') || $current === $prefix;
    }

    return false;
}

/**
 * Generate a URL for a given route.
 *
 * @param  string $route       The route (e.g., 'about', 'legal/terms')
 * @param  array  $queryParams Optional query string parameters
 * @return string              The URL path
 */
function routeURL(string $route = '', array $queryParams = []): string
{
    $url = '/' . ltrim($route, '/');

    if (!empty($queryParams))
    {
        // 📖 Reference: https://www.php.net/manual/en/function.http-build-query.php
        $url .= '?' . http_build_query($queryParams);
    }

    return $url;
}
