<?php
/**
 * Bot token, application public key, and target channel — the three
 * things a Discord Application provides that this plugin needs. The
 * bot token is treated as a secret exactly like the reference plugin
 * this was modelled on (TABARC-Code/WP-Discord-Autoposter-V2) treats
 * its webhook URL: never redisplayed once saved, only replaced.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TECDR_Settings {

	private static $instance = null;
	const OPTION_KEY = 'tecdr_settings';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function default_settings() {
		return array(
			'bot_token'   => '',
			'public_key'  => '',
			'channel_id'  => '',
		);
	}

	public static function get() {
		$settings = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $settings, self::default_settings() );
	}

	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . TEC_POST_TYPE,
			__( 'Discord RSVP', 'tabletop-events-discord-rsvp' ),
			__( 'Discord RSVP', 'tabletop-events-discord-rsvp' ),
			'manage_options',
			'tecdr-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'tecdr_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * A blank bot_token field on save means "leave it alone", not
	 * "clear it" — the field is always rendered empty (see
	 * render_page()) so there's no way to distinguish "admin left it
	 * blank on purpose" from "this is just the placeholder", and
	 * silently wiping a working bot token because of that ambiguity
	 * would be a nasty surprise. Only a new, deliberately-designed
	 * "clear" flow removes it (see the checkbox in render_page()).
	 */
	public function sanitize( $input ) {
		// WordPress adds magic-quotes slashes to all of $_POST before it
		// ever reaches a sanitize_callback (see the matching comment in
		// the core plugin's class-tec-settings.php) — without this, a
		// bot token or public key containing a backslash-sensitive
		// character would be saved with a stray literal backslash.
		$input    = wp_unslash( $input );
		$existing = self::get();
		$output   = array(
			'public_key' => preg_replace( '/[^a-f0-9]/i', '', (string) ( $input['public_key'] ?? '' ) ),
			'channel_id' => preg_replace( '/[^0-9]/', '', (string) ( $input['channel_id'] ?? '' ) ),
		);

		if ( ! empty( $input['clear_bot_token'] ) ) {
			$output['bot_token'] = '';
		} elseif ( '' !== trim( (string) ( $input['bot_token'] ?? '' ) ) ) {
			$output['bot_token'] = trim( (string) $input['bot_token'] );
		} else {
			$output['bot_token'] = $existing['bot_token'];
		}

		return $output;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings          = self::get();
		$has_token         = '' !== $settings['bot_token'];
		$interactions_url  = rest_url( 'tecdr/v1/interactions' );

		if ( class_exists( 'TEC_Admin' ) ) {
			TEC_Admin::page_header(
				'format-chat',
				__( 'Discord RSVP', 'tabletop-events-discord-rsvp' ),
				__( 'Lets people RSVP to an event straight from Discord — a button opens a native Discord form, no need to leave the app.', 'tabletop-events-discord-rsvp' )
			);
		}
		?>
		<div class="wrap">
			<div class="tec-admin-card" style="max-width:820px;">
				<h2><?php esc_html_e( 'One-time Discord setup', 'tabletop-events-discord-rsvp' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Create an application at discord.com/developers/applications, then add a Bot to it.', 'tabletop-events-discord-rsvp' ); ?></li>
					<li><?php esc_html_e( 'Invite the bot to your server with permission to send messages in the channel you want announcements posted to.', 'tabletop-events-discord-rsvp' ); ?></li>
					<li><?php esc_html_e( 'Copy the Bot Token (Bot page) and the Public Key (General Information page) into the fields below.', 'tabletop-events-discord-rsvp' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: the interactions endpoint URL to paste into the Discord Developer Portal */
							esc_html__( 'Paste this URL into "Interactions Endpoint URL" on the General Information page: %s', 'tabletop-events-discord-rsvp' ),
							'<code>' . esc_html( $interactions_url ) . '</code>'
						);
						?>
					</li>
				</ol>
				<p class="description"><?php esc_html_e( 'This sends its own richer announcement with an RSVP button. If the core plugin\'s plain Discord/Slack webhook (Settings ▸ Integrations) is also configured, you\'ll get two messages per event — most sites want one or the other, not both.', 'tabletop-events-discord-rsvp' ); ?></p>

				<form method="post" action="options.php">
					<?php settings_fields( 'tecdr_settings_group' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="tecdr_bot_token"><?php esc_html_e( 'Bot Token', 'tabletop-events-discord-rsvp' ); ?></label></th>
							<td>
								<input type="password" id="tecdr_bot_token" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bot_token]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $has_token ? esc_attr__( '•••••••• (saved — leave blank to keep it)', 'tabletop-events-discord-rsvp' ) : esc_attr__( 'Paste the bot token', 'tabletop-events-discord-rsvp' ); ?>">
								<?php if ( $has_token ) : ?>
									<label style="display:block;margin-top:6px;"><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[clear_bot_token]" value="1"> <?php esc_html_e( 'Remove the saved bot token', 'tabletop-events-discord-rsvp' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Never shown again once saved — treat it like a password. Leave blank when saving other fields to keep the existing token.', 'tabletop-events-discord-rsvp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="tecdr_public_key"><?php esc_html_e( 'Application Public Key', 'tabletop-events-discord-rsvp' ); ?></label></th>
							<td>
								<input type="text" id="tecdr_public_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[public_key]" value="<?php echo esc_attr( $settings['public_key'] ); ?>" class="regular-text" autocomplete="off">
								<p class="description"><?php esc_html_e( 'From the application\'s General Information page — used to verify that an interaction really came from Discord.', 'tabletop-events-discord-rsvp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="tecdr_channel_id"><?php esc_html_e( 'Channel ID', 'tabletop-events-discord-rsvp' ); ?></label></th>
							<td>
								<input type="text" id="tecdr_channel_id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[channel_id]" value="<?php echo esc_attr( $settings['channel_id'] ); ?>" class="regular-text" autocomplete="off">
								<p class="description"><?php esc_html_e( 'Right-click the channel in Discord (Developer Mode must be on) → Copy Channel ID.', 'tabletop-events-discord-rsvp' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
