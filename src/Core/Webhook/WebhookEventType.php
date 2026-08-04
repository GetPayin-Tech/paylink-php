<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Webhook;

/**
 * Every webhook event type PayLink can deliver. The raw event string lives on
 * {@see WebhookEvent::$event}; use {@see tryFrom()} to resolve it to a case.
 */
enum WebhookEventType: string
{
    case InvoicePaid = 'invoice.paid';
    case InvoiceNotPaid = 'invoice.not_paid';
    case InvoiceVoided = 'invoice.voided';
    case InvoiceRefunded = 'invoice.refunded';
    case InvoiceAuthorizationReversed = 'invoice.authorization_reversed';
    case InvoiceAuthorized = 'invoice.authorized';
    case SubscriptionActivated = 'subscription.activated';
    case SubscriptionCharged = 'subscription.charged';
    case SubscriptionPaymentFailed = 'subscription.payment_failed';
    case SubscriptionSuspended = 'subscription.suspended';
    case SubscriptionCancelled = 'subscription.cancelled';
    case SubscriptionCompleted = 'subscription.completed';
    case InstallmentPlanActivated = 'installment.plan_activated';
    case InstallmentCharged = 'installment.charged';
    case InstallmentPaymentFailed = 'installment.payment_failed';
    case InstallmentPlanSuspended = 'installment.plan_suspended';
    case InstallmentPlanCompleted = 'installment.plan_completed';
    case VccPaymentSuccess = 'vcc.payment_success';
    case VccPaymentFailed = 'vcc.payment_failed';
    case CardTokenChargeSucceeded = 'card_token.charge_succeeded';
    case CardTokenChargeFailed = 'card_token.charge_failed';
}
