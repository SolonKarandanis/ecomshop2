<?php

namespace App\Http\Controllers;

use App\Data\LabelsData;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodRepository;
use Spatie\LaravelData\DataCollection;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodRepository $paymentMethodRepository,
    ) {}

    public function index(): DataCollection
    {
        $methods = $this->paymentMethodRepository->findActive()
            ->map(fn (PaymentMethod $method) => LabelsData::fromPaymentMethod($method));

        return LabelsData::collect($methods, DataCollection::class)->wrap('data');
    }
}
