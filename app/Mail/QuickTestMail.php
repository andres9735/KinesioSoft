<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class QuickTestMail extends Mailable
{
    public function build()
    {
        return $this->subject('QuickTest')
            ->text('emails.quick_test'); // usa la vista de texto plano
    }
}
