<?php
require_once('admin.php');

$parent_file = 'edit.php';
$submenu_file = 'edit-pages.php';

wp_reset_vars(array('action'));

if (isset($_POST['deletepost'])) {
$action = "delete";
}

switch($action) {
case 'post':
	check_admin_referer('add-page');
	$page_ID = write_post();

	// Redirect.
	if (!empty($_POST['mode'])) {
	switch($_POST['mode']) {
		case 'bookmarklet':
			$location = $_POST['referredby'];
			break;
		case 'sidebar':
			$location = 'sidebar.php?a=b';
			break;
		default:
			$location = 'page-new.php';
			break;
		}
	} else {
		$location = "page-new.php?posted=$page_ID";
	}

	if ( isset($_POST['save']) )
		$location = "page.php?action=edit&post=$page_ID";

	wp_redirect($location);
	exit();
	break;

case 'edit':
	$title = __('Edit');
	$editing = true;
	$page_ID = $post_ID = $p = (int) $_GET['post'];
	$post = get_post_to_edit($page_ID);

	if ( 'post' == $post->post_type ) {
		wp_redirect("post.php?action=edit&post=$post_ID");
		exit();
	}

	if($post->post_status == 'draft') {
		wp_enqueue_script('prototype');
		wp_enqueue_script('autosave');
	}
	require_once('admin-header.php');

	if ( !current_user_can('edit_page', $page_ID) )
		die ( __('You are not allowed to edit this page.') );

	include('edit-page-form.php');
	?>
	<div id='preview' class='wrap'>
	<h2 id="preview-post"><?php _e('Page Preview (updated when page is saved)'); ?></h2>
		<iframe src="<?php echo clean_url(apply_filters('preview_page_link', add_query_arg('preview', 'true', get_permalink($post->ID)))); ?>" width="100%" height="600" ></iframe>
	</div>
	<?php
	break;

case 'editattachment':
	$page_id = $post_ID = (int) $_POST['post_ID'];
	check_admin_referer('update-attachment_' . $page_id);

	// Don't let these be changed
	unset($_POST['guid']);
	$_POST['post_type'] = 'attachment';

	// Update the thumbnail filename
	$newmeta = wp_get_attachment_metadata( $page_id, true );
	$newmeta['thumb'] = $_POST['thumb'];

	wp_update_attachment_metadata( $newmeta );

case 'editpost':
	$page_ID = (int) $_POST['post_ID'];
	check_admin_referer('update-page_' . $page_ID);

	$page_ID = edit_post();

	if ( 'post' == $_POST['originalaction'] ) {
		if (!empty($_POST['mode'])) {
		switch($_POST['mode']) {
			case 'bookmarklet':
				$location = $_POST['referredby'];
				break;
			case 'sidebar':
				$location = 'sidebar.php?a=b';
				break;
			default:
				$location = 'page-new.php';
				break;
			}
		} else {
			$location = "page-new.php?posted=$page_ID";
		}

		if ( isset($_POST['save']) )
			$location = "page.php?action=edit&post=$page_ID";		
	} else {
		if ($_POST['save']) {
			$location = "page.php?action=edit&post=$page_ID";
		} elseif ($_POST['updatemeta']) {
			$location = wp_get_referer() . '&message=2#postcustom';
		} elseif ($_POST['deletemeta']) {
			$location = wp_get_referer() . '&message=3#postcustom';
		} elseif (!empty($_POST['referredby']) && $_POST['referredby'] != wp_get_referer()) {
			$location = $_POST['referredby'];
			if ( $_POST['referredby'] == 'redo' )
				$location = get_permalink( $page_ID );
		} elseif ($action == 'editattachment') {
			$location = 'attachments.php';
		} else {
			$location = 'page-new.php';
		}
	}
	wp_redirect($location); // Send user on their way while we keep working

	exit();
	break;

case 'delete':
	$page_id = (isset($_GET['post']))  ? intval($_GET['post']) : intval($_POST['post_ID']);
	check_admin_referer('delete-page_' .  $page_id);

	$page = & get_post($page_id);

	if ( !current_user_can('delete_page', $page_id) )
		wp_die( __('You are not allowed to delete this page.') );

	if ( $page->post_type == 'attachment' ) {
		if ( ! wp_delete_attachment($page_id) )
			wp_die( __('Error in deleting...') );
	} else {
		if ( !wp_delete_post($page_id) ) 
			wp_die( __('Error in deleting...') );
	}

	$sendback = wp_get_referer();
	if (strstr($sendback, 'page.php')) $sendback = get_option('siteurl') .'/wp-admin/page.php';
	elseif (strstr($sendback, 'attachments.php')) $sendback = get_option('siteurl') .'/wp-admin/attachments.php';
	$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
	wp_redirect($sendback);
	exit();
	break;

default:
	wp_redirect('edit-pages.php');
	exit();
	break;
} // end switch
include('admin-footer.php');
?>
