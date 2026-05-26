<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskFailed;

class EventServiceProvider extends ServiceProvider
{
	protected $listen = [
		// ScheduledTaskStarting::class => [
		// 	\App\Listeners\ScheduledTaskStartingListener::class,
		// ],
		// ScheduledTaskFinished::class => [
		// 	\App\Listeners\ScheduledTaskFinishedListener::class,
		// ],
		ScheduledTaskSkipped::class => [
			\App\Listeners\ScheduledTaskSkippedListener::class,
		],
		// ScheduledTaskFailed::class => [
		// 	\App\Listeners\ScheduledTaskFailedListener::class,
		// ],
	];
}