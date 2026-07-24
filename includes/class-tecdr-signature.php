<?php
/**
 * Verifies that an incoming interaction request really came from
 * Discord. Discord signs every request to an Interactions Endpoint
 * URL with Ed25519 (headers X-Signature-Ed25519 / X-Signature-
 * Timestamp, signing timestamp+body against the application's public
 * key) — this is the entire authentication model for that endpoint;
 * there's no separate secret/token in the request itself. A request
 * that fails this check is not a real Discord interaction and must be
 * rejected with 401, which is also what Discord's own dashboard uses
 * to validate the URL when you first save it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TECDR_Signature {

	/**
	 * @param string $signature Hex-encoded value of the X-Signature-Ed25519 header.
	 * @param string $timestamp Value of the X-Signature-Timestamp header.
	 * @param string $body      The raw, unmodified request body.
	 * @param string $public_key Hex-encoded application public key.
	 * @return bool
	 */
	public static function verify( $signature, $timestamp, $body, $public_key ) {
		if ( '' === $signature || '' === $timestamp || '' === $public_key ) {
			return false;
		}
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			// PHP 7.2+ bundles libsodium, so this should never trip on a
			// supported host — but failing closed (reject) rather than
			// open (accept unverified) is the only safe default if it
			// somehow does.
			return false;
		}

		$signature_bin = @hex2bin( $signature ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$key_bin       = @hex2bin( $public_key ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $signature_bin || false === $key_bin ) {
			return false;
		}

		try {
			return sodium_crypto_sign_verify_detached( $signature_bin, $timestamp . $body, $key_bin );
		} catch ( SodiumException $e ) {
			// Malformed key/signature length etc. — not a real Discord
			// request, or a misconfigured public key. Either way, reject.
			return false;
		}
	}
}
