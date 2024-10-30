<?php
/*
Plugin Name: BuddyPress Group Twitter
Plugin URI: http://wordpress.org/extend/plugins/buddypress-group-twitter/
Description: Link Twitter accounts to groups and track new tweets.
Version: 1.2
Requires at least: WordPress 2.9.1 / BuddyPress 1.2
Tested up to: WordPress 2.9.2 / BuddyPress 1.2
License: GNU/GPL 2
Author: Andy Peatling
Author URI: http://buddypress.org/developers/apeatling
Site Wide Only: true
*/

/* Only load the plugin functions if BuddyPress is loaded and initialized. */
function bp_group_twitter_init() {
	require( dirname( __FILE__ ) . '/bp-group-twitter.php' );
}
add_action( 'bp_init', 'bp_group_twitter_init' );

/* On activation register the cron to refresh twitter feeds. */
function bp_group_twitter_activate() {
	wp_schedule_event( time(), 'hourly', 'bp_group_twitter_cron' );
}
register_activation_hook( __FILE__, 'bp_group_twitter_activate' );

/* On deacativation, clear the cron. */
function bp_group_twitter_deactivate() {
	wp_clear_scheduled_hook( 'bp_group_twitter_cron' );

	/* Remove all gruop twitter activity */
	if ( function_exists( 'bp_activity_delete' ) )
		bp_activity_delete( array( 'type' => 'new_tweet' ) );
}
register_deactivation_hook( __FILE__, 'bp_group_twitter_deactivate' );

?>