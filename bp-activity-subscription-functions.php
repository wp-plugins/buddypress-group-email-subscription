<?php

//
// !SEND EMAIL UDPATES FOR FORUM TOPICS AND POSTS
//


// send email notificaitons for new forum topics
function ass_group_notification_new_forum_topic( $content ) {
	global $bp;
	
	/* New forum topics only */
	if ( $content->type != 'new_forum_topic' )
		return;	

	/* Check to see if user has been registered long enough */
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) ) 
		return;
	
	/* Subject */
	$subject = ass_clean_subject( $content->action ) . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
	
	/* Content */
	$the_content = strip_tags( stripslashes( $content->content ) );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply to this topic, log in and follow the link below:
%s

---------------------
', 'bp-ass' ), strip_tags( $content->action ), $the_content, $content->primary_link );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );
	
	$group_id = $content->item_id;	
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	// cycle through subscribed members and send an email
	foreach ( (array)$subscribed_users as $user_id => $group_status ) { 		

		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;

		if ( $group_status == 'sub' || $group_status == 'supersub' )  {
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message );  // Send the email
		} elseif ( $group_status == 'dig' || $group_status == 'sum' ) {
			ass_digest_record_activity( $content->id, $user_id, $group_id );
		}
		//echo '<br>Email: ' . $user->user_email;
	}	
}

add_action( 'bp_activity_after_save', 'ass_group_notification_new_forum_topic' );




// send email notificaitons for forum replies (or store for digest)
function ass_group_notification_forum_reply( $content ) {
	global $bp;

	/* New forum posts only */
	if ( $content->type != 'new_forum_post' )
		return;
		
	/* Check to see if user has been registered long enough */
	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;

	/* Subject */
	$subject = ass_clean_subject( $content->action ) . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';

	/* Content */
	$the_content = strip_tags( stripslashes( $content->content ) );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply to this topic, log in and follow the link below:
%s

---------------------
', 'bp-ass' ), strip_tags( $content->action ), $the_content, $content->primary_link );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	$group_id = $content->item_id;
	//$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );

	$post = bp_forums_get_post( $content->secondary_item_id );	
	$topic = get_topic( $post->topic_id );
	
	// pre-load these arrays to reduce db calls in the loop
	$ass_replies_to_my_topic = ass_user_settings_array( 'ass_replies_to_my_topic' );
	$ass_replies_after_me_topic = ass_user_settings_array( 'ass_replies_after_me_topic' );
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id , 'ass_user_topic_status_' . $topic->topic_id );
	$previous_posters = ass_get_previous_posters( $post->topic_id );

	// consolidate the arrays to speed up processing
	foreach ( array_keys( $previous_posters) as $previous_poster ) {
		if ( !$subscribed_users[ $previous_poster ] )
			$subscribed_users[ $previous_poster ] = 'prev-post';
	}
	
	foreach ( (array)$subscribed_users as $user_id => $group_status ) {
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		$send_it = NULL;
		//$group_status = $subscribed_users[ $user_id ]; // only need this if we're looping through user_ids
		$topic_status = $user_topic_status[ $user_id ];
	
	 	//echo '<p>uid:' . $user_id .' | gstat:' . $group_status . ' | tstat:'.$topic_status . ' | owner:'.$topic->topic_poster . ' | prev:'.$previous_posters[ $user_id ];
		
		if ( $topic_status == 'mute' )  // the topic mute button will override the subscription options below
			continue;
		
		if ( $group_status == 'sum' ) // skip if user set to weekly summary
			continue;
		
		if ( $group_status == 'supersub' )
			$send_it = true;	
		elseif ( $topic_status == 'sub' )
			$send_it = true;	
		elseif ( $topic->topic_poster == $user_id && $ass_replies_to_my_topic[ $user_id ] != 'no' )
			$send_it = true;
		elseif ( $previous_posters[ $user_id ] && $ass_replies_after_me_topic[ $user_id ] != 'no' )
			$send_it = true; 
		
		if ( $send_it ) {
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message );  // Send the email
			//echo '<br>Email: ' . $user->user_email;
		} 
		
		if ( $group_status == 'dig' ) {
			ass_digest_record_activity( $content->id, $user_id, $group_id );
			//echo '<br>Digest: ' . $user_id;
		}
		
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';
}
add_action( 'bp_activity_after_save', 'ass_group_notification_forum_reply' );





