# Customer email delivery

Future business events enqueue encrypted, deduplicated messages on the existing
`messaging` database connection. No historical backfill runs on activation.

- Customer verification: one welcome message per customer/email address.
- Submitted web/mobile booking: confirmation after its final OBR reference exists.
- Booking conversion: approval with the resulting customer shipment link.
- New active payment receipt: amount, original currency, method, receipt reference,
  and a link to authenticated receipt downloads and remaining balance. Each
  installment has its own email. This does not claim the entire bill is paid.
- Pending, failed, review, refunded, voided, and zero-value payments do not trigger
  a payment confirmation. Receipt updates do not resend the original email.

Messages use the shipment/booking owner's account email, never the cashier's or
recipient's phone/contact details. Receipt events inside a transaction roll back
their outbox entries with the transaction. Queue dispatch waits for commit.

## Operations

Enable Email in the existing `/messaging` settings only after checking SMTP and
starting the dedicated `nwc-messaging-notifications` Supervisor worker. Its
configuration is in `deploy/supervisor/nwc-messaging-notifications.conf`.
Keep `nwc-messaging-otp` running separately so notifications cannot occupy the
OTP worker. Bulk campaigns are not enabled or launched by this setup.

The existing rate limits, expiry checks, atomic send claims, and recovery command
apply. SMTP acceptance is not proof of inbox delivery. Ambiguous transport errors
are marked `unknown`, not automatically resent, to avoid duplicate messages.
Investigate these using mail-provider logs before manually retrying.

Disabling Email prevents new lifecycle emails and suppresses queued ones. It does
not change legacy password-reset/recovery mail behavior or legacy notification
gateway settings. Portal OTP retains its existing synchronous fallback while
queued email is disabled. This deployment does not rewrite other mail templates.

Verification: `vendor/bin/phpunit --filter 'CustomerEmailsTest|MessagingTest|ShipmentPaymentDocumentsTest|LipilaPaymentsTest|CustomerPortal'`.
