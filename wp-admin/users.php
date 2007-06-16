<?php
require_once('admin.php');
require_once( ABSPATH . WPINC . '/registration.php');

if ( !current_user_can('edit_users') )
	wp_die(__('Cheatin&#8217; uh?'));

$title = __('Users');
$parent_file = 'users.php';

$action = $_REQUEST['action'];
$update = '';

if ( empty($_POST) ) {
	$referer = '<input type="hidden" name="wp_http_referer" value="'. attribute_escape(stripslashes($_SERVER['REQUEST_URI'])) . '" />';
} elseif ( isset($_POST['wp_http_referer']) ) {
	$redirect = remove_query_arg(array('wp_http_referer', 'updated', 'delete_count'), stripslashes($_POST['wp_http_referer']));
	$referer = '<input type="hidden" name="wp_http_referer" value="' . attribute_escape($redirect) . '" />';
} else {
	$redirect = 'users.php';
}


// WP_User_Search class
// by Mark Jaquith


class WP_User_Search {
	var $results;
	var $search_term;
	var $page;
	var $raw_page;
	var $users_per_page = 50;
	var $first_user;
	var $last_user;
	var $query_limit;
	var $query_from_where;
	var $total_users_for_query = 0;
	var $too_many_total_users = false;
	var $search_errors;

	function WP_User_Search ($search_term = '', $page = '') { // constructor
		$this->search_term = $search_term;
		$this->raw_page = ( '' == $page ) ? false : (int) $page;
		$this->page = (int) ( '' == $page ) ? 1 : $page;

		$this->prepare_query();
		$this->query();
		$this->prepare_vars_for_template_usage();
		$this->do_paging();
	}

	function prepare_query() {
		global $wpdb;
		$this->first_user = ($this->page - 1) * $this->users_per_page;
		$this->query_limit = 'LIMIT ' . $this->first_user . ',' . $this->users_per_page;
		if ( $this->search_term ) {
			$searches = array();
			$search_sql = 'AND (';
			foreach ( array('user_login', 'user_nicename', 'user_email', 'user_url', 'display_name') as $col )
				$searches[] = $col . " LIKE '%$this->search_term%'";
			$search_sql .= implode(' OR ', $searches);
			$search_sql .= ')';
		}
		$this->query_from_where = "FROM $wpdb->users WHERE 1=1 $search_sql";

	}

	function query() {
		global $wpdb;
		$this->results = $wpdb->get_col('SELECT ID ' . $this->query_from_where . $this->query_limit);

		if ( $this->results )
			$this->total_users_for_query = $wpdb->get_var('SELECT COUNT(ID) ' . $this->query_from_where); // no limit
		else
			$this->search_errors = new WP_Error('no_matching_users_found', __('No matching users were found!'));
	}

	function prepare_vars_for_template_usage() {
		$this->search_term = stripslashes($this->search_term); // done with DB, from now on we want slashes gone
	}

	function do_paging() {
		if ( $this->total_users_for_query > $this->users_per_page ) { // have to page the results
			$this->paging_text = paginate_links( array(
				'total' => ceil($this->total_users_for_query / $this->users_per_page),
				'current' => $this->page,
				'prev_text' => __('&laquo; Previous Page'),
				'next_text' => __('Next Page &raquo;'),
				'base' => 'users.php?%_%',
				'format' => 'userspage=%#%',
				'add_args' => array( 'usersearch' => urlencode($this->search_term) )
			) );
		}
	}

	function get_results() {
		return (array) $this->results;
	}

	function page_links() {
		echo $this->paging_text;
	}

	function results_are_paged() {
		if ( $this->paging_text )
			return true;
		return false;
	}

	function is_search() {
		if ( $this->search_term )
			return true;
		return false;
	}
}


