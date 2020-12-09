<?php

function upAffiliateManagerInitGateways()
{
    require_once __DIR__ . '/BankWirePaymentGateway.php';
    require_once __DIR__ . '/PaypalPaymentGateway.php';
    require_once __DIR__ . '/CreditCardPaymentGateway.php';
}

add_action( 'plugins_loaded', 'upAffiliateManagerInitGateways' );

function upAffiliateManagerAddPayments($methods)
{
    $methods[] = 'CreditCardPaymentGateway';
    $methods[] = 'PaypalPaymentGateway';
    $methods[] = 'BankWirePaymentGateway';
    return $methods;
}

