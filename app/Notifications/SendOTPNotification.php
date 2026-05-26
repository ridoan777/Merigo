<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOTPNotification extends Notification
{
    use Queueable;
    
    private $otp;
    private $type;
    private $expiryTime;
    private $name;
    
    public function __construct($otp, $type, $expiryTime, $name = "User")
    {
        $this->otp = $otp;
        $this->type = $type;
        $this->expiryTime = $expiryTime;
        $this->name = $name;
    }
    
    public function via($notifiable)
    {
        return ['mail'];
    }
    
    public function toMail($notifiable)
    {
        $subject = $this->type === 'email_verification' 
            ? 'Verify Your Email Address' 
            : 'Reset Your Password';
            
        $message = $this->type === 'email_verification'
            ? 'Your email change verification OTP is:'
            : 'Your password reset OTP is:';
        
        // Get the name - could be from notifiable or a default
        // $name = is_object($notifiable) && isset($notifiable->name) 
        //     ? $notifiable->name 
        //     : 'User';
        
        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $this->name . '!')
            ->line($message)
            ->line('**' . $this->otp . '**')
            ->line("This OTP will expire in {$this->expiryTime} minutes.")
            ->line('If you did not request this, please ignore this email.');
    }
}