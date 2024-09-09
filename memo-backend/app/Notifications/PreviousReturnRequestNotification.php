<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use app\Events\NotificationEvent;


class PreviousReturnRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $memo;
    protected $status;
    protected $prevFirstName;
    protected $approverFirstname;
    protected $approverLastname;
    protected $comment;
    protected $requesterFirstname; 
    protected $requesterLastname;

     public function __construct($memo,$status,$prevFirstName,$approverFirstname,$approverLastname,$comment,$requesterFirstname,$requesterLastname)
    {
        $this->memo = $memo;
        $this->status = $status;
        $this->prevFirstName =$prevFirstName;
        $this->approverFirstname =$approverFirstname;
        $this->approverLastname =$approverLastname;
        $this->comment=$comment;
        $this->requesterFirstname=$requesterFirstname;
        $this->requesterLastname=$requesterLastname;
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
                    ->view('emails.previous_return_request',[
                        'memo' => $this->memo,
                        'prevFirstName' =>$this->prevFirstName,
                        'status' => $this->status,
                        'approverFirstname' =>$this->approverFirstname,
                        'approverLastname' =>$this->approverLastname,
                        'comment' =>$this->comment,
                        'requesterFirstname' =>$this->requesterFirstname,
                        'requesterLastname' =>$this->requesterLastname

                        ])
                    ->subject('Return Request Notification - '.$this->memo->re.' '. now()->format('Y-m-d H:i:s'))
                    ->line('The memo' .$this->memo->re.' is ' .$this->status. '.It has now returned to the requester.')
                    ->line('Request Type: '.$this->memo->form_type)
                    ->line('Status:' . $this->status)
                    ->action('Notification Action', url('/'));

    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'This informs you that the memo '. $this->memo->form_type.' requested by ' .$this->requesterFirstname .' ' .$this->requesterLastname.' has been'. $this->status. ' by ' .$this->approverFirstname. ' '.$this->approverLastname,
            'memo' => $this->memo->form_type,
            'status' => $this->status,
            'prevFirstName' =>$this->prevFirstName,
            'approverFirstname' =>$this->approverFirstname,
            'approverLastname' =>$this->approverLastname,
            'comment' =>$this->comment,
            'requesterFirstname' =>$this->requesterFirstname,
            'requesterLastname' =>$this->requesterLastname,
            'created_at' => now()->toDateTimeString(),

        ];
    }

   /*  public function toBroadcast($notifiable)
    {
        //broadcast(new NotificationEvent($this->toArray($notifiable)));
        return new BroadcastMessage([
            'message' => 'The request' .$this->requestForm->form_type.' is ' .$this->status. '.It has now returned to the requester.',
            'form_type' => $this->requestForm->form_type,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
        ]);
    } */

}