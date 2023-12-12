<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendMailJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $mail_view;
    public $user_email;
    public $mail_subject;
    public $user_data;
    public function __construct($mail_view,$user_email,$mail_subject,$user_data)
    {
        $this->mail_view = $mail_view;
        $this->user_email = $user_email;
        $this->mail_subject = $mail_subject;
        $this->user_data = $user_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $mail_view = $this->mail_view;
        $user_email = $this->user_email;
        $mail_subject = $this->mail_subject;
        $user_data = $this->user_data;

        try {
            
            Mail::send($mail_view, ['user_data'=>$user_data], function ($message) use ($user_email,$mail_subject) {
                $message->to($user_email);
                $message->subject($mail_subject);
                
            });
        } catch (Exception $e) {
            
        }
    }
}
