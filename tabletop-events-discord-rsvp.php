<?php
/**
 * Plugin Name:       Tabletop Events Calendar — Discord RSVP
 * Plugin URI:        https://github.com/TABARC-Code/tabletop-events-discord-rsvp
 * Description:       RSVP to Tabletop Events Calendar events straight from Discord — a button on the announcement message opens a native Discord modal, no persistent bot process required. Requires the Tabletop Events Calendar plugin (https://github.com/TABARC-Code/tabletop-gaming-events-calendar).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  tabletop-events-calendar
 * Author:            TABARC-Code
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tabletop-events-discord-rsvp
 *
 * Architecture note: real Discord "reactions" (an emoji react on a
 * message) only fire over Discord's Gateway — a persistent WebSocket
 * connection that a WordPress plugin can't hold open between requests.
 * This plugin deliberately uses Discord's Interactions webhook model
 * instead (buttons + modals), which is plain HTTPS request/response —
 * no bot process to host, no uptime to babysit, just another REST
 * route on this WordPress install. See README.md for the trade-off
 * this involves and the one-time Discord Developer Portal setup it
 * requires.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'TECDR_VERSION', '1.0.0' );
define( 'TECDR_PLUGIN_FILE', __FILE__ );
define( 'TECDR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TECDR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload this plugin's includes/class-tecdr-*.php files. Separate
 * from the core plugin's TEC_ autoloader (and the venues/LFG plugins'
 * own autoloaders) — independently-installable plugins that happen to
 * depend on each other, not one plugin split across repos.
 */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'TECDR_' ) !== 0 ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) );
		$path = TECDR_PLUGIN_DIR . 'includes/class-' . $slug . '.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Boot once all plugins are loaded — same reasoning as the venues and
 * LFG plugins: every plugin's file is included before any
 * plugins_loaded callback fires, so checking for the core plugin's
 * classes/constants here is safe regardless of activation order.
 */
function tecdr_init() {
	if ( ! tecdr_dependency_met() ) {
		add_action( 'admin_notices', 'tecdr_missing_dependency_notice' );
		return;
	}

	load_plugin_textdomain( 'tabletop-events-discord-rsvp', false, dirname( plugin_basename( TECDR_PLUGIN_FILE ) ) . '/languages' );

	TECDR_Settings::instance();
	TECDR_Announcer::instance();
	TECDR_Interactions::instance();
}
add_action( 'plugins_loaded', 'tecdr_init', 20 );

/**
 * Specifically checks for TEC_Rsvp, not just TEC_POST_TYPE — this
 * plugin's entire purpose is calling the core plugin's RSVP REST
 * route internally, so it's the one class that actually has to exist,
 * not just the event post type.
 */
function tecdr_dependency_met() {
	return defined( 'TEC_POST_TYPE' ) && class_exists( 'TEC_Rsvp' );
}

function tecdr_missing_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Tabletop Events Calendar — Discord RSVP requires the Tabletop Events Calendar plugin to be installed and active.', 'tabletop-events-discord-rsvp' ) .
		'</p></div>';
}

function tecdr_activate() {
	flush_rewrite_rules();
}
register_activation_hook( TECDR_PLUGIN_FILE, 'tecdr_activate' );

function tecdr_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( TECDR_PLUGIN_FILE, 'tecdr_deactivate' );
