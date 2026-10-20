<?php
/**
 * Plugin Name: WP Core (Lakstena)
 * Description: Shared must-use module for Lakstena group sites: SEO head, JSON-LD helpers, robots.txt, sitemap tweaks, llms.txt, IndexNow, GA4 + Consent Mode v2, health endpoint, head cleanup. Configured by the site through the `wp_core_config` filter.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: MIT
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_CORE_VERSION' ) ) {
	return;
}

define( 'WP_CORE_VERSION', '0.1.0' );
define( 'WP_CORE_DIR', __DIR__ );
define( 'WP_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Schema.php';
require_once __DIR__ . '/src/Meta.php';
require_once __DIR__ . '/src/Head.php';
require_once __DIR__ . '/src/Robots.php';
require_once __DIR__ . '/src/Cleanup.php';
require_once __DIR__ . '/src/Llms.php';
require_once __DIR__ . '/src/Analytics.php';
require_once __DIR__ . '/src/Webmaster.php';
require_once __DIR__ . '/src/Health.php';
require_once __DIR__ . '/src/Headers.php';

\Lakstena\WpCore\Meta::register();
\Lakstena\WpCore\Head::register();
\Lakstena\WpCore\Robots::register();
\Lakstena\WpCore\Cleanup::register();
\Lakstena\WpCore\Llms::register();
\Lakstena\WpCore\Analytics::register();
\Lakstena\WpCore\Webmaster::register();
\Lakstena\WpCore\Health::register();
\Lakstena\WpCore\Headers::register();
