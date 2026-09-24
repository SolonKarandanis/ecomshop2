# A Stripe `checkout.session.completed` webhook finalizes the payment; the browser redirect only displays the result

Today, the only thing that moves an Order out of Payment Status `Pending` is the Buyer's browser landing on `GET /api/success?session_id=...`. `StripeRedirectController::success` then calls `OrderService::successOrFailStripeOrder()`, which retrieves the Stripe session and marks the Order Paid or Failed. If the Buyer pays and then closes the tab, loses connection, or Stripe's redirect never completes, the Order stays `Pending` forever: the money has been taken, but no Order is marked Paid, and neither the Buyer nor the Supplier is notified.

We add a Stripe webhook endpoint, `POST /api/stripe/webhook`, subscribed to `checkout.session.completed`, `checkout.session.async_payment_succeeded` and `checkout.session.async_payment_failed`. It becomes the source of truth for payment status:

- **Authentication is Stripe's signature, not the session.** The endpoint sits outside `auth:sanctum` and verifies the `Stripe-Signature` header against `STRIPE_WEBHOOK_SECRET` via `Stripe\Webhook::constructEvent()`. Unsigned or badly signed requests get a 400. The route is exempt from CSRF, which `statefulApi()` otherwise applies to requests from stateful domains.
- **The Order is found by session id, not by "the user's latest order".** The webhook resolves the Order through `stripe_order_details.session_id`, which `StripePaymentHandler` already writes at checkout. The success endpoint switches to the same lookup (scoped to the authenticated Buyer), replacing `getUsersLatestOrder()`, which picks the wrong Order when a Buyer has more than one checkout in flight.
- **One idempotent transition, shared by both paths.** The webhook and the success redirect both call the same `OrderService` transition. Its existing `Pending`-status guard stays, but it now locks the Order row (`lockForUpdate()` inside the transaction). The webhook and the redirect routinely arrive within milliseconds of each other, and without the lock both can read `Pending` and send the Notifications twice.
- **The webhook always acknowledges quickly.** It returns 2xx once the event is verified and handled, including for events it ignores or Orders already finalized, so Stripe doesn't retry. Stripe's retries (up to three days) cover transient failures.

The success redirect stays, because the Buyer needs to see a result. It still calls the shared transition, so a Buyer who arrives before the webhook sees `Paid` straight away rather than `Pending`.

The redirect URLs themselves point at the Nuxt frontend, not the API. `success_url` and `cancel_url` become `{FRONTEND_URL}/success?session_id={CHECKOUT_SESSION_ID}` and `{FRONTEND_URL}/cancel`, built from a new `app.frontend_url` config value rather than `route('success')`. The Nuxt pages then call `GET /api/success` and `GET /api/cancel`, as issue #36 already plans. Before this change, Stripe sent the Buyer's browser straight to the JSON API response. `CashOnDeliveryPaymentHandler` returns the same frontend success URL.

## Considered options

- **Keep the redirect as the only confirmation (rejected)**: this loses paid Orders whenever the redirect doesn't complete, which Stripe's own documentation says is common enough that fulfillment must not depend on it.
- **Webhook only, and the success page polls until the Order leaves `Pending` (rejected)**: this is cleaner in principle, but it adds polling to the frontend and a visible delay for every Buyer, while shared idempotent handling gets the same correctness for free.
- **A scheduled job that reconciles `Pending` Orders against Stripe (rejected as the primary mechanism)**: it has hours of latency unless it runs very often, and it rebuilds what Stripe's webhook retries already provide. It could still be added later as a safety net.
- **Laravel Cashier (rejected)**: it is built around subscriptions and a `Billable` customer model. For one-off Checkout Sessions, a single controller using `stripe-php`, which is already installed, is less machinery.
