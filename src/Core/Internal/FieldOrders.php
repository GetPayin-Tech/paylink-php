<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Internal;

/**
 * The signed-field registry. Each factory returns the request fields for one
 * endpoint in the EXACT order the server concatenates them when it rebuilds the
 * HMAC signature (the FormRequest `rules()` order, minus `token`/`signature`,
 * and minus `payment_mode` where noted). Getting this order right is the whole
 * job of the SDK — see the source FormRequests under
 * app/Http/Requests/Application/ExternalPaymentIntegration.
 *
 * Rules encoded here, mirroring the server:
 *  - Absent optional fields (null at call time) are skipped entirely,
 *    contributing nothing to the body or the signature.
 *  - `signed: false` fields (payment_mode) are sent in the body but excluded
 *    from the signature.
 *  - `countryStateBlock` appends the per-country state fields after the listed
 *    fields: US -> us_state, postal_code; CA -> canada_state, postal_code.
 */
final class FieldOrders
{
    public static function invoiceCreate(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/v2/integration/init', [
            new FieldSpec('firstName', 'first_name'),
            new FieldSpec('lastName', 'last_name'),
            new FieldSpec('email', 'email'),
            new FieldSpec('orderTitle', 'order_title'),
            new FieldSpec('orderAmount', 'order_amount'),
            new FieldSpec('address', 'address'),
            new FieldSpec('city', 'city'),
            new FieldSpec('country', 'country'),
            new FieldSpec('state', 'state'),
            new FieldSpec('currency', 'currency'),
            new FieldSpec('redirectionUrl', 'redirection_url'),
            new FieldSpec('webhookUrl', 'webhook_url'),
            new FieldSpec('orderDetails', 'order_details'),
            new FieldSpec('paymentMode', 'payment_mode', signed: false),
        ]);
    }

    public static function paymentVoid(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/integration/void', [
            new FieldSpec('invoiceId', 'invoice_id'),
        ]);
    }

    public static function paymentRefund(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/integration/refund', [
            new FieldSpec('invoiceId', 'invoice_id'),
            new FieldSpec('amount', 'amount'),
        ]);
    }

    public static function paymentSettle(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/integration/settle', [
            new FieldSpec('invoiceId', 'invoice_id'),
            new FieldSpec('amount', 'amount'),
        ]);
    }

    public static function paymentReverseAuthorization(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/integration/reverse-authorization', [
            new FieldSpec('invoiceId', 'invoice_id'),
        ]);
    }

    public static function paymentCheckStatus(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/integration/check-status', [
            new FieldSpec('invoiceId', 'invoice_id'),
        ]);
    }

    public static function vccCharge(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/v2/integration/vcc/charge', [
            new FieldSpec('firstName', 'first_name'),
            new FieldSpec('lastName', 'last_name'),
            new FieldSpec('email', 'email'),
            new FieldSpec('phone', 'phone'),
            new FieldSpec('currencyId', 'currency_id'),
            new FieldSpec('price', 'price'),
            new FieldSpec('product', 'product'),
            new FieldSpec('referenceNumber', 'reference_number'),
            new FieldSpec('cardNumber', 'card_number'),
            new FieldSpec('cardExpiryMonth', 'card_expiry_month'),
            new FieldSpec('cardExpiryYear', 'card_expiry_year'),
            new FieldSpec('cardCvv', 'card_cvv'),
            new FieldSpec('country', 'country'),
            new FieldSpec('address', 'address'),
            new FieldSpec('city', 'city'),
        ], countryStateBlock: true);
    }

    public static function cardTokenize(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/v2/integration/tokens/card', [
            new FieldSpec('firstName', 'first_name'),
            new FieldSpec('lastName', 'last_name'),
            new FieldSpec('email', 'email'),
            new FieldSpec('customerReference', 'customer_reference'),
            new FieldSpec('externalReference', 'external_reference'),
            new FieldSpec('cardNumber', 'card_number'),
            new FieldSpec('cardExpiryMonth', 'card_expiry_month'),
            new FieldSpec('cardExpiryYear', 'card_expiry_year'),
            new FieldSpec('cardCvv', 'card_cvv'),
            new FieldSpec('country', 'country'),
            new FieldSpec('address', 'address'),
            new FieldSpec('city', 'city'),
        ], countryStateBlock: true);
    }

    public static function cardCharge(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/v2/integration/tokens/charge', [
            new FieldSpec('cardToken', 'card_token'),
            new FieldSpec('initiator', 'initiator'),
            new FieldSpec('firstName', 'first_name'),
            new FieldSpec('lastName', 'last_name'),
            new FieldSpec('email', 'email'),
            new FieldSpec('currency', 'currency'),
            new FieldSpec('price', 'price'),
            new FieldSpec('product', 'product'),
            new FieldSpec('referenceNumber', 'reference_number'),
            new FieldSpec('country', 'country'),
            new FieldSpec('address', 'address'),
            new FieldSpec('city', 'city'),
        ], countryStateBlock: true);
    }

    public static function cardRevoke(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/v2/integration/tokens/revoke', [
            new FieldSpec('cardToken', 'card_token'),
        ]);
    }

    public static function recurringCreate(): EndpointSpec
    {
        return new EndpointSpec('POST', '/api/v2/integration/recurring/init', [
            new FieldSpec('firstName', 'first_name'),
            new FieldSpec('lastName', 'last_name'),
            new FieldSpec('email', 'email'),
            new FieldSpec('orderTitle', 'order_title'),
            new FieldSpec('orderAmount', 'order_amount'),
            new FieldSpec('currency', 'currency'),
            new FieldSpec('cadenceInterval', 'cadence_interval'),
            new FieldSpec('cadenceCount', 'cadence_count'),
            new FieldSpec('totalCycles', 'total_cycles'),
            new FieldSpec('endDate', 'end_date'),
            new FieldSpec('consentText', 'consent_text'),
            new FieldSpec('externalReference', 'external_reference'),
            new FieldSpec('redirectionUrl', 'redirection_url'),
            new FieldSpec('webhookUrl', 'webhook_url'),
        ]);
    }
}
