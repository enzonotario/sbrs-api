<?php

namespace App;

use App\Jobs\WikipediaCrawlerJob;
use Illuminate\Support\Facades\Storage;
use Weidner\Goutte\GoutteFacade;

/**
 * Ejecuta el WC en un determinado sitio
 * Class Crawler
 * @package App
 */
class Crawler
{
    public function turismoSaltaGovAr()
    {
        $invalids = 0; // Contador de páginas inválidas (tienen que haber 500 páginas inválidas CONSECUTIVAS para que pare el crawler)
        $c = 1; // Contador que representa el id de la página a crawlear

        while ($invalids <= 500) {
            $page = collect();
            $page->id = $c;
            $page->url = "http://turismo.salta.gov.ar/contenido/{$page->id}/crawl";
            $page->name = '';
            $page->description = '';
            $page->title = '';
            $page->subtitle = '';
            $page->text = collect();

            $crawler = GoutteFacade::request('GET', $page->url);

            $crawler->filter('#layout > div > div.container > div.box-container-full > div.box-container-titulo > h1')->each(function ($node) use ($page) {
                $page->name = $node->text();
            });

            $crawler->filter('#layout > div > div.container > div.box-container-full > div.box-container-inner > div > p')->each(function ($node) use ($page) {
                $page->type = $node->text();
            });

            $crawler->filter('#layout > div > div.container > div.box-container-full > div.box-container-inner > div > h2')->each(function ($node) use ($page) {
                $page->description = $node->text();
            });

            $crawler->filter('#layout > div > div.container > div.box-container-full > div.box-container-inner > div > div.noticia-contenido > *:not(table)')->each(function ($node) use ($page) {
                $page->text->push($node->text());
            });

            $crawler->filter('#layout > div > div.container > div.box-container-full > div.box-container-inner > div > h1')->each(function ($node) use ($page) {
                $page->title = $node->text();
            });

            if ($page->name == 'Noticias') {
                $page->name = $page->title;
            }

            $page->title = $page->type;

            if (count($page->text)) {
                $invalids = 0;

                Storage::disk('corpus')->put("turismo.salta.gov.ar/{$page->id}.json", json_encode([
                    'id' => $page->id,
                    'url' => $page->url,
                    'page' => $page->name,
                    'title' => $page->title,
                    'subtitle' => $page->description,
                    'text' => $page->text,
                ]));
            } else {
                $invalids++;
            }

            $c++;
        }
    }

    public function saltaGovAr()
    {
        $invalids = 0; // Contador de páginas inválidas (tienen que haber 500 páginas inválidas CONSECUTIVAS para que pare el crawler)
        $c = 1; // Contador que representa el id de la página a crawlear
        $output = collect();

        while ($invalids <= 500) {
            $page = collect();
            $page->id = $c;
            $page->url = "http://www.salta.gov.ar/contenidos/crawl/{$page->id}";
            $page->type = 'Acerca de Salta';
            $page->page = '';
            $page->title = '';
            $page->imageUrl = '';
            $page->subtitle = '';
            $page->date = '';
            $page->text = collect();

            $crawler = GoutteFacade::request('GET', $page->url);

            $crawler->filter('#main > section:nth-child(1) > article > header > h1')->each(function ($node) use ($page) {
                $page->page = $node->text();
                $page->title = $node->text();
            });

            $crawler->filter('#main > section:nth-child(1) > article > header > h2')->each(function ($node) use ($page) {
                $page->type = $node->text();
            });

            $crawler->filter('#main > section:nth-child(1) > article > p')->each(function ($node) use ($page) {
                $page->text->push($node->text());
            });

            $crawler->filter('#main > section:nth-child(1) > article > div.box-dynamic-content')->each(function ($node) use ($page) {
                $page->text->push($node->text());
            });

            if ($page->title && count($page->text)) {
                $invalids = 0;

                $output->push($page);

                Storage::disk('corpus')->put("salta.gov.ar/{$page->id}.json", json_encode([
                    'id' => $page->id,
                    'url' => $page->url,
                    'page' => $page->page,
                    'title' => $page->title,
                    'subtitle' => $page->subtitle,
                    'type' => $page->type,
                    'text' => $page->text,
                ]));
            } else {
                $invalids++;
            }

            $c++;
        }
    }

    public function wikipediaOrg($url = 'https://es.wikipedia.org/wiki/Provincia_de_Salta')
    {
        if (VisitedSite::where('url', '=', $url)->first()) return;

        VisitedSite::create([
            'url' => $url,
        ]);

        $page = [
            'url' => $url,
            'name' => '',
            'sections' => collect(),
        ];

        $links = collect();

        $foundSalta = false;

        $crawler = GoutteFacade::request('GET', $page['url']);

        $crawler->filter('#firstHeading')->each(function ($node) use (&$page) {
            $page['name'] = $node->text();
            $page['sections']->push($this->initSection($node->text()));
        });

        $crawler->filter('#mw-content-text h2, #mw-content-text h3, #mw-content-text p, #mw-content-text a')->each(function ($node) use ($page, $links, &$foundSalta) {
            $tag = $node->nodeName();

            switch ($tag) {
                case 'h2':
                    if ($node->text() == 'Índice') {
                        return;
                    }

                    $page['sections']->push($this->initSection($node->text()));
                    break;

                case 'h3':
                    $page['sections']->last()['subsections']->push($this->initSubsection($node->text()));

                    break;

                case 'p':
                    if (str_contains($node->text(), 'Salta')) $foundSalta = true;

                    $page['sections']->last()['subsections']->last()['paragraphs']->push($node->text());

                    break;

                case 'a':
                    $href = $node->attr('href');

                    if (starts_with($href, '/')
                        && ! strpos($href, '.')) {
                        $links->push('https://es.wikipedia.org' . $node->attr('href'));
                    }

                    break;
            }
        });

        foreach ($links as $link) {
            if (! QueuedSite::where('url', '=', $link)->first()) {
                QueuedSite::create([
                    'url' => $link,
                ]);

                dispatch(new WikipediaCrawlerJob($link));
            }
        }

        if ($foundSalta) {
            Storage::disk('corpus')->put('wikipedia.asd/'. str_slug($page['name']) .'.json', json_encode($page));
        }
    }

    public function initSection(string $title)
    {
        $section = [
            'title' => $this->cleanSquareBrackets($title),
            'subsections' => collect(),
        ];

        $section['subsections']->push($this->initSubsection(''));

        return $section;
    }

    public function initSubsection(string $subtitle)
    {
        $subsection = [
            'subtitle' => $this->cleanSquareBrackets($subtitle),
            'paragraphs' => collect(),
        ];

        return $subsection;
    }

    public function cleanSquareBrackets($text)
    {
        $text = preg_replace('#\s*\[.+\]\s*#U', ' ', $text);
        $text = preg_replace('!\s+!', ' ', $text); // Elimina múltiples espacios y deja sólo uno

        return trim($text) ;
    }
}