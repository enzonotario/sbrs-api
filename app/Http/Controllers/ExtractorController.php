<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * A partir de un texto o archivo, llama a la clase Extractor para extraer los Documentos
 * Class ExtractorController
 * @package App\Http\Controllers
 */
class ExtractorController extends Controller
{
    public function extract() {
        $filepath = \Request::input('filepath') or null;
        $text = \Request::input('text') or null;

        $json = null;

        if ($filepath) {
            $file = Storage::disk('corpus')->get($filepath);
            $json = $this->fromFile($filepath, true, true);
        } elseif ($text) {
            $json = $this->fromText($text, false);
        }

        if (! $json) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => [
                'original' => isset($file) ? json_decode($file, true) : $text,
                'parsed' => $json,
            ]
        ]);
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
                        }

                        if ($exportToSolr) {
                            $documents->exportToSolr();
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

    public function fromText($text, $save = false)
    {
        return Extractor::fromText($text)
            ->withUrl('-')
            ->withPage('-')
            ->withTitle('-')
            ->withSubtitle('-')
            ->withSave($save)
            ->withSite('-')
            ->formatToSolr();
    }
}
