<?php
/**
 * Plugin Name: senangPay Gateway for Paid Memberships Pro
 * Plugin URI: https://zoewebs.com
 * Description: Plugin to add senangPay payment gateway into Paid Memberships Pro
 * Version: 1.0.0
 * Author: WMS @ Zoewebs
 * License: GPLv2 or later
 */

// include_once plugin_dir_path(__FILE__) . 'class-senangpay-plugin-tracker.php';
defined('ABSPATH') or die('No script kiddies please!');
if (!function_exists('SenangPay_Pmp_Gateway_load')) {
    add_action('plugins_loaded', 'SenangPay_Pmp_Gateway_load', 20);

    DEFINE('SENANGPAY_PMPRO', "senangpay-paidmembershipspro");

    function SenangPay_Pmp_Gateway_load()
    {
        // paid memberships pro required
        if (!class_exists('PMProGateway')) {
            return;
        }

        // load classes init method
        add_action('init', array('PMProGateway_SenangPay', 'init'));

        // plugin links
        add_filter('plugin_action_links', array('PMProGateway_SenangPay', 'plugin_action_links'), 10, 2);

        if (!class_exists('PMProGateway_SenangPay')) {
            /**
             * PMProGateway_SenangPay Class
             *
             * Handles SenangPay integration.
             *
             */
            class PMProGateway_SenangPay extends PMProGateway
            {

                function __construct($gateway = null)
                {
                    $this->gateway = $gateway;
                    $this->gateway_environment =  pmpro_getOption("gateway_environment");

                    return $this->gateway;
                }

                /**
                 * Run on WP init
                 */
                static function init()
                {
                    //make sure SenangPay is a gateway option
                    add_filter('pmpro_gateways', array('PMProGateway_SenangPay', 'pmpro_gateways'));

                    //add fields to payment settings
                    add_filter('pmpro_payment_options', array('PMProGateway_SenangPay', 'pmpro_payment_options'));
                    add_filter('pmpro_payment_option_fields', array('PMProGateway_SenangPay', 'pmpro_payment_option_fields'), 10, 2);
                    // add_action('wp_ajax_kkd_pmpro_senangpay_ipn', array('PMProGateway_SenangPay', 'kkd_pmpro_senangpay_ipn'));
                    // add_action('wp_ajax_nopriv_kkd_pmpro_senangpay_ipn', array('PMProGateway_SenangPay', 'kkd_pmpro_senangpay_ipn'));
                    //code to add at checkout
                    $gateway = pmpro_getGateway();
                    if ($gateway == "senangpay") {
                        add_filter('pmpro_include_billing_address_fields', '__return_false');
                        add_filter('pmpro_required_billing_fields', array('PMProGateway_SenangPay', 'pmpro_required_billing_fields'));
                        add_filter('pmpro_include_payment_information_fields', '__return_false');
                        add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_SenangPay', 'pmpro_checkout_before_change_membership_level'), 10, 2);

                        add_filter('pmpro_gateways_with_pending_status', array('PMProGateway_SenangPay', 'pmpro_gateways_with_pending_status'));
                        add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_SenangPay', 'pmpro_checkout_default_submit_button'));
                        // custom confirmation page
                        add_filter('pmpro_pages_shortcode_confirmation', array('PMProGateway_SenangPay', 'pmpro_pages_shortcode_confirmation'), 20, 1);
                    }
                }

                /**
                 * Redirect Settings to PMPro settings
                 */
                static function plugin_action_links($links, $file)
                {
                    static $this_plugin;

                    if (false === isset($this_plugin) || true === empty($this_plugin)) {
                        $this_plugin = plugin_basename(__FILE__);
                    }

                    if ($file == $this_plugin) {
                        $settings_link = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__('Settings', SENANGPAY_PMPRO).'</a>';
                        array_unshift($links, $settings_link);
                    }

                    return $links;
				}
				
                static function pmpro_checkout_default_submit_button($show)
                {
                    global $gateway, $pmpro_requirebilling;

                    //show our submit buttons
                    ?>
                    <span id="pmpro_submit_span">
                    <input type="hidden" name="submit-checkout" value="1" />
                    <input type="submit" class="pmpro_btn pmpro_btn-submit-checkout" value="<?php _e('Check Out with SenangPay', 'pmpro'); ?> &raquo;" />
                    </span>
                    <?php

                    //don't show the default
                    return false;
				}
				
                /**
                 * Make sure SenangPay is in the gateways list
                 */
                static function pmpro_gateways($gateways)
                {
                    if (empty($gateways['senangpay'])) {
                        $gateways = array_slice($gateways, 0, 1) + array("senangpay" => __('SenangPay', SENANGPAY_PMPRO)) + array_slice($gateways, 1);
                    }
                    return $gateways;
				}

                /**
                 * Get a list of payment options that the SenangPay gateway needs/supports.
                 */
                static function getGatewayOptions()
                {
                    $options = array (
                        'senangpay_ssk',
                        'senangpay_smi',
                        'senangpay_lsk',
                        'senangpay_lmi',
                        'gateway_environment',
                        'currency',
                        'tax_state',
                        'tax_rate'
                        );

                    return $options;
                }

                /**
                 * Set payment options for payment settings page.
                 */
                static function pmpro_payment_options($options)
                {
                    //get SenangPay options
                    $senangpay_options = self::getGatewayOptions();

                    //merge with others.
                    $options = array_merge($senangpay_options, $options);

                    return $options;
                }

                /**
                 * Display fields for SenangPay options.
                 */
                static function pmpro_payment_option_fields($values, $gateway)
                {
                    ?>
                    <tr class="pmpro_settings_divider gateway gateway_senangpay" <?php if($gateway != "senangpay") { ?>style="display: none;"<?php } ?>>
                        <td colspan="2">
                            <?php _e('SenangPay API Configuration', 'pmpro'); ?>
                        </td>
                    </tr>
                    <tr class="gateway gateway_senangpay" <?php if($gateway != "senangpay") { ?>style="display: none;"<?php } ?>>
                        <th scope="row" valign="top">
                            <label for="senangpay_ssk"><?php _e('Sandbox Secret Key', 'pmpro');?>:</label>
                        </th>
                        <td>
                            <input type="text" id="senangpay_ssk" name="senangpay_ssk" size="60" value="<?php echo esc_attr($values['senangpay_ssk'])?>" />
                        </td>
                    </tr>
                    <tr class="gateway gateway_senangpay" <?php if($gateway != "senangpay") { ?>style="display: none;"<?php } ?>>
                        <th scope="row" valign="top">
                            <label for="senangpay_smi"><?php _e('Sandbox Merchant id', 'pmpro');?>:</label>
                        </th>
                        <td>
                            <input type="text" id="senangpay_smi" name="senangpay_smi" size="60" value="<?php echo esc_attr($values['senangpay_smi'])?>" />
                        </td>
                    </tr>
                    <tr class="gateway gateway_senangpay" <?php if($gateway != "senangpay") { ?>style="display: none;"<?php } ?>>
                        <th scope="row" valign="top">
                            <label for="senangpay_lsk"><?php _e('Live Secret Key', 'pmpro');?>:</label>
                        </th>
                        <td>
                            <input type="text" id="senangpay_lsk" name="senangpay_lsk" size="60" value="<?php echo esc_attr($values['senangpay_lsk'])?>" />
                        </td>
                    </tr>
                    <tr class="gateway gateway_senangpay" <?php if($gateway != "senangpay") { ?>style="display: none;"<?php } ?>>
                        <th scope="row" valign="top">
                            <label for="senangpay_lmi"><?php _e('Live Merchant id', 'pmpro');?>:</label>
                        </th>
                        <td>
                            <input type="text" id="senangpay_lmi" name="senangpay_lmi" size="60" value="<?php echo esc_attr($values['senangpay_lpk'])?>" />
                        </td>
                    </tr>

                    <?php
                }

                /**
                 * Remove required billing fields
                 */
                static function pmpro_required_billing_fields($fields)
                {
                    unset($fields['bfirstname']);
                    unset($fields['blastname']);
                    unset($fields['baddress1']);
                    unset($fields['bcity']);
                    unset($fields['bstate']);
                    unset($fields['bzipcode']);
                    unset($fields['bphone']);
                    unset($fields['bemail']);
                    unset($fields['bcountry']);
                    unset($fields['CardType']);
                    unset($fields['AccountNumber']);
                    unset($fields['ExpirationMonth']);
                    unset($fields['ExpirationYear']);
                    unset($fields['CVV']);

                    return $fields;
                }

                static function pmpro_gateways_with_pending_status($gateways) {
                    $morder = new MemberOrder();
                    $found = $morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending")));

                    if ((!in_array("senangpay", $gateways)) && $found) {
                        array_push($gateways, "senangpay");
                    } elseif (($key = array_search("senangpay", $gateways)) !== false) {
                        unset($gateways[$key]);
                    }

                    return $gateways;
                }

                /**
                 * Instead of change membership levels, send users to SenangPay payment page.
                 */
                static function pmpro_checkout_before_change_membership_level($user_id, $morder)
                {
                    global $wpdb, $discount_code_id;

                    //if no order, no need to pay
                    if (empty($morder)) {
                        return;
                    }
                    if (empty($morder->code))
                        $morder->code = $morder->getRandomCode();

                    $morder->payment_type = "senangpay";
                    $morder->status = "pending";
                    $morder->user_id = $user_id;
                    $morder->saveOrder();

                    //save discount code use
                    if (!empty($discount_code_id))
                        $wpdb->query("INSERT INTO $wpdb->pmpro_discount_codes_uses (code_id, user_id, order_id, timestamp) VALUES('" . $discount_code_id . "', '" . $user_id . "', '" . $morder->id . "', now())");
                    
                    $morder->Gateway->sendToSenangPay($morder);
                }

                function sendToSenangPay(&$order)
                {
                    global $wp, $current_user;

                    do_action("pmpro_paypalexpress_session_vars");

                    // $params = array();
                    $amount = $order->PaymentAmount;
                    $amount_tax = $order->getTaxForPrice($amount);
                    $amount = round((float)$amount + (float)$amount_tax, 2);
                    
                    $last_order = new MemberOrder();
                    $found_lo = $last_order->getLastMemberOrder(get_current_user_id());

                    if (!$found_lo || $last_order->membership_id != $order->membership_id) {
                        //no order
                        $amount = floatval($order->InitialPayment);
                    }
                    
                    $mode = pmpro_getOption("gateway_environment");
                    if ($mode == 'sandbox') {
                        $secretkey = pmpro_getOption("senangpay_ssk");
						$merchant_id = pmpro_getOption("senangpay_smi");
						$host = "https://sandbox.senangpay.my/payment/" . $merchant_id;
                    } else {
                        $secretkey = pmpro_getOption("senangpay_lsk");
						$merchant_id = pmpro_getOption("senangpay_lmi");
						$host = "https://app.senangpay.my/payment/" . $merchant_id;
                    }
                    if ($secretkey  == '') {
                        echo "Api keys not set";
                    }

                    $senangpay_url = $host . $merchant_id;
					
					$detail = "Payment_for_order_" . $order->code;
					$order_id = $order->code;

					$hashed_string = md5($secretkey.$detail.$amount.$order_id);

					$name = $_POST['full_name'];
					$email = $order->Email;
					$phone = $_POST['mobile'];

					$senangpay_data = array(
						'detail' => $detail,
						'amount' => $amount,
						'order_id' => $order_id,
						'name' => $name,
						'email' => $email,
						'phone' => $phone,
						'hash' => $hashed_string,
					);

					$senangpay_param = "";
			
					$senangpay_param = http_build_query( $senangpay_data );
					
					//Build complete URI for senangPay redirect
					$post_url = "{$host}?{$senangpay_param}";
					
					wp_redirect($post_url);
					exit;
                }

                static function pmpro_pages_shortcode_checkout($content)
                {
                    $morder = new MemberOrder();
                    $found = $morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending")));
                    if ($found) {
                        $morder->Gateway->delete($morder);
                    }

                    if (isset($_REQUEST['error'])) {
                        global $pmpro_msg, $pmpro_msgt;

                        $pmpro_msg = __("IMPORTANT: Something went wrong during the payment. Please try again later or contact the site owner to fix this issue.<br/>" . urldecode($_REQUEST['error']), "pmpro");
                        $pmpro_msgt = "pmpro_error";

                        $content = "<div id='pmpro_message' class='pmpro_message ". $pmpro_msgt . "'>" . $pmpro_msg . "</div>" . $content;
                    }

                    return $content;
                }

                /**
                 * Custom confirmation page
                 */
                public static function pmpro_pages_shortcode_confirmation($content)
                {
                    global $wpdb, $current_user, $pmpro_invoice, $pmpro_currency,$gateway;
                    
                    if(isset($_GET['status_id']) && isset($_GET['order_id']) && isset($_GET['msg']) && isset($_GET['transaction_id']) && isset($_GET['hash']))
                    {
                        $morder =  new MemberOrder($_REQUEST['order_id']);
                        if (!empty($morder) && $morder->gateway == "senangpay") {
                            $pmpro_invoice = $morder;
                        }
                        
                        $morder = $pmpro_invoice;
                        
                        $mode = pmpro_getOption("gateway_environment");
                        if ($mode == 'sandbox') 
                        {
                            $secretkey = pmpro_getOption("senangpay_ssk");
                            $merchant_id = pmpro_getOption("senangpay_smi");
                            $host = "https://sandbox.senangpay.my/apiv1/query_transaction_status/";
                        }
                        else 
                        {
                            $secretkey = pmpro_getOption("senangpay_lsk");
                            $merchant_id = pmpro_getOption("senangpay_lmi");
                            $host = "https://app.senangpay.my/apiv1/query_transaction_status/";
                        }

                        # verify that the data was not tempered, verify the hash
                        $hashed_string = md5($secretkey.urldecode($_GET['status_id']).urldecode($_GET['order_id']).urldecode($_GET['transaction_id']).urldecode($_GET['msg']));
                        
                        # if hash is the same then we know the data is valid
                        if($hashed_string == urldecode($_GET['hash']))
                        {
                            # this is a simple result page showing either the payment was successful or failed. In real life you will need to process the order made by the customer
                            if(urldecode($_GET['status_id']) == '1')
                            {
                                // confirm transaction
                                $transaction_reference = $_GET['transaction_id'];
                                $hash = md5($merchant_id.$secretkey.$transaction_reference);

                                $transaction_array = array(
                                    'merchant_id' => $merchant_id,
                                    'transaction_reference' => $transaction_reference,
                                    'hash' => $hash
                                );

                                $argument = http_build_query( $transaction_array );
                                $post_url = "{$host}?{$argument}";

                                $request_confirmation = wp_remote_get( $post_url );
                                $body_confirmation = wp_remote_retrieve_body( $request_confirmation );
                                $data_confirmation = json_decode($body_confirmation);

                                if( ! empty( $data_confirmation ) ) {
                                    $data_retrieve = $data_confirmation->data;
                                    foreach ($data_retrieve as $data){
                                        $amount_total = $data->order_detail->grand_total;
                                    }
                                }

                                $morder->subtotal = $amount_total;
                                $morder->total = $amount_total;
                                $pmpro_invoice->subtotal = $amount_total;
                                $pmpro_invoice->total = $amount_total;
                                
                                $pmpro_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . (int)$morder->membership_id . "' LIMIT 1");
                                $pmpro_level = apply_filters("pmpro_checkout_level", $pmpro_level);
                                $startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time("mysql") . "'", $morder->user_id, $pmpro_level);

                                do_action('pmpro_after_checkout', $morder->user_id, $morder);

                                //--------------------------------------------------
                                if (strlen($order->subscription_transaction_id) > 3) {
                                    $enddate = "'" . date("Y-m-d", strtotime("+ " . $order->subscription_transaction_id, current_time("timestamp"))) . "'";
                                } elseif (!empty($pmpro_level->expiration_number)) {
                                    $enddate = "'" . date("Y-m-d", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time("timestamp"))) . "'";
                                } else {
                                    $enddate = "NULL";
                                }

                                $custom_level = array(
                                    'user_id'           => $morder->user_id,
                                    'membership_id'     => $pmpro_level->id,
                                    'code_id'           => '',
                                    'initial_payment'   => $amount_total,
                                    'billing_amount'    => $pmpro_level->billing_amount,
                                    'cycle_number'      => $pmpro_level->cycle_number,
                                    'cycle_period'      => $pmpro_level->cycle_period,
                                    'billing_limit'     => $pmpro_level->billing_limit,
                                    'trial_amount'      => $pmpro_level->trial_amount,
                                    'trial_limit'       => $pmpro_level->trial_limit,
                                    'startdate'         => $startdate,
                                    'enddate'           => $enddate
                                );

                                if ($morder->status != 'success') {

                                    if (pmpro_changeMembershipLevel($custom_level, $morder->user_id, 'changed')) {
                                        $morder->membership_id = $pmpro_level->id;
                                        $morder->payment_transaction_id = $_GET['transaction_id'];
                                        $morder->subscription_transaction_id = "SENANGPAY" . $_GET['order_id'];
                                        $morder->status = "success";
                                        $morder->saveOrder();
                                    }

                                }
                                //--------------------------------------------------

                                // setup some values for the emails
                                if (!empty($morder)) {
                                    $pmpro_invoice = new MemberOrder($morder->id);
                                } else {
                                    $pmpro_invoice = null;
                                }

                                $current_user->membership_level = $pmpro_level; //make sure they have the right level info
                                $current_user->membership_level->enddate = $enddate;
                                if ($current_user->ID) {
                                    $current_user->membership_level = pmpro_getMembershipLevelForUser($current_user->ID);
                                }

                                //send email to member
                                $pmproemail = new PMProEmail();
                                $pmproemail->sendCheckoutEmail($current_user, $invoice);

                                //send email to admin
                                $pmproemail = new PMProEmail();
                                $pmproemail->sendCheckoutAdminEmail($current_user, $invoice);

                                $content = "<ul>
                                    <li><strong>".__('Account', SENANGPAY_PMPRO).":</strong> ".$current_user->display_name." (".$current_user->user_email.")</li>
                                    <li><strong>".__('Order', SENANGPAY_PMPRO).":</strong> ".$pmpro_invoice->code."</li>
                                    <li><strong>".__('Membership Level', SENANGPAY_PMPRO).":</strong> ".$pmpro_level->name."</li>
                                    <li><strong>".__('Amount Paid', SENANGPAY_PMPRO).":</strong> ".$pmpro_currency." ".$amount_total."</li>
                                </ul>";
                                ob_start();
                                if (file_exists(get_stylesheet_directory() . "/paid-memberships-pro/pages/confirmation.php")) {
                                    include get_stylesheet_directory() . "/paid-memberships-pro/pages/confirmation.php";
                                } else {
                                    include PMPRO_DIR . "/pages/confirmation.php";
                                }

                                $content .= ob_get_contents();
                                ob_end_clean();
                            }
                            else
                            {
                                $content = 'Payment failed with message: '.urldecode($_GET['msg']);;
                            }
                        }
                        else
                        {
                            $content =  'Hashed value is not correct';
                        }
                    }
                    return $content;
                }

                function cancel(&$order)
                {
                    //no matter what happens below, we're going to cancel the order in our system
                    $order->updateStatus("cancelled");
                    if(empty($order->subscription_transaction_id))
		                return false;
                }

                function delete(&$order)
                {
                    //no matter what happens below, we're going to cancel the order in our system
                    $order->updateStatus("cancelled");
                }
            }
        }
    }
}