<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use app\Events\NotificationEvent;

class ReturnRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

     protected $memo;
     protected $status;

     protected $firstname;
     protected $approverFirstname;
     protected $approverLastname;
     protected $comment;
    public function __construct($memo, $status, $firstname,$approverFirstname,$approverLastname,$comment)
    {
        $this->memo = $memo;
        $this->status = $status;
        $this->firstname = $firstname;
        $this->approverFirstname =$approverFirstname;
        $this->approverLastname =$approverLastname;
        $this->comment=$comment;

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
        $approvalUrl = route('view.single.request.form.for.approval', ['request_form_id' => $this->memo->id]);
                    return (new MailMessage)
                    ->view('emails.return_request',[
                        'memo' => $this->memo,
                        'firstname' =>$this->firstname,
                        'approvalUrl' => $approvalUrl,
                        'status' =>$this->status,
                        'approverFirstname' =>$this->approverFirstname,
                        'approverLastname' =>$this->approverLastname,
                        'comment' =>$this->comment


                        ])
                    ->subject('Memo Update - '.$this->memo->re.' '. now()->format('Y-m-d H:i:s'))
                    ->line('Your memo has been returned because it is ' . $this->status);

    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approvalUrl = route('view.single.request.form.for.approval', ['request_form_id' => $this->memo->id]);
        return [
            'message' => 'Your memo'. $this->memo->re.' has been returned because it is ' . $this->status . ' by '. $this->approverFirstname.' '. $this->approverLastname,
            'memo_re' => $this->memo->re,
            'status' => $this->status,
            'created_at' => now()->toDateTimeString(),
            'firstname' =>$this->firstname,
            'approvalUrl' => $approvalUrl,
            'approverFirstname' =>$this->approverFirstname,
            'approverLastname' =>$this->approverLastname,
            'comment' =>$this->comment
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