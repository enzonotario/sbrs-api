<?php

namespace App\Jobs;

use App\Http\Controllers\Extractor;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;

/**
 * Extrae Documentos a partir de un determinado archivo
 * Class ExtractorJob
 * @package App\Jobs
 */
class ExtractorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filepath;
    protected $saveInDb;
    protected $exportToSolr;
    protected $file;

    public function __construct($filepath, $saveInDb = false, $exportToSolr = false)
    {
        $this->filepath = $filepath;
        $this->saveInDb = $saveInDb;
        $this->exportToSolr = $exportToSolr;
        $this->file = Storage::disk('corpus')->get($this->filepath);
    }

    public function handle()
    {
        $this->fromFile($this->filepath, $this->saveInDb, $this->exportToSolr);
    }

    public function fromFile($filepath, $saveInDb, $exportToSolr)
    {
        $output = collect();

        $json = collect(json_decode(Storage::disk('corpus')->get($filepath), true));

        $site = substr($filepath, 0, strpos($filepath, '/'));

        if (isset($json['sections'])) {
            foreach($json['sections'] as $section) {
                foreach ($section['subsections'] as $subsection) {
                    foreach ($subsection['paragraphs'] as $paragraph) {
                        $documents = Extractor::fromText($paragraph)
                            ->withUrl($json['url'])
                            ->withPage($json['name'])
                            ->withTitle($section['title'])
                            ->withSubtitle($subsection['subtitle'])
                            ->withSave($saveInDb)
                            ->withSite($site)
                            ->formatToSolr();

                        if (count($documents)) {
                            $output->push($documents);

                            if ($exportToSolr) {
                                $documents->exportToSolr();
                            }
                        }
                    }
                }
            }

            return $output;
        } else {
            $json->text = collect($json['text']);

            $page = collect();
            $page['url'] = $json['url'];
            $page['name'] = $json['page'];
            $page['sections'] = collect();

            $section = collect();
            $section['title'] = $json['title'];
            $section['subsections'] = collect();

            $subsection = collect();
            $subsection['subtitle'] = $json['subtitle'];
            $subsection['paragraphs'] = $json['text'];

            $section['subsections']->push($subsection);
            $page['sections']->push($section);


            foreach($page['sections'] as $section) {
                foreach ($section['subsections'] as $subsection) {
                    foreach ($subsection['paragraphs'] as $paragraph) {
                        $documents = Extractor::fromText($paragraph)
                            ->withUrl($page['url'])
                            ->withPage($page['name'])
                            ->withTitle($section['title'])
                            ->withSubtitle($subsection['subtitle'])
                            ->withSave($saveInDb)
                            ->withSite($site)
                            ->formatToSolr();

                        if (count($documents)) {
                            $output->push($documents);

                            if ($exportToSolr) {
                                $documents->exportToSolr();
                            }
                        }
                    }
                }
            }

            return $output;
        }
    }
}
