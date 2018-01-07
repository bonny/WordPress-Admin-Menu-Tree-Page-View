<?php
/*
Plugin Name: Admin Menu Tree Page View
Plugin URI: http://eskapism.se/code-playground/admin-menu-tree-page-view/
Description: Get a tree view of all your pages directly in the admin menu. Search, edit, view and add pages - all with just one click away!
Version: 2.7
Author: Pär Thernström
Author URI: http://eskapism.se/
License: GPL2
*/

/*  Copyright 2010  Pär Thernström (email: par.thernstrom@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// No need to run all code if we're not on an admin page
if (!is_admin()) {
	return;
}

add_action("admin_head", "admin_menu_tree_page_view_admin_head");
add_action('admin_menu', 'admin_menu_tree_page_view_admin_menu');
add_action("admin_init", "admin_menu_tree_page_view_admin_init");
add_action('wp_ajax_admin_menu_tree_page_view_add_page', 'admin_menu_tree_page_view_add_page');
add_action('wp_ajax_admin_menu_tree_page_view_move_page', 'admin_menu_tree_page_view_move_page');

function admin_menu_tree_page_view_admin_init() {

	define( "admin_menu_tree_page_view_VERSION", "2.7" );
	define( "admin_menu_tree_page_view_URL", WP_PLUGIN_URL . '/admin-menu-tree-page-view/' );
	define( "admin_menu_tree_page_view_DIR", WP_PLUGIN_DIR . '/admin-menu-tree-page-view/' );

	// Find the plugin directory URL
	$aa = __FILE__;
	if ( isset( $mu_plugin ) ) {
		$aa = $mu_plugin;
	}
	if ( isset( $network_plugin ) ) {
		$aa = $network_plugin;
	}
	if ( isset( $plugin ) ) {
		$aa = $plugin;
	}
	$plugin_dir_url = plugin_dir_url(basename($aa)) . 'admin-menu-tree-page-view/';

	define( "ADMIN_MENU_TREE_PAGE_VIEW_URL", $plugin_dir_url);

	wp_enqueue_style("admin_menu_tree_page_view_styles", ADMIN_MENU_TREE_PAGE_VIEW_URL . "css/styles.css", false, admin_menu_tree_page_view_VERSION);
	wp_enqueue_script("jquery.highlight", ADMIN_MENU_TREE_PAGE_VIEW_URL . "js/jquery.highlight.js", array("jquery"));
	wp_enqueue_script("jquery-cookie", ADMIN_MENU_TREE_PAGE_VIEW_URL . "js/jquery.biscuit.js", array("jquery")); // renamed from cookie to fix problems with mod_security
	wp_enqueue_script("jquery.ui.nestedSortable", ADMIN_MENU_TREE_PAGE_VIEW_URL . "js/jquery.ui.nestedSortable.js", array("jquery", "jquery-ui-sortable"));
	wp_enqueue_script("jquery.client", ADMIN_MENU_TREE_PAGE_VIEW_URL . "js/jquery.client.js", array("jquery"));
	wp_enqueue_script("jquery-ui-sortable");
	wp_enqueue_script("admin_menu_tree_page_view", ADMIN_MENU_TREE_PAGE_VIEW_URL . "js/scripts.js", array("jquery"));

	// The way CMS TPV does it:
	// wp_enqueue_script( "jquery-cookie", CMS_TPV_URL . "scripts/jquery.biscuit.js", array("jquery"));

	$oLocale = array(
		"Edit" => __("Edit", 'admin-menu-tree-page-view'),
		"View" => __("View", 'admin-menu-tree-page-view'),
		"Add_new_page_here" => __("Add new page after", 'admin-menu-tree-page-view'),
		"Add_new_page_inside" => __("Add new page inside", 'admin-menu-tree-page-view'),
		"Untitled" => __("Untitled", 'admin-menu-tree-page-view'),
		'nonce' => wp_create_nonce('admin-menu-tree-page-view')
	);

	wp_localize_script( "admin_menu_tree_page_view", 'amtpv_l10n', $oLocale);
}

function admin_menu_tree_page_view_admin_head() {

}

/**
 * I know, I know. Should have made a class from the beginning...
 */
class admin_menu_tree_page_view {

	public static $arr_all_pages_id_parent;
	public static $one_page_parents;

