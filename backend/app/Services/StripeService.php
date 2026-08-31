<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeService
{

    public function __construct(){
        Stripe::setApiKey(config('app.stripe_secret_key'));
    }

    /**
     * @throws PaymentException
     */
    public function createSession(array $line_items):Session{
        try {
            /** @var array[] $line_items */
            return Session::create([
                'payment_method_types' => ['card'],
                'customer_email' => (string) auth()->user()->email,
                'line_items'=>$line_items,
                'mode'=>'payment',
                'success_url'=>route('success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'=>route('cancel'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error($e);
            throw PaymentException::createStipeSession();
        }
    }


    /**
     * @throws PaymentException
     */
    public function retrieveSession(string $sessionId):Session{
        try {
            return Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            Log::error($e);
            throw PaymentException::retrieveStipeSession();
        }
    }
}
