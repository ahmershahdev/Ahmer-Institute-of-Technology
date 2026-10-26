<?php
// robots.txt. Portals and private paths are excluded; crawlers are pointed at
// the generated sitemap and at llms.txt for AI agents.
declare(strict_types=1);
require_once __DIR__ . '/../backend/env.php';
require_once __DIR__ . '/../backend/site.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$base = ait_base_path();
$private = ['/admin/', '/staff/', '/teachers/login.php', '/teachers/dashboard.php', '/student/', '/backend/', '/database/', '/uploads/', '/admission/', '/docker/', '/tests/', '/bin/',
    '/dashboard', '/student-dashboard', '/login', '/log-in', '/student-login', '/registration', '/register', '/logout', '/healthz.php', '/*.php$'];

echo "User-agent: *\n";
echo "Allow: {$base}/\n";
foreach ($private as $path) {
    echo 'Disallow: ', $base, $path, "\n";
}
echo "\n# Public teacher profiles stay crawlable.\nAllow: {$base}/teachers/T\n";
echo "\nSitemap: ", ait_site_url(), "/sitemap.xml\n";
echo "# AI-readable summary: ", ait_site_url(), "/llms.txt\n";