	static function get_all_pages_id_parent() {
		if (!isset(admin_menu_tree_page_view::$arr_all_pages_id_parent)) {
			// get all pages, once, to spare some queries looking for children
			$all_pages = get_posts(array(
				"numberposts" 	=> -1,
				"post_type"		=> "page",
				"post_status" 	=> "any",
				"fields"		=> "id=>parent"
			));
			//print_r($all_pages);exit;
			admin_menu_tree_page_view::$arr_all_pages_id_parent = $all_pages;
		}
		return admin_menu_tree_page_view::$arr_all_pages_id_parent;
	}

	static function get_post_ancestors($post_to_check_parents_for) {

		if ( ! isset(admin_menu_tree_page_view::$one_page_parents) ) {

			wp_cache_delete($post_to_check_parents_for, 'posts');
			$one_page_parents = get_post_ancestors($post_to_check_parents_for);
			admin_menu_tree_page_view::$one_page_parents = $one_page_parents;

		}

		return admin_menu_tree_page_view::$one_page_parents;

	}

}

function admin_menu_tree_page_view_get_pages($args) {

	$defaults = array(
    	"post_type" => "page",
		"parent" => "0",
		"post_parent" => "0",
		"numberposts" => "-1",
		"orderby" => "menu_order",
		"order" => "ASC",
		"post_status" => "any",
		"suppress_filters" => 0 // suppose to fix problems with WPML
	);
	$args = wp_parse_args( $args, $defaults );

	// contains all page ids as keys and their parent as the val
	$arr_all_pages_id_parent = admin_menu_tree_page_view::get_all_pages_id_parent();

	$pages = get_posts($args);
	$output = "";
	$str_child_output = "";
	foreach ($pages as $one_page) {
		$edit_link = get_edit_post_link($one_page->ID);
		$title = get_the_title($one_page->ID);
		$title = esc_html($title);

		// add num of children to the title
		// @done: this is still being done for each page, even if it does not have children. can we check if it has before?
		// we could fetch all pages once and store them in an array and then just check if the array has our id in it. yeah. let's do that.
		// if our page id exists in $arr_all_pages_id_parent and has a value
		// so result is from 690 queries > 474 = 216 queries less. still many..
		// from 474 to 259 = 215 less
		// so total from 690 to 259 = 431 queries less! grrroooovy
		if (in_array($one_page->ID, $arr_all_pages_id_parent)) {
			$post_children = get_children(array(
				"post_parent" => $one_page->ID,
				"post_type" => "page"
			));
			$post_children_count = sizeof($post_children);
			$title .= " <span class='child-count'>($post_children_count)</span>";
		} else {
			$post_children_count = 0;
		}

		$class = "";
		if (isset($_GET["action"]) && $_GET["action"] == "edit" && isset($_GET["post"]) && $_GET["post"] == $one_page->ID) {
			$class = "current";
		}
		$status_span = "";
		if ($one_page->post_password) {
			$status_span .= "<span class='admin-menu-tree-page-view-protected'></span>";
		}
		if ($one_page->post_status != "publish") {
			$status_span .= "<span class='admin-menu-tree-page-view-status admin-menu-tree-page-view-status-{$one_page->post_status}'>".__(ucfirst($one_page->post_status))."</span>";
		}

		// add css if we have childs
		$args_childs = $args;
		$args_childs["parent"] = $one_page->ID;
		$args_childs["post_parent"] = $one_page->ID;
		$args_childs["child_of"] = $one_page->ID;

		// can we run this only if the page actually has children? is there a property in the result of get_children for this?
		// eh, you moron, we already got that info in $post_children_count!
		// so result is from 690 queries > 474 = 216 queries less. still many..
		$str_child_output = "";
		if ($post_children_count>0) {
			$str_child_output = admin_menu_tree_page_view_get_pages($args_childs);
			$class .= " admin-menu-tree-page-view-has-childs";
		}

		// determine if ul should be opened or closed
		$isOpened = FALSE;

		// check cookie first
		$cookie_opened = isset($_COOKIE["admin-menu-tree-page-view-open-posts"]) ? $_COOKIE["admin-menu-tree-page-view-open-posts"] : ""; // 2,95,n
		$cookie_opened = explode(",", $cookie_opened);

		// if we are editing a post, we should see it in the tree, right?
		// don't use on bulk edit, then post is an array and not a single post id
		if ( isset($_GET["action"]) && "edit" == $_GET["action"] && isset($_GET["post"]) && is_integer($_GET["post"]) ) {

			// if post with id get[post] is a parent of the current post, show it
			if ( $_GET["post"] != $one_page->ID ) {

				$post_to_check_parents_for = $_GET["post"];

				// seems to be a problem with get_post_ancestors (yes, it's in the trac too)
				// Long time since I wrote this, but perhaps this is the problem (adding for future reference):
				// http://core.trac.wordpress.org/ticket/10381

				// @done: this is done several times. only do it once please
				// before: 441. after: 43
				$one_page_parents = admin_menu_tree_page_view::get_post_ancestors($post_to_check_parents_for);
				if (in_array($one_page->ID, $one_page_parents)) {
					$isOpened = TRUE;
				}

			}

		}

		if (in_array($one_page->ID, $cookie_opened) || $isOpened && $post_children_count>0) {
			$class .= " admin-menu-tree-page-view-opened";
		} elseif ($post_children_count>0) {
			$class .= " admin-menu-tree-page-view-closed";
		}

		$class .= " nestedSortable";

		$output .= "<li class='$class'>";
		// first div used for nestedSortable
		$output .= "<div>";
		// div used to make hover work and to put edit-popup outside the <a>
		$output .= "<div class='amtpv-linkwrap' data-post-id='".$one_page->ID."'>";
		$output .= "<a href='$edit_link' data-post-id='".$one_page->ID."'>$status_span";
		$output .= $title;

		// add the view link, hidden, used in popup
		$permalink = get_permalink($one_page->ID);
		// $output .= "<span class='admin-menu-tree-page-view-view-link'>$permalink</span>";
		// $output .= "<span class='admin-menu-tree-page-view-edit'></span>";

		// drag handle
		$output .= "<span class='amtpv-draghandle'></span>";

		$output .= "</a>";


		// popup edit div
		$output .= "
			<div class='amtpv-editpopup'>
				<div class='amtpv-editpopup-editview'>
					<div class='amtpv-editpopup-edit' data-link='".$edit_link."'>".__("Edit", 'admin-menu-tree-page-view')."</div>
					 |
					<div class='amtpv-editpopup-view' data-link='".$permalink."'>".__("View", 'admin-menu-tree-page-view')."</div>
				</div>
				<div class='amtpv-editpopup-add'>".__("Add new page", 'admin-menu-tree-page-view')."<br />
					<div class='amtpv-editpopup-add-after'>".__("After", 'admin-menu-tree-page-view')."</div>
					 |
					<div class='amtpv-editpopup-add-inside'>".__("Inside", 'admin-menu-tree-page-view')."</div>
				</div>
				<div class='amtpv-editpopup-postid'>".__("Post ID:", 'admin-menu-tree-page-view')." " . $one_page->ID."</div>
			</div>
		";

		// close div used to make hover work and to put edit-popup outside the <a>
		$output .= "</div>";

		// close div for nestedSortable
		$output .= "</div>";

		// add child articles
		$output .= $str_child_output;

		$output .= "</li>";
	}

	// if this is a child listing, add ul
	if (isset($args["child_of"]) && $args["child_of"] && $output != "") {
		$output = "<ul class='admin-menu-tree-page-tree_childs'>$output</ul>";
	}

	return $output;
}

