<?php

namespace App\Notifications\Channels;

class WhatsappMessage 
{
    public $content;
    public $content_sid;
    public $variables;

    public function content($content)
    {
        $this->content = $content;
        return $this;
    }
    
    public function content_sid($content_sid)
    {
        $this->content_sid = $content_sid;
        return $this;
    }

    public function variables($variables)
    {
        $this->variables = json_encode($variables);
        return $this;
    }
}