<?php

/*
Plugin Name: Easy Digital Downloads - FairBill Gateway
Plugin URL: https://github.com/bum2/edd-fairbill-gateway
Description: A Fairbill gateway for Easy Digital Downloads
Version: 0.5
Author: Bumbum
Author URI: https://getfaircoin.net
*/

//Language
load_plugin_textdomain( 'edd-fairbill', false,  dirname(plugin_basename(__FILE__)) );

//Load post fields management
require_once ( __DIR__ . '/edd-fairbill-post.php');

// registers the gateway
function fairbill_edd_register_gateway($gateways) {
  $gateways['fairbill'] = array('admin_label' => 'FairBill Gateway', 'checkout_label' => __('FairBill Gateway', 'edd-fairbill'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'fairbill_edd_register_gateway');

/**
 * CoopShares-Mixed Remove CC Form
 * @access private
 * @since 1.0
 */
function edd_fairbill_gateway_cc_form() {
    $output = '<div>';
    global $edd_options;
    $output .= $edd_options['fairbill_checkout_info'];
	$output .= "</div>";
	echo $output;
    return false;
}
add_action( 'edd_fairbill_cc_form', 'edd_fairbill_gateway_cc_form' );


// adds the settings to the Payment Gateways section
function fairbill_edd_add_settings($settings) {

	$fairbill_gateway_settings = array(
		array(
			'id' => 'fairbill_gateway_settings',
			'name' => '<strong>' . __('FairBill Gateway Settings', 'edd-fairbill') . '</strong>',
			'desc' => __('Configure the Fairbill gateway, adding their given URL and the \'Secret Key\' given to your project', 'edd-fairbill'),
			'type' => 'header'
		),
		array(
			'id' => 'fairbill_api_url',
			'name' => __('URL of the Fairbill API', 'edd-fairbill'),
			'desc' => __('Insert the URL of the Fairbill API you have been given', 'edd-fairbill'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'fairbill_test_api_url',
			'name' => __('URL of the Fairbill Test API', 'edd-fairbill'),
			'desc' => __('Insert the URL of the Fairbill Test API you have been given', 'edd-fairbill'),
			'type' => 'text',
			'size' => 'regular'
		),
		/*array(
			'id' => 'fairbill_secret_key',
			'name' => __('Secret Key given from Fairbill', 'edd-fairbill'),
			'desc' => __('Put here the secret key string given to you from Fairbill', 'edd-fairbill'),
			'type' => 'text',
            'size' => 'regular'
		),*/
        array(
			'id' => 'fairbill_checkout_info',
			'name' => __( 'Fairbill Checkout Text', 'edd-fairbill' ),
			'desc' => __( 'Insert here the markup to add in the checkout page when fairbill gateway is selected', 'edd-fairbill' ),
			'type' => 'rich_editor'
		),
		/*array( // maybe is better to only use next fields from the pòst
			'id' => 'fairbill_from_email',
			'name' => __( 'Fairbill Email From', 'edd-fairbill' ),
			'desc' => __( 'The remitent email to send the notification email to the user', 'edd-fairbill' ),
			'type' => 'text',
			'size' => 'regular',
			'std'  => get_bloginfo( 'admin_email' )
		),
		array(
			'id' => 'fairbill_subject_email',
			'name' => __( 'Fairbill Email Subject', 'edd-fairbill' ),
			'desc' => __( 'The Subject of the notification email to the user (use email tags)', 'edd-fairbill' ), // . '<br/>' . edd_get_emails_tags_list(),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'fairbill_body_email',
			'name' => __( 'Fairbill Email Body', 'edd-fairbill' ),
			'desc' => __('The body of the email sended to the user', 'edd-fairbill') . '<br/>' . edd_get_emails_tags_list()  ,
			'type' => 'rich_editor',
		),*/
	);

	return array_merge($settings, $fairbill_gateway_settings);
}
add_filter('edd_settings_gateways', 'fairbill_edd_add_settings');


function fairbill_add_status($statuses){
	$new_status = array(
		'sended' => __('Sended', 'edd-fairbill')
	);
	return array_merge($new_status, $statuses);
}
add_filter('edd_payment_statuses', 'fairbill_add_status');

/**
 * Process FairBill Purchase
 *
 * @since 1.0
 * @global $edd_options Array of all the EDD Options
 * @param array $purchase_data Purchase Data
 * @return void
 */
function edd_process_fairbill_purchase( $purchase_data ) {
    global $edd_options;

    // Collect payment data
    $payment_data = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => edd_get_currency(),
        'downloads'     => $purchase_data['downloads'],
        'user_info'     => $purchase_data['user_info'],
        'cart_details'  => $purchase_data['cart_details'],
        'gateway'       => 'fairbill',
        'status'        => 'sended' //'pending'
     );

    // Record the pending payment
    $payment = edd_insert_payment( $payment_data );

    // Check payment
    if ( ! $payment ) {
    	// Record the error
        edd_record_gateway_error( __( 'Payment Error', 'edd-fairbill' ), sprintf( __( 'Payment creation failed before sending buyer to FairBill. Payment data: %s', 'edd-fairbill' ), json_encode( $payment_data ) ), $payment );
        // Problems? send back
        edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
    } else {
        // Only send to FairBill if the pending payment is created successfully
        $listener_url = trailingslashit( home_url( 'index.php' ) ).'?edd-listener=fairIPN';

         // Get the success url
        /*$return_url = add_query_arg( array(
        	'payment-confirmation' => 'fairbill',
        	'payment-id' => $payment

        ), get_permalink( $edd_options['success_page'] ) );
        */

        // Get the FairBill redirect uri
        $fairbill_redirect = trailingslashit( edd_get_fairbill_redirect() ) . '?';

	// bumbum	
	//print_r($purchase_data);
	$fairaddress = $purchase_data['post_data']['edd_fairaddress'];
	if($fairaddress == '0000000000000000000000000000000000') $fairaddress = 'fairsaving_service';
    
        //$token_id = password_hash( $purchase_data['purchase_key'] . $edd_options['fairbill_secret_key'] );
        
	$cp_price = $purchase_data['cart_details'][0]['item_price']; // ONLY ONE ITEM ON CART! [0]
	
        // Setup FairBill arguments
        $fairbill_args = array(
            //'business'      => $edd_options['fairbill_email'],
            'order_id'	       => $payment,
            'cp_price'	       => $cp_price,
            'project_id'       => $fairaddress,
            'email_id'         => $purchase_data['user_email'], // bum2 field
            'first_name_id'    => $purchase_data['user_info']['first_name'],
            'last_name_id'     => $purchase_data['user_info']['last_name'],
            //'token_id'         => $token_id
	        //'invoice'		=> $purchase_data['purchase_key'],
            //'no_shipping'   => '1',
            //'shipping'      => '0',
            //'no_note'       => '1',
            //'currency_code' => edd_get_currency(),
            //'charset'       => get_bloginfo( 'charset' ),
            //'rm'            => '2',
            //'return'        => $return_url,
            //'cancel_return' => edd_get_failed_transaction_uri(),
            //'notify_url'    => $listener_url,
            //'page_style'    => edd_get_fairbill_page_style(),
            //'site_name'	=> get_bloginfo( 'name' ),
        );

        //if( ! empty( $purchase_data['user_info']['address'] ) ) {
        //	$fairbill_args['address1'] = $purchase_data['user_info']['address']['line1'];
        //    $fairbill_args['address2'] = $purchase_data['user_info']['address']['line2'];
        //    $fairbill_args['city']     = $purchase_data['user_info']['address']['city'];
        //    $fairbill_args['country']  = $purchase_data['user_info']['address']['country'];
        //}

		
        $fairbill_args = apply_filters('edd_fairbill_redirect_args', $fairbill_args, $purchase_data );

	// Build query
	$fairbill_redirect .= http_build_query( $fairbill_args );

	// Fix for some sites that encode the entities
	$fairbill_redirect = str_replace( '&amp;', '&', $fairbill_redirect );

	// Get rid of cart contents
	edd_empty_cart();
	
	// Redirect to FairBill
	wp_redirect( $fairbill_redirect );
	exit;
    }

}
add_action( 'edd_gateway_fairbill', 'edd_process_fairbill_purchase' );

/**
 * Listens for a FairBill IPN requests and then sends to the processing function
 *
 * @since 1.0
 * @global $edd_options Array of all the EDD Options
 * @return void
 */
function edd_listen_for_fairbill_ipn() {
	global $edd_options;

	// Regular FairBill IPN
	if ( isset( $_GET['edd-listener'] ) && $_GET['edd-listener'] == 'fairIPN' ) {
		do_action( 'edd_verify_fairbill_ipn' );
	}
}
add_action( 'init', 'edd_listen_for_fairbill_ipn' );

/**
 * Process FairBill IPN
 *
 * @since 1.0
 * @global $edd_options Array of all the EDD Options
 * @return void
 */
function edd_process_fairbill_ipn() {
	global $edd_options;

	// Check the request method is POST
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] != 'POST' ) {
		return;
	}

	// Set initial post data to false
	$post_data = false;

	// Fallback just in case post_max_size is lower than needed
	if ( ini_get( 'allow_url_fopen' ) ) {
		$post_data = file_get_contents( 'php://input' );
	} else {
		// If allow_url_fopen is not enabled, then make sure that post_max_size is large enough
		ini_set( 'post_max_size', '12M' );
	}
	// Start the encoded data collection with notification command
	$encoded_data = 'cmd=_notify-validate';

	// Get current arg separator
	$arg_separator = edd_get_php_arg_separator_output();

	// Verify there is a post_data
	if ( $post_data || strlen( $post_data ) > 0 ) {
		// Append the data
                $data_str = str_replace(',', '&', trim($post_data, '{}'));//json_decode($post_data);
                $data_str = str_replace(':', '=', $data_str);
                $data_str = str_replace('"', '', $data_str);
		$encoded_data .= $arg_separator.$data_str;
	} else {
		// Check if POST is empty
		if ( empty( $_POST ) ) {
			// Nothing to do
			return;
		} else {
			// Loop trough each POST
			foreach ( $_POST as $key => $value ) {
				// Encode the value and append the data
				$encoded_data .= $arg_separator."$key=" . urlencode( $value );
			}
		}
	}
	
	// Convert collected post data to an array
	parse_str( $encoded_data, $encoded_data_array );

	// Get the FairBill redirect uri
	$fairbill_redirect = edd_get_fairbill_redirect(true);


	// Check if $post_data_array has been populated
	if ( ! is_array( $encoded_data_array ) && !empty( $encoded_data_array ) )
		return;

	// Fallback to web accept just in case the txn_type isn't present
	do_action( 'edd_fairbill_web_accept', $encoded_data_array );

	exit;
}
add_action( 'edd_verify_fairbill_ipn', 'edd_process_fairbill_ipn' );

