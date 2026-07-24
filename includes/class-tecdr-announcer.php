<?php
/**
 * Posts a richer Discord announcement than the core plugin's plain
 * webhook — an embed plus an RSVP button — when an event is first
 * published. Sent via the Discord Bot API (not a plain incoming
 * webhook) because a message's buttons only route their click
 * interactions to an application that actually posted them; a bare
 * webhook message has no such application backing it.
 *
 * Same duplicate-send and mention-safety hardening as the core
 * plugin's class-tec-webhooks.php (reviewed from TABARC-Code's own
 * wp-discord-announcer plugin) — worth repeating here rather than
 * assuming "it's a different plugin, it won't hit the same bug".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TECDR_Announcer {

	private static $instance = null;
	const META_MESSAGE_FINGERPRINT = '_tecdr_announced_fingerprint';
	const API_BASE = 'https://discord.com/api/v10';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'transition_post_status', array( $this, 'notify_on_publish' ), 10, 3 );
	}

	public function notify_on_publish( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || TEC_POST_TYPE !== $post->post_type || 'publish' === $old_status || 'publish' !== $new_status ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		$settings = TECDR_Settings::get();
		if ( '' === $settings['bot_token'] || '' === $settings['channel_id'] ) {
			return;
		}

		$fingerprint = hash( 'sha256', $post->ID . '|' . $post->post_modified_gmt . '|' . $post->post_status );
		$last        = (string) get_post_meta( $post->ID, self::META_MESSAGE_FINGERPRINT, true );
		if ( '' !== $last && hash_equals( $last, $fingerprint ) ) {
			return;
		}

		$sent = $this->send_announcement( $post, $settings );
		if ( $sent ) {
			update_post_meta( $post->ID, self::META_MESSAGE_FINGERPRINT, $fingerprint );
		}
	}

	private function send_announcement( WP_Post $post, array $settings ) {
		$date = get_post_meta( $post->ID, '_tec_date', true );
		$city = get_post_meta( $post->ID, '_tec_city', true );
		$url  = get_permalink( $post );

		$embed = array(
			'title'       => $this->truncate( get_the_title( $post ), 256 ),
			'url'         => $url,
			'description' => $this->truncate( wp_strip_all_tags( get_post_meta( $post->ID, '_tec_notes', true ) ), 400 ),
			'fields'      => array_filter(
				array(
					array( 'name' => 'Date', 'value' => (string) $date, 'inline' => true ),
					$city ? array( 'name' => 'City', 'value' => (string) $city, 'inline' => true ) : null,
				)
			),
		);

		$rsvp_open = null !== TEC_Rsvp::instance()->get_summary( $post->ID );

		$components = array();
		if ( $rsvp_open ) {
			$components[] = array(
				'type'       => 1, // Action Row
				'components' => array(
					array(
						'type'      => 2, // Button
						'style'     => 3, // Success (green)
						'label'     => __( 'RSVP', 'tabletop-events-discord-rsvp' ),
						'custom_id' => 'tecdr_rsvp:' . $post->ID,
						'emoji'     => array( 'name' => '✅' ),
					),
					array(
						'type'      => 2,
						'style'     => 2, // Secondary (grey)
						'label'     => __( "Can't Make It", 'tabletop-events-discord-rsvp' ),
						'custom_id' => 'tecdr_decline:' . $post->ID,
						'emoji'     => array( 'name' => '❌' ),
					),
				),
			);
		}

		$payload = array(
			'content'          => sprintf( '📅 %s', $url ),
			'embeds'           => array( array_filter( $embed ) ),
			'components'       => $components,
			'allowed_mentions' => array( 'parse' => array() ),
		);
		$payload = apply_filters( 'tecdr_discord_payload', $payload, $post );

		$response = wp_remote_post(
			self::API_BASE . '/channels/' . rawurlencode( $settings['channel_id'] ) . '/messages',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bot ' . $settings['bot_token'],
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( sprintf( '[Tabletop Events Calendar — Discord RSVP] Request failed for event %d: %s', $post->ID, $response->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			error_log( sprintf( '[Tabletop Events Calendar — Discord RSVP] Discord API returned HTTP %d for event %d: %s', $code, $post->ID, wp_remote_retrieve_body( $response ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
		return true;
	}

	private function truncate( $text, $max ) {
		$text = (string) $text;
		if ( '' === $text || strlen( $text ) <= $max ) {
			return $text;
		}
		return substr( $text, 0, max( 0, $max - 1 ) ) . '…';
	}
}
