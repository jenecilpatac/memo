<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ExplainApprovalProcessNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

     
    protected $approvalProcess;
    protected $firstname;
    protected $memo;

    protected $userExplainFirstName; 
    protected $userExplainLastName;
    public function __construct($approvalProcess,$firstname,$userExplainFirstName,$userExplainLastName)
    {
        $this->approvalProcess = $approvalProcess;
        $this->firstname = $firstname;
        $this->userExplainFirstName = $userExplainFirstName;
        $this->userExplainLastName = $userExplainLastName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database','broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
       
        return (new MailMessage)
                    ->view('emails.explain_approval_process',[
                        'approvalProcess' => $this->approvalProcess,
                        'firstname' =>$this->firstname,
                        'userExplainFirstName' => $this->userExplainFirstName,
                        'userExplainLastName' =>$this->userExplainLastName,
                        ])
                    ->subject($this->userExplainFirstName.' sent you an explanation for you to check and approve'.now()->format('Y-m-d H:i:s'));
                    
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        
        return [
            'message' => 'Hi '.$this->firstname .' .'.$this->userExplainFirstName.' sent you an explanation for you to check and approve',
            'created_at' => now()->toDateTimeString(),
            'userExplainFirstName' => $this->userExplainFirstName,
            'userExplainLastName' =>$this->userExplainLastName,
            
            //'level' => $this->approvalProcess->level,
            //'status' => $this->approvalProcess->status,
        ];
    }

    public function toBroadcast($notifiable)
    {
       
        return new BroadcastMessage([
            'message' => 'Hi '.$this->firstname .' .'.$this->userExplainFirstName.' sent you an explanation for you to check and approve',
           
        ]);
    }
}
