<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Events\NotificationEvent;

class ExplainEmployeeNotification extends Notification
{
    use Notifiable, Queueable;

    /**
     * Create a new notification instance.
     */

     protected $memo;

     protected $status;
     protected $firstname;


    public function __construct($memo, $status, $firstname)
    {
        $this->memo = $memo;
        $this->status = $status;
        $this->firstname =$firstname;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail','database','broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage) 
                    ->view('emails.explain_employee',[
                        'request_form' => $this->memo,
                        'status' => $this->status,
                        'firstname' =>$this->firstname,
                        'memo' =>$this->memo->re,
                    ])
                    ->subject('Explanation Update - '.$this->memo->re.' '. now()->format('Y-m-d H:i:s'))
                    ->line('Your explanation has been ' . $this->status);
        
    }

    /**
     * Get the array representation of the notification. 
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your explanation has been ' . $this->status . ' successfully by the HR Department',
            'form_type' => $this->memo->re,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
        ];
    }

  /*   public function toBroadcast($notifiable)
    {
        //broadcast(new NotificationEvent($this->toArray($notifiable)));
        return new BroadcastMessage([
            'message' => 'Your request has been ' . $this->status,
            'form_type' => $this->requestForm->form_type,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
        ]);
    } */
}