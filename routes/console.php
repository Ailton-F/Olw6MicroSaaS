<?php

use App\Models\Task;
use App\Notifications\GenericNotification;
use App\Notifications\ReminderNotificaiton;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('send:reminder', function () {
    $now = now();
    $tasks = Task::with('users')
    ->whereBetween('reminder_at', [$now->subMinute()->toDateTimeString(), $now->addMinute()->toDateTimeString()]);

    $tasks_get = $tasks->get();
    ds($tasks_get);

    foreach ($tasks_get as $task) {
        if($task->users->last_wtts_at->diffInHours(now())>=24){
            $task->users->notify(new ReminderNotificaiton());
        } else {
            $message = "Lembrete de tarefa: {$task->desc}";
            $task->users->notify(new GenericNotification($message));
        }
        
    }
})->everyMinute();
