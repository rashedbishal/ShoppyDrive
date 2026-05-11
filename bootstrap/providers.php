<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Barryvdh\DomPDF\ServiceProvider as DomPDFServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    DomPDFServiceProvider::class,
];