/**
 * Process web accept (one time) payment IPNs
 *
 * @since 1.3.4
 * @global $edd_options Array of all the EDD Options
 * @param array $data IPN Data
 * @return void
 */
function edd_process_fairbill_web_accept_and_cart( $data ) {
	global $edd_options;
	//$data = json_decode($data);
	// Collect payment details
	$payment_id     = $data['order_id'];
	//$purchase_key   = isset( $data['invoice'] ) ? $data['invoice'] : $data['item_number'];
	$fairbill_amount  = $data['price'];
	$payment_status = strtolower( $data['status'] );
        //$testmode = $data['mode'];
        

	if( get_post_status( $payment_id ) == 'publish' )
		return; // Only complete payments once

	if ( edd_get_payment_gateway( $payment_id ) != 'fairbill' )
		return; // this isn't a FairBill standard IPN

	if( ! edd_get_payment_user_email( $payment_id ) ) {
        
            //echo 'THE PAYMENT HAS NO EMAIL! ';
        
		// No email associated with purchase, so store from FairBill
		//update_post_meta( $payment_id, '_edd_payment_user_email', $data['payer_email'] );

		// Setup and store the customers's details
		/*$address = array();
		$address['line1']   = ! empty( $data['address_street']       ) ? $data['address_street']       : false;
		$address['city']    = ! empty( $data['address_city']         ) ? $data['address_city']         : false;
		$address['state']   = ! empty( $data['address_state']        ) ? $data['address_state']        : false;
		$address['country'] = ! empty( $data['address_country_code'] ) ? $data['address_country_code'] : false;
		$address['zip']     = ! empty( $data['address_zip']          ) ? $data['address_zip']          : false;

		$user_info = array(
			'id'         => '-1',
			'email'      => $data['payer_email'],
			'first_name' => $data['first_name'],
			'last_name'  => $data['last_name'],
			'discount'   => '',
			'address'    => $address
		);*/

		//$payment_meta = get_post_meta( $payment_id, '_edd_payment_meta', true );
		//$payment_meta['user_info'] = serialize( $user_info );
		//update_post_meta( $payment_id, '_edd_payment_meta', $payment_meta );
	}

        if( $data['mode'] != 'live' ) return;

        $payment_meta = get_post_meta( $payment_id, '_edd_payment_meta', true );
        $payment_meta['fairbill_id'] = $data['id'];
        $payment_meta['fairbill_key'] = $data['key'];
        $payment_meta['fairbill_authkey'] = $data['authorization_key'];
        if( isset($data['transactionNumber']) ) $payment_meta['betabank_tn'] = $data['transactionNumber'];
        update_post_meta( $payment_id, '_edd_payment_meta', $payment_meta );
    
	
	//if ( $payment_status == 'refunded' ) {
		// Process a refund
		//edd_process_fairbill_refund( $data );
        //    echo ' REFUNDED?! ';
	//} else {

		// Retrieve the total purchase amount (before FairBill)
		$payment_amount = edd_get_payment_amount( $payment_id );

		if ( number_format( (float) $fairbill_amount, 2 ) < number_format( (float) $payment_amount, 2 ) ) {
			// The prices don't match
			edd_record_gateway_error( __( 'Fairbill IPN Error', 'edd-fairbill' ), sprintf( __( 'Invalid payment amount in fairIPN response. IPN data: %s', 'edd-fairbill' ), json_encode( $data ) ), $payment_id );
		   return;
		}
		
	
        if ( $payment_status == 'pending' ) {
            edd_insert_payment_note( $payment_id, sprintf( __( 'FairBill Payment ID: %s', 'edd-fairbill' ) , $data['id'] ) ); //txn_id'] ) );
            edd_update_payment_status( $payment_id, 'pending' );
            
            // Don't send email to user or admin (fairbill will do) when its a delayed payment (transfer, etc)
            //$payment_data = edd_get_payment_meta( $payment_id );
            //if ( !edd_admin_notices_disabled( $payment_id ) ) {
            //    do_action( 'fairbill_admin_sale_notice', $payment_id, $payment_data );
            //}
        }
        
        if ( $payment_status == 'publish' ) { //completed' || edd_is_test_mode() ) {
            edd_insert_payment_note( $payment_id, sprintf( __( 'Betabank Transaction ID: %s', 'edd-fairbill' ) , $data['transactionNumber'] ) ); //txn_id'] ) );
            add_filter( 'edd_email_purchase_receipt', 'edd_fairbill_remove_paypal_email');

            edd_update_payment_status( $payment_id, 'complete' );
            //$update_fields = array( 'ID' => $payment_id, 'post_status' => $payment_status, 'edit_date' => current_time( 'mysql' ) );
            //wp_update_post( apply_filters( 'edd_update_payment_status_fields', $update_fields ) );
            remove_filter( 'edd_email_purchase_receipt', 'edd_fairbill_remove_paypal_email');

            // send email with payment info
            fairbill_email_purchase_order( $payment_id , false);
            
            //remove_filter( 'edd_email_purchase_receipt', 'edd_fairbill_remove_paypal_email');
        }
	//}
}
add_action( 'edd_fairbill_web_accept', 'edd_process_fairbill_web_accept_and_cart' );


