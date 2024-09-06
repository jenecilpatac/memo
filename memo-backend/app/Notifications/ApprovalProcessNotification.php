<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Events\NotificationEvent;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\InteractsWithSockets;

class ApprovalProcessNotification extends Notification
{
    use Queueable,InteractsWithSockets;

    /**
     * Create a new notification instance.
     */

     
    protected $approvalProcess;
    protected $firstname;
    protected $memo;

    protected $toFirstname; 
    protected $toLastname;
    public function __construct($approvalProcess,$firstname,$memo,$toFirstname,$toLastname)
    {
        $this->approvalProcess = $approvalProcess;
        $this->firstname = $firstname;
        $this->memo = $memo;
        $this->toFirstname = $toFirstname;
        $this->toLastname = $toLastname;
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
                    ->view('emails.approval_process',[
                        'approvalProcess' => $this->approvalProcess,
                        'firstname' =>$this->firstname,
                        'toFirstname' => $this->toFirstname,
                        'toLastname' =>$this->toLastname,
                        ])
                    ->subject('You have a new memo to approve. Subject: '.$this->memo->re . ' '.now()->format('Y-m-d H:i:s'))
                    ->line('You have a new memo to approve.')
                    ->line('Re : '. $this->approvalProcess->memo->re);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        
        return [
            'message' => 'You have a new memo to approve',         
            'memo_id' => $this->approvalProcess->memo->re,
            'created_at' => now()->toDateTimeString(),
            'toFirstname' => $this->toFirstname,
            'toLastname' =>$this->toLastname,
            
            //'level' => $this->approvalProcess->level,
            //'status' => $this->approvalProcess->status,
        ];
    }

  
}