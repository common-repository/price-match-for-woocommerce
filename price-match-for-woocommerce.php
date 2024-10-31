<?php
/**
 * Plugin Name: Price Match for WooCommerce
 * Description: Easily process product price match requests from your customers.
 * Version: 1.0.2
 * Author: Potent Plugins
 * Author URI: https://potentplugins.com/?utm_source=price-match-for-woocommerce&utm_medium=link&utm_campaign=wp-plugin-author-uri
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */

// Add Settings link in plugins list
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'hmwcpm_action_links');
function hmwcpm_action_links($links) {
	array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'edit.php?post_type=price_match_request&page=hmwcpm_settings')).'">Settings</a>');
	return $links;
}

// Add settings page
add_action('admin_menu', 'hmwcpm_admin_menu');
function hmwcpm_admin_menu() {
	add_submenu_page('edit.php?post_type=price_match_request', 'Settings', 'Settings', 'manage_woocommerce', 'hmwcpm_settings', 'hmwcpm_settings_page');
}


// Display settings page
function hmwcpm_settings_page() {
	if (!empty($_POST)) {
		$options = array('hmwcpm_form_text');
		foreach ($options as $option)
			if (isset($_POST[$option]))
				update_option($option, $_POST[$option]);
	}

	echo('
		<div class="wrap">
			<h2>Price Match for WooCommerce</h2>
			<form action="" method="post">
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="hm_sbp_field_report_time">Price Match Form Text:</label>
						</th>
						<td>
							<textarea name="hmwcpm_form_text" rows="5" cols="50">'.htmlspecialchars(get_option('hmwcpm_form_text')).'</textarea>
							<p class="description">Enter text or HTML to be displayed above the price match request form.</p>
						</td>
					</tr>
				</table>
			<button type="submit" class="button-primary" style="margin-bottom: 30px;">Save</button>
	');
	$potent_slug = 'price-match-for-woocommerce';
	include(__DIR__.'/plugin-credit.php');
	echo('
		</div>
	');
}

add_action('init', 'hmwcpm_init');
function hmwcpm_init() {
	register_post_type('price_match_request', array(
		'label' => 'Price Match Requests',
		'public' => false,
		'show_ui' => true,
		'capabilities' => array(
			'create_posts' => false,
			'delete_posts' => true,
			'delete_post' => true
		),
		'menu_position' => 56
	));
	register_post_status('hmwcpm_pending', array(
		'label' => _x('Pending Review', 'hmwcpm'),
		'public' => true,
		'internal' => false,
		'label_count' => _n_noop('Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>')
	));
	register_post_status('hmwcpm_approved', array(
		'label' => _x('Approved', 'hmwcpm'),
		'public' => true,
		'internal' => false,
		'label_count' => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>')
	));
	register_post_status('hmwcpm_denied', array(
		'label' => _x('Denied', 'hmwcpm'),
		'public' => true,
		'internal' => false,
		'label_count' => _n_noop('Denied <span class="count">(%s)</span>', 'Denied <span class="count">(%s)</span>')
	));
}

add_action('admin_init', 'hmwcpm_admin_init');
function hmwcpm_admin_init() {
	global $pagenow;
	if ($pagenow == 'post.php' && isset($_GET['action']) && in_array($_GET['action'], array('hmwcpm_approve', 'hmwcpm_deny'))
			&& isset($_GET['id']) && is_numeric($_GET['id']) && get_post_type($_GET['id']) == 'price_match_request') {
		wp_update_post(array(
			'ID' => $_GET['id'],
			'post_status' => ($_GET['action'] == 'hmwcpm_approve' ? 'hmwcpm_approved' : 'hmwcpm_denied')
		));
		if (isset($_GET['return'])) {
			header('Location: '.$_GET['return']);
			exit;
		}
	}
}

add_filter('woocommerce_email_classes', 'hmwcpm_wc_email_classes');
function hmwcpm_wc_email_classes($emails) {

	require_once(__DIR__.'/includes/emails/class-wc-email-new-price-match-request.php');
	$emails['WC_Email_New_Price_Match_Request'] = new WC_Email_New_Price_Match_Request();
	
	require_once(__DIR__.'/includes/emails/class-wc-email-price-match-request-approved.php');
	$emails['WC_Email_Price_Match_Request_Approved'] = new WC_Email_Price_Match_Request_Approved();
	
	require_once(__DIR__.'/includes/emails/class-wc-email-price-match-request-denied.php');
	$emails['WC_Email_Price_Match_Request_Denied'] = new WC_Email_Price_Match_Request_Denied();
	
	return $emails;
}

add_filter('manage_price_match_request_posts_columns', 'hmwcpm_columns');
function hmwcpm_columns($cols) {
	return array(
		'request_date' => 'Date',
		'status' => 'Status',
		'email' => 'Customer Email',
		'product' => 'Product',
		'price_list' => 'List Price',
		'price_request' => 'Requested Price',
		'source' => 'Source',
	);
}

add_action('manage_price_match_request_posts_custom_column', 'hmwcpm_column', 10, 2);
function hmwcpm_column($col, $postId) {
	switch ($col) {
		case 'request_date':
			echo(get_the_date(get_option('date_format'), $postId));
			break;
		case 'email':
			$email = htmlspecialchars(trim(get_post_meta($postId, '_email', true)));
			echo('<a href="mailto:'.$email.'">'.$email.'</a>');
			break;
		case 'product':
			$productId = get_post_meta($postId, '_product_id', true);
			echo(edit_post_link(get_the_title($productId), '', '', $productId));
			break;
		case 'price_list':
			echo(wc_price(get_post_meta(get_post_meta($postId, '_product_id', true), '_price', true)));
			break;
		case 'price_request':
			echo(wc_price(get_post_meta($postId, '_price', true)));
			break;
		case 'source':
			$source = htmlspecialchars(trim(get_post_meta($postId, '_source', true)));
			if (substr($source, 0, 4) == 'http')
				echo('<a href="'.$source.'" target="_blank">'.$source.'</a>');
			else
				echo($source);
			break;
		case 'status':
			echo(get_post_status_object(get_post_status($postId))->label);
			break;
	}
}

add_filter('manage_edit-price_match_request_sortable_columns', 'hmwcpm_sortable_columns');
function hmwcpm_sortable_columns($cols) {
	$cols['request_date'] = 'post_date';
	return $cols;
}

add_filter('post_row_actions', 'hmwcpm_post_row_actions', 10, 2);
function hmwcpm_post_row_actions($actions, $post) {
	if ($post->post_type == 'price_match_request') {
		$actions = array();
		if ($post->post_status == 'hmwcpm_pending') {
			$actions['hmwcpm_approve'] = '<a href="post.php?id='.$post->ID.'&amp;action=hmwcpm_approve&amp;return='.urlencode($_SERVER['REQUEST_URI']).'">Approve</a>';
			$actions['hmwcpm_deny'] = '<a href="post.php?id='.$post->ID.'&amp;action=hmwcpm_deny&amp;return='.urlencode($_SERVER['REQUEST_URI']).'">Deny</a>';
		}
		$actions['delete'] = '<a href="'.get_delete_post_link($post, null, true).'">Delete</a>';
	}
	return $actions;
}


add_action('woocommerce_before_template_part', 'hmwcpm_before_template_part');
function hmwcpm_before_template_part($part) {
	global $product;
	if ($part == 'single-product/tabs/tabs.php') {
		echo('
			<div style="clear: both;">
				<a href="javascript:void(0);" class="hmwcpm-link">Request Price Match</a><br />
		');
		
		if (!empty($_POST['hmwcpm_request'])) {
			
			if (empty($_POST['hmwcpm_price']) || !is_numeric($_POST['hmwcpm_price']) || empty($_POST['hmwcpm_source']) || empty($_POST['hmwcpm_email']) || !is_email($_POST['hmwcpm_email'])) {
				echo('<div class="hmwcpm-message hmwcpm-error">Your price match request could not be submitted because one or more entries are missing or invalid.</div>');
				$formSubmitError = true;
			} else {
				$postId = wp_insert_post(array(
					'post_type' => 'price_match_request',
					'post_status' => 'hmwcpm_pending'
				));
				update_post_meta($postId, '_product_id', $product->id);
				update_post_meta($postId, '_email', $_POST['hmwcpm_email']);
				update_post_meta($postId, '_source', $_POST['hmwcpm_source']);
				update_post_meta($postId, '_price', $_POST['hmwcpm_price']);
				
				WC()->mailer();
				do_action('hmwcpm_request_created', $postId);
				
				echo('<div class="hmwcpm-message hmwcpm-success">Your price match request has been submitted.</div>');
				
				unset($_POST['hmwcpm_email']);
				unset($_POST['hmwcpm_source']);
				unset($_POST['hmwcpm_price']);
			}
			
		}
		
		echo('
				<form action="" method="post" class="hmwcpm-form'.(empty($formSubmitError) ? ' hmwcpm-hidden' : '').'">
					<input type="hidden" name="hmwcpm_request" value="1" />
		');
		$formText = get_option('hmwcpm_form_text');
		if (!empty($formText))
			echo('<div>'.htmlspecialchars($formText).'</div>');
		woocommerce_form_field('hmwcpm_price', array('label' => 'Price:', 'type' => 'number'), (empty($_POST['hmwcpm_price']) ? '' : $_POST['hmwcpm_price']));
		woocommerce_form_field('hmwcpm_source', array('label' => 'Source:', 'type' => 'text'), (empty($_POST['hmwcpm_source']) ? '' : $_POST['hmwcpm_source']));
		woocommerce_form_field('hmwcpm_email', array('label' => 'Your Email:', 'type' => 'email'), (empty($_POST['hmwcpm_email']) ? '' : $_POST['hmwcpm_email']));
		echo('		<button type="submit" class="button alt">Submit Price Match Request</button>
				</form>
			</div>
		
		
		');
	}
}

add_action('hmwcpm_approved_price_match_request', 'hmwcpm_approve_request', 10, 2);
function hmwcpm_approve_request($postId, $post) {
	$keyChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$keyCharsMax = strlen($keyChars) - 1;
	$key = '';
	for ($i = 0; $i < 32; ++$i)
		$key .= $keyChars[rand(0, $keyCharsMax)];
	update_post_meta($postId, '_key', $key);
	
	WC()->mailer();
	do_action('hmwcpm_request_approved', $postId);
}

add_action('hmwcpm_denied_price_match_request', 'hmwcpm_deny_request', 10, 2);
function hmwcpm_deny_request($postId, $post) {
	WC()->mailer();
	do_action('hmwcpm_request_denied', $postId);
}

add_filter('woocommerce_product_is_on_sale', 'hmwcpm_product_is_on_sale', 10, 2);
function hmwcpm_product_is_on_sale($on_sale, $product) {
	if (isset($_REQUEST['hmwcpm']) && hmwcpm_validate_key($_REQUEST['hmwcpm'], $product->id) !== false)
			return true;
	return $on_sale;
}

function hmwcpm_validate_key($key, $product_id) {
	$key = explode('-', $key);
	$isUsed = get_post_meta($key[0], '_used', true);
	if ($key[1] == $product_id && is_numeric($key[0]) && get_post_type($key[0]) == 'price_match_request'
				&& get_post_meta($key[0], '_product_id', true) == $product_id && empty($isUsed))
		return $key[0];
	else
		return false;
}

add_filter('woocommerce_get_price', 'hmwcpm_product_price', 10, 2);
function hmwcpm_product_price($price, $product) {
	if (isset($_REQUEST['hmwcpm'])) {
		$requestId = hmwcpm_validate_key($_REQUEST['hmwcpm'], $product->id);
		if ($requestId !== false) {
			return get_post_meta($requestId, '_price', true);
		}
	}
	return $price;
}

if (isset($_REQUEST['hmwcpm']))
	add_action('woocommerce_before_add_to_cart_button', 'hmwcpm_before_add_to_cart_button');
function hmwcpm_before_add_to_cart_button() {
	echo('<input type="hidden" name="hmwcpm" value="'.htmlspecialchars($_REQUEST['hmwcpm']).'" />');
}


add_filter('woocommerce_add_cart_item', 'hmwcpm_add_cart_item');
function hmwcpm_add_cart_item($item) {
	if (isset($_REQUEST['hmwcpm'])) {
		$requestId = hmwcpm_validate_key($_REQUEST['hmwcpm'], $item['data']->id);
		if ($requestId !== false) {
			$item['data']->price = get_post_meta($requestId, '_price', true);
			$item['price_match_request'] = $requestId;
		}
	}
	return $item;
}

add_action('woocommerce_add_order_item_meta', 'hmwcpm_order_add_item', 10, 2);
function hmwcpm_order_add_item($item_id, $values) {
	if (isset($values['price_match_request']))
		update_post_meta($values['price_match_request'], '_used', 1);
}

add_filter('bulk_actions-edit-price_match_request', 'hmwcpm_none');
function hmwcpm_none() {
	return array();
}

// Set product price when loading the cart
add_filter('woocommerce_get_cart_item_from_session', 'hmwcpm_get_cart_item_from_session');
function hmwcpm_get_cart_item_from_session($session_data) {
	if (isset($session_data['price_match_request']))
		$session_data['data']->price = get_post_meta($session_data['price_match_request'], '_price', true);
	return $session_data;
}

add_action('wp_enqueue_scripts', 'hmwcpm_enqueue_scripts');
function hmwcpm_enqueue_scripts() {
	wp_enqueue_script('hmwcpm', plugins_url('js/price-match.js', __FILE__), array('jquery'));
	wp_enqueue_style('hmwcpm', plugins_url('css/price-match.css', __FILE__));
}

?>