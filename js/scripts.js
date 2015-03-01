
jQuery(function($) {
	
	// @todo: this is only needed for WordPress earlier than 3.2?
	setTimeout(function() {
		jQuery("#toplevel_page_admin-menu-tree-page-tree_main").addClass("wp-menu-open");
	}, 100);
		
	// search/filter pages
	$("li.admin-menu-tree-page-filter input").keyup(function(e) {
		var ul = $(this).closest("ul.admin-menu-tree-page-tree");
		ul.find("li").hide();
		ul.find("li.admin-menu-tree-page-tree_headline,li.admin-menu-tree-page-filter").show();
		var s = $(this).val();
		var selector = "li:AminMenuTreePageContains('"+s+"')";
		var hits = ul.find(selector);
		if (hits.length > 0 || s !== "") {
			ul.find("div.admin-menu-tree-page-filter-reset").fadeIn("fast");
			ul.unhighlight();
		}
		if (s === "") {
			ul.find("div.admin-menu-tree-page-filter-reset").fadeOut("fast");
		}
		ul.highlight(s);
		hits.show();
		
		// hits can be childs of hidden li:s, so we must show the parents of the hits too
		hits.each(function(i, elm) {
			var parent = elm.parentNode;
			if (parent) {
				parent = $(parent);
				// ul -> div -> ul
				parent.parent().parent().addClass("admin-menu-tree-page-view-opened").removeClass("admin-menu-tree-page-view-closed");
				parent.show();
			}
		});
		
		// if no hits: tell the user so we have less confusion. confusion is bad.
		var nohits_div = ul.find("div.admin-menu-tree-page-filter-nohits");
		if (hits.length === 0) {
			nohits_div.show();
		} else {
			nohits_div.hide();
		}
		
	});

	// clear/reset filter and show all pages again
	$("div.admin-menu-tree-page-filter-reset").click(function() {
		var $t = $(this);
		var ul = $t.closest("ul.admin-menu-tree-page-tree");
		ul.find("li").fadeIn("fast");
		$t.fadeOut("fast");
		$t.closest("li.admin-menu-tree-page-filter").find("input").val("").focus();
		ul.unhighlight();
		ul.find("div.admin-menu-tree-page-filter-nohits").hide();
	});
	
	// label = hide in and focus input
	$("li.admin-menu-tree-page-filter label, li.admin-menu-tree-page-filter input").click(function() {
		var $t = $(this);
		$t.closest("li.admin-menu-tree-page-filter").find("label").hide();
		$t.closest("li.admin-menu-tree-page-filter").find("input").focus();
	});

	var trees = jQuery("ul.admin-menu-tree-page-tree");
	
	// add links to expand/collapse
	trees.find("li.admin-menu-tree-page-view-has-childs > div").after("<div class='admin-menu-tree-page-expand' title='Show/Hide child pages' />");
	trees.on("click", "div.admin-menu-tree-page-expand", function(e) {
		
		e.preventDefault();
		var $t = $(this);
		var $li = $t.closest("li");
		var $a = $li.find("a:first");
		var $ul = $li.find("ul:first");
		
		var isOpen = false;
		if ($ul.is(":visible")) {
			$ul.slideUp(function() {
				$li.addClass("admin-menu-tree-page-view-closed").removeClass("admin-menu-tree-page-view-opened");
			});
			
		} else {
			$ul.slideDown(function() {
				$li.addClass("admin-menu-tree-page-view-opened").removeClass("admin-menu-tree-page-view-closed");
			});
			isOpen = true;
		}

		var post_id = $a.attr("href").match(/\?post=([\d]+)/)[1];
		var array_pos = $.inArray(post_id, admin_menu_tree_page_view_opened_posts);
		if (array_pos > -1) {
			// did exist in cookie
			admin_menu_tree_page_view_opened_posts = admin_menu_tree_page_view_opened_posts.splice(array_pos+1, 1);
		}
		// array now has not our post_id. so add it if visible/open
		if (isOpen) {
			admin_menu_tree_page_view_opened_posts.push(post_id);
		}

		admin_menu_tree_page_view_save_opened_posts();

	});


	// mouse over to show edit-box
	trees.on("mouseenter mouseleave", "li div.amtpv-linkwrap:first-child", function(e) {

		var t = $(this);
		var li = t.closest("li");
		var popupdiv = li.find("div.amtpv-editpopup:first");
		var linkwrap = li.find("div.amtpv-linkwrap:first");
		//var popup_linkwrap = popupdiv.closest("div.amtpv-linkwrap");
		
		if (e.type == "mouseenter" || e.type == "mouseover") {

			var ul = t.closest("ul.admin-menu-tree-page-tree");

			// don't show if another one is in edit mode
			if (ul.find("div.amtpv-editpopup-is-working").length > 0) {
			} else {
				ul.find("div.amtpv-editpopup").removeClass("amtpv-editpopup-hover");
				ul.find("div.amtpv-linkwrap").removeClass("amtpv-linkwrap-hover");
				popupdiv.addClass("amtpv-editpopup-hover");
				linkwrap.addClass("amtpv-linkwrap-hover");
			}
			
		} else if (e.type == "mouseleave" || e.type == "mouseout") {

			// don't hide if related target is the shadow of the menu, aka #adminmenushadow
			var do_hide = true;
			if (e.relatedTarget && e.relatedTarget.id == "adminmenushadow") {
				do_hide = false;
			}
			
			// also don't hide if wrap div has .amtpv-editpopup-is-working
			if (linkwrap.hasClass("amtpv-editpopup-is-working")) {
				do_hide = false;
			}
			
			if (do_hide) {
				popupdiv.removeClass("amtpv-editpopup-hover");
				linkwrap.removeClass("amtpv-linkwrap-hover");
			}
			
		}
	});
	
	//
	trees.on("mouseenter mouseleave", "div.amtpv-editpopup", function(e) {
		var t = $(this);
		var li = t.closest("li");
		var popupdiv = li.find("div.amtpv-editpopup:first");
		var linkwrap = li.find("div.amtpv-linkwrap:first");
		
		if (e.type == "mouseenter" || e.type == "mouseover") {
			t.addClass("amtpv-editpopup-hover-hover");
		} else if (e.type == "mouseleave" || e.type == "mouseout") {
			if (linkwrap.hasClass("amtpv-editpopup-is-working")) {
			} else {
				t.removeClass("amtpv-editpopup-hover-hover");
			}
		}
	});
		
	// edit/view links
	trees.on("click", "div.amtpv-editpopup-edit, div.amtpv-editpopup-view", function(e) {

		e.preventDefault();
		var t = $(this);
		var link = t.data("link");
		var new_win = false;
		
		if ( ($.client.os == "Mac" && (e.metaKey || e.shiftKey)) || ($.client.os != "Mac" && e.ctrlKey) ) {
			new_win = true;
		}
		if (new_win) {
			window.open(link);
		} else {
			document.location = link;
		}
		
	});
	
	// add links
	trees.on("click", "div.amtpv-editpopup-add-after, div.amtpv-editpopup-add-inside", function(e) {

		var t = $(this);
		var post_id = t.closest("a").data("post-id");
		var popup = t.closest("div.amtpv-editpopup");
		var popup_linkwrap = popup.closest("div.amtpv-linkwrap");
		var editpopup_add = popup.find("div.amtpv-editpopup-add");
		var editpopup_editview = popup.find("div.amtpv-editpopup-editview");
		
		// hide all divs
		// @todo: should put all in one div, and hide just that one
		popup.find("> div").hide();

		// add class that tell us that we are in "adding-mode"/"working-mode"
		popup_linkwrap.addClass("amtpv-editpopup-is-working");

		var type = "after";
		if (t.hasClass("amtpv-editpopup-add-inside")) {
			type = "inside";
		}
		
		// remove possibly previous added add-stuff
		popup.find("form.amtpv-editpopup-addpages").remove();
		
		var add_pages = $("<form />")
			.addClass("amtpv-editpopup-addpages")
			.insertAfter(editpopup_add)
			;
		add_pages.append( "<div class='amtpv-editpopup-addpages-headline'>Add new page(s)</div>" );
		add_pages.append( $("<input type='hidden' class='amtpv-editpopup-addpages-type' value='"+type+"' />") );

		// var type = popup.find(".amtpv-editpopup-addpages-type").val();
		if (type=="after") {
			add_pages.append( $("<div class='amtpv-editpopup-addpages-position'><input checked='checked' type='radio' name='amtpv-editpopup-addpages-position' id='amtpv-editpopup-addpages-position-after' value='after' /><label for='amtpv-editpopup-addpages-position-after'>After</label> <input type='radio' name='amtpv-editpopup-addpages-position' id='amtpv-editpopup-addpages-position-inside' value='inside' /><label for='amtpv-editpopup-addpages-position-inside'>Inside</label> </div") );
		} else if (type=="inside") {
			add_pages.append( $("<div class='amtpv-editpopup-addpages-position'><input type='radio' name='amtpv-editpopup-addpages-position' id='amtpv-editpopup-addpages-position-after' value='after' /><label for='amtpv-editpopup-addpages-position-after'>After</label> <input checked='checked' type='radio' name='amtpv-editpopup-addpages-position' id='amtpv-editpopup-addpages-position-inside' value='inside' /><label for='amtpv-editpopup-addpages-position-inside'>Inside</label> </div") );
		}

		add_pages.append( $("<div class='amtpv-editpopup-addpages-publish'><label for='amtpv-editpopup-addpages-publish-select'>Status</label><select id='amtpv-editpopup-addpages-publish-select' name='status'><option value='publish'>Published</option><option value='pending'>Pending Review</option><option value='draft' selected='selected'>Draft</option></select></div") );

		add_pages.append( $("<div class='amtpv-editpopup-addpages-names'><label class='amtpv-editpopup-addpages-label'>Name(s)</label>") );
		add_pages.append( $("<ul class='amtpv-editpopup-addpages-names-ul'><li><span></span><input class='amtpv-editpopup-addpages-name' type='text' value=''/></li></ul>") );
		//add_pages.append( $("<div class='amtpv-editpopup-addpages-addpage'><a href='#'>+ page</a></div></div>"));
		
		add_pages.append( $("<div class='amtpv-editpopup-addpages-submit'><input type='submit' class='button-primary' value='Add' /> or <a href='#' class='amtpv-editpopup-addpages-cancel'>cancel</a></div>"));
		add_pages.find(".amtpv-editpopup-addpages-name").focus();
		
		add_pages.find("ul.amtpv-editpopup-addpages-names-ul").sortable({
			"xaxis": "y",
			"containment": 'parent',
			"forceHelperSize": true,
			"forcePlaceholderSize": true,
			"handle": "span:first",
			"placeholder": "ui-state-highlight"
		});
		
		return;
		
	});

	// add new page-link
	trees.on("click", "div.amtpv-editpopup-addpages-addpage a", function(e) {
		e.preventDefault();
		var t = $(this);
		var newelm = $("<li><span></span><input class='amtpv-editpopup-addpages-name' type='text' value=''/></li>");
		t.parent().prev("ul.amtpv-editpopup-addpages-names-ul").append( newelm );
		newelm.find("input").focus();
	});
	
	// when typing in the input, add another input if we are at the last input
	// this way we don't have to click that "add page" button. less clicks = more productive.
	trees.on("keyup", "input.amtpv-editpopup-addpages-name", function(e) {
		// check if this is the last li
		var t = $(this);
		var ul = t.closest("ul");
		var li = t.closest("li");
		
		// if this input is the last one, and we have entered something, add another one
		var isLast = (li.index() == ul.find("li").length-1);
		if (isLast && t.val() !== "") {
			var newelm = $("<li class='hidden'><span></span><input class='amtpv-editpopup-addpages-name' type='text' value=''/></li>");
			ul.append( newelm );
			newelm.show();
		}
		
	});
	
	// cancel-link
	trees.on("click", "a.amtpv-editpopup-addpages-cancel", function(e) {

		e.preventDefault();

		var t = $(this),
			popup = t.closest("div.amtpv-editpopup"),
			linkwrap = popup.closest("div.amtpv-linkwrap");
		
		popup.find(".amtpv-editpopup-addpages").hide().remove();
		popup.find("> div").show();
		
		linkwrap.removeClass("amtpv-editpopup-is-working");
	});
	
	// woho, add da pages!
	trees.on("submit", "form.amtpv-editpopup-addpages", function(e) {
		// fetch all .amtpv-editpopup-addpages-name for this popup
		
		e.preventDefault();
		
		var t = $(this);
		var post_id = t.closest("div.amtpv-linkwrap").data("post-id");
		var popup = t.closest("div.amtpv-editpopup");
		var names = popup.find(".amtpv-editpopup-addpages-name");

		var arr_names = [];
		names.each(function(i, elm) {
			var name = $.trim($(elm).val());
			if (name) {
				arr_names.push( $(elm).val() );
			}
		});
		
		// we must at least have one name
		// @todo: make this a bit better looking
		if (arr_names.length === 0) {
			alert("Please enter a name for the new page");
			return false;
		}

		popup.find("div.amtpv-editpopup-addpages-submit input").val("Adding...");
		
		// detect after or inside
		// var type = popup.find(".amtpv-editpopup-addpages-type").val();
		var type = popup.find("input[name=amtpv-editpopup-addpages-position]:checked").val();
		
		// post status
		var post_status = popup.find("#amtpv-editpopup-addpages-publish-select").val();
		
		var data = {
			"action": 'admin_menu_tree_page_view_add_page',
			"pageID": post_id,
			"type": type,
			"page_titles": arr_names,
			"post_type": "page",
			"post_status": post_status
		};

		jQuery.post(ajaxurl, data, function(response) {
			if (response != "0") {
				var new_win = false;
				//if ( ($.client.os == "Mac" && (e.metaKey || e.shiftKey)) || ($.client.os != "Mac" && e.ctrlKey) ) {
				//	new_win = true;
				//}
				//return;
				if (new_win) {
					window.open(response);
				} else {
					document.location = response;
				}

			}
		});

	});
	
	// make the tree sortable
	$("ul.admin-menu-tree-page-tree, ul.admin-menu-tree-page-tree ul").sortable({
		"axis": "y",
		"containment": 'parent',
		"forceHelperSize": true,
		"forcePlaceholderSize": true,
		"delay": 20,
		"distance": 5,
		"xhandle": "span.amtpv-draghandle",
		"revert": true,
		"start": function(event, ui) {
			var li = $(ui.item);
			li.data("startindex", li.index());
		},
		"update": function(event, ui) {
			/*
			ui.item <- the post that was moved
			send post id to server, with info about post above or under, depending on if there is a post above/under
			*/
			var li = $(ui.item);
			var a = li.find("a:first");
			var post_id = a.data("post-id");
			
			// check if we have a post above
			var prev = li.prev();
			var aboveOrNextItem;
			var aboveOrNext;
			if (prev.length > 0 && !prev.hasClass("admin-menu-tree-page-filter")) {
				aboveOrNextItem = prev;
				aboveOrNext = "above";
			} else {
				// ... or below
				var next = li.next();
				aboveOrNextItem = next;
				aboveOrNext = "below";
			}
			// get id of above or below post
			var aboveOrNextPostID = $(aboveOrNextItem).find("a:first").data("post-id");
			
			// flytt upp = start > update
			// flytt ner = start < update
			var startindex = li.data("startindex");
			var updateindex = li.index();
			var direction;
			if (startindex > updateindex) {
				direction = "up";
			} else {
				direction = "down";
			}
			
			// now we have all we need, tell the server to do the move
			$.post(ajaxurl, {
				"action": "admin_menu_tree_page_view_move_page",
				"post_to_update_id": post_id,
				"direction": direction,
				"aboveOrNextPostID": aboveOrNextPostID
			}, function(data) {
				// console.log(data);
			});
			
		}
	});

	// click "pages" headline to hide or show the tree
	// @todo: remember state in a cookie, to be read by PHP
	// @todo: also add arrow or something that shows state
	trees.on("click", ".admin-menu-tree-page-tree_headline", function() {
		var t = $(this);
		var ul = t.closest("ul");
		var lis = ul.find("li").not(".admin-menu-tree-page-tree_headline"); // also consider .admin-menu-tree-page-filter
		if (ul.hasClass("admin-menu-tree-page-view-closed")) {
			// it's closed, so open it
			ul.addClass("admin-menu-tree-page-view-opened").removeClass("admin-menu-tree-page-view-closed");
			lis.show();
		} else {
			// it's opened, so close it
			ul.removeClass("admin-menu-tree-page-view-opened").addClass("admin-menu-tree-page-view-closed");
			lis.hide();
		}
	});

});

function admin_menu_tree_page_view_save_opened_posts() {
	jQuery.cookie('admin-menu-tree-page-view-open-posts', admin_menu_tree_page_view_opened_posts.join(","));
}

// array with all post ids that are open
var admin_menu_tree_page_view_opened_posts = jQuery.cookie('admin-menu-tree-page-view-open-posts') || "";
admin_menu_tree_page_view_opened_posts = admin_menu_tree_page_view_opened_posts.split(",");
//if (admin_menu_tree_page_view_opened_posts[0] == "") {
//	admin_menu_tree_page_view_opened_posts = [];
//}

// http://stackoverflow.com/questions/187537/is-there-a-case-insensitive-jquery-contains-selector
jQuery.expr[':'].AminMenuTreePageContains = function(a,i,m){
     return (a.textContent || a.innerText || "").toLowerCase().indexOf(m[3].toLowerCase())>=0;
};
