<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use  Illuminate\Http\Request;

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
        $domain = \Request::getHost();
        $subdomain = explode('.', $domain);
        if($subdomain[0] == 'galgotias')
        {
            return $this->view('verify.login_mail_galgotias')
                    //->cc('rushik994@gmail.com')
                    //->bcc('rushik994@gmail.com')
                    ->with([ 'fullname' => $this->data['fullname'],'email_id' => $this->data['email_id'],'password' => $this->data[0]]);
        }elseif($subdomain[0] == 'monad'||$subdomain[0] == 'demo'){
            return $this->view('verify.reset_password_monad')
                    ->with([ 'fullname' => $this->data['fullname'],'email_id' => $this->data['email_id'],'password' => $this->data[0]]);
        }else{
            return $this->view('verify.login_mail')
                    //->cc('rushik994@gmail.com')
                    //->bcc('rushik994@gmail.com')
                    ->with([ 'fullname' => $this->data['fullname'],'email_id' => $this->data['email_id'],'password' => $this->data[0]]);
        }
        
    }
}
