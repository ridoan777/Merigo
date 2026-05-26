<?php

use App\Console\Commands\DemoCommand;
use App\DemoClass;
use App\Models\Users\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

/*
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DemoCommand::class)->everyFifteenSeconds();

Schedule::call(function(){
    logger('From a closure');
})->everyTenSeconds();

Schedule::call(new DemoClass())->everyTenSeconds();
*/

// Schedule::exec('echo 123')->everyTenSeconds();
// Schedule::command('filepond:clear --all')->everyTenSeconds();


Schedule::command('filepond:clear')->everyTenMinutes();
Schedule::command('log:clear')->everyTenMinutes();

// Schedule::call(function () {
//     User::where('status', 0)->update(['status' => 1]);
// })
//     ->everyTenSeconds()
//     ->when(function () {
//         return User::where('status', 0)->exists();
//     })->onSuccess(function () {
//         Log::info("The blacklisted users have been added...... at: " . now());
//     })
//     ->onFailure(function () {
//         Log::error("Skipped reverse blacklisting ...... at: " . now());
//     });