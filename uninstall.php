<?php
/**
 * Runs only when the plugin is deleted from wp-admin (not on simple
 * deactivation). This plugin stores no content of its own (no CPT, no
 * posts) — just its settings — so there's nothing to conservatively
 * preserve the way the other companion plugins preserve profiles/
 * listings. Also clears the per-event announcement fingerprint meta
 * the announcer uses for duplicate-send protection, so a later
 * reinstall doesn't inherit stale state from a previous install.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tecdr_settings' );

if ( defined( 'TEC_POST_TYPE' ) ) {
	$posts = get_posts(
		array(
			'post_type'      => TEC_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_tecdr_announced_fingerprint',
		)
	);
	foreach ( $posts as $post_id ) {
		delete_post_meta( $post_id, '_tecdr_announced_fingerprint' );
	}
}
