<?php

namespace App\Repositories;

use App\Models\StripeOrderDetail;
use Illuminate\Database\Eloquent\Builder;

class StripeOrderDetailRepository
{

    public function modelQuery(): Builder| StripeOrderDetail{
        return StripeOrderDetail::query();
    }

    public function createStripeOrderDetail(int $orderId,string $sessionId):StripeOrderDetail{
        return $this->modelQuery()->create([
            'order_id' => $orderId,
            'session_id' => $sessionId
        ]);
    }
}
