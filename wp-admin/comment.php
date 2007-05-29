<?php
require_once('admin.php');

$parent_file = 'edit-comments.php';
$submenu_file = 'edit-comments.php';

wp_reset_vars(array('action'));

if ( isset( $_POST['deletecomment'] ) )
	$action = 'deletecomment';

switch($action) {
case 'editcomment':
	$title = __('Edit Comment');

	require_once ('admin-header.php');

	$comment = (int) $_GET['c'];

	if ( ! $comment = get_comment($comment) )
		wp_die(__('Oops, no comment with this ID.').sprintf(' <a href="%s">'.__('Go back').'</a>!', 'javascript:history.go(-1)'));

	if ( !current_user_can('edit_post', $comment->comment_post_ID) )
		wp_die( __('You are not allowed to edit comments on this post.') );

	$comment = get_comment_to_edit($comment);

	include('edit-form-comment.php');

	break;

case 'cdc':
case 'mac':

	require_once('./admin-header.php');

	$comment = (int) $_GET['c'];
	$formaction = 'cdc' == $action ? 'deletecomment' : 'approvecomment';
	$nonce_action = 'cdc' == $action ? 'delete-comment_' : 'approve-comment_';
	$nonce_action .= $comment;

	if ( ! $comment = get_comment_to_edit($comment) )
		wp_die(__('Oops, no comment with this ID.').sprintf(' <a href="%s">'.__('Go back').'</a>!', 'edit.php'));

	if ( !current_user_can('edit_post', $comment->comment_post_ID) )
		wp_die( 'cdc' == $action ? __('You are not allowed to delete comments on this post.') : __('You are not allowed to edit comments on this post, so you cannot approve this comment.') );
?>
<div class='wrap'>

<div class="narrow">
<?php if ( 'spam' == $_GET['dt'] ) { ?>
<p><?php echo '<strong>'.__('Caution:').'</strong> '.__('You are about to mark the following comment as spam:'); ?></p>
<?php } elseif ( 'cdc' == $action ) { ?>
<p><?php echo '<strong>'.__('Caution:').'</strong> '.__('You are about to delete the following comment:'); ?></p>
<?php } else { ?>
<p><?php echo '<strong>'.__('Caution:').'</strong> '.__('You are about to approve the following comment:'); ?></p>
<?php } ?>

<p><?php _e('Are you sure you want to do that?'); ?></p>

<form action='<?php echo get_option('siteurl'); ?>/wp-admin/comment.php' method='get'>

<table width="100%">
<tr>
<td><input type='button' value='<?php _e('No'); ?>' onclick="self.location='<?php echo get_option('siteurl'); ?>/wp-admin/edit-comments.php';" /></td>
<td align="right"><input type='submit' value='<?php _e('Yes'); ?>' /></td>
</tr>
</table>

<?php wp_nonce_field($nonce_action); ?>
<input type='hidden' name='action' value='<?php echo $formaction; ?>' />
<?php if ( 'spam' == $_GET['dt'] ) { ?>
<input type='hidden' name='dt' value='spam' />
<?php } ?>
<input type='hidden' name='p' value='<?php echo $comment->comment_post_ID; ?>' />
<input type='hidden' name='c' value='<?php echo $comment->comment_ID; ?>' />
<input type='hidden' name='noredir' value='1' />
</form>

<table class="editform" cellpadding="5">
<tr class="alt">
<th scope="row"><?php _e('Author:'); ?></th>
<td><?php echo $comment->comment_author; ?></td>
</tr>
<?php if ( $comment->comment_author_email ) { ?>
<tr>
<th scope="row"><?php _e('E-mail:'); ?></th>
<td><?php echo $comment->comment_author_email; ?></td>
</tr>
<?php } ?>
<?php if ( $comment->comment_author_url ) { ?>
<tr>
<th scope="row"><?php _e('URL:'); ?></th>
<td><?php echo $comment->comment_author_url; ?></td>
</tr>
<?php } ?>
<tr>
<th scope="row" valign="top"><p><?php _e('Comment:'); ?></p></th>
<td><?php echo $comment->comment_content; ?></td>
</tr>
</table>

</div>
</div>
<?php
	break;

case 'deletecomment':
	$comment = (int) $_REQUEST['c'];
	check_admin_referer('delete-comment_' . $comment);

	if ( isset($_REQUEST['noredir']) ) {
		$noredir = true;
	} else {
		$noredir = false;
	}

	if ( ! $comment = get_comment($comment) )
			 wp_die(__('Oops, no comment with this ID.').sprintf(' <a href="%s">'.__('Go back').'</a>!', 'edit-comments.php'));

	if ( !current_user_can('edit_post', $comment->comment_post_ID) )
		wp_die( __('You are not allowed to edit comments on this post.') );

	if ( 'spam' == $_REQUEST['dt'] )
		wp_set_comment_status($comment->comment_ID, 'spam');
	else
		wp_delete_comment($comment->comment_ID);

	if ((wp_get_referer() != '') && (false == $noredir)) {
		wp_redirect(wp_get_referer());
	} else {
		wp_redirect(get_option('siteurl') .'/wp-admin/edit-comments.php');
	}
	exit();
	break;

case 'unapprovecomment':
	$comment = (int) $_GET['c'];
	check_admin_referer('unapprove-comment_' . $comment);

	if (isset($_GET['noredir'])) {
		$noredir = true;
	} else {
		$noredir = false;
	}

	if ( ! $comment = get_comment($comment) )
		wp_die(__('Oops, no comment with this ID.').sprintf(' <a href="%s">'.__('Go back').'</a>!', 'edit.php'));

	if ( !current_user_can('edit_post', $comment->comment_post_ID) )
		wp_die( __('You are not allowed to edit comments on this post, so you cannot disapprove this comment.') );

	wp_set_comment_status($comment->comment_ID, "hold");

	if ((wp_get_referer() != "") && (false == $noredir)) {
		wp_redirect(wp_get_referer());
	} else {
		wp_redirect(get_option('siteurl') .'/wp-admin/edit.php?p='. (int) $comment->comment_post_ID.'&c=1#comments');
	}
	exit();
	break;

case 'approvecomment':
	$comment = (int) $_GET['c'];
	check_admin_referer('approve-comment_' . $comment);

	if (isset($_GET['noredir'])) {
		$noredir = true;
	} else {
		$noredir = false;
	}

	if ( ! $comment = get_comment($comment) )
		wp_die(__('Oops, no comment with this ID.').sprintf(' <a href="%s">'.__('Go back').'</a>!', 'edit.php'));

	if ( !current_user_can('edit_post', $comment->comment_post_ID) )
		wp_die( __('You are not allowed to edit comments on this post, so you cannot approve this comment.') );

	wp_set_comment_status($comment->comment_ID, "approve");
	if (get_option("comments_notify") == true) {
		wp_notify_postauthor($comment->comment_ID);
	}


	if ((wp_get_referer() != "") && (false == $noredir)) {
		wp_redirect(wp_get_referer());
	} else {
		wp_redirect(get_option('siteurl') .'/wp-admin/edit.php?p='. (int) $comment->comment_post_ID.'&c=1#comments');
	}
	exit();
	break;

case 'editedcomment':

	$comment_ID = (int) $_POST['comment_ID'];
	$comment_post_ID = (int) $_POST['comment_post_id'];

	check_admin_referer('update-comment_' . $comment_ID);

	edit_comment();

	$location = ( empty($_POST['referredby']) ? "edit.php?p=$comment_post_ID&c=1" : $_POST['referredby'] ) . '#comment-' . $comment_ID;
	$location = apply_filters('comment_edit_redirect', $location, $comment_ID);
	wp_redirect($location);
	exit();
	break;
default:
	break;
} // end switch

include('admin-footer.php');

?>
