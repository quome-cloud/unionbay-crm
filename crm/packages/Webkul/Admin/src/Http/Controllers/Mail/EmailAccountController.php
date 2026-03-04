<?php

namespace Webkul\Admin\Http\Controllers\Mail;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmailAccountController extends Controller
{
    /**
     * Display the email accounts management page.
     */
    public function index()
    {
        return view('admin::mail.email-accounts.index');
    }
}
