<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewUserNotification;

class StripeServices
{
    public function payment(User $user)
    {
        $result = $user->checkout('price_1QMuQtJYCsvB3c7VvhUDRzln', [
            'phone_number_collection' => ['enabled'=>true],
            'mode'=>'subscription',
            'success_url'=>'https://wa.me/'. str_replace( '+', '', config('twilio.from_phone')),
            'cancel_url'=>'https://wa.me/'. str_replace( '+', '', config('twilio.from_phone')),
        ])->toArray();

        $user->notify(new NewUserNotification(
            $user->name, 
            str_replace("https://checkout.stripe.com/c/pay/", "", $result["url"])
        ));
        return $result;
    }
}