switch ($action) {

case 'promote':
	check_admin_referer('bulk-users');

	if (empty($_POST['users'])) {
		wp_redirect($redirect);
		exit();
	}

	if ( !current_user_can('edit_users') )
		wp_die(__('You can&#8217;t edit users.'));

	$userids = $_POST['users'];
	$update = 'promote';
	foreach($userids as $id) {
		if ( ! current_user_can('edit_user', $id) )
			wp_die(__('You can&#8217;t edit that user.'));
		// The new role of the current user must also have edit_users caps
		if($id == $current_user->ID && !$wp_roles->role_objects[$_POST['new_role']]->has_cap('edit_users')) {
			$update = 'err_admin_role';
			continue;
		}

		$user = new WP_User($id);
		$user->set_role($_POST['new_role']);
	}

	wp_redirect(add_query_arg('update', $update, $redirect));
	exit();

break;

case 'dodelete':

	check_admin_referer('delete-users');

	if ( empty($_POST['users']) ) {
		wp_redirect($redirect);
		exit();
	}

	if ( !current_user_can('delete_users') )
		wp_die(__('You can&#8217;t delete users.'));

	$userids = $_POST['users'];
	$update = 'del';
	$delete_count = 0;

	foreach ( (array) $userids as $id) {
		if ( ! current_user_can('delete_user', $id) )
			wp_die(__('You can&#8217;t delete that user.'));

		if($id == $current_user->ID) {
			$update = 'err_admin_del';
			continue;
		}
		switch($_POST['delete_option']) {
		case 'delete':
			wp_delete_user($id);
			break;
		case 'reassign':
			wp_delete_user($id, $_POST['reassign_user']);
			break;
		}
		++$delete_count;
	}

	$redirect = add_query_arg( array('delete_count' => $delete_count, 'update' => $update), $redirect);
	wp_redirect($redirect);
	exit();

break;

case 'delete':

	check_admin_referer('bulk-users');

	if ( empty($_POST['users']) ) {
		wp_redirect($redirect);
		exit();
	}

	if ( !current_user_can('delete_users') )
		$errors = new WP_Error('edit_users', __('You can&#8217;t delete users.'));

	$userids = $_POST['users'];

	include ('admin-header.php');
?>
<form action="" method="post" name="updateusers" id="updateusers">
<?php wp_nonce_field('delete-users') ?>
<?php echo $referer; ?>
<div class="wrap">
<h2><?php _e('Delete Users'); ?></h2>
<p><?php _e('You have specified these users for deletion:'); ?></p>
<ul>
<?php
	$go_delete = false;
	foreach ( (array) $userids as $id ) {
		$user = new WP_User($id);
		if ( $id == $current_user->ID ) {
			echo "<li>" . sprintf(__('ID #%1s: %2s <strong>The current user will not be deleted.</strong>'), $id, $user->user_login) . "</li>\n";
		} else {
			echo "<li><input type=\"hidden\" name=\"users[]\" value=\"{$id}\" />" . sprintf(__('ID #%1s: %2s'), $id, $user->user_login) . "</li>\n";
			$go_delete = true;
		}
	}
	$all_logins = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users ORDER BY user_login");
	$user_dropdown = '<select name="reassign_user">';
	foreach ( (array) $all_logins as $login )
		if ( $login->ID == $current_user->ID || !in_array($login->ID, $userids) )
			$user_dropdown .= "<option value=\"{$login->ID}\">{$login->user_login}</option>";
	$user_dropdown .= '</select>';
	?>
	</ul>
<?php if ( $go_delete ) : ?>
	<p><?php _e('What should be done with posts and links owned by this user?'); ?></p>
	<ul style="list-style:none;">
		<li><label><input type="radio" id="delete_option0" name="delete_option" value="delete" checked="checked" />
		<?php _e('Delete all posts and links.'); ?></label></li>
		<li><input type="radio" id="delete_option1" name="delete_option" value="reassign" />
		<?php echo '<label for="delete_option1">'.__('Attribute all posts and links to:')."</label> $user_dropdown"; ?></li>
	</ul>
	<input type="hidden" name="action" value="dodelete" />
	<p class="submit"><input type="submit" name="submit" value="<?php _e('Confirm Deletion'); ?>" /></p>
<?php else : ?>
	<p><?php _e('There are no valid users selected for deletion.'); ?></p>
<?php endif; ?>
</div>
</form>
<?php

break;

case 'adduser':
	check_admin_referer('add-user');

	if ( ! current_user_can('create_users') )
		wp_die(__('You can&#8217;t create users.'));

	$user_id = add_user();
	$update = 'add';
	if ( is_wp_error( $user_id ) )
		$add_user_errors = $user_id;
	else {
		$new_user_login = apply_filters('pre_user_login', sanitize_user(stripslashes($_POST['user_login']), true));
		$redirect = add_query_arg( array('usersearch' => urlencode($new_user_login), 'update' => $update), $redirect );
		wp_redirect( $redirect . '#user-' . $user_id );
		die();
	}

default:
	wp_enqueue_script('admin-users');

	include('admin-header.php');

	// Query the users
	$wp_user_search = new WP_User_Search($_GET['usersearch'], $_GET['userspage']);

	// Make the user objects
	foreach ( $wp_user_search->get_results() as $userid ) {
		$tmp_user = new WP_User($userid);
		$roles = $tmp_user->roles;
		$role = array_shift($roles);
		$roleclasses[$role][$tmp_user->user_login] = $tmp_user;
	}

	if ( isset($_GET['update']) ) :
		switch($_GET['update']) {
		case 'del':
		case 'del_many':
		?>
			<?php $delete_count = (int) $_GET['delete_count']; ?>
			<div id="message" class="updated fade"><p><?php printf(__ngettext('%s user deleted', '%s users deleted', $delete_count), $delete_count); ?></p></div>
		<?php
			break;
		case 'add':
		?>
			<div id="message" class="updated fade"><p><?php _e('New user created.'); ?></p></div>
		<?php
			break;
		case 'promote':
		?>
			<div id="message" class="updated fade"><p><?php _e('Changed roles.'); ?></p></div>
		<?php
			break;
		case 'err_admin_role':
		?>
			<div id="message" class="error"><p><?php _e("The current user's role must have user editing capabilities."); ?></p></div>
			<div id="message" class="updated fade"><p><?php _e('Other user roles have been changed.'); ?></p></div>
		<?php
			break;
		case 'err_admin_del':
		?>
			<div id="message" class="error"><p><?php _e("You can't delete the current user."); ?></p></div>
			<div id="message" class="updated fade"><p><?php _e('Other users have been deleted.'); ?></p></div>
		<?php
			break;
		}
	endif; ?>

<?php if ( is_wp_error( $errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
			foreach ( $errors->get_error_messages() as $message )
				echo "<li>$message</li>";
		?>
		</ul>
	</div>
<?php endif; ?>

<div class="wrap">

	<?php if ( $wp_user_search->is_search() ) : ?>
		<h2><?php printf(__('Users Matching "%s" by Role'), wp_specialchars($wp_user_search->search_term)); ?></h2>
	<?php else : ?>
		<h2><?php _e('User List by Role'); ?></h2>
	<?php endif; ?>

	<form action="" method="get" name="search" id="search">
		<p><input type="text" name="usersearch" id="usersearch" value="<?php echo attribute_escape($wp_user_search->search_term); ?>" /> <input type="submit" value="<?php _e('Search Users &raquo;'); ?>" class="button" /></p>
	</form>

	<?php if ( is_wp_error( $wp_user_search->search_errors ) ) : ?>
		<div class="error">
			<ul>
			<?php
				foreach ( $wp_user_search->search_errors->get_error_messages() as $message )
					echo "<li>$message</li>";
			?>
			</ul>
		</div>
	<?php endif; ?>


<?php if ( $wp_user_search->get_results() ) : ?>

	<?php if ( $wp_user_search->is_search() ) : ?>
		<p><a href="users.php"><?php _e('&laquo; Back to All Users'); ?></a></p>
	<?php endif; ?>

	<h3><?php 
	if ( 0 == $wp_user_search->first_user && $wp_user_search->total_users_for_query <= 50 )
		printf(__('%3$s shown below'), $wp_user_search->first_user + 1, min($wp_user_search->first_user + $wp_user_search->users_per_page, $wp_user_search->total_users_for_query), $wp_user_search->total_users_for_query);
	else
		printf(__('%1$s &#8211; %2$s of %3$s shown below'), $wp_user_search->first_user + 1, min($wp_user_search->first_user + $wp_user_search->users_per_page, $wp_user_search->total_users_for_query), $wp_user_search->total_users_for_query); ?></h3>

	<?php if ( $wp_user_search->results_are_paged() ) : ?>
		<div class="user-paging-text"><p><?php $wp_user_search->page_links(); ?></p></div>
	<?php endif; ?>

<form action="" method="post" name="updateusers" id="updateusers">
<?php wp_nonce_field('bulk-users') ?>
<table class="widefat">
<?php
foreach($roleclasses as $role => $roleclass) {
	uksort($roleclass, "strnatcasecmp");
?>
<tbody>
<tr>
<?php if ( !empty($role) ) : ?>
	<th colspan="7"><h3><?php echo $wp_roles->role_names[$role]; ?></h3></th>
<?php else : ?>
	<th colspan="7"><h3><em><?php _e('No role for this blog'); ?></em></h3></th>
<?php endif; ?>
</tr>
<tr class="thead">
	<th><?php _e('ID') ?></th>
	<th><?php _e('Username') ?></th>
	<th><?php _e('Name') ?></th>
	<th><?php _e('E-mail') ?></th>
	<th><?php _e('Website') ?></th>
	<th colspan="2" style="text-align: center"><?php _e('Actions') ?></th>
</tr>
</tbody>
<tbody id="role-<?php echo $role; ?>"><?php
$style = '';
foreach ( (array) $roleclass as $user_object ) {
	$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
	echo "\n\t" . user_row($user_object, $style);
}
?>

</tbody>
<?php } ?>
</table>

<?php if ( $wp_user_search->results_are_paged() ) : ?>
	<div class="user-paging-text"><p><?php $wp_user_search->page_links(); ?></p></div>
<?php endif; ?>

	<h3><?php _e('Update Selected'); ?></h3>
	<ul style="list-style:none;">
		<li><input type="radio" name="action" id="action0" value="delete" /> <label for="action0"><?php _e('Delete checked users.'); ?></label></li>
		<li>
			<input type="radio" name="action" id="action1" value="promote" /> <label for="action1"><?php _e('Set the Role of checked users to:'); ?></label>
			<select name="new_role" onchange="getElementById('action1').checked = 'true'"><?php wp_dropdown_roles(); ?></select>
		</li>
	</ul>
	<p class="submit" style="width: 420px">
		<?php echo $referer; ?>
		<input type="submit" value="<?php _e('Bulk Update &raquo;'); ?>" />
	</p>
</form>
<?php endif; ?>
</div>

<?php
	if ( is_wp_error($add_user_errors) ) {
		foreach ( array('user_login' => 'user_login', 'first_name' => 'user_firstname', 'last_name' => 'user_lastname', 'email' => 'user_email', 'url' => 'user_uri', 'role' => 'user_role') as $formpost => $var ) {
			$var = 'new_' . $var;
			$$var = attribute_escape(stripslashes($_POST[$formpost]));
		}
		unset($name);
	}
?>

<div class="wrap">
<h2 id="add-new-user"><?php _e('Add New User') ?></h2>

<?php if ( is_wp_error( $add_user_errors ) ) : ?>
	<div class="error">
		<?php
			foreach ( $add_user_errors->get_error_messages() as $message )
				echo "<p>$message</p>";
		?>
	</div>
<?php endif; ?>
<div id="ajax-response"></div>

<div class="narrow">

<?php 
	if ( get_option('users_can_register') ) 
		echo '<p>' . sprintf(__('Users can <a href="%1$s">register themselves</a> or you can manually create users here.'), get_option('siteurl').'/wp-register.php') . '</p>'; 
	else 
        echo '<p>' . sprintf(__('Users cannot currently <a href="%1$s">register themselves</a>, but you can manually create users here.'), get_option('siteurl').'/wp-admin/options-general.php#users_can_register') . '</p>'; 
?>
<form action="#add-new-user" method="post" name="adduser" id="adduser">
<?php wp_nonce_field('add-user') ?>
<table class="editform" width="100%" cellspacing="2" cellpadding="5">
	<tr>
		<th scope="row" width="33%"><?php _e('Username (required)') ?><input name="action" type="hidden" id="action" value="adduser" /></th>
		<td width="66%"><input name="user_login" type="text" id="user_login" value="<?php echo $new_user_login; ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><?php _e('First Name') ?> </th>
		<td><input name="first_name" type="text" id="first_name" value="<?php echo $new_user_firstname; ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><?php _e('Last Name') ?> </th>
		<td><input name="last_name" type="text" id="last_name" value="<?php echo $new_user_lastname; ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><?php _e('E-mail (required)') ?></th>
		<td><input name="email" type="text" id="email" value="<?php echo $new_user_email; ?>" /></td>
	</tr>
	<tr>
		<th scope="row"><?php _e('Website') ?></th>
		<td><input name="url" type="text" id="url" value="<?php echo $new_user_uri; ?>" /></td>
	</tr>

<?php if ( apply_filters('show_password_fields', true) ) : ?>
	<tr>
		<th scope="row"><?php _e('Password (twice)') ?> </th>
		<td><input name="pass1" type="password" id="pass1" />
		<br />
		<input name="pass2" type="password" id="pass2" /></td>
	</tr>
<?php endif; ?>

	<tr>
		<th scope="row"><?php _e('Role'); ?></th>
		<td><select name="role" id="role">
			<?php
			if ( !$new_user_role )
				$new_user_role = get_option('default_role');
			wp_dropdown_roles($new_user_role);
			?>
			</select>
		</td>
	</tr>
</table>
<p class="submit">
	<?php echo $referer; ?>
	<input name="adduser" type="submit" id="addusersub" value="<?php _e('Add User &raquo;') ?>" />
</p>
</form>

</div>
</div>

<?php
break;

} // end of the $action switch

include('admin-footer.php');
?>
