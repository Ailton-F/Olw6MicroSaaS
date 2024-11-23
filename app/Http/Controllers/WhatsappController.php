<?php

namespace App\Http\Controllers;

use App\Services\StripeServices;
use App\Models\User;
use App\Services\ConversacionalServices;
use App\Services\UserServices;
use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    public function __construct(
        protected UserServices $userServices, 
        protected StripeServices $stripeServices,
        protected ConversacionalServices $conversacionalServices
    ){}
    
    public function new_message(Request $request)
    {
        $phone = "+".$request->post('WaId');
        $user = User::where('phone', '=', $phone)->first();
        
        if(!$user)
        {
            $user = $this->userServices->store($request->all());
        }
        
        if(!$user->subscribed())
        {
            $this->stripeServices->payment($user);
        }

        
        $user->last_wtts_at = now();
        $user->save();
        
        $this->conversacionalServices->setUser($user);
        $this->conversacionalServices->handleIncomingMessage($request->all());
    }
}
