<?php

declare(strict_types=1);

use GetPayin\Paylink\Core\Internal\FieldOrders;
use GetPayin\Paylink\Core\Internal\SignRequest;
use GetPayin\Paylink\Core\Signature;
use GetPayin\Paylink\Tests\Support\FakeTransport;

/*
 * These vectors are generated from the PayLink server's own signing primitive
 * (test/fixtures/generate-golden.php) and are the SAME file the JS SDK verifies
 * against, so a passing run proves byte-for-byte parity across server, JS, and
 * PHP.
 */

it('reproduces the server signature from the ordered values', function (array $case): void {
    expect(Signature::build($case['values'], $case['hashToken']))->toBe($case['expected']);
})->with(goldenCases());

it('signs endpoint input identically to the server', function (array $case): void {
    $spec = match ($case['endpoint']) {
        'INVOICE_CREATE' => FieldOrders::invoiceCreate(),
        'PAYMENT_VOID' => FieldOrders::paymentVoid(),
        'PAYMENT_REFUND' => FieldOrders::paymentRefund(),
        'PAYMENT_SETTLE' => FieldOrders::paymentSettle(),
        'PAYMENT_REVERSE_AUTHORIZATION' => FieldOrders::paymentReverseAuthorization(),
        'PAYMENT_CHECK_STATUS' => FieldOrders::paymentCheckStatus(),
        'VCC_CHARGE' => FieldOrders::vccCharge(),
        'CARD_TOKENIZE' => FieldOrders::cardTokenize(),
        'CARD_CHARGE' => FieldOrders::cardCharge(),
        'CARD_REVOKE' => FieldOrders::cardRevoke(),
        'RECURRING_CREATE' => FieldOrders::recurringCreate(),
    };

    $body = SignRequest::build($spec, $case['input'], 'public_token', $case['hashToken']);

    expect($body['signature'])->toBe($case['expected'])
        ->and($body['token'])->toBe('public_token');
})->with(goldenCasesFor([
    'INVOICE_CREATE', 'PAYMENT_VOID', 'PAYMENT_REFUND', 'PAYMENT_SETTLE',
    'PAYMENT_REVERSE_AUTHORIZATION', 'PAYMENT_CHECK_STATUS', 'VCC_CHARGE',
    'CARD_TOKENIZE', 'CARD_CHARGE', 'CARD_REVOKE', 'RECURRING_CREATE',
]));

it('signs a recurring mandate action from the uid alone', function (array $case): void {
    expect(Signature::build([$case['input']['mandateId']], $case['hashToken']))->toBe($case['expected']);
})->with(goldenCasesFor(['RECURRING_MANDATE_ACTION']));

it('verifies a webhook signed exactly like the server', function (array $case): void {
    $client = makeClient(new FakeTransport, $case['hashToken']);
    $payload = $case['input'] + ['signature' => $case['expected']];

    expect($client->webhooks->isValid($payload))->toBeTrue();
})->with(goldenCasesFor(['WEBHOOK']));
