<?php

use App\Http\Controllers\Auth\Api\ApiAuthController;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/users', function () {
    $users = User::all();
    return "Hello";
})->middleware('auth:sanctum');

Route::get('/', function () {
    return 'hello, api';
});

