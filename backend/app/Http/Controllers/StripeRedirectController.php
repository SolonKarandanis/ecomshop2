<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placeholder targets for StripeService's success_url/cancel_url — real order
 * status handling lands in issue #8 (Stripe success/cancel -> Order Paid/Failed).
 * These exist now only so route('success')/route('cancel') resolve during checkout.
 */
class StripeRedirectController extends Controller
{
    public function success(Request $request): JsonResponse
    {
        return response()->json(['session_id' => $request->query('session_id')]);
    }

    public function cancel(): JsonResponse
    {
        return response()->json([]);
    }
}
