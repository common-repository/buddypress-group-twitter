<?php

class BP_Group_Twitter extends BP_Group_Extension {

	function bp_group_twitter() {
		global $bp;

		$this->name = __( 'Twitter', 'bp-group-twitter' );
		$this->slug = 'twitter';
		$this->nav_item_name = __( 'Twitter', 'bp-group-twitter' );

		$this->create_step_position = 21;
		$this->nav_item_position = 31;

		$this->enable_nav_item = false;
	}

	function create_screen() {
		global $bp;

		if ( !bp_is_group_creation_step( $this->slug ) )
			return false;
		?>

		<p><?php _e(
			"Add Twitter account names that you'd like to attach to this group in the box below.
			 Any future tweets by these users will show up on the group page.", 'bp-group-twitter' ) ?>
		</p>

		<p class="desc"><?php _e( "Separate accounts with commas. Example: @apeatling, @buddypressdev, @wordpress", 'bp-group-twitter' ) ?></p>

		<p>
			<label for="twitteraccounts"><?php _e( "Twitter Accounts:", 'bp-group-twitter' ) ?></label>
			<textarea name="twitteraccounts" id="twitteraccounts"><?php echo attribute_escape( implode( ', ', (array)groups_get_groupmeta( $bp->groups->current_group->id, 'twitteraccounts' ) ) ) ?></textarea>
		</p>

		<?php
		wp_nonce_field( 'groups_create_save_' . $this->slug );
	}

	function create_screen_save() {
		global $bp;

		check_admin_referer( 'groups_create_save_' . $this->slug );

		$unfiltered_accounts = explode( ',', $_POST['twitteraccounts'] );

		foreach( (array) $unfiltered_accounts as $twitter_account ) {
			if ( !empty( $twitter_account ) )
				$twitter_accounts[] = trim( $twitter_account );
		}

		/* Re-fetch */
		bp_group_twitter_fetch( $bp->groups->current_group->id, $twitter_accounts );

		groups_update_groupmeta( $bp->groups->current_group->id, 'twitteraccounts', $twitter_accounts );
		groups_update_groupmeta( $bp->groups->current_group->id, 'bp_group_twitter_lastupdate', gmdate( "Y-m-d H:i:s" ) );
	}

	function edit_screen() {
		global $bp;

		if ( !bp_is_group_admin_screen( $this->slug ) )
			return false; ?>

		<p class="desc">
			<?php _e( "Add Twitter account names that you'd like to attach to this group in the box below. Any future tweets by these users will show up on the group page.", 'bp-group-twitter' ) ?>
		</p>

		<p>
			<label for="twitteraccounts"><?php _e( "Twitter Accounts:", 'bp-group-twitter' ) ?></label>
			<textarea name="twitteraccounts" id="twitteraccounts"><?php echo attribute_escape( implode( ', ', (array)groups_get_groupmeta( $bp->groups->current_group->id, 'twitteraccounts' ) ) ) ?></textarea>
		</p>

		<input type="submit" name="save" value="<?php _e( "Update Accounts", 'bp-group-twitter' ) ?>" />

		<?php
		wp_nonce_field( 'groups_edit_save_' . $this->slug );
	}

	function edit_screen_save() {
		global $bp;

		if ( !isset( $_POST['save'] ) )
			return false;

		check_admin_referer( 'groups_edit_save_' . $this->slug );

		$unfiltered_accounts = explode( ',', $_POST['twitteraccounts'] );

		foreach( (array) $unfiltered_accounts as $twitter_account ) {
			if ( !empty( $twitter_account ) )
				$twitter_accounts[] = trim( $twitter_account );
		}

		/* Get a list of the twitter accounts before they were updated */
		$orig_twitter_accounts = groups_get_groupmeta( $bp->groups->current_group->id, 'twitteraccounts' );

		/* Loop and find any accounts that have been removed, so we can delete activity stream items */
		if ( !empty( $orig_twitter_accounts ) ) {
			foreach( (array) $orig_twitter_accounts as $account ) {
				if ( !in_array( $account, (array) $twitter_accounts ) )
					$removed[] = $account;
			}
		}

		/* Remove tweets for these accounts from the activity stream */
		if ( $removed ) {
			include_once( ABSPATH . WPINC . '/rss.php' );

			/* Remove activity for accounts that have been removed. */
			foreach( (array) $removed as $username ) {
				if ( $tweets = fetch_rss( "http://twitter.com/statuses/user_timeline/$username.rss" ) ) {
					/* Check there are tweets */
					if ( empty( $tweets->items ) )
						return false;

					foreach ( $tweets->items as $tweet ) {
						if ( function_exists( 'bp_activity_delete' ) ) {
							bp_activity_delete( array(
								'item_id' => $bp->groups->current_group->id,
								'secondary_item_id' => wp_hash( $username . ':' . wp_filter_kses( $tweet['link'] ) ),
								'component' => $bp->groups->id,
								'type' => 'new_tweet'
							) );
						}
					}
				}
			}
		}

		groups_update_groupmeta( $bp->groups->current_group->id, 'twitteraccounts', $twitter_accounts );
		groups_update_groupmeta( $bp->groups->current_group->id, 'bp_group_twitter_lastupdate', gmdate( "Y-m-d H:i:s" ) );

		/* Re-fetch */
		bp_group_twitter_fetch( $bp->groups->current_group->id, $twitter_accounts );

		bp_core_add_message( __( 'Twitter accounts updated successfully!', 'bp-group-twitter' ) );
		bp_core_redirect( bp_get_group_permalink( $bp->groups->current_group ) . '/admin/' . $this->slug );
	}

	/* We don't need display functions since the group activity stream handles it all. */
	function display() {}
	function widget_display() {}
}
bp_register_group_extension( 'BP_Group_Twitter' );

/* Fetch and record in activity stream */
function bp_group_twitter_fetch( $group_id = false, $usernames = false ) {
	global $bp;

	include_once(ABSPATH . WPINC . '/rss.php');

	if ( empty( $group_id ) )
		$group_id = $bp->groups->current_group->id;

	if ( empty( $usernames ) )
		$usernames = groups_get_groupmeta( $group_id, 'twitteraccounts' );

	if ( empty( $group_id ) || empty( $usernames ) )
		return false;

	if ( $group_id == $bp->groups->current_group->id )
		$group = $bp->groups->current_group;
	else
		$group = new BP_Groups_Group( $group_id );

	/* Set the visibility */
	$hide_sitewide = ( 'public' != $group->status ) ? true : false;

	/* Fetch the tweets for each username */
	foreach( (array) $usernames as $username ) {
		if ( !$tweets = fetch_rss( "http://twitter.com/statuses/user_timeline/$username.rss" ) )
			return false;

		/* Check there are tweets */
		if ( empty( $tweets->items ) )
			return false;

		/* Oldest first please. */
		$tweets->items = array_reverse( $tweets->items );

		foreach ( $tweets->items as $tweet ) {
			$content = utf8_encode( substr( strstr( $tweet['description'], ': ' ), 2, strlen( $tweet['description'] ) ) );
			$link = wp_filter_kses( $tweet['link'] );
			$pubdate = $tweet['pubdate'];

			/* Auto-hyperlink URL's and Twitter users */
			$content = make_clickable( $content );
			$content = bp_group_twitter_twitterize( $content );

			/* Filter the content for nasties */
			$content = wp_filter_kses( $content );

			/* Add a permalink link # at the end of each tweet */
			$content = "$content <a href='$link'>#</a>";

			/* Now post it to the activity stream */
			$activity_action = sprintf( __( 'New post from Twitter user <a href="%s">%s</a> in the group <a href="%s">%s</a>:', 'bp-group-twitter' ), "http://twitter.com/" . $username, $username, bp_get_group_permalink( $bp->groups->current_group ), attribute_escape( $bp->groups->current_group->name ) );
			$activity_content = '<blockquote>' . $content . '</blockquote>';
			$activity_content = apply_filters( 'bp_group_twitter_activity_content', $activity_content, &$post, &$group );

			/* Fetch an existing activity_id if one exists. */
			if ( function_exists( 'bp_activity_get_activity_id' ) )
				$id = bp_activity_get_activity_id( array( 'user_id' => false, 'action' => $activity_action, 'component' => $bp->groups->id, 'type' => 'new_tweet', 'item_id' => $group_id, 'secondary_item_id' => wp_hash( $username . ':' . $link ) ) );

			/* Record or update in activity streams. */
			groups_record_activity( array(
				'id' => $id,
				'user_id' => false,
				'action' => $activity_action,
				'content' => $activity_content,
				'primary_link' => $link,
				'type' => 'new_tweet',
				'item_id' => $group_id,
				'secondary_item_id' => wp_hash( $username . ':' . $link ),
				'recorded_time' => gmdate( "Y-m-d H:i:s", strtotime( $pubdate ) ),
				'hide_sitewide' => $hide_sitewide
			) );
		}
	}
}

function bp_group_twitter_twitterize($text) {
	$text = preg_replace('/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/$2\" class=\"twitter-user\">@$2</a>$3 ", $text);
	return $text;
}

/* Add a filter option to the filter select box on group activity pages */
function bp_group_twitter_add_filter() { ?>
	<option value="new_tweet"><?php _e( 'Show Twitter Posts', 'bp-group-twitter' ) ?></option><?php
}
add_action( 'bp_group_activity_filter_options', 'bp_group_twitter_add_filter' );
add_action( 'bp_activity_filter_options', 'bp_group_twitter_add_filter' );

/* Fetch group twitter posts after 30 mins expires and someone hits the group page */
function bp_group_twitter_refetch() {
	global $bp;

	$last_refetch = groups_get_groupmeta( $bp->groups->current_group->id, 'bp_group_twitter_lastupdate' );
	if ( strtotime( gmdate( "Y-m-d H:i:s" ) ) >= strtotime( '+30 minutes', strtotime( $last_refetch ) ) )
		add_action( 'wp_footer', '_bp_group_twitter_refetch' );

	/* Refetch the latest group twitter posts via AJAX so we don't stall a page load. */
	function _bp_group_twitter_refetch() {
		global $bp;?>
		<script type="text/javascript">
			jQuery(document).ready( function() {
				jQuery.post( ajaxurl, {
					action: 'refetch_group_twitter',
					'cookie': encodeURIComponent(document.cookie),
					'group_id': <?php echo $bp->groups->current_group->id ?>
				});
			});
		</script><?php

		groups_update_groupmeta( $bp->groups->current_group->id, 'bp_group_twitter_lastupdate', gmdate( "Y-m-d H:i:s" ) );
	}
}
add_action( 'groups_screen_group_home', 'bp_group_twitter_refetch' );

/* Refresh via an AJAX post for the group */
function bp_group_twitter_refresh() {
	bp_group_twitter_fetch( $_POST['group_id'] );
}
add_action( 'wp_ajax_refetch_group_twitter', 'bp_group_twitter_refresh' );

/* Automatically refresh blog posts for all groups every hour */
function bp_group_twitter_cron_refresh() {
	global $bp, $wpdb;

	$group_ids = $wpdb->get_col( $wpdb->prepare( "SELECT group_id FROM " . $bp->groups->table_name_groupmeta . " WHERE meta_key = 'twitteraccounts'" ) );

	foreach( $group_ids as $group_id )
		bp_group_twitter_fetch( $group_id );
}
add_action( 'bp_group_twitter_cron', 'bp_group_twitter_cron_refresh' );


?>