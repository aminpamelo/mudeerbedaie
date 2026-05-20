<?php

namespace App\Policies;

use App\Models\ProductOrder;
use App\Models\User;

class ProductOrderPolicy
{
    public function confirmPayment(User $user, ProductOrder $order): bool
    {
        return $user->hasRole('accountant')
            && $order->payment_status === 'pending'
            && $order->payment_method === 'cod';
    }
}
