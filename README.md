# lakstena/wp-core

Shared must-use WordPress module for the Lakstena group sites (MIT). Installs with Composer as a Bedrock mu-plugin and is configured by the site through one filter. It contains no domains, keys, tokens or IP addresses.

| Module | What it does |
|---|---|
| `Head` | `<title>`, meta description, Open Graph, Twitter card, geo meta, `wp_robots` directives |
| `Meta` | per-post SEO fields (`{prefix}title`, `desc`, `schema`, `noindex`) |
| `Schema` | JSON-LD helpers: Organization, WebSite, BreadcrumbList, `print_graph()` |
| `Robots` | robots.txt with AI search crawlers allowed and the sitemap announced |
| `Cleanup` | sitemap without users/taxonomies/noindex, author and attachment redirects, clean `<head>` |
| `Llms` | `/llms.txt` |
| `Analytics` | GA4 + Consent Mode v2 + cookie banner, production only, gtag.js after consent |
| `Webmaster` | Search Console / Bing verification tags, IndexNow key file and ping |
| `Health` | `/_health` for an external monitor (token in a header, only its SHA-256 in config) |
| `Headers` | `Cross-Origin-Opener-Policy` |

## Install (Bedrock)

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/lakstena/wp-core" }],
"require": { "lakstena/wp-core": "0.1.0" }
```

`composer/installers` places it in `web/app/mu-plugins/wp-core/` (type `wordpress-muplugin`). Add `!web/app/mu-plugins/wp-core/` handling to `.gitignore` only if the directory is not Composer-managed.

## Configure

In the site's own mu-plugin:

```php
add_filter( 'wp_core_config', static function ( array $c ): array {
	return array_replace_recursive( $c, array(
		'option_key'  => 'site_seo',
		'meta_prefix' => '_site_',
		'env_prefix'  => 'SITE_',          // SITE_GA4_ID, SITE_GSC_VERIFICATION, SITE_BING_VERIFICATION
		'text_domain' => 'site-seo',
		'defaults'    => array( 'name' => 'Example', 'brand' => 'Example', /* … */ ),
		'health_token_sha256' => '…',      // SHA-256 of the token, never the token
		'indexnow_key'        => '…',      // public by design
	) );
} );
```

See `src/Config.php` for every key. Extension filters: `wp_core_organization`, `wp_core_health_checks`, `wp_core_llms_page_args`, `wp_core_llms_pages`, `wp_core_llms_after_pages`, `wp_core_llms_lines`, `wp_core_analytics_config`. Config `print_head_meta => false` lets a site print its own description/canonical/Open Graph block.

Markup and cookie names (`lk-consent`, cookie `lk_consent`, `window.lkAnalytics`) keep the group-wide `lk-` prefix.

## Rules

- Generic code only. A domain, key, token, IP or customer data in a commit is a bug; run `gitleaks detect` before every release.
- A change must keep every consumer's output byte-identical unless the release notes say otherwise (consumers compare HTML, JSON-LD, robots.txt, sitemap and llms.txt before and after).
- Versions are git tags; consumers pin an exact version.