function edd_fairbill_remove_paypal_email() {
    
    return false;

}


/**
 * Process FairBill IPN Refunds
 *
 * @since 1.3.4
 * @global $edd_options Array of all the EDD Options
 * @param array $data IPN Data
 * @return void
 */
/*function edd_process_fairbill_refund( $data ) {
	global $edd_options;

	// Collect payment details
	$payment_id = intval( $data['custom'] );

	edd_insert_payment_note( $payment_id, sprintf( __( 'FairBill Payment #%s Refunded', 'edd' ) , $data['parent_txn_id'] ) );
	edd_insert_payment_note( $payment_id, sprintf( __( 'FairBill Refund Transaction ID: %s', 'edd' ) , $data['txn_id'] ) );
	edd_update_payment_status( $payment_id, 'refunded' );
}*/

/**
 * Get FairBill Redirect
 *
 * @since 1.0.8.2
 * @global $edd_options Array of all the EDD Options
 * @param bool $ssl_check Is SSL?
 * @return string
 */
function edd_get_fairbill_redirect( $ssl_check = false ) {
	global $edd_options;

	/*if ( is_ssl() || ! $ssl_check ) {
		$protocal = 'https://';
	} else {
		$protocal = 'http://';
	}*/

	// Check the current payment mode
	if ( edd_is_test_mode() ) {
		// Test mode
		$fairbill_uri = $edd_options['fairbill_test_api_url'];
	} else {
		// Live mode 
    	$fairbill_uri = $edd_options['fairbill_api_url']; 
	}

	return apply_filters( 'edd_fairbill_uri', $fairbill_uri );
}