// The email notification function for all other activity
function ass_group_notification_activity( $content ) {
	global $bp;
	$type = $content->type;	
	
	/* all other activity notifications */	
	if ( $content->component != 'groups' || $type == 'new_forum_topic' || $type == 'new_forum_post' || $type == 'created_group' )
		return;
			
	if ( $type == 'joined_group' ) // TODO: in a future version, maybe allow mods and admins to get joined_group emails (?)
		return;	

	if ( !ass_registered_long_enough( $bp->loggedin_user->id ) )
		return;

	/* Subject */
	$subject = ass_clean_subject( $content->action ) . ' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';

	/* Content */
	$the_content = strip_tags( stripslashes( $content->content ) );
	
	/* If it's an activity item, switch the activity permalink to the group homepage rather than the user's homepage */
	$activity_permalink = ( isset( $content->primary_link ) && $content->primary_link != bp_core_get_user_domain( $content->user_id ) ) ? $content->primary_link : bp_get_group_permalink( $bp->groups->current_group );
	
	$message = sprintf( __(
'%s

"%s"

To view or reply, log in and follow the link below:
%s

---------------------
', 'bp-ass' ), strip_tags( $content->action ), $the_content, $activity_permalink );

	/* Content footer */
	$settings_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications/';
	$message .= sprintf( __( 'To disable these notifications please log in and go to: %s', 'buddypress' ), $settings_link );

	$group_id = $content->item_id;
	$subscribed_users = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
		
	$this_activity_is_important = FALSE;
	do_action( 'ass_group_notification_activity', $type, $subject, $message );	
	
	// cycle through subscribed users
	foreach ( (array)$subscribed_users as $user_id => $group_status ) { 			
		//echo '<p>uid: ' . $user_id .' | gstat: ' . $group_status ;
		if ( $user_id == $bp->loggedin_user->id )  // don't send email to topic author	
			continue;
		
		// activity notifications only go to Email and Digest. However plugin authors can make important activity updates get emailed out to Weekly summary and New topics by using the ass_group_notification_activity action hook. 
		
		if ( $group_status == 'supersub' || $group_status == 'sub' && $this_activity_is_important ) {
			$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
			wp_mail( $user->user_email, $subject, $message );  // Send the email
			//echo '<br>EMAIL: ' . $user->user_email . "<br>";
		} elseif ( $group_status == 'dig' || $group_status == 'sum' && $this_activity_is_important ) {
			ass_digest_record_activity( $content->id, $user_id, $group_id );
			//echo '<br>DIGEST: ' . $user_id . "<br>";
		}
	}
	
	//echo '<p>Subject: ' . $subject;
	//echo '<pre>'; print_r( $message ); echo '</pre>';	
}
add_action( 'bp_activity_after_save' , 'ass_group_notification_activity' , 50 );





//
//	!GROUP SUBSCRIPTION
//


// returns the subscription status of a user in a group
function ass_get_group_subscription_status( $user_id, $group_id ) {
	global $bp;
	
	if ( !$user_id )
		$bp->loggedin_user->id;
		
	if ( !$group_id )
		$bp->groups->current_group->id;
	
	$group_user_subscriptions = groups_get_groupmeta( $group_id, 'ass_subscribed_users' );
	return $group_user_subscriptions[ $user_id ];
}


// updates the group's user subscription list.
function ass_group_subscription( $action, $user_id, $group_id ) {
	if ( !$action || !$user_id || !$group_id )
		return false;
		
	$group_user_subscriptions = groups_get_groupmeta( $group_id , 'ass_subscribed_users' );
	
	// we're being overly careful here	
	if ( $action == 'no' ) {
		$group_user_subscriptions[ $user_id ] = 'no';
	} elseif ( $action == 'sum' ) {
		$group_user_subscriptions[ $user_id ] = 'sum';
	} elseif ( $action == 'dig' ) {
		$group_user_subscriptions[ $user_id ] = 'dig';
	} elseif ( $action == 'sub' ) {
		$group_user_subscriptions[ $user_id ] = 'sub';
	} elseif ( $action == 'supersub' ) {
		$group_user_subscriptions[ $user_id ] = 'supersub';
	} elseif ( $action == 'delete' ) {
		unset( $group_user_subscriptions[ $user_id ] );
	}
	
	groups_update_groupmeta( $group_id , 'ass_subscribed_users', $group_user_subscriptions );
}



// show group subscription settings on the notification page. 
function ass_group_subscribe_settings ( $group = false ) {
	global $bp, $groups_template;

	if ( !$group )
		$group = $bp->groups->current_group;
	
	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return false;
		
	$group_status = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );
	
	$submit_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications';
	
	?>
	<h3 class="activity-subscription-settings-title">Email Subscription Options</h3>
	<form action="<?php echo $submit_link ?>" method="post">
	<input type="hidden" name="ass_group_id" value="<?php echo $group->id; ?>"/>
	<?php wp_nonce_field( 'ass_subscribe' ); ?>
	
	<b><?php _e('How do you want to read this group?', 'bp-ass'); ?></b>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="no" <?php if ( $group_status == "no" || $group_status == "un" || !$group_status ) echo 'checked="checked"'; ?>><?php _e('No Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('I will read this group on the web', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="sum" <?php if ( $group_status == "sum" ) echo 'checked="checked"'; ?>><?php _e('Weekly Summary Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Get a summary of new topics each week', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="dig" <?php if ( $group_status == "dig" ) echo 'checked="checked"'; ?>><?php _e('Daily Digest Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Get all the day\'s activity bundled into a single email', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="sub" <?php if ( $group_status == "sub" ) echo 'checked="checked"'; ?>><?php _e('New Topics Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Send new topics as they arrive (but don\'t send replies)', 'bp-ass'); ?></div>
	</div>
	
	<div class="ass-email-type">
	<label><input type="radio" name="ass_group_subscribe" value="supersub" <?php if ( $group_status == "supersub" ) echo 'checked="checked"'; ?>><?php _e('Email', 'bp-ass'); ?></label>
	<div class="ass-email-explain"><?php _e('Send all group activity as it arrives', 'bp-ass'); ?></div>
	</div>

	<input type="submit" value="Save Settings" id="ass-save" name="ass-save" class="button-primary">

	<p class="ass-sub-note"><?php _e('Note: Normally, you receive email notifications for topics you start or comment on. This can be changed at', 'bp-ass'); ?> <a href="<?php echo $bp->loggedin_user->domain . 'settings/notifications/' ?>"><?php _e('email notifications', 'bp-ass'); ?></a>.</p>
	
	</form>

	<?php
}

// update the users' notification settings
function ass_update_group_subscribe_settings() {
	global $bp;
	
	if ( $bp->current_component == 'groups' && $bp->current_action == 'notifications' ) {

		// If the edit form has been submitted, save the edited details
		if ( isset( $_POST['ass-save'] ) ) {
		
			//if ( !wp_verify_nonce( $nonce, 'ass_subscribe' ) ) die( 'A Security check failed' );
			
			$user_id = $bp->loggedin_user->id;
			$group_id = $_POST[ 'ass_group_id' ];
			$action = $_POST[ 'ass_group_subscribe' ];
			
			if ( !groups_is_user_member( $user_id, $group_id ) )
				return;
				
			ass_group_subscription( $action, $user_id, $group_id ); // save the settings
			
			bp_core_add_message( __( $security.'Your email notifications are set to ' . ass_subscribe_translate( $action ) . ' for this group.', 'bp-ass' ) );
			bp_core_redirect( wp_get_referer() );	
		}
	}
}
add_action( 'wp', 'ass_update_group_subscribe_settings', 4 );



// translate the short code subscription status into a nicer version
function ass_subscribe_translate( $status ){
	if ( $status == 'no' )
		$output = __('No email', 'bp-ass');
	elseif ( $status == 'sum' )
		$output = __('Weekly summary', 'bp-ass');
	elseif ( $status == 'dig' )
		$output = __('Daily digest', 'bp-ass');
	elseif ( $status == 'sub' )
		$output = __('New topics', 'bp-ass');
	elseif ( $status == 'supersub' )
		$output = __('Email', 'bp-ass');
	
	return $output;
}


// this adds the ajax-based subscription option in the group header
function ass_group_subscribe_button( $group = false ) {
	global $bp, $groups_template;

	if ( !$group )
		$group =& $groups_template->group;

	if ( !is_user_logged_in() || $group->is_banned || !$group->is_member )
		return false;
	
	$group_status = ass_get_group_subscription_status( $bp->loggedin_user->id, $group->id );
	
	if ( $group_status == 'no' )
		$group_status = NULL;
		
	$link_text = __('Email Options', 'bp-ass');
	$sep = '/ ';
	
	if ( !$group_status ) {
		$link_text = __('Get email updates', 'bp-ass');
		$sep = '';
	}
	
	$status = ass_subscribe_translate( $group_status );
	?>
		
	<div class="group-subscription-div">
	<span class="gemail_icon" id="gsubstat-<?php echo $group->id; ?>"><?php echo $status; ?></span> <?php echo $sep; ?>	
	<a class="group-subscription-options-link" id="gsublink-<?php echo $group->id; ?>"><?php echo $link_text; ?>&nbsp;&#187;</a>
	<!--<span class="ajax-loader2" id="gsubajaxloader-<?php echo $group->id; ?>"></span>'-->
	</div>
	
	<div class="generic-button group-subscription-options" id="gsubopt-<?php echo $group->id; ?>">
	<a class="group-subscription-close" id="gsubclose-<?php echo $group->id; ?>">x</a>	
	<a class="group-subscription" id="no-<?php echo $group->id; ?>">No email</a> I will read this group on the web<br>
	<a class="group-subscription" id="sum-<?php echo $group->id; ?>">Weekly summary</a> Get a summary of topics each <?php echo ass_weekly_digest_week(); ?><br>
	<a class="group-subscription" id="dig-<?php echo $group->id; ?>">Daily digest</a> Get the day's activity bundled into one email<br>
	<a class="group-subscription" id="sub-<?php echo $group->id; ?>">New topics</a> Send new topics as they arrive (but no replies)<br>
	<a class="group-subscription" id="supersub-<?php echo $group->id; ?>">Email</a> Send all group activity as it arrives
	</div>
	<?php
}
add_action ( 'bp_group_header_meta', 'ass_group_subscribe_button' );
add_action ( 'bp_directory_groups_actions', 'ass_group_subscribe_button' );
//add_action ( 'bp_directory_groups_item', 'ass_group_subscribe_button' );  //useful to put in different location with css abs pos



// Handles AJAX request to subscribe/unsubscribe from group
function ass_group_ajax_callback() {
	global $bp;
	//check_ajax_referer( "ass_group_subscribe" );
	
	$action = $_POST['a'];
	$user_id = $bp->loggedin_user->id;
	$group_id = $_POST['group_id'];
		
	ass_group_subscription( $action, $user_id, $group_id );
	
	echo $action;
	exit();
}
add_action( 'wp_ajax_ass_group_ajax', 'ass_group_ajax_callback' );


// if the user leaves the group, delete their subscription status
function ass_unsubscribe_on_leave( $group_id, $user_id ){
	ass_group_subscription( 'delete', $user_id, $group_id );
}
add_action( 'groups_leave_group', 'ass_unsubscribe_on_leave', 100, 2 );



//
//	!Default Group Subscription
//

// when a user joins a group, set their default subscription level
function ass_set_default_subscription( $groups_member ){
	global $bp;
	
	// only set the default if the user has no subscription history for this group
	if ( ass_get_group_subscription_status( $groups_member->user_id, $groups_member->group_id ) )
		return;
	
	if ( $default_gsub = groups_get_groupmeta( $groups_member->group_id, 'ass_default_subscription' ) ) {
		ass_group_subscription( $default_gsub, $groups_member->user_id, $groups_member->group_id );
	}
}
add_action( 'groups_member_after_save', 'ass_set_default_subscription', 20, 1 );


// give the user a notice if they are default subscribed to this group (does not work for invites or requests)
function ass_join_group_message( $group_id, $user_id ) {
	global $bp;
	if ( groups_get_groupmeta( $group_id, 'ass_default_subscription' ) != 'no' && $user_id == $bp->loggedin_user->id )
		bp_core_add_message( __( 'You successfully joined the group. You are subscribed via email to new group content.', 'buddypress' ) );
}
add_action( 'groups_join_group', 'ass_join_group_message', 100, 2 );




// create the default subscription settings during group creation and editing
function ass_default_subscription_settings_form() {
	?>
	<h4><?php _e('Email Subscription Defaults', 'bp-ass'); ?></h4>
	<p><?php _e('When new users join this group, their default email notification settings will be:', 'bp-ass'); ?></p>
	<div class="radio">
		<label><input type="radio" name="ass-default-subscription" value="no" <?php ass_default_subscription_settings( 'no' ) ?> /> 
			<?php _e( 'No Email (users will read this group on the web - good for any group - the default)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="sum" <?php ass_default_subscription_settings( 'sum' ) ?> /> 
			<?php _e( 'Weekly Summary Email (the week\'s topics - good for large groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="dig" <?php ass_default_subscription_settings( 'dig' ) ?> /> 
			<?php _e( 'Daily Digest Email (all daily activity bundles in one email - good for medium-size groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="sub" <?php ass_default_subscription_settings( 'sub' ) ?> /> 
			<?php _e( 'New Topics Email (new topics are sent as they arrive, but not replies - good for small groups)', 'bp-ass' ) ?></label>
		<label><input type="radio" name="ass-default-subscription" value="supersub" <?php ass_default_subscription_settings( 'supersub' ) ?> /> 
			<?php _e( 'Email (send emails about everything - recommended only for working groups)', 'bp-ass' ) ?></label>
	</div>
	<hr />
	<?php
}
add_action ( 'bp_after_group_settings_admin' ,'ass_default_subscription_settings_form' );
add_action ( 'bp_after_group_settings_creation_step' ,'ass_default_subscription_settings_form' );

// echo subscription default checked setting for the group admin settings - default to 'unsubscribed' in group creation
function ass_default_subscription_settings( $setting ) {
	$stored_setting = ass_get_default_subscription();
	
	if ( $setting == $stored_setting )
		echo ' checked="checked"';
	else if ( $setting == 'no' && !$stored_setting )
		echo ' checked="checked"';
}


// Save the announce group setting in the group meta, if normal, delete it
function ass_save_default_subscription( $group ) { 
	global $bp, $_POST;
	
	if ( $postval = $_POST['ass-default-subscription'] ) {
		if ( $postval && $postval != 'no' )
			groups_update_groupmeta( $group->id, 'ass_default_subscription', $postval );
		elseif ( $postval == 'no' )
			groups_delete_groupmeta( $group->id, 'ass_default_subscription' );
	}
}
add_action( 'groups_group_after_save', 'ass_save_default_subscription' );


// Get the default subscription settings for the group
function ass_get_default_subscription( $group = false ) {
	global $groups_template;
	if ( !$group )
		$group =& $groups_template->group;
	$default_subscription =  groups_get_groupmeta( $group->id, 'ass_default_subscription' );
	return apply_filters( 'ass_get_default_subscription', $default_subscription );
}








//
//	!TOPIC SUBSCRIPTION
//


function ass_get_topic_subscription_status( $user_id, $topic_id ) {	
	global $bp;
	
	if ( !$user_id || !$topic_id )
		return false;
	
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id, 'ass_user_topic_status_' . $topic_id );
		
	if ( $user_topic_status[ $user_id ] ) 
		return ( $user_topic_status[ $user_id ] );
	else
		return false;
}


// Creates "subscribe/unsubscribe" link on forum directory page and each topic page
function ass_topic_follow_or_mute_link() {
	global $bp;  
	
	//echo '<pre>'; print_r( $bp ); echo '</pre>';
	
	if ( !$bp->groups->current_group->is_member )
		return;
	
	$topic_id = bp_get_the_topic_id();
	$topic_status = ass_get_topic_subscription_status( $bp->loggedin_user->id, $topic_id );
	$group_status = ass_get_group_subscription_status( $bp->loggedin_user->id, $bp->groups->current_group->id );
			
	if ( $topic_status == 'mute' || ( $group_status != 'supersub' && !$topic_status ) ) {
		$action = 'follow';
		$link_text = 'Follow';
		$title = 'You are not following this topic. Click to follow it and get email updates for new posts';
	} else if ( $topic_status == 'sub' || ( $group_status == 'supersub' && !$topic_status ) ) {
		$action = 'mute';
		$link_text = 'Mute';
		$title = 'You are following this topic. Click to stop getting email updates';
	} else {
		echo 'nothing'; // do nothing
	}
	
	if ( $topic_status == 'mute' )
		$title = 'This conversation is muted. Click to follow it';
			
	if ( $action && $bp->action_variables[0] == 'topic' ) { // we're viewing one topic
		echo "<div class=\"generic-button ass-topic-subscribe\"><a title=\"{$title}\" 
			id=\"{$action}-{$topic_id}\">{$link_text} this topic</a></div>"; 
	} else if ( $action )  { // we're viewing a list of topics
		echo "<td><div class=\"generic-button ass-topic-subscribe\"><a title=\"{$title}\" 
			id=\"{$action}-{$topic_id}\">{$link_text}</a></div></td>"; 
	}
}
add_action( 'bp_directory_forums_extra_cell', 'ass_topic_follow_or_mute_link', 50 );
add_action( 'bp_before_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );
add_action( 'bp_after_group_forum_topic_posts', 'ass_topic_follow_or_mute_link' );



// Handles AJAX request to follow/mute a topic
function ass_ajax_callback() {
	global $bp;
	//check_ajax_referer( "ass_subscribe" );
	
	$action = $_POST['a'];  // action is used by ajax, so we use a here
	$user_id = $bp->loggedin_user->id;
	$topic_id = $_POST['topic_id'];
		
	ass_topic_subscribe_or_mute( $action, $user_id, $topic_id );
	
	echo $action;
	die();
}
add_action( 'wp_ajax_ass_ajax', 'ass_ajax_callback' );


// Adds/removes a $topic_id from the $user_id's mute list.
function ass_topic_subscribe_or_mute( $action, $user_id, $topic_id ) {
	global $bp;
	
	if ( !$action || !$user_id || !$topic_id )
		return false;
		
	//$mute_list = get_usermeta( $user_id, 'ass_topic_mute' );
	$user_topic_status = groups_get_groupmeta( $bp->groups->current_group->id, 'ass_user_topic_status_' . $topic_id );
	
	if ( $action == 'unsubscribe' ||  $action == 'mute' ) {
		//$mute_list[ $topic_id ] = 'mute';
		$user_topic_status[ $user_id ] = 'mute'; 
	} elseif ( $action == 'subscribe' ||  $action == 'follow'  ) {
		//$mute_list[ $topic_id ] = 'subscribe';
		$user_topic_status[ $user_id ] = 'sub'; 
	}
	
	//update_usermeta( $user_id, 'ass_topic_mute', $mute_list );
	groups_update_groupmeta( $bp->groups->current_group->id , 'ass_user_topic_status_' . $topic_id, $user_topic_status );
	//bb_update_topicmeta( $topic_id, 'ass_mute_users', $user_id );
}





//
//	!SUPPORT FUNCTIONS
//


// return array of previous posters' ids
function ass_get_previous_posters( $topic_id ) {
	do_action( 'bbpress_init' );
	global $bbdb, $wpdb;

	$posters = $bbdb->get_results( "SELECT poster_id FROM $bbdb->posts WHERE topic_id = {$topic_id}" );
	
	foreach( $posters as $poster ) {
		$user_ids[ $poster->poster_id ] = true;
	}
	
	return $user_ids;
}

// return array of users who match a usermeta value
function ass_user_settings_array( $setting ) {
	global $wpdb;
	$results = $wpdb->get_results( "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key LIKE '{$setting}'" );
	
	foreach ( $results as $result ) {
		$settings[ $result->user_id ] = $result->meta_value;
	}
	
	return $settings;
}


// cleans up the subject for email, strips trailing colon if exists
function ass_clean_subject( $subject ) {
	$subject = strip_tags( $subject );
	
	if ( substr( $subject, -1 ) == ':' )
		$subject = substr( $subject, 0, -1 );
		
	return $subject;
}


// Check how long the user has been registered and return false if not long enough. Return true if setting not active off ( ie. 'n/a')
function ass_registered_long_enough( $activity_user_id ) {
	$ass_reg_age_setting = get_site_option( 'ass_activity_frequency_ass_registered_req' );
	
	if ( is_numeric( $ass_reg_age_setting ) ) {
		$current_user_info = get_userdata( $activity_user_id );
	
		if ( strtotime(current_time("mysql", 0)) - strtotime($current_user_info->user_registered) < ( $ass_reg_age_setting*24*60*60 ) )
			return false;
			
	}
	
	return true;
}


// show group subscription status on group member pages (for admins and mods only)
function ass_show_subscription_status_in_member_list() {
	global $bp, $members_template;
	
	$group_id = $bp->groups->current_group->id;
	
	if ( !groups_is_user_admin( $bp->loggedin_user->id , $group_id ) && !groups_is_user_mod( $bp->loggedin_user->id , $group_id ) )
		return;
		
	if ( $sub_type = ass_get_group_subscription_status( $members_template->member->user_id, $group_id ) ) {
		echo '<div class="ass_member_list_sub_status">'. ucfirst($sub_type) .'scribed</div>';
	}
}
add_action( 'bp_group_members_list_item_action', 'ass_show_subscription_status_in_member_list', 100 );







//
//	!FRONT END ADMIN AND SETTINGS FUNCTIONS
//


// creata a form that allows admins to email everyone in the group
function ass_admin_notice_form() {	
	global $bp;	

	if ( groups_is_user_admin( $bp->loggedin_user->id , $bp->groups->current_group->id ) || is_site_admin() ) {
		$submit_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/notifications';
		?> 
		<h3><?php _e('Send an email notice to everyone in the group', 'bp-ass'); ?></h3>
		<p><?php _e('You can use the form below to send an email notice to all group members.', 'bp-ass'); ?> <br>
		<b><?php _e('Everyone in the group will receive the email -- regardless of their email settings -- so use with caution', 'bp-ass'); ?></b>.</p>
		<form action="<?php echo $submit_link ?>" method="post">
		<?php wp_nonce_field( 'ass_admin_notice' ); ?>
		<input type="hidden" name="ass_group_id" value="<?php echo $bp->groups->current_group->id; ?>"/>
		Email Subject:<br>
		<input type="text" name="ass_admin_notice_subject" value=""/><br><br>
		Email Content:<br>
		<textarea value="" name="ass_admin_notice" id="ass-admin-notice-textarea"></textarea><br>
		<input type="submit" name="ass_admin_notice_send" value="Email this notice to everyone in the group" />
		</form> 
		<?php
	}
}


// This function sends an email out to all group members regardless of subscription status. 
function ass_admin_notice() {
    global $bp;

    if ( $bp->current_component == 'groups' && $bp->current_action == 'admin' && $bp->action_variables[0] == 'notifications' ) {
    
	    // Make sure the user is an admin
		if ( !groups_is_user_admin( $bp->loggedin_user->id , $group_id ) && !is_site_admin() )
			return;
		
		// make sure the correct form variables are here
		if ( isset( $_POST[ 'ass_admin_notice_send' ] ) && isset( $_POST[ 'ass_admin_notice' ] ) ) {
			//echo '<pre>'; print_r( $_POST ); echo '</pre>';
			$group_id = $_POST[ 'ass_group_id' ];
			$group_name = $bp->groups->current_group->name;
			$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/';
			$subject = $_POST[ 'ass_admin_notice_subject' ];
			$subject .= __(' - sent from the group ', 'bp-ass') . $group_name .' [' . get_blog_option( BP_ROOT_BLOG, 'blogname' ) . ']';
			$message = sprintf( __(
'This is a notice from the group \'%s\':

"%s"


To view this group log in and follow the link below:
%s

---------------------
', 'bp-ass' ), $group_name,  $_POST[ 'ass_admin_notice' ], $group_link );
			
			$message .= __( 'Please note: admin notices are sent to everyone in the group and cannot be disabled. 
If you feel this service is being misused please speak to the website administrator.', 'bp-ass' );
			
			$user_ids = BP_Groups_Member::get_group_member_ids( $group_id );
			
			// cycle through all group members
			foreach ( (array)$user_ids as $user_id ) { 		
				$user = bp_core_get_core_userdata( $user_id ); // Get the details for the user
				wp_mail( $user->user_email, $subject, $message );  // Send the email
				//echo '<br>Email: ' . $user->user_email;
			}
			
			bp_core_add_message( __( 'The email notice was sent successfully.', 'bp-ass' ) );
			//echo '<p>Subject: ' . $subject;
			//echo '<pre>'; print_r( $message ); echo '</pre>';
		}
	}
}
add_action('wp', 'ass_admin_notice');  





// adds forum notification options in the users settings->notifications page 
function ass_group_subscription_notification_settings() {
	global $current_user; ?>
	<table class="notification-settings" id="groups-notification-settings">
		<tr>
			<th class="icon"></th>
			<th class="title"><?php _e( 'Group Forum', 'buddypress' ) ?></th>
			<th class="yes"><?php _e( 'Yes', 'buddypress' ) ?></th>
			<th class="no"><?php _e( 'No', 'buddypress' )?></th>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'A member replies in a forum topic you\'ve started', 'buddypress' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'ass_replies_to_my_topic') || 'yes' == get_usermeta( $current_user->id, 'ass_replies_to_my_topic') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_to_my_topic]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'ass_replies_to_my_topic') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>
		<tr>
			<td></td>
			<td><?php _e( 'A member replies after you in a forum topic', 'buddypress' ) ?></td>
			<td class="yes"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="yes" <?php if ( !get_usermeta( $current_user->id, 'ass_replies_after_me_topic') || 'yes' == get_usermeta( $current_user->id, 'ass_replies_after_me_topic') ) { ?>checked="checked" <?php } ?>/></td>
			<td class="no"><input type="radio" name="notifications[ass_replies_after_me_topic]" value="no" <?php if ( 'no' == get_usermeta( $current_user->id, 'ass_replies_after_me_topic') ) { ?>checked="checked" <?php } ?>/></td>
		</tr>

		<?php do_action( 'ass_group_subscription_notification_settings' ); ?>
	</table>
<?php
}
add_action( 'bp_notification_settings', 'ass_group_subscription_notification_settings' );







//
//	!WP BACKEND ADMIN SETTINGS
//


// Functions to add the backend admin menu to control changing default settings
function ass_admin_menu() {
	add_submenu_page( 'bp-general-settings', "Group Email Options", "Group Email Options", 'manage_options', 'ass_admin_options', "ass_admin_options" );
}
add_action('admin_menu', 'ass_admin_menu');

// function to create the back end admin form
function ass_admin_options() {
	//print_r($_POST); die();
	
	if ( $_POST )
		ass_update_dashboard_settings();
	
	//set the first time defaults
	if ( !$ass_digest_time = get_option( 'ass_digest_time' ) )
		$ass_digest_time = array( 'hours' => '05', 'minutes' => '00' );
	
	if ( !$ass_weekly_digest = get_option( 'ass_weekly_digest' ) )
		$ass_weekly_digest = 5; // friday
		
	$next = date( "r", wp_next_scheduled( 'ass_digest_event' ) );
	?>
	<div class="wrap">
		<h2>Group Email Subscription Settings</h2>

		<form id="ass-admin-settings-form" method="post" action="admin.php?page=ass_admin_options">
		<?php wp_nonce_field( 'ass_admin_settings' ); ?>
		
		<h3><?php _e('General', 'bp-ass'); ?></h3>
			<p><?php _e('To help protect against spam, you may wish to require a user to have been a member of the site for a certain amount of days before any group updates are emailed to the other group members. This is disabled by default.', 'bp-ass'); ?> </p>
			<?php _e('Member must be registered for', 'bp-ass'); ?><input type="text" size="1" name="ass_registered_req" value="<?php echo get_option( 'ass_registered_req' ); ?>" style="text-align:center"/><?php _e('days', 'bp-ass'); ?>
		
		<h3><?php _e( 'Digests & Summaries', 'bp-ass' ) ?></h3>
		<p>
			<label for="ass_digest_time"><?php _e( '<strong>Daily Digests</strong> should be sent at this time:', 'bp-ass' ) ?> </label>
			<select name="ass_digest_time[hours]" id="ass_digest_time[hours]">
				<?php for( $i = 0; $i <= 23; $i++ ) : ?>
					<?php if ( $i < 10 ) $i = '0' . $i ?>
					<option value="<?php echo $i?>" <?php if ( $i == $ass_digest_time['hours'] ) : ?>selected="selected"<?php endif; ?>><?php echo $i ?></option>
				<?php endfor; ?>	
			</select>
			
			<select name="ass_digest_time[minutes]" id="ass_digest_time[minutes]">
				<?php for( $i = 0; $i <= 55; $i += 5 ) : ?>
					<?php if ( $i < 10 ) $i = '0' . $i ?>
					<option value="<?php echo $i?>" <?php if ( $i == $ass_digest_time['minutes'] ) : ?>selected="selected"<?php endif; ?>><?php echo $i ?></option>
				<?php endfor; ?>	
			</select>
		</p>
		
		<p>
			<label for="ass_weekly_digest"><?php _e( '<strong>Weekly Summaries</strong> should be sent on:', 'bp-ass' ) ?> </label>
			<select name="ass_weekly_digest" id="ass_weekly_digest">
				<?php /* disabling "no weekly digest" option for now because it will complicate the individual settings pages */ ?>
				<?php /* <option value="No weekly digest" <?php if ( 'No weekly digest' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'No weekly digest', 'bp-ass' ) ?></option> */ ?>
				<option value="1" <?php if ( '1' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Monday' ) ?></option>
				<option value="2" <?php if ( '2' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Tuesday' ) ?></option>
				<option value="3" <?php if ( '3' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Wednesday' ) ?></option>
				<option value="4" <?php if ( '4' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Thursday' ) ?></option>
				<option value="5" <?php if ( '5' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Friday' ) ?></option>
				<option value="6" <?php if ( '6' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Saturday' ) ?></option>
				<option value="0" <?php if ( '0' == $ass_weekly_digest ) : ?>selected="selected"<?php endif; ?>><?php _e( 'Sunday' ) ?></option>
			</select>	
			<!-- (the summary will be sent one hour after the daily digests) -->			
		</p>
		
		<p><i><?php echo sprintf( __( 'The server timezone is %s (%s); the current server time is %s (%s); and the day is %s.', 'bp-ass' ), date( 'T' ), date( 'e' ), date( 'g:ia' ), date( 'H:i' ), date( 'l' ) ) ?></i></p>
		
			<p class="submit">
				<input type="submit" value="Save Settings" id="bp-admin-ass-submit" name="bp-admin-ass-submit" class="button-primary">
			</p>

		</form>
		
	</div>
	<?php
}


// save the back-end admin settings
function ass_update_dashboard_settings() {
	check_admin_referer( 'ass_admin_settings' );
	
	if ( !is_site_admin() )
		return;

	if ( $_POST['ass_registered_req'] != get_option( 'ass_registered_req' ) )
		update_option( 'ass_registered_req', $_POST['ass_registered_req'] );
	
	/* The daily digest time has been changed */
	if ( $_POST['ass_digest_time'] != get_option( 'ass_digest_time' ) ) {
		
		/* Concatenate the hours-minutes entered, and turn it into a timestamp today */
		$the_time = date( 'Y-m-d' ) . ' ' . $_POST['ass_digest_time']['hours'] . ':' . $_POST['ass_digest_time']['minutes'];
		$the_timestamp = strtotime( $the_time );
		
		/* If the time has already passed today, the next run will be tomorrow */
		$the_timestamp = ( $the_timestamp > time() ) ? $the_timestamp : (int)$the_timestamp + 86400;
		
		/* Clear the old recurring event and set up a new one */
		wp_clear_scheduled_hook( 'ass_digest_event' );
		//wp_schedule_event( $the_timestamp, 'daily', 'ass_digest_event' );	
		// Two minutes for testing
		wp_schedule_event( $the_timestamp, 'twominutes', 'ass_digest_event' );
		
		/* Finally, save the option */
		update_option( 'ass_digest_time', $_POST['ass_digest_time'] );
	}
	
	/* The weekly digest day has been changed */
	if ( $_POST['ass_weekly_digest'] != get_option( 'ass_weekly_digest' ) ) {
		
		if ( !$next_weekly = wp_next_scheduled( 'ass_digest_event_weekly' ) )
			$next_weekly = wp_next_scheduled( 'ass_digest_event' ); 
		
		while ( date( 'w', $next_weekly ) != $_POST['ass_weekly_digest'] ) {
			$next_weekly += 86400;
		}
		
		/* Clear the old recurring event and set up a new one */
		wp_clear_scheduled_hook( 'ass_digest_event_weekly' );	
		wp_schedule_event( $next_weekly, 'weekly', 'ass_digest_event_weekly' );
		
		/* Finally, save the option */
		update_option( 'ass_weekly_digest', $_POST['ass_weekly_digest'] );
	}
//print_r($_POST);
}


function ass_weekly_digest_week() {
	$ass_weekly_digest = get_option( 'ass_weekly_digest' );
	if ( $ass_weekly_digest == 1 )
		return __('Monday' );
	elseif ( $ass_weekly_digest == 2 )
		return __('Tuesday' );
	elseif ( $ass_weekly_digest == 3 )
		return __('Wednesday' );
	elseif ( $ass_weekly_digest == 4 )
		return __('Thursday' );
	elseif ( $ass_weekly_digest == 5 )
		return __('Friday' );
	elseif ( $ass_weekly_digest == 6 )
		return __('Saturday' );
	elseif ( $ass_weekly_digest == 0 )
		return __('Sunday' );
}


?>