<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/**
 * Response headers that belong to the application, not to nginx:
 * Cross-Origin-Opener-Policy: same-origin-allow-popups (set in PHP so the FastCGI cache stores it with the page).
 */
final class Headers {

	public static function register(): void {
		add_action( 'send_headers', static function () {
			if ( ! headers_sent() ) {
				header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
			}
		} );
	}
}
