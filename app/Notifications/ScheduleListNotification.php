<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsappChannel;
use App\Notifications\Channels\WhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleListNotification extends Notification
{
    use Queueable;

    private string $message = '📅 Comando !agenda

Olá! Aqui está a sua agenda, {{name}}:

✅ Próximos compromissos:
{{tasks}}

💡 Dica: Caso precise adicionar, alterar ou excluir algum compromisso, envie uma mensagem com os detalhes ou entre em contato com o suporte.

🕒 Mantenha sua agenda atualizada para não perder nada importante! 🚀';

    protected $tasks = [];
    protected $name;
    /**
     * Create a new notification instance.
     */
    public function __construct($tasks, $name){
        $this->tasks = $tasks->reduce(function($carry, $item){
            return "{$carry}\n🕒 {$item->desc} as {$item->due_at->format('H:i')} no dia {$item->due_at->format('d/m')}";
        }) ?? '*Sem compromissos agendados*';
        $this->name = $name;
    }

   
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
        $this->message = str_replace("{{name}}", $this->name, $this->message);
        $this->message = str_replace("{{tasks}}", $this->tasks, $this->message);
        return (new WhatsappMessage)
        ->content($this->message);
    }

}
