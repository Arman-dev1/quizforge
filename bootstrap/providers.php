<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FolioServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FolioServiceProvider::class,
    PaymentServiceProvider::class,
    VoltServiceProvider::class,
];