function admin_menu_tree_page_view_admin_menu() {

	load_plugin_textdomain('admin-menu-tree-page-view', false, "/admin-menu-tree-page-view/languages");

	// add main menu
	#add_menu_page( "title", "Simple Menu Pages", "edit_pages", "admin-menu-tree-page-tree_main", "bonnyFunction", null, 5);

	// end link that is written automatically by WP, and begin ul
	// <!-- <span class='admin-menu-tree-page-tree_headline'>" . __("Pages", 'admin-menu-tree-page-view') . "</span> -->
	$output = "
		</a>
		<ul class='admin-menu-tree-page-tree'>
			<li class='admin-menu-tree-page-tree_headline'>" . __("Pages", 'admin-menu-tree-page-view') . "</li>
			<li class='admin-menu-tree-page-filter'>
				<label>".__("Search", 'admin-menu-tree-page-view')."</label>
				<input type='text' class='' />
				<div class='admin-menu-tree-page-filter-reset' title='".__("Reset search and show all pages", 'admin-menu-tree-page-view')."'></div>
				<div class='admin-menu-tree-page-filter-nohits'>".__("No pages found", 'admin-menu-tree-page-view')."</div>
			</li>
		";

	// get root items
	$args = array(
		"echo" => 0,
		"sort_order" => "ASC",
		"sort_column" => "menu_order",
		"parent" => 0
	);

	$output .= admin_menu_tree_page_view_get_pages($args);

	// end our ul and add the a-tag that wp automatically will close
	$output .= "
		</ul>
		<a href='#'>
	";

	// add subitems to main menu
	add_submenu_page("edit.php?post_type=page", "Admin Menu Tree Page View", $output, "edit_pages", "admin-menu-tree-page-tree", "admin_menu_tree_page_page");

}

