<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * GA4 + Consent Mode v2 + own cookie banner (no jQuery, no CDN, no plugin).
 *  – loaded only when WP_ENV = production and {prefix}GA4_ID is set; stage and local get no GA4 and no banner
 *  – Consent Mode v2: all four signals default to "denied"; "update" after the visitor chooses (180 days, cookie lk_consent)
 *  – gtag.js itself is requested only after the visitor agrees
 *  – events (click_tel, click_email, click_directions, …) are sent by assets/analytics.js
 * Banner texts are gettext strings (config `text_domain`, English source).
 */
final class Analytics {

	public static function register(): void {
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_footer', array( self::class, 'banner' ) );
	}

	/** GA4 measurement ID or '' (not production, not set, or wrong format). */
	public static function ga4_id(): string {
		static $id = null;
		if ( null !== $id ) {
			return $id;
		}
		$id = '';
		if ( ! defined( 'WP_ENV' ) || 'production' !== WP_ENV ) {
			return $id;
		}
		$raw = Config::env( 'GA4_ID' );
		if ( preg_match( '/^G-[A-Z0-9]{4,20}$/', $raw ) ) {
			$id = $raw;
		}
		return $id;
	}

	private static function load_texts(): void {
		$d = (string) Config::get( 'text_domain' );
		load_textdomain( $d, WP_LANG_DIR . '/' . $d . '-' . get_locale() . '.mo' );
	}

	/** Privacy policy URL in the CURRENT language, only when that page is published. */
	public static function privacy_url(): string {
		$id = (int) get_option( 'wp_page_for_privacy_policy' );
		if ( $id && function_exists( 'pll_get_post' ) && function_exists( 'pll_current_language' ) ) {
			$tr = pll_get_post( $id, pll_current_language() );
			$id = $tr ? (int) $tr : $id;
		}
		return ( $id && 'publish' === get_post_status( $id ) ) ? get_permalink( $id ) : '';
	}

	public static function head(): void {
		$id = self::ga4_id();
		if ( ! $id ) {
			return;
		}
		?>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});
/* gtag.js is requested only after the visitor agrees (Consent Mode v2, basic): nothing leaves the browser before that. */
window.lkLoadGtag=function(){if(window.lkGtagLoaded){return;}window.lkGtagLoaded=1;var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js( rawurlencode( $id ) ); ?>';document.head.appendChild(s);};
gtag('js',new Date());gtag('config','<?php echo esc_js( $id ); ?>');
(function(){var m=document.cookie.match(/(?:^|; )lk_consent=(granted|denied)/);if(m&&m[1]==='granted'){gtag('consent','update',{analytics_storage:'granted'});window.lkLoadGtag();}})();
</script>
<style>
.lk-consent{position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;max-width:560px;margin:0 auto;padding:16px 20px;border-radius:16px;background:var(--wp--preset--color--neutral-900,#1d1d1b);color:var(--wp--preset--color--neutral-100,#fff);font:400 14px/1.5 system-ui,sans-serif;box-shadow:0 8px 32px rgba(0,0,0,.25)}
.lk-consent[hidden]{display:none}
.lk-consent__title{display:block;margin:0 0 6px;font-size:16px}
.lk-consent p{margin:0 0 12px}
.lk-consent a{color:inherit;text-decoration:underline}
.lk-consent__btns{display:flex;flex-wrap:wrap;gap:8px}
.lk-consent button{flex:1 1 0;min-width:120px;cursor:pointer;border:2px solid var(--wp--preset--color--accent,#f5b400);border-radius:999px;padding:8px 18px;font:inherit;font-weight:600;background:var(--wp--preset--color--accent,#9c592d);color:#fff}
</style>
	<?php
	}

	public static function enqueue(): void {
		if ( ! self::ga4_id() ) {
			return;
		}
		$id_page = is_singular() ? get_queried_object_id() : 0;
		wp_enqueue_script( 'lk-analytics', WP_CORE_URL . 'assets/analytics.js', array(), WP_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script(
			'lk-analytics',
			'window.lkAnalytics=' . wp_json_encode( apply_filters( 'wp_core_analytics_config', array(
				'lang' => Head::lang( $id_page ),
				'maps' => array_values( array_filter( array( Options::get( 'gbp_url' ) ) ) ),
			), $id_page ) ) . ';',
			'before'
		);
	}

	public static function banner(): void {
		if ( ! self::ga4_id() ) {
			return;
		}
		self::load_texts();
		$d    = (string) Config::get( 'text_domain' );
		$priv = self::privacy_url();
		?>
<div class="lk-consent" id="lk-consent" role="dialog" aria-live="polite" aria-labelledby="lk-consent-title" hidden>
	<strong class="lk-consent__title" id="lk-consent-title"><?php echo esc_html( __( '🍯 Sweet, but honest', $d ) ); // phpcs:ignore WordPress.WP.I18n ?></strong>
	<p><?php echo esc_html( __( 'We like our honey pure and our cookies optional. With your OK we use analytics cookies (Google Analytics) to see what helps. Say no, and nothing is tracked.', $d ) ); // phpcs:ignore WordPress.WP.I18n ?><?php if ( $priv ) : ?> <a href="<?php echo esc_url( $priv ); ?>"><?php echo esc_html( __( 'More in our privacy policy', $d ) ); // phpcs:ignore WordPress.WP.I18n ?></a><?php endif; ?></p>
	<div class="lk-consent__btns">
		<button type="button" data-lk-consent="granted"><?php echo esc_html( __( 'Accept', $d ) ); // phpcs:ignore WordPress.WP.I18n ?></button>
		<button type="button" data-lk-consent="denied"><?php echo esc_html( __( 'Decline', $d ) ); // phpcs:ignore WordPress.WP.I18n ?></button>
	</div>
</div>
	<?php
	}
}
