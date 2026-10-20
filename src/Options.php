<?php
namespace Lakstena\WpCore;

defined( 'ABSPATH' ) || exit;

/** Company/SEO settings: wp_options row (config `option_key`) merged over config `defaults`. */
final class Options {

	public static function all(): array {
		return wp_parse_args( (array) get_option( Config::get( 'option_key' ), array() ), (array) Config::get( 'defaults' ) );
	}

	/** @return mixed */
	public static function get( ?string $key = null ) {
		$o = self::all();
		return null === $key ? $o : ( $o[ $key ] ?? '' );
	}
}
