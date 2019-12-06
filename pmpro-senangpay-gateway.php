<?php
/*
    Plugin Name: Paid Memberships Pro - SenangPay Payment Gateway Add On
    Description: SenangPay Gateway for Paid Memberships Pro
    Version: 1.0
    Author: Wan @ Zoewebs
    Author URI: https://wmswina.my
*/

define("PMPRO_SENANGPAYGATEWAY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_SENANGPAYGATEWAY_DIR . "/classes/class.pmprogateway_senangpay.php");