<?php

use App\Mail\testMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/send', function () {
   Mail::to('fakherahmad359@gmail.com')->send(new TestMail());
   return 'Email sent';
});
