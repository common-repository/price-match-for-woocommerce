<?php

// From https://github.com/skyverge/woocommerce-expedited-order-email/blob/master/includes/class-wc-expedited-order-email.php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Email_New_Price_Match_Request extends WC_Email {

	public function __construct() {

		// set ID
		$this->id = 'wc_new_price_match_request';

		// this is the title in WooCommerce Email settings
		$this->title = 'New Price Match Request';

		// this is the description in WooCommerce email settings
		$this->description = 'Sent to the admin when a customer submits a price match request.';

		// these are the default heading and subject lines that can be overridden using the settings
		$this->heading = 'New Price Match Request';
		$this->subject = 'New Price Match Request';

		// Trigger on new price match requests
		add_action( 'hmwcpm_request_created', array( $this, 'trigger' ) );

		// Call parent constructor to load any other defaults not explicity defined here
		parent::__construct();

		// this sets the recipient to the settings defined below in init_form_fields()
		$this->recipient = $this->get_option( 'recipient' );

		// if none was entered, just use the WP admin email as a fallback
		if ( ! $this->recipient )
			$this->recipient = get_option( 'admin_email' );
	}


	public function trigger( $request_id ) {
	
		if ( ! $request_id )
			return;

		$this->request_id = $request_id;

		if ( ! $this->is_enabled() || ! $this->get_recipient() )
			return;

		// woohoo, send the email!
		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}


	public function get_content_plain() {
		ob_start();
		echo('A price match request has been received. Please log in to your WooCommerce admin for details.');
		$message = ob_get_contents();
		ob_end_clean();
		return $message;
	}
	

	public function get_content_html() {
		$mailer = WC()->mailer();
		ob_start();
		do_action('woocommerce_email_header', $this->heading, $mailer);
		$email = htmlspecialchars(trim(get_post_meta($this->request_id, '_email', true)));
		
		$source = htmlspecialchars(trim(get_post_meta($this->request_id, '_source', true)));
		if (substr($source, 0, 4) == 'http')
			$source = '<a href="'.$source.'" target="_blank">'.$source.'</a>';
		
		$product_id = get_post_meta($this->request_id, '_product_id', true);
		
		echo('
			<p>A price match request has been received.</p>
			
			<table>
				<tbody>
					<tr>
						<th><strong>Customer Email:</strong></th>
						<td><a href="mailto:'.$email.'">'.$email.'</a></td>
					</tr>
					<tr>
						<th><strong>Product:</strong></th>
						<td><a href="'.htmlspecialchars(admin_url('post.php?post='.$product_id.'&action=edit')).'">'.htmlspecialchars(get_the_title($product_id)).'</a></td>
					</tr>
					<tr>
						<th><strong>List Price:</strong></th>
						<td>'.wc_price(get_post_meta($product_id, '_price', true)).'</td>
					</tr>
						<th><strong>Requested Price:</strong></th>
						<td>'.get_post_meta($this->request_id, '_price', true).'</td>
					</tr>
					<tr>
						<th><strong>Source:</strong></th>
						<td>'.$source.'</td>
					</tr>
				</tbody>
			</table>
			
			<p>
				<a href="'.admin_url('post.php').'?id='.$this->request_id.'&amp;action=hmwcpm_approve&amp;return=edit.php%3Fpost_type%3Dprice_match_request">Approve Request</a> |
				<a href="'.admin_url('post.php').'?id='.$this->request_id.'&amp;action=hmwcpm_deny&amp;return=edit.php%3Fpost_type%3Dprice_match_request">Deny Request</a>
			</p>
		');
		
		do_action('woocommerce_email_footer', $mailer);
		
		$message = ob_get_contents();
		ob_end_clean();
		return $message;
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable this email notification',
				'default' => 'yes'
			),
			'recipient'  => array(
				'title'       => 'Recipient(s)',
				'type'        => 'text',
				'description' => sprintf( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', esc_attr( get_option( 'admin_email' ) ) ),
				'placeholder' => '',
				'default'     => ''
			),
			'subject'    => array(
				'title'       => 'Subject',
				'type'        => 'text',
				'description' => sprintf( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', $this->subject ),
				'placeholder' => '',
				'default'     => ''
			),
			'heading'    => array(
				'title'       => 'Email Heading',
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.' ), $this->heading ),
				'placeholder' => '',
				'default'     => ''
			),
			'email_type' => array(
				'title'       => 'Email type',
				'type'        => 'select',
				'description' => 'Choose which format of email to send.',
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'	    => __( 'Plain text', 'woocommerce' ),
					'html' 	    => __( 'HTML', 'woocommerce' ),
					'multipart' => __( 'Multipart', 'woocommerce' ),
				)
			)
		);
	}


}