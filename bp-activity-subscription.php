<?php
/*
Plugin Name: BuddyPress Group Email Subscription
Plugin URI: http://wordpress.org/extend/plugins/buddypress-group-email-subscription/
Description: Allow BuddyPress members to get email updates for new forum topics, posts, activity updates and more with levels of control. 
Author: boonebgorges, Deryk Wenaus, David Cartwright
Revision Date: May 12, 2010
Version: 2.2.1
*/

function activitysub_load_buddypress() {
	global $ass_activities;
	if ( function_exists( 'bp_core_setup_globals' ) ) {
		require_once ('bp-activity-subscription-main.php');
		return true;
	}
	/* Get the list of active sitewide plugins */
	$active_sitewide_plugins = maybe_unserialize( get_site_option( 'active_sitewide_plugins' ) );

	if ( !isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) )
		return false;

	if ( isset( $active_sidewide_plugins['buddypress/bp-loader.php'] ) && !function_exists( 'bp_core_setup_globals' ) ) {
		require_once( WP_PLUGIN_DIR . '/buddypress/bp-loader.php' );
		require_once ('bp-activity-subscription-main.php');
		return true;
	}

	return false;
}
add_action( 'plugins_loaded', 'activitysub_load_buddypress', 1 );

function activitysub_setup_digest_defaults() {
	require_once( WP_PLUGIN_DIR.'/buddypress-group-email-subscription/bp-activity-subscription-digest.php' );
	ass_set_daily_digest_time( '05', '00' );	
	ass_set_weekly_digest_time( '4' );
}
register_activation_hook( __FILE__, 'activitysub_setup_digest_defaults' );

function activitysub_unset_digests() {
	wp_clear_scheduled_hook( 'ass_digest_event' );
	wp_clear_scheduled_hook( 'ass_digest_event_weekly' );
}
register_deactivation_hook( __FILE__, 'activitysub_unset_digests' );


?>