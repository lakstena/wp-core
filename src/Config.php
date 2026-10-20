<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * Site configuration. The site's own mu-plugin adds a filter:
 *
 *   add_filter( 'wp_core_config', fn( $c ) => array_replace( $c, array( 'env_prefix' => 'NB_', ... ) ) );
 *
 * The filter is applied once, lazily, the first time a value is read (always after all mu-plugins are loaded).
 */
final class Config {

	private static ?array $cfg = null;

	public static function defaults(): array {
		return array(
			// Naming. Markup and cookie names keep the group-wide `lk-` prefix on purpose.
			'option_key'       => 'wp_core_seo',   // wp_options row holding company/SEO settings
			'meta_prefix'      => '_wpc_',         // post meta: {prefix}title, {prefix}desc, {prefix}schema, {prefix}noindex
			'env_prefix'       => 'WPC_',          // env/constants: {prefix}GA4_ID, GSC_VERIFICATION, BING_VERIFICATION
			'text_domain'      => 'wp-core',       // gettext domain of the consent banner (.mo in WP_LANG_DIR/{domain}-{locale}.mo)
			'post_types'       => array( 'page', 'post' ),
			'defaults'         => array(),         // default values of the settings option
			// Output details.
			'head_comment'     => 'WP Core SEO',
			'theme_color'      => '#ffffff',
			'geo_region'       => '',              // e.g. LT-UT; geo meta is printed only when options lat and lng are set
			'locales'          => array(),         // language slug => og:locale, e.g. array( 'en' => 'en_GB' )
			'robots_comment'   => 'AI search: allowed',
			'ai_bots'          => array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot-Extended', 'Bingbot' ),
			// Secrets-free identifiers.
			'health_token_sha256' => '',           // SHA-256 of the health token (the token itself lives in Bitwarden). Empty = endpoint disabled
			'indexnow_key'     => '',              // public by design; empty = IndexNow off
			// llms.txt (see Llms.php).
			'llms'             => array(
				'title'    => '',                  // empty = {name} option
				'summary'  => '',                  // blockquote; empty = first lines of facts_* joined
				'contact'  => '',                  // one line under the summary
				'facts'    => array(),             // lang => section title; options facts_{lang} hold the lines
				'pages_title' => 'Pages',
				'brands'   => array(),             // list of [ name, url, description ]
			),
		);
	}

	public static function all(): array {
		if ( null === self::$cfg ) {
			$base      = self::defaults();
			$cfg       = apply_filters( 'wp_core_config', $base );
			self::$cfg = array_replace_recursive( $base, is_array( $cfg ) ? $cfg : array() );
		}
		return self::$cfg;
	}

	/** @return mixed */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/** Environment value: constant first, then env(), then getenv(). Empty string when not set. */
	public static function env( string $name ): string {
		$full = self::get( 'env_prefix' ) . $name;
		$raw  = defined( $full ) ? constant( $full ) : ( function_exists( 'env' ) ? env( $full ) : getenv( $full ) );
		return is_string( $raw ) ? trim( $raw ) : '';
	}
}
