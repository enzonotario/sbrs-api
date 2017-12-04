<?php

namespace App\Jobs;

use App\Crawler;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Realiza WC en una determinada página de Wikipedia
 * Class WikipediaCrawlerJob
 * @package App\Jobs
 */
class WikipediaCrawlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function handle()
    {
        app(Crawler::class)->wikipediaOrg($this->url);
    }
}
