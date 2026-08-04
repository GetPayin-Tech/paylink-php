<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Internal\FieldOrders;
use GetPayin\Paylink\Core\Internal\SignRequest;

it('drops absent optional fields from the body and the signature', function (): void {
    $body = SignRequest::build(FieldOrders::invoiceCreate(), [
        'firstName' => 'Jane', 'lastName' => 'Roe', 'email' => 'jane@example.com',
        'orderTitle' => 'Basic', 'orderAmount' => '100.00', 'currency' => 'EGP',
    ], 'pub', 'secret');

    expect($body)->not->toHaveKey('address')
        ->and($body)->not->toHaveKey('city')
        ->and($body['first_name'])->toBe('Jane')
        ->and($body['order_amount'])->toBe('100.00')
        ->and($body)->toHaveKeys(['token', 'signature']);
});

it('sends payment_mode in the body but excludes it from the signature', function (): void {
    $base = [
        'firstName' => 'A', 'lastName' => 'B', 'email' => 'a@b.co',
        'orderTitle' => 'X', 'orderAmount' => '5', 'currency' => 'USD',
    ];

    $withMode = SignRequest::build(FieldOrders::invoiceCreate(), $base + ['paymentMode' => 'authorize'], 'pub', 'secret');
    $withoutMode = SignRequest::build(FieldOrders::invoiceCreate(), $base, 'pub', 'secret');

    expect($withMode['payment_mode'])->toBe('authorize')
        ->and($withMode['signature'])->toBe($withoutMode['signature']);
});

it('appends the US state block after the listed fields', function (): void {
    $body = SignRequest::build(FieldOrders::vccCharge(), [
        'firstName' => 'Sam', 'lastName' => 'Smith', 'currencyId' => 1, 'price' => 100,
        'product' => 'Widget', 'cardNumber' => '4111111111111111', 'cardExpiryMonth' => '12',
        'cardExpiryYear' => '2030', 'country' => 'US', 'address' => '5 Ave', 'city' => 'NYC',
        'usState' => 'NY', 'postalCode' => '10001',
    ], 'pub', 'secret');

    expect($body['us_state'])->toBe('NY')
        ->and($body['postal_code'])->toBe('10001')
        ->and($body)->not->toHaveKey('canada_state');
});

it('omits the state block for countries other than US and CA', function (): void {
    $body = SignRequest::build(FieldOrders::vccCharge(), [
        'firstName' => 'Ali', 'lastName' => 'Hassan', 'currencyId' => 2, 'price' => '75',
        'product' => 'Book', 'cardNumber' => '4111111111111111', 'cardExpiryMonth' => '01',
        'cardExpiryYear' => '2031', 'country' => 'EG', 'address' => 'Nile St', 'city' => 'Giza',
    ], 'pub', 'secret');

    expect($body)->not->toHaveKey('us_state')
        ->and($body)->not->toHaveKey('postal_code');
});