////   R E C E I P T    //// not used!

function edd_fairbill_payment_receipt_after($payment){ //
  if( edd_get_payment_gateway( $payment->ID ) == 'fairbill'){
    $payment_data = edd_get_payment_meta( $payment->ID );
    $downloads = edd_get_payment_meta_cart_details( $payment->ID );
    $post_id = $downloads[0]['id']; // ONLY FIRST ITEM ON CART
    $message = stripslashes ( get_post_meta( $post_id, 'fairbill_edd_wp_post_receipt', true ));
    $message = edd_do_email_tags( $message, $payment->ID );
    //$message = edd_get_payment_gateway( $payment->ID );
    echo $message;
  }
}
add_action('edd_payment_receipt_after_table', 'edd_fairbill_payment_receipt_after');


////   E M A I L   T O   U S E R   ////

//Sent transfer instructions
function fairbill_email_purchase_order ( $payment_id, $admin_notice = true ) {

	global $edd_options;

	$payment_data = edd_get_payment_meta( $payment_id );
	$user_id      = edd_get_payment_user_id( $payment_id );
	$user_info    = maybe_unserialize( $payment_data['user_info'] );
	$to           = edd_get_payment_user_email( $payment_id );

	if ( isset( $user_id ) && $user_id > 0 ) {
		$user_data = get_userdata($user_id);
		$name = $user_data->display_name;
	} elseif ( isset( $user_info['first_name'] ) && isset( $user_info['last_name'] ) ) {
		$name = $user_info['first_name'] . ' ' . $user_info['last_name'];
	} else {
		$name = $email;
	}

	$message = edd_get_email_body_header();

        $downloads = edd_get_payment_meta_cart_details( $payment_id );
        $post_id = $downloads[0]['id']; // ONLY FIRST ITEM ON CART
        $email = stripslashes (get_post_meta( $post_id, 'fairbill_edd_wp_post_body_mail', true ));
        $subject = wp_strip_all_tags(get_post_meta( $post_id, 'fairbill_edd_wp_post_subject_mail', true ));

        $from_email = get_post_meta( $post_id, 'fairbill_edd_wp_post_from_email', true );

	$message .= edd_do_email_tags( $email, $payment_id );
	$message .= edd_get_email_body_footer();

	$from_name = get_bloginfo('name');

	$subject = edd_do_email_tags( $subject, $payment_id );

	$headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
	$headers .= "Reply-To: ". $from_email . "\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8\r\n";
	$headers = apply_filters( 'edd_receipt_headers', $headers, $payment_id, $payment_data );

	if ( apply_filters( 'edd_email_purchase_receipt', true ) ) {
		wp_mail( $to, $subject, $message, $headers);//, $attachments );
	}

	if ( $admin_notice && !edd_admin_notices_disabled( $payment_id ) ) {
		do_action( 'fairbill_admin_sale_notice', $payment_id, $payment_data );
	}
}

