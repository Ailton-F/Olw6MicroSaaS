<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GenericNotification;
use App\Notifications\MenuNotification;
use App\Notifications\ScheduleListNotification;
use Exception;
use OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;

class ConversacionalServices 
{
    protected User $user;
    protected $client;
    protected array $commands = [
        '!menu' => 'showMenu',
        '!agenda'=>'showSchedule',
        '!insights'=>'showInsights',
        '!update'=>'showUpdate',
    ];

    public function __construct()
    {
        if(config('app.env')=='testing'){
            $this->client = new ClientFake([
                CreateResponse::fake([
                    'choices' => [
                        [
                            'text' => 'awesome!',
                        ],
                    ],
                ]),
            ]);
        } else {
            $this->client = OpenAI::client(config('openia.token'));
        }

    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function handleIncomingMessage($message_data)
    {
        $message_text = $message_data['Body'];

        if(array_key_exists(strtolower($message_text), $this->commands))
        {
            $handler = $this->commands[strtolower($message_text)];
            return $this->{$handler}(); 
        }
        if(empty($this->user->memory))
        {
            $now = now();
            $messages = [
                ['role'=>'user', 'content'=>"Aja como um assistente pessoal, hoje é $now, se for necessário faça mais perguntas para poder enteder melhro a situação."],
                ['role'=>'user', 'content'=>$message_text   ]
            ];
        } else {
            $messages = $this->user->memory;
            $messages[] = ['role'=>'user', 'content'=>$message_text];
        }

        $this->talkToGpt($messages);
    }

    public function showMenu()
    {
        $this->user->notify(new MenuNotification());
    }

    public function showSchedule()
    {
        $tasks = $this->user->tasks()->where('due_at', '>', now())
        ->orderBy('due_at')
        ->get();
        
        ds($tasks);

        $this->user->notify(new ScheduleListNotification($tasks, $this->user->name));
    }

    public function createUserTask($desc, $due_at, $meta, $reminder_at="", $additional_info="")
    {
        $data = [
            'desc' => $desc,
            'due_at' => $due_at,
            'meta' => $meta,
            'reminder_at' => $reminder_at,
            'additional_info' => $additional_info
        ];
        
        return $this->user->tasks()->create($data);
    }

    public function updateUserTask(...$params)
    {   
        $task = $this->user->tasks()->find($params['task_id']);

        if($task)
        {
            return $task->update($params);
        }

        return 'Task não encontrado, talvez o usuário tenha passado o id errado';
    }

    public function getUserRoutine($days=30)
    {
        return $this->user->tasks()->where('due_at', '>', now()->subDays($days))->get();
    }

    public function showInsights()
    {
        $now = now();
        $messages = [
            ['role'=>'user', 'content'=>"Aja como um assistente pessoal, hoje é $now, se for necessário faça mais perguntas para poder enteder melhro a situação."],
            ['role'=>'function', 'name'=>'getUserRoutine', 'content'=> $this->getUserRoutine()->toJson()]
        ];

        $this->talkToGpt($messages);
    }

    public function showUpdate()
    {
        $tasks = $this->user->tasks()->where('due_at', '>', now())->get();
        $message = "Aqui está a lista de tarefas que você pode atualizar: \n\n";
        $message .= $tasks->reduce(function($carry, $item){
            return "\n*ID: {$item->id}*: {$carry}🕒 {$item->desc} as {$item->due_at->format('H:i')} no dia {$item->due_at->format('d/m')}";
        });
        $message .= "\n\nDigite o ID da tarefa que deseja atualizar";
        $now = now();
        $this->user->memory = [
            ['role'=>'user', 'content'=>"Aja como um assistente pessoal, hoje é $now, se for necessário faça mais perguntas para poder enteder melhro a situação."],
            ['role'=>'assistant', 'content'=>$message]
        ];
        $this->user->save();
        $this->user->notify(new GenericNotification($message));
    }
    
    public function talkToGpt($messages, $clear_memory=false)
    {
        $result = $this->client->chat()->create([
            'model'=>'gpt-4o',
            'messages'=>$messages,
            'functions'=>[
                [
                    'name'=>'createUserTask',
                    'description'=>'Cria uma tarefa para um usuário',
                    'parameters'=>[
                        'type' => 'object',
                        'properties' =>[
                            'desc' =>[
                                'type' => 'string',
                                'description' => 'Nome da tarefa solicitada pelo usuário'
                            ],
                            'due_at'=>[
                                'type' => 'string',
                                'description' => 'Data e hora da tarefa solicitada pelo usuário, no formato Y-m-d H:i:s'
                            ],
                            'meta'=>[
                                'type' => 'string',
                                'description' => 'Metadados da tarefa do usuário que o chatgpt ache interessante para posteriormente gerar insights sobre a rotina do usuário. Ex.: Reunião de negócios; Discurssão de projetos'
                            ],
                            'reminder_at'=>[
                                'type'=> 'string',
                                'description' => 'Data e hora do lembrete da tarefa em si no formato Y-m-d H:i:s'   
                            ],
                            'additional_info'=>[
                                'type'=> 'string',
                                'description' => 'Informações que podem ou não serem solicitadas ao usuário'
                            ]
                        ],
                        'required'=>['desc', 'due_at', 'meta',]
                    ],
                ],
                [
                    'name'=>'updateUserTask',
                    'description'=>'atualiza uma tarefa para o usuário',
                    'parameters'=>[
                        'type'=> 'object',
                        'properties'=>[
                            'task_id'=>[
                                'type'=> 'integer',
                                'descriptions'=>'ID da tarefa do usuário'
                            ],
                            'desc' =>[
                                'type' => 'string',
                                'description' => 'Nome da tarefa solicitada pelo usuário'
                            ],
                            'due_at'=>[
                                'type' => 'string',
                                'description' => 'Data e hora da tarefa solicitada pelo usuário,no formato Y-m-d H:i:s'
                            ],
                            'meta'=>[
                                'type' => 'string',
                                'description' => 'Metadados da tarefa do usuário que o chatgpt ache interessante para posteriormente gerar insights sobre a rotina do usuário. Ex.: Reunião de negócios; Discurssão de projetos'
                            ],
                            'reminder_at'=>[
                                'type'=> 'string',
                                'description' => 'Data e hora do lembrete da tarefa em si no formato Y-m-d H:i:s'   
                            ],
                            'additional_info'=>[
                                'type'=> 'string',
                                'description' => 'Informações que podem ou não serem solicitadas ao usuário'
                            ]

                        ],
                        'required'=>['desc', 'due_at', 'meta','task_id']
                    ],
                ],
                [
                    'name'=>'getUserRoutine',
                    'description'=>'recupera as tarefas de um usuario dos ultimos dias, a quantidade de dias passado por parametro, sendo 30 dias o limite de recuperação, também gera insights e ideias sobre as rotinas',
                    'parameters'=>[
                        'type'=> 'object',
                        'properties'=>[
                            'days'=>[
                                'type' => 'integer',
                                'description' => 'Quantidade de dias atras para recuperar as tarefas'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        if(!isset($result->choices[0]->message->functionCall))
        {
            if(!$clear_memory)
            {
                $messages[] = $result->choices[0]->message;     
                $this->user->memory = $messages;
            } else {
                $this->user->memory = null;
            }

            $this->user->save();
            return $this->user->notify(new GenericNotification($result->choices[0]->message->content));
        }

        $function_name = $result->choices[0]->message->functionCall->name;
        $arguments = json_decode($result->choices[0]->message->functionCall->arguments, true);
        $messages[]=[
            'role' => 'assistant',
            'content'=>"",
            'function_call'=>[
                'name'=>$function_name,
                'arguments'=>$result->choices[0]->message->functionCall->arguments
            ]
        ];

        if(!method_exists($this, $function_name)){
            throw new Exception("function {$function_name} not found");
        }

        $result = $this->{$function_name}(...$arguments);
        $messages[] = [
            'role'=>'function',
            'name'=>$function_name,
            'content'=>json_encode($result)
        ];

        $this->talkToGpt($messages, true);
    }
}