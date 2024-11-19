<?php

namespace App\Notifications\Channels;

use App\Notifications\NewUserNotification;
use Illuminate\Notifications\Notification;
use Twilio\Rest\Client;

class WhatsappChannel 
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification)
    {
        $message = $notification->toWhatsapp($notifiable);
        $to = $notifiable->RouteNotificationFor('WhatsApp');
        $from = config('twilio.from_phone');
        $twilio = new Client(config('twilio.account_sid'), config('twilio.auth_token'));

        if($message->content_sid){
            return $twilio->messages->create(
                'whatsapp:'.$to,
                [
                    'from' => 'whatsapp:'.$from,
                    'contentSid' => $message->content_sid,
                    'contentVariables' => $message->variables
                ]
            );
        }

        return $twilio->messages->create(
            'whatsapp:'.$to,
            [
                'from' => 'whatsapp:'.$from,
                'body' => $message->content
            ]
        );
    }   
}