////   E M A I L   T O   A D M I N S   ////

/**
 * Sends the Admin Sale Notification Email
 *
 * @since 1.4.2
 * @param int $payment_id Payment ID (default: 0)
 * @param array $payment_data Payment Meta and Data
 * @return void
 */
function fairbill_admin_email_notice( $payment_id = 0, $payment_data = array(), $testdata = false ) {
	global $edd_options;

	/* Send an email notification to the admin */
	$admin_email = fairbill_get_admin_notice_emails( $payment_id ); // bumbum
	$user_id     = edd_get_payment_user_id( $payment_id );
	$user_info   = maybe_unserialize( $payment_data['user_info'] );

	if ( isset( $user_id ) && $user_id > 0 ) {
		$user_data = get_userdata($user_id);
		$name = $user_data->display_name;
	} elseif ( isset( $user_info['first_name'] ) && isset( $user_info['last_name'] ) ) {
		$name = $user_info['first_name'] . ' ' . $user_info['last_name'];
	} else {
		$name = $user_info['email'];
	}

	$admin_message = edd_get_email_body_header();
	
	if( $testdata !== false) $admin_message .= $testdata; // bumbum
	
	$admin_message .= edd_get_sale_notification_body_content( $payment_id, $payment_data );
	$admin_message .= edd_get_email_body_footer();

	if( !empty( $edd_options['sale_notification_subject'] ) ) {
		$admin_subject = wp_strip_all_tags( $edd_options['sale_notification_subject'], true );
	} else {
		$admin_subject = sprintf( __( 'New getfaircoin purchase - Order #%1$s', 'edd' ), $payment_id );
	}
	
	if( $testdata !== false ) $admin_subject = 'TEST payment {payment_id} of {price}';
	
	$admin_subject = edd_do_email_tags( $admin_subject, $payment_id );
	$admin_subject = apply_filters( 'edd_admin_sale_notification_subject', $admin_subject, $payment_id, $payment_data );

	$from_name  = isset( $edd_options['from_name'] )  ? $edd_options['from_name']  : get_bloginfo('name');
	$from_email = isset( $edd_options['from_email'] ) ? $edd_options['from_email'] : get_option('admin_email');

	$admin_headers = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
	$admin_headers .= "Reply-To: ". $from_email . "\r\n";
	$admin_headers .= "MIME-Version: 1.0\r\n";
	$admin_headers .= "Content-Type: text/html; charset=utf-8\r\n";
	$admin_headers .= apply_filters( 'edd_admin_sale_notification_headers', $admin_headers, $payment_id, $payment_data );

	$admin_attachments = apply_filters( 'edd_admin_sale_notification_attachments', array(), $payment_id, $payment_data );

	wp_mail( $admin_email, $admin_subject, $admin_message, $admin_headers, $admin_attachments );
}
add_action( 'fairbill_admin_sale_notice', 'fairbill_admin_email_notice', 10, 2 );

/**
 * Retrieves the emails for which admin notifications are sent to (these can be
 * changed in the EDD Settings)
 *
 * @since 1.0
 * @global $edd_options Array of all the EDD Options
 * @return void
 */
function fairbill_get_admin_notice_emails( $payment_id ) {
	global $edd_options;

	$emails = isset( $edd_options['admin_notice_emails'] ) && strlen( trim( $edd_options['admin_notice_emails'] ) ) > 0 ? $edd_options['admin_notice_emails'] : get_bloginfo( 'admin_email' );

	$emails = array_map( 'trim', explode( "\n", $emails ) );

	return apply_filters( 'edd_admin_notice_emails', $emails, $payment_id );
}


