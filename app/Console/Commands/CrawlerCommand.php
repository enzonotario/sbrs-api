<?php

namespace App\Console\Commands;

use App\Crawler;
use Illuminate\Console\Command;

/**
 * Realiza WC en un determinado sitio
 * Class CrawlerCommand
 * @package App\Console\Commands
 */
class CrawlerCommand extends Command
{
    protected $signature = 'crawler {site}';

    protected $description = 'Realiza WC en un determinado sitio';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        switch ($this->argument('site')) {
            case 'turismo.salta.gov.ar':
                $this->info('WC turismo.salta.gov.ar');
                app(Crawler::class)->turismoSaltaGovAr();
                $this->info('ok');

                break;

            case 'salta.gov.ar':
                $this->info('WC salta.gov.ar');
                app(Crawler::class)->saltaGovAr();
                $this->info('ok');

                break;

            case 'wikipedia.org':
                $this->info('WC wikipedia.org');
                app(Crawler::class)->wikipediaOrg();
                $this->info('ok');

                break;

            default:
                $this->error('Sitio inválido');
                break;
        }
    }
}
