<?php

// From https://github.com/skyverge/woocommerce-expedited-order-email/blob/master/includes/class-wc-expedited-order-email.php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Email_Price_Match_Request_Approved extends WC_Email {

	public function __construct() {

		// set ID
		$this->id = 'wc_price_match_request_approved';

		// this is the title in WooCommerce Email settings
		$this->title = 'Price Match Request Approved';

		// this is the description in WooCommerce email settings
		$this->description = 'Sent to the customer when their price match request is approved.';

		// these are the default heading and subject lines that can be overridden using the settings
		$this->heading = 'Price Match Request Approved';
		$this->subject = 'Your price match request was approved!';

		// Trigger on price match request approval
		add_action( 'hmwcpm_request_approved', array( $this, 'trigger' ) );

		// Call parent constructor to load any other defaults not explicity defined here
		parent::__construct();

	}
	
	public function trigger( $request_id ) {

		if ( ! $request_id )
			return;

		$this->request_id = $request_id;
		$this->recipient = get_post_meta($this->request_id, '_email', true);

		if ( ! $this->is_enabled() || ! $this->get_recipient() )
			return;

		// woohoo, send the email!
		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}


	public function get_content_plain() {
		ob_start();
		
		$product_id = get_post_meta($this->request_id, '_product_id', true);
		$link = get_permalink($product_id);
		$link .= (strpos($link, '?') === false ? '?' : '&').'hmwcpm='.$this->request_id.'$'.get_post_meta($this->request_id, '_key', true);
		
		echo('Congratulations! Your price match request for "'.get_the_title($product_id).'" has been approved!
		
			To purchase the product at the matched price, please visit the URL below:
			'.$link);
		
		$message = ob_get_contents();
		ob_end_clean();
		return $message;
	}

	
	public function get_content_html() {
		$mailer = WC()->mailer();
		ob_start();
		do_action('woocommerce_email_header', $this->heading, $mailer);
		
		$product_id = get_post_meta($this->request_id, '_product_id', true);
		$link = get_permalink($product_id);
		$link .= (strpos($link, '?') === false ? '?' : '&').'hmwcpm='.$this->request_id.'-'.$product_id.'-'.get_post_meta($this->request_id, '_key', true);
		
		echo('
			<p>Congratulations! Your price match request for <strong>'.get_the_title($product_id).'</strong>
			at <strong>'.wc_price(get_post_meta($this->request_id, '_price', true)).'</strong> has been approved!</p>
			<p>To purchase the product at the matched price, <a href="'.htmlspecialchars($link).'">click here</a>.</p>
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