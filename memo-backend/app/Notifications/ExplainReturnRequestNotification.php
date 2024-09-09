<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use app\Events\NotificationEvent;

class ExplainReturnRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */


     protected $status;

     protected $firstname;
     protected $approverFirstname;
     protected $approverLastname;
     protected $comment;
    public function __construct( $status, $firstname,$approverFirstname,$approverLastname)
    {
        $this->status = $status;
        $this->firstname = $firstname;
        $this->approverFirstname =$approverFirstname;
        $this->approverLastname =$approverLastname;

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
                    ->view('emails.explain_return_request',[
                        'firstname' =>$this->firstname,
                        'status' =>$this->status,
                        'approverFirstname' =>$this->approverFirstname,
                        'approverLastname' =>$this->approverLastname,


                        ])
                    ->subject('Explanation Update - '. now()->format('Y-m-d H:i:s'))
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
            'message' => 'Your explanation has been ' . $this->status . ' by '. $this->approverFirstname.' '. $this->approverLastname,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
            'firstname' =>$this->firstname,
            'approverFirstname' =>$this->approverFirstname,
            'approverLastname' =>$this->approverLastname,
        ];
    }


   /*  public function toBroadcast($notifiable)
    {
        //broadcast(new NotificationEvent($this->toArray($notifiable)));
        return new BroadcastMessage([
            'message' => 'Your request has been returned because it is ' . $this->status,
            'form_type' => $this->requestForm->form_type,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
        ]);
    } */
}