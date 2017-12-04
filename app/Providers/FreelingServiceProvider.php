<?php

namespace App\Providers;

use App\Http\Controllers\Analyzer;
use Illuminate\Support\ServiceProvider;

class FreelingServiceProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Register any application services.
     *
     * @return  void
     */
    public function register()
    {
        $this->app->bind(Analyzer::class, function($app) {
            return new Analyzer('localhost:50005', '--ner --outlv "splitted"');
        });
    }

    public function provides()
    {
        return [Analyzer::class];
    }
}
