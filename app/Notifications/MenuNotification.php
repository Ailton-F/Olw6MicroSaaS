<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsappChannel;
use App\Notifications\Channels\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MenuNotification extends Notification
{
    use Queueable;
    private string $message = "🌟 Comandos disponíveis no nosso sistema! 🌟

1️⃣ !menu
Exibe a lista completa de comandos e opções disponíveis para facilitar sua navegação. É o seu ponto de partida!

2️⃣ !agenda
Acesse rapidamente sua agenda de compromissos ou eventos. Ideal para se organizar de forma prática e eficiente.

3️⃣ !insights
Receba análises ou informações úteis baseadas nos seus dados. Um comando perfeito para ajudar na tomada de decisões!

4️⃣ !update
Atualize informações, registros ou configurações do sistema. Garanta que tudo esteja sempre atualizado.

💬 Digite qualquer um desses comandos aqui para começar! 🚀

";

    /**
     * Create a new notification instance.
     */
    public function __construct(){}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WhatsappChannel::class];
    }

    public function toWhatsapp($notification)
    {
        return (new WhatsappMessage)
        ->content($this->message);
    }
    
}
