<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $data;

    public function __construct($data)
    {
        //
        $this->data = $data;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        

        return $this->view('verify.login_mail')
                    //->cc('rushik994@gmail.com')
                    //->bcc('rushik994@gmail.com')
                    ->with([ 'fullname' => $this->data['fullname'],'email_id' => $this->data['email_id'],'password' => $this->data[0]]);
        }
}
