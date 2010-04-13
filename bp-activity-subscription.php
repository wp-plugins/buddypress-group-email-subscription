<?php
/*
Plugin Name: BuddyPress Group Email Subscription
Plugin URI: http://www.namoo.co.uk
Description: Allow BuddyPress members to get email updates for new forum topics, posts, activity updates and more with levels of control. 
Author: David Cartwright, boonebgorges, Deryk Wenaus
Revision Date: Mar 24, 2010
Version: 2.1b
*/


//global $ass_activities;
//$ass_activities = 'we are going to put the activities data in here later';

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
//add_action( 'plugins_loaded', 'ass_init', 1 );


function ass_more_reccurences() {
	return array(
		'weekly' => array('interval' => 604800, 'display' => 'Once Weekly'), 
		'twominutes' => array('interval' => 120, 'display' => 'Every Two Minutes' )
	);
}
//add_filter('cron_schedules', 'ass_more_reccurences');

/* On activation, this function sets the default digest interval: daily, starting 30 seconds from activation */
function ass_activation_setup() {
	if ( !get_option( 'ass_digest_frequency' ) )
		update_option( 'ass_digest_frequency', 24 );
		
	wp_schedule_event( time() + 30, 'daily', 'ass_digest_event' );
}
register_activation_hook( __FILE__, 'ass_activation_setup' );

/* On deactivation, remove the digest event */
function ass_deactivation_setup() {
	wp_clear_scheduled_hook('ass_digest_event');
}
register_deactivation_hook(__FILE__, 'ass_deactivation_setup' );




/* broken?
function load_activity_subscription_plugin() {
	require_once( WP_PLUGIN_DIR . '/bp-activity-subscription/bp-activity-subscription-main.php' );
}

if ( defined( 'BP_VERSION' ) )
	load_activity_subscription_plugin();
else
	add_action( 'bp_init', 'load_activity_subscription_plugin' );
*/

/* DIGEST */
/* To activate when all digest functions are finished
register_activation_hook ( __FILE__ , 'my_activation' ) ;
add_action ( Ô my_hourly_event Ô , 'do_this' );
add_action ( Ô my_daily_event Ô , 'do_this' );
add_action ( Ô my_weekly_event Ô , 'do_this' );

*/


?>