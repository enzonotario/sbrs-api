<?php

namespace App\Http\Controllers;

use App\Synonym;
use Illuminate\Support\Facades\Storage;
use Weidner\Goutte\GoutteFacade;

/**
 * Devuelve los sinónimos para un determinado verbo
 * Class Synonymous
 * @package App\Http\Controllers
 */
class Synonymous
{
    /**
     * Obtiene los sinónimos del verbo que se pasó como parámetro desde sinonimos.woxikon.es
     * y lo guarda en la BD
     * @param $verb
     * @return \Illuminate\Support\Collection
     */
    public static function get(string $verb)
    {
//        return collect();

        $verb = str_replace('_', ' ', $verb);

        $fromDb = Synonymous::getFromDb($verb);
        if ($fromDb) return $fromDb;

        $fromCorpus = Synonymous::getFromCorpus($verb);
        if ($fromCorpus->count()) return $fromCorpus;

        $output = collect();

        foreach (explode(' ', $verb) as $subverb) {
            $subverb = str_replace(',', '', $subverb);

            if ($fromDb = Synonymous::getFromDb($subverb)) {
                foreach ($fromDb as $synonym) {
                    if ($output->search($synonym)) continue;

                    $output->push($synonym);
                }

                continue;
            }

            $subverbSynonymous = collect();

            foreach (Synonymous::sinonimosWoxiconEs($subverb) as $synonim) {
                if ($output->search($synonim)) continue;

                $subverbSynonymous->push($synonim);
                $output->push($synonim);
            }

            foreach (Synonymous::sinonimosOrg($subverb) as $synonim) {
                if ($output->search($synonim)) continue;

                $subverbSynonymous->push($synonim);
                $output->push($synonim);
            }

            Synonym::create([
                'verb' => $verb,
                'synonymous' => $subverbSynonymous->implode(' '),
            ]);
        }

        if ($output->count()) {
            Synonym::create([
                'verb' => $verb,
                'synonymous' => $output->implode(' '),
            ]);
        }

        return $output;
    }

    public static function getFromDb($verb)
    {
        $synonym = Synonym::where('verb', '=', $verb)->first();

        if (! $synonym) {
            return null;
        }

        return collect(explode(' ', $synonym->synonymous));
    }

    public static function getFromCorpus($verb)
    {
        $synonymous = collect(json_decode(Storage::disk('corpus')->get('sinonimos.json'), true));

        $output = collect($synonymous->get($verb));

        $output = $output->filter(function($item) {
            // Limpio los valores vacíos

            if (! $item || trim($item) == '') {
                return false;
            }

            return true;
        });

        if ($output->count()) {
            Synonym::create([
                'verb' => $verb,
                'synonymous' => $output->implode(' '),
            ]);
        }

        return $output;
    }

    public static function sinonimosWoxiconEs($verb)
    {
        $synonymous = collect();

        $url = 'http://sinonimos.woxikon.es/es/' . $verb;

        $crawler = GoutteFacade::request('GET', $url);

        $crawler->filter('#content > ol > li.synonyms-list-item > div.synonyms-list-content')
            ->each(function ($nodes) use ($synonymous) {
                $nodes = explode(', ', trim(str_replace(["\r\n", "\n", "\r"], ' ', $nodes->text())));
                foreach ($nodes as $node) {
                    if (preg_match('/({.+}|\(.+\)|[.+])/', $node)) {
                        $synonym = trim(preg_split('/({.+}|\(.+\)|[.+])/', $node)[0]);
                    } else {
                        $synonym = trim($node);
                    }

                    if (! $synonymous->search($synonym)) {
                        $synonymous->push($synonym);
                    }
                }
            });

        return $synonymous;
    }

    public static function sinonimosOrg($verb)
    {
        $synonymous = collect();

        $url = 'http://www.sinonimos.org/' . $verb;

        $crawler = GoutteFacade::request('GET', $url);

        $crawler->filter('body > div:nth-child(2) > div:nth-child(5) > b')
            ->each(function ($node) use ($synonymous) {
                $synonymous->push($node->text());
            });

        return $synonymous;
    }
}