<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Notifications\MenuNotification;
use App\Notifications\NewUserNotification;
use App\Notifications\ScheduleListNotification;
use App\Services\ConversacionalServices;
use Illuminate\Support\Facades\Notification;
use Twilio\Security\RequestValidator;

function generateTwilioSignature($url, $data)
{
    $validator = new RequestValidator(config('twilio.auth_token'));
    return $validator->computeSignature($url, $data);
}

test('new message creates user if not exists', function () {
    $phone = "5547989025033";
    $profileName = "Teste User";

    $request = [
        'From' => 'whatsapp:+' . $phone,
        'ProfileName' => $profileName,
        'WaId' => $phone,
        'To' => config('twilio.from'),
        'Body' => 'Teste'
    ];

    $signature = generateTwilioSignature(config('twilio.new_message_url'), $request);
    $response = $this->withHeaders([
        'X-Twilio-Signature' => $signature,
    ])->postJson('/api/new_message', $request);
    $response->assertStatus(200);
    $this->assertDatabaseHas('users', [
        'phone' => "+" . $phone,
        'name' => $profileName,
    ]);
});

test('unsubscribed user receives subscription link', function (){
    Notification::fake();
    $user = User::factory()->create();
    $request = [
        'From' => 'whatsapp:+' . $user->phone,
        'ProfileName' => $user->name,
        'WaId' => str_replace('+', '', $user->phone),
        'To' => config('twilio.from'),
        'Body' => 'Teste'
    ];
    $signature = generateTwilioSignature(config('twilio.new_message_url'), $request);
    $response = $this->withHeaders([
        'X-Twilio-Signature' => $signature,
    ])->postJson('/api/new_message', $request);
    $response->assertStatus(200);
    Notification::assertSentTo($user, NewUserNotification::class);
});

test('handle menu command', function (){
    Notification::fake();
    $user = User::factory()->create();
    $service = new ConversacionalServices();
    $service->setUser($user);
    $service->handleIncomingMessage(['Body'=>'!menu']);
    Notification::assertSentTo($user, MenuNotification::class);
});

test('handle agenda command', function (){
    Notification::fake();
    $user = User::factory()->create();
    $service = new ConversacionalServices();
    $service->setUser($user);
    $service->handleIncomingMessage(['Body'=>'!agenda']);
    Notification::assertSentTo($user, ScheduleListNotification::class);
});

test('handle insights command', function (){
    Notification::fake();
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'due_at' => now()->addDay(),
    ]);

    $service = new ConversacionalServices();
    $service->setUser($user);
    $service->handleIncomingMessage(['Body'=>'!insights']);
    Notification::assertSentTo($user, GenericNotification::class);
});

test('create task with success', function (){
    $user = User::factory()->create();
    $service = new ConversacionalServices();

    $service->setUser($user);

    $task = [
        'desc'=>'test task',
        'due_at' => now()->addDay(),
        'meta'=>'teste',
        'reminder_at' => now()->addDay()->subMinutes(30)
    ];

    $task = $service->createUserTask(...$task);
    $this->assertDatabaseHas('tasks', [
        'id'=>$task->id,
        'desc'=>'test task',
        'user_id'=>$task->user_id,
        'meta'=>'teste',
        'due_at'=>now()->addDay(),
        'reminder_at'=>now()->addDay()->subMinutes(30)
    ]);
});

test('update task with success', function (){
    $user = User::factory()->create();

    $old_task = Task::factory()->create([
        'user_id'=>$user->id,
        'desc'=>'test task',
        'due_at' => now()->addDay(),
        'meta'=>'teste',
        'reminder_at' => now()->addDay()->subMinutes(30)
    ]);
    
    
    $service = new ConversacionalServices();
    $service->setUser($user);

    $updated_task = [
        'task_id'=>$old_task->id,
        'due_at' => now()->addDay(2),
        'reminder_at' => now()->addDay()->subMinutes(45),
        'desc'=>'new desc',
        'meta'=>'update'
    ];

    $task = $service->updateUserTask(...$updated_task);

    $this->assertDatabaseHas('tasks', [
        'id'=>$old_task->id,
        'user_id'=>$user->id,
        'due_at'=>$updated_task['due_at'],
        'reminder_at'=>$updated_task['reminder_at'],
        'desc'=>$updated_task['desc'],
        'meta'=>$updated_task['meta'],
    ]);

    $this->assertDatabaseMissing('tasks', [
        'id'=>$old_task->id,
        'desc'=>'test task',
        'due_at' => now()->addDay(),
        'meta'=>'teste',
        'reminder_at' => now()->addDay()->subMinutes(30)
    ]);
});