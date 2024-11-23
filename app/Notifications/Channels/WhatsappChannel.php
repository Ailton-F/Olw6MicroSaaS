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

        $messages = $this->split_message($message->content);
        $sent = [];
        foreach($messages as $msg)
        {
            $sent[] = $twilio->messages->create(
                'whatsapp:'.$to,
                [
                    'from' => 'whatsapp:'.$from,
                    'body' => $msg
                ]
            );
        }

        return $sent;
    }  

    protected function split_message($message, $max_len=1600)
    {
        $parts = [];    
        $lines = explode("\n", $message);
        $current_part = '';

        foreach($lines as $line)
        {
            if(mb_strlen($line) > $max_len)
            {
                if(!empty($current_part))
                {
                    $parts[] = $current_part;
                    $current_part = '';
                }

                $words = explode(" ", $line);
                $temp_line = '';

                foreach($words as $word)
                {
                    if(mb_strlen($temp_line.' '.$word)<=$max_len)
                    {
                        $temp_line .= (empty($temp_line) ? '':'').$word;
                    } else {
                        if(!empty($temp_line))
                        {
                            $parts[] = $temp_line;
                        }

                        if(mb_strlen($word) > $max_len)
                        {
                            $parts = array_merge($parts, str_split($word, $max_len));
                        } else {
                            $temp_line = $word;
                        }
                    }
                }

                if(!empty($temp_line))
                {
                    $current_part = $temp_line;
                }
            } else {
                if(mb_strlen($current_part .  (!empty($current_part) ? "\n":'').$line) > $max_len)
                {
                    $parts[]=$current_part;
                    $current_part=$line;
                } else {
                    $current_part .= (!empty($current_part) ? "\n":'').$line;
                }
            }
        }

        if(!empty($current_part))
        {
            $parts[]=$current_part;
        }
        
        return $parts;
    }
}