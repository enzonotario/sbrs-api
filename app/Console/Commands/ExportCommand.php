<?php

namespace App\Console\Commands;

use App\Document;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'extractor {site}';

    protected $description = 'Exporta los Documentos de la base de datos a SOLR';

    protected $validSites;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->line('Exportando...');

        $guzzle = new Client([
            'base_uri' => env('SOLR_POST_URL'),
        ]);

        Document::chunk(50000, function ($documents) use ($guzzle) {
            $this->line('...');

            $documents = $documents->map(function ($document) {
                return [
                    'arg1' => $document->arg1,
                    'rel' => $document->rel,
                    'arg2' => $document->arg2,

                    'site' => $document->site1,
                    'url' => $document->url,

                    'page' => $document->page,
                    'title' => $document->title,
                    'subtitle' => $document->subtitle,

                    'nps' => explode(', ', $document->nps),
                    'ns' => explode(', ', $document->ns),
                    'synonymous' => explode(', ', $document->synonymous),
                    'relInf' => explode(', ', $document->relInf),

                    'sentence' => $document->sentence,
                ];
            });

            $guzzle->post('', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($documents),
            ]);
        });

        $this->info('OK');
    }
}