function admin_menu_tree_page_page() {
	?>

	<h2>Admin Menu Tree Page View</h2>
	<p>Nothing to see here. Move along! :)</p>

	<?php
}



/**
 * Code from plugin CMS Tree Page View
 * http://wordpress.org/extend/plugins/cms-tree-page-view/
 * Used with permission! :)
 */
function admin_menu_tree_page_view_add_page() {

	check_ajax_referer('admin-menu-tree-page-view', 'amtpv-nonce');

	global $wpdb;

	/*
	(
	[action] => cms_tpv_add_page
	[pageID] => cms-tpv-1318
	type
	)
	action	admin_menu_tree_page_view_add_page
	pageID	448
	page_titles[]	pending inside
	post_status	pending
	post_type	page
	type	inside
	*/
	$type = $_POST["type"];
	$pageID = (int) $_POST["pageID"];
	$post_type = $_POST["post_type"];
	$wpml_lang = isset($_POST["wpml_lang"]) ? $_POST["wpml_lang"] : "";
	$page_titles = (array) $_POST["page_titles"];
	$ref_post = get_post($pageID);
	$post_status = $_POST["post_status"];
	if (!$post_status) { $post_status = "draft"; }

	$post_id_to_return = NULL;

	if ("after" == $type) {

		/*
			add page under/below ref_post
		*/

		if (!function_exists("admin_menu_tree_page_view_add_page_after")) {
		function admin_menu_tree_page_view_add_page_after($ref_post_id, $page_title, $post_type, $post_status = "draft") {

			global $wpdb;

			$ref_post = get_post($ref_post_id);
			// update menu_order of all pages below our page
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = menu_order+2 WHERE post_parent = %d AND menu_order >= %d AND id <> %d ", $ref_post->post_parent, $ref_post->menu_order, $ref_post->ID ) );

			// create a new page and then goto it
			$post_new = array();
			$post_new["menu_order"] = $ref_post->menu_order+1;
			$post_new["post_parent"] = $ref_post->post_parent;
			$post_new["post_type"] = "page";
			$post_new["post_status"] = $post_status;
			$post_new["post_title"] = $page_title;
			$post_new["post_content"] = "";
			$post_new["post_type"] = $post_type;
			$newPostID = wp_insert_post($post_new);
			return $newPostID;
		}
		}

		$ref_post_id = $ref_post->ID;
		$loopNum = 0;
		foreach ($page_titles as $one_page_title) {
			$newPostID = admin_menu_tree_page_view_add_page_after($ref_post_id, $one_page_title, $post_type, $post_status);
			$new_post = get_post($newPostID);
			$ref_post_id = $new_post->ID;
			if ($loopNum == 0) {
				$post_id_to_return = $newPostID;
			}
			$loopNum++;
		}


	} else if ( "inside" == $type ) {

		/*
			add page inside ref_post
		*/
		if (!function_exists("admin_menu_tree_page_view_add_page_inside")) {
		function admin_menu_tree_page_view_add_page_inside($ref_post_id, $page_title, $post_type, $post_status = "draft") {

			global $wpdb;

			$ref_post = get_post($ref_post_id);

			// update menu_order, so our new post is the only one with order 0
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = menu_order+1 WHERE post_parent = %d", $ref_post->ID) );

			$post_new = array();
			$post_new["menu_order"] = 0;
			$post_new["post_parent"] = $ref_post->ID;
			$post_new["post_type"] = "page";
			$post_new["post_status"] = $post_status;
			$post_new["post_title"] = $page_title;
			$post_new["post_content"] = "";
			$post_new["post_type"] = $post_type;
			$newPostID = wp_insert_post($post_new);
			return $newPostID;

		}
		}

		// add reversed
		$ref_post_id = $ref_post->ID;
		$page_titles = array_reverse($page_titles);
		$loopNum = 0;
		foreach ($page_titles as $one_page_title) {
			$newPostID = admin_menu_tree_page_view_add_page_inside($ref_post_id, $one_page_title, $post_type, $post_status);
			$new_post = get_post($newPostID);
			// $ref_post_id = $new_post->ID;
			if ($loopNum == 0) {
				$post_id_to_return = $newPostID;
			}
			$loopNum++;
		}
		$post_id_to_return = $newPostID;

	}

	if ($post_id_to_return) {
		// return editlink for the newly created page
		$editLink = get_edit_post_link($post_id_to_return, '');
		if ($wpml_lang) {
			$editLink = add_query_arg("lang", $wpml_lang, $editLink);
		}
		echo $editLink;
	} else {
		// fail, tell js
		echo "0";
	}
	#print_r($post_new);
	exit;
}



