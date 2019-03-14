<?php
/*
    Plugin Name:       Unifiedpurse Easy Digital Downloads Payment Gateway
    Plugin URL:        https://unifiedpurse.com
    Description:       Universal payment gateway for Easy Digital Downloads
    Version:           1.0.0
    Author:            Unifiedpurse
    Author URI:        http://unifiedpurse.com/
    License:           GPL-2.0+
    License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Check if Easy Digital Downloads is active
if ( ! class_exists( 'Easy_Digital_Downloads' ) ) return;


function tbz_edd_unifiedpurse_add_errors() {
    echo '<div id="edd-unifiedpurse-payment-errors"></div>';
}
add_action( 'edd_after_cc_fields', 'tbz_edd_unifiedpurse_add_errors', 999 );

add_action( 'edd_unifiedpurse_cc_form', '__return_false' );

define( 'TBZ_EDD_UNIFIEDPURSEURL', plugin_dir_url( __FILE__ ) );


define( 'TBZ_EDD_UNIFIEDPURSEVERSION', '1.0.0' );


function tbz_edd_unifiedpurse_settings_section( $sections ) {
    $sections['unifiedpurse-settings'] = 'Unifiedpurse';
    return $sections;

}
add_filter( 'edd_settings_sections_gateways', 'tbz_edd_unifiedpurse_settings_section' );


function tbz_edd_unifiedpurse_settings( $settings ) {
	
	$widget_url= trim( edd_get_option( 'edd_unifiedpurse_widget_url' ) );
	$action_btn="";
	if(!empty($widget_url))$action_btn='<div style="text-align:center;"> <a href="'.$widget_url.'" target="_blank" class="button button-info">View UnifiedPurse Transactions</a></div>';
		
    $unifiedpurse_settings = array(
        array(
            'id' => 'edd_unifiedpurse_settings',
            'name' => '<strong>Unifiedpurse Settings</strong>',
            'desc' => 'Configure the gateway settings',
            'type' => 'header'
        ),
        array(
            'id'   => 'edd_unifiedpurse_username',
            'name' => 'Username',
            'desc' => 'Enter your username here',
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'edd_unifiedpurse_widget_url',
            'name' => 'Transaction Widget URL',
            'desc' => 
				'<p>Transaction widget allows you to easily access and manage records on your transaction history. (this is optional)<br/>
				Generate one at <a href="https://unifiedpurse.com/accept_payments#transaction_widget">https://unifiedpurse.com/accept_payments#transaction_widget</a> '.$action_btn.'</p>',
            'type' => 'text',
            'size' => 'regular'
        ),
    );
	

    if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
        $unifiedpurse_settings = array( 'unifiedpurse-settings' => $unifiedpurse_settings );
    }

    return array_merge( $settings, $unifiedpurse_settings );
}
add_filter( 'edd_settings_gateways', 'tbz_edd_unifiedpurse_settings', 1 );


function tbz_edd_register_unifiedpurse_gateway( $gateways ) {
    if ( tbz_unifiedpurse_edd_is_setup() ) {
        $gateways['unifiedpurse'] = array(
            'admin_label'       => 'Unifiedpurse',
            'checkout_label'    => 'UnifiedPurse (Universal Payment Gateways)'
        );
    }
    return $gateways;
}

add_filter( 'edd_payment_gateways', 'tbz_edd_register_unifiedpurse_gateway' );

function tbz_edd_unifiedpurse_check_config() {
    $is_enabled = edd_is_gateway_active( 'unifiedpurse' );
    if ( ( ! $is_enabled || false === tbz_unifiedpurse_edd_is_setup() ) && 'unifiedpurse' == edd_get_chosen_gateway() ) {
        edd_set_error( 'unifiedpurse_gateway_not_configured', 'There is an error with the Unifiedpurse configuration.' );
    }
}
add_action( 'edd_pre_process_purchase', 'tbz_edd_unifiedpurse_check_config', 1  );


function tbz_unifiedpurse_edd_is_setup() {
	$username     = trim( edd_get_option( 'edd_unifiedpurse_username' ) );
	return !empty($username);
}

function tbz_edd_unifiedpurse_process_payment( $purchase_data ) {
    $payment_data = array(
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency'     => edd_get_currency(),
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $purchase_data['user_info'],
        'status'       => 'pending',
        'gateway'      => 'unifiedpurse'
    );

    $payment = edd_insert_payment( $payment_data );

    if ( ! $payment ) {
        edd_record_gateway_error( 'Payment Error', sprintf( 'Payment creation failed before sending buyer to Unifiedpurse. Payment data: %s', json_encode( $payment_data ) ), $payment );
		// edd_set_error( 'unifiedpurse_error', 'Can\'t connect to the gateway, Please try again.' );
        edd_send_back_to_checkout( '?payment-mode=unifiedpurse' );
    } else {
        $unifiedpurse_data = array();
        $unifiedpurse_data['receiver']=trim(edd_get_option('edd_unifiedpurse_username'));
        $unifiedpurse_data['amount']    = $purchase_data['price'];
        $unifiedpurse_data['currency']    = $payment_data['currency'];
        $unifiedpurse_data['email']     = $purchase_data['user_email'];
        $unifiedpurse_data['ref'] = 'EDD-' . $payment . '-' . uniqid();
		$unifiedpurse_data['notification_url']=add_query_arg( 'edd-listener', 'unifiedpurseipn', home_url( 'index.php' ) );
		$unifiedpurse_data['success_url']=add_query_arg( 'edd-listener', 'unifiedpurse', home_url( 'index.php' ) );
		//cancel_url
        $unifiedpurse_data['memo'] = substr($purchase_data['cart_details'],0,65);
        edd_set_payment_transaction_id($payment, $unifiedpurse_data['ref'] );
		
		$get_payment_url='https://unifiedpurse.com/sci/?'.http_build_query($unifiedpurse_data);
		wp_redirect( $get_payment_url );
		exit;
    }
}
add_action( 'edd_gateway_unifiedpurse', 'tbz_edd_unifiedpurse_process_payment' );


function tbz_edd_unifiedpurse_redirect() {
    if ( isset( $_GET['edd-listener'] ) && ($_GET['edd-listener'] == 'unifiedpurse'||$_GET['edd-listener'] == 'unifiedpurseipn') ) {
        do_action( 'tbz_edd_unifiedpurse_redirect_verify' );
    }
}
add_action( 'init', 'tbz_edd_unifiedpurse_redirect' );

function tbz_edd_unifiedpurse_redirect_verify() {
    if(isset($_REQUEST['ref'])){
        $transaction_id = $_REQUEST['ref'];
        $the_payment_id = edd_get_purchase_id_by_transaction_id( $transaction_id );

        if ($the_payment_id && get_post_status($the_payment_id) == 'publish' ){
            edd_empty_cart();
            edd_send_to_success_page();
        }

		$order_info= explode( '-', $transaction_id );
        
		if(!empty($order_info[1])){
			$payment_id= $order_info[1];
			$unifiedpurse_txn   = tbz_edd_unifiedpurse_verify_transaction( $transaction_id );
			$new_status=$unifiedpurse_txn['status'];
			$info=$unifiedpurse_txn['response_description'];
			
			if($new_status===null){
				$temp_msg="Unable to verify your payment; $info.<br/><i>Please check the transaction email sent to you by UnifiedPurse for follow-up.</i>";
				edd_set_error($temp_msg);
				edd_send_back_to_checkout( '?payment-mode=unifiedpurse' );
			}
			elseif($new_status==-1){
				edd_set_error( 'failed_payment',"Payment failed. Please try again. $info " );
				edd_send_back_to_checkout( '?payment-mode=unifiedpurse' );
			}
			else {
				$payment           = new EDD_Payment( $payment_id );
				$order_total       = floatval(edd_get_payment_amount( $payment_id ));
				$currency_code=$payment->currency;
				$amount_paid       = floatval($unifiedpurse_txn['amount']);
				$currency_paid       = $unifiedpurse_txn['currency'];
				$unifiedpurse_txn_ref  = $transaction_id;
				
				if($new_status==1){
					if ($amount_paid!=$order_total||$currency_paid!=$currency_code) {
						$note = "Look into this purchase. This order is currently revoked. Reason: Amount paid is different from the total order amount. Amount Paid was $amount_paid $currency_paid while the total order amount is $order_total $currency_code. UnifiedPurse Transaction Reference:  $unifiedpurse_txn_ref";
						$payment->status = 'revoked';
						$payment->add_note( $note );
						$payment->transaction_id = $unifiedpurse_txn_ref;
						
						
						$temp_msg="<strong>Wrong Amount</strong><br/><i>Amount Paid was equivalent of $amount_paid $currency_paid while the total order amount is $order_total $currency_code</i>";
						edd_set_error($temp_msg);
					}
					else {
						$note = 'Payment transaction was successful. Unifiedpurse Transaction Reference: ' . $unifiedpurse_txn_ref;
						$payment->status = 'publish';
						$payment->add_note( $note );
						$payment->transaction_id = $unifiedpurse_txn_ref;
					}
				}
				else {
					$temp_msg="<strong>Payment Pending</strong><br/>$info.<br/><i>Please check the transaction email sent to you by UnifiedPurse for follow-up.</i>";
					edd_set_error($temp_msg);
				}

				$payment->save();
				edd_empty_cart();
				edd_send_to_success_page();
			}
		}
    }
}
add_action( 'tbz_edd_unifiedpurse_redirect_verify', 'tbz_edd_unifiedpurse_redirect_verify' );

function tbz_edd_unifiedpurse_verify_transaction($payment_token ) {
	$username= trim( edd_get_option('edd_unifiedpurse_username') );	
	$url="https://unifiedpurse.com/api_v1?action=get_transaction&receiver=$username&ref=$payment_token"; //&amount=$order_amount&currency=$currency_code
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);			
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);
	$response = @curl_exec($ch);
	$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if($response_code != 200)$response=curl_error($ch);
	curl_close($ch);
	
	if($response_code == 200)$json=@json_decode($response,true);
	else $response="HTTP Error $response_code: $response. ";
	$new_status=null; $amount=0; $currency_code='';
	
	if(!empty($json['error'])){
		$response_description=$json['error'];
		$response_code=$response_code;
	}
	elseif(!empty($json)){
		$response_description=$json['info'];
		$response_code=$new_status=$json['status'];
		$amount=$json['original_amount']; //$json['amount'];
		$currency_code=$json['original_currency_code']; //$json['original_amount'];
	}
	else{
		$response_description=$response;
		$response_code=$response_code;
	}

    return array('status'=>$new_status,'response_code'=>$response_code,'response_description'=>$response_description,'amount'=>$amount,'currency_code'=>$currency_code);
}

function tbz_edd_unifiedpurse_payment_icons( $icons ) {
    $icons[ TBZ_EDD_UNIFIEDPURSEURL . 'assets/images/unifiedpurse.png' ]   = 'Unifiedpurse';
    return $icons;
}
add_filter( 'edd_accepted_payment_icons', 'tbz_edd_unifiedpurse_payment_icons' );


function tbz_edd_unifiedpurse_advert_notice(){
	//$is_enabled = edd_is_gateway_active( 'unifiedpurse' );
	$is_enabled=true;
    if(!$is_enabled){
    ?>
        <div class="update-nag">
            <a href='https://cheapglobalsms.com' target='_blank'>CheapGlobalSMS.com</a> offers instant bulk-SMS delivery worldwide, with delivery reports in real-time, all at the <a href='https://cheapglobalsms.com/coverage_list?traffic_volume=50000' target='_blank'>best SMS pricing</a>.  See <a href='' target='_blank'>cheap global sms plugin for wordpress</a>
        </div>
    <?php
    }
}
add_action( 'admin_notices', 'tbz_edd_unifiedpurse_advert_notice' );


function tbz_edd_unifiedpurse_plugin_action_links( $links ) {
    $settings_link = array(
        'settings' => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=unifiedpurse-settings' ) . '" title="Settings">Settings</a>'
    );
	$widget_url= trim( edd_get_option( 'edd_unifiedpurse_widget_url' ) );
	if(!empty($widget_url)){
		$settings_link[]='<a href="' .$widget_url.'" title="Unifiedpurse Transaction Widget" target="_blank" >UnifiedPurse Transactions</a>';
	}
	
    return array_merge( $settings_link, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tbz_edd_unifiedpurse_plugin_action_links' );
