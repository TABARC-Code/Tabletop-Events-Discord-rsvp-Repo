<?php
/**
 * The single REST route Discord calls for everything: the initial
 * PING handshake when you save the Interactions Endpoint URL, a
 * button click, and a modal submission. All three arrive as POSTs to
 * the same URL with a `type` field telling us which is which.
 *
 * Deliberately does NOT write directly into the core plugin's RSVP
 * data — it calls the core plugin's own public REST route internally
 * via rest_do_request() (WordPress's built-in in-process REST
 * dispatch, no real HTTP round trip) exactly the way this plugin's own
 * front-end JS would call it over HTTP. That's the entire point of the
 * jigsaw architecture: this plugin doesn't need to know or duplicate
 * anything about how RSVP capacity/waitlisting/confirmation emails
 * work, it just becomes one more caller of the same API surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TECDR_Interactions {

	private static $instance = null;

	// Discord interaction types.
	const TYPE_PING                       = 1;
	const TYPE_APPLICATION_COMMAND        = 2;
	const TYPE_MESSAGE_COMPONENT          = 3;
	const TYPE_MODAL_SUBMIT               = 5;

	// Discord interaction response types.
	const RESPONSE_PONG                          = 1;
	const RESPONSE_CHANNEL_MESSAGE_WITH_SOURCE   = 4;
	const RESPONSE_MODAL                         = 9;

	const EPHEMERAL_FLAG = 64;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'tecdr/v1',
			'/interactions',
			array(
				'methods'             => 'POST',
				// Discord's Ed25519 signature IS the authentication —
				// verified by hand inside handle(), not by WordPress's
				// cookie/nonce system, which Discord's servers have no
				// way to participate in.
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'handle' ),
			)
		);
	}

	public function handle( WP_REST_Request $request ) {
		$settings = TECDR_Settings::get();

		$signature = (string) $request->get_header( 'X-Signature-Ed25519' );
		$timestamp = (string) $request->get_header( 'X-Signature-Timestamp' );
		$body      = (string) $request->get_body();

		if ( ! TECDR_Signature::verify( $signature, $timestamp, $body, $settings['public_key'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid request signature' ), 401 );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'malformed interaction' ), 400 );
		}

		switch ( (int) $data['type'] ) {
			case self::TYPE_PING:
				return rest_ensure_response( array( 'type' => self::RESPONSE_PONG ) );

			case self::TYPE_MESSAGE_COMPONENT:
				return $this->handle_component( $data );

			case self::TYPE_MODAL_SUBMIT:
				return $this->handle_modal_submit( $data );

			default:
				// Unrecognised interaction type (e.g. a slash command
				// this plugin doesn't register) — acknowledge harmlessly
				// rather than erroring, since Discord may retry a
				// non-2xx response.
				return rest_ensure_response( array( 'type' => self::RESPONSE_PONG ) );
		}
	}

	private function handle_component( array $data ) {
		$custom_id = (string) ( $data['data']['custom_id'] ?? '' );

		if ( 0 === strpos( $custom_id, 'tecdr_decline:' ) ) {
			return $this->ephemeral_message( __( "No worries — thanks for letting us know!", 'tabletop-events-discord-rsvp' ) );
		}

		if ( 0 === strpos( $custom_id, 'tecdr_rsvp:' ) ) {
			$event_id = (int) substr( $custom_id, strlen( 'tecdr_rsvp:' ) );
			if ( ! $event_id || ! get_post( $event_id ) ) {
				return $this->ephemeral_message( __( 'This event could not be found.', 'tabletop-events-discord-rsvp' ) );
			}
			return $this->rsvp_modal( $event_id );
		}

		return $this->ephemeral_message( __( "That button isn't recognised.", 'tabletop-events-discord-rsvp' ) );
	}

	/**
	 * Discord's native form UI — collects name + email without the
	 * player ever leaving Discord. This is what lets a Discord click
	 * become a real RSVP: the core plugin's RSVP record is keyed on
	 * email (for the confirmation/cancel/reminder emails it sends),
	 * and Discord's interaction payload never includes one — only a
	 * Discord user ID and username, which aren't an email address.
	 */
	private function rsvp_modal( $event_id ) {
		return rest_ensure_response(
			array(
				'type' => self::RESPONSE_MODAL,
				'data' => array(
					'custom_id'  => 'tecdr_modal:' . $event_id,
					'title'      => __( 'RSVP', 'tabletop-events-discord-rsvp' ),
					'components' => array(
						array(
							'type'       => 1,
							'components' => array(
								array(
									'type'      => 4, // Text Input
									'custom_id' => 'name',
									'label'     => __( 'Your Name', 'tabletop-events-discord-rsvp' ),
									'style'     => 1, // Short
									'required'  => true,
									'max_length' => 100,
								),
							),
						),
						array(
							'type'       => 1,
							'components' => array(
								array(
									'type'      => 4,
									'custom_id' => 'email',
									'label'     => __( 'Your Email', 'tabletop-events-discord-rsvp' ),
									'style'     => 1,
									'required'  => true,
									'max_length' => 200,
								),
							),
						),
					),
				),
			)
		);
	}

	private function handle_modal_submit( array $data ) {
		$custom_id = (string) ( $data['data']['custom_id'] ?? '' );
		if ( 0 !== strpos( $custom_id, 'tecdr_modal:' ) ) {
			return $this->ephemeral_message( __( "That form isn't recognised.", 'tabletop-events-discord-rsvp' ) );
		}
		$event_id = (int) substr( $custom_id, strlen( 'tecdr_modal:' ) );
		if ( ! $event_id || ! get_post( $event_id ) ) {
			return $this->ephemeral_message( __( 'This event could not be found.', 'tabletop-events-discord-rsvp' ) );
		}

		$fields = $this->flatten_modal_components( $data['data']['components'] ?? array() );
		$name   = sanitize_text_field( $fields['name'] ?? '' );
		$email  = sanitize_email( $fields['email'] ?? '' );

		if ( ! $name || ! is_email( $email ) ) {
			return $this->ephemeral_message( __( 'Please enter your name and a valid email address, then try again.', 'tabletop-events-discord-rsvp' ) );
		}

		// The same call this plugin's own front-end JS would make over
		// HTTP — dispatched in-process instead, so the core plugin's
		// capacity/waitlist/confirmation-email logic runs exactly once,
		// in exactly one place, regardless of which "client" triggered it.
		$internal_request = new WP_REST_Request( 'POST', '/tec/v1/rsvp/' . $event_id );
		$internal_request->set_header( 'content-type', 'application/json' );
		$internal_request->set_body( wp_json_encode( array( 'name' => $name, 'email' => $email ) ) );
		$response = rest_do_request( $internal_request );

		$body = $response->get_data();
		if ( $response->get_status() >= 400 ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( 'Could not RSVP right now — please try again from the event page.', 'tabletop-events-discord-rsvp' );
			return $this->ephemeral_message( $message );
		}

		$message = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( "You're on the list!", 'tabletop-events-discord-rsvp' );
		return $this->ephemeral_message( $message );
	}

	/**
	 * Modal submissions arrive as action-row-wrapped text inputs, same
	 * shape as the rows this plugin built in rsvp_modal() — this just
	 * undoes that wrapping into a flat custom_id => value map.
	 *
	 * @return array<string,string>
	 */
	private function flatten_modal_components( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			foreach ( (array) ( $row['components'] ?? array() ) as $component ) {
				if ( isset( $component['custom_id'] ) ) {
					$out[ $component['custom_id'] ] = (string) ( $component['value'] ?? '' );
				}
			}
		}
		return $out;
	}

	private function ephemeral_message( $text ) {
		return rest_ensure_response(
			array(
				'type' => self::RESPONSE_CHANNEL_MESSAGE_WITH_SOURCE,
				'data' => array(
					'content' => (string) $text,
					'flags'   => self::EPHEMERAL_FLAG,
				),
			)
		);
	}
}