// move a post up or down
// code from my other plugin cms tree page view
function admin_menu_tree_page_view_move_page() {

	check_ajax_referer('admin-menu-tree-page-view', 'amtpv-nonce');

	/*
	Array ( [action] => admin_menu_tree_page_view_move_page [post_to_update_id] => 567 [direction] => down )
	*/

	// fetch all info we need from $_GET-params
	$post_to_update_id = (int) $_POST["post_to_update_id"];
	$direction = $_POST["direction"];
	$post_to_update = get_post($post_to_update_id);
	$aboveOrNextPostID = $_POST["aboveOrNextPostID"];
	$post_aboveOrNext = get_post($aboveOrNextPostID);

	/*
	 the node that was moved,
	 the reference node in the move,
	 the new position relative to the reference node (one of "before", "after" or "inside"),
	 	inside = man placerar den under en sida som inte har några barn?
	*/

	global $wpdb;

	$node_id = (int) $_POST["post_to_update_id"]; // the node that was moved
	$ref_node_id = (int) $_POST["aboveOrNextPostID"];
	$type = $_POST["direction"];

	$_POST["skip_sitepress_actions"] = true; // sitepress.class.php->save_post_actions

	if ($node_id && $ref_node_id) {
		#echo "\nnode_id: $node_id";
		#echo "\ntype: $type";

		$post_node = get_post($node_id);
		$post_ref_node = get_post($ref_node_id);

		// first check that post_node (moved post) is not in trash. we do not move them
		if ($post_node->post_status == "trash") {
			exit;
		}

		if ( "inside" == $type ) {
			// note: inside does not exist for Admin Menu Tree Page View

			// post_node is moved inside ref_post_node
			// add ref_post_node as parent to post_node and set post_nodes menu_order to 0
			// @todo: shouldn't menu order of existing items be changed?
			$post_to_save = array(
				"ID" => $post_node->ID,
				"menu_order" => 0,
				"post_parent" => $post_ref_node->ID
			);
			wp_update_post( $post_to_save );

			echo "did inside";

		} elseif ( "up" == $type ) {

			// post_node is placed before ref_post_node
			// update menu_order of all pages with a menu order more than or equal ref_node_post and with the same parent as ref_node_post
			// we do this so there will be room for our page if it's the first page
			// so: no move of individial posts yet
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = menu_order+1 WHERE post_parent = %d", $post_ref_node->post_parent ) );

			// update menu order with +1 for all pages below ref_node, this should fix the problem with "unmovable" pages because of
			// multiple pages with the same menu order (...which is not the fault of this plugin!)
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = menu_order+1 WHERE menu_order >= %d", $post_ref_node->menu_order+1) );

			$post_to_save = array(
				"ID" => $post_node->ID,
				"menu_order" => $post_ref_node->menu_order,
				"post_parent" => $post_ref_node->post_parent
			);
			wp_update_post( $post_to_save );

			echo "did before";

		} elseif ( "down" == $type ) {

			// post_node is placed after ref_post_node

			// update menu_order of all posts with the same parent ref_post_node and with a menu_order of the same as ref_post_node, but do not include ref_post_node
			// +2 since multiple can have same menu order and we want our moved post to have a unique "spot"
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = menu_order+2 WHERE post_parent = %d AND menu_order >= %d AND id <> %d ", $post_ref_node->post_parent, $post_ref_node->menu_order, $post_ref_node->ID ) );

			// update menu_order of post_node to the same that ref_post_node_had+1
			#$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET menu_order = %d, post_parent = %d WHERE ID = %d", $post_ref_node->menu_order+1, $post_ref_node->post_parent, $post_node->ID ) );

			$post_to_save = array(
				"ID" => $post_node->ID,
				"menu_order" => $post_ref_node->menu_order+1,
				"post_parent" => $post_ref_node->post_parent
			);
			wp_update_post( $post_to_save );

			echo "did after";
		}

		#echo "ok"; // I'm done here!

	} else {
		// error
	}
	echo 1;
	die();

} // move post
