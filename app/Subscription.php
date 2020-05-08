<?php

namespace App;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Laravel\Cashier\Subscription as CashierSubscription;

use Illuminate\Database\Eloquent\Model;

class Subscription extends CashierSubscription
{
    //
    use UsesSystemConnection;
}
