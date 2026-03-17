<?php

namespace App\Providers;

use App\Events\ButtonResponseCaptured;
use App\Listeners\LogButtonResponse;
use App\Listeners\SendThankYouMessageOnButtonResponse;
use App\Listeners\UpdateCampaignMetricsOnResponse;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de eventos a listeners
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ButtonResponseCaptured::class => [
            LogButtonResponse::class,
            UpdateCampaignMetricsOnResponse::class,
            SendThankYouMessageOnButtonResponse::class,
        ],
    ];

    /**
     * Enable the detection of events that have no registered listeners.
     *
     * @var bool
     */
    protected bool $shouldDiscoverEvents = false;
}
