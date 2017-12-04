<?php

namespace App\Console\Commands;

use App\Jobs\ExtractorJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Dispara los Jobs que extraerán los Documentos de un determinado sitio
 * Class ExtractorCommand
 * @package App\Console\Commands
 */
class ExtractorCommand extends Command
{
    protected $signature = 'extractor {site}';

    protected $description = 'Dispara los Jobs que extraeran los Documentos de un determinado sitio';

    protected $validSites;

    public function __construct()
    {
        parent::__construct();

        $this->validSites = [
            'turismo.salta.gov.ar',
            'salta.gov.ar',
            'wikipedia.org',
        ];
    }

    public function handle()
    {
        $site = $this->argument('site');

        if (! in_array($site, $this->validSites)) {
            $this->error('Sitio desconocido');
            return;
        }

        $files = Storage::disk('corpus')->files($site);

        foreach ($files as $filepath) {
            dispatch(new ExtractorJob($filepath, true, true));
        }

        $this->info('Jobs disparados');
    }
}
