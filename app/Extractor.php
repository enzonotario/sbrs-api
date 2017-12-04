<?php

namespace App\Http\Controllers;

use App\Document;
use GuzzleHttp\Client;

/**
 * Extrae Documentos a partir de un texto
 * También se puede encargar de indexarlo en Solr
 * Class Extractor
 * @package App\Http\Controllers
 */
class Extractor
{
    protected $freeling;
    protected $guzzle;
    protected $text;
    protected $textAnalyzed;
    public $sentences;
    public $documents;
    protected $previousNp;
    protected $url;
    protected $page;
    protected $title;
    protected $subtitle;
    protected $save = false;
    public $solrOutput;
    protected $site = 'wikipedia';

    protected $export;

    public function __construct(Analyzer $freeling)
    {
        $this->freeling = $freeling;
        $this->guzzle = new Client([
            'base_uri' => env('SOLR_POST_URL'),
        ]);
        $this->sentences = collect();
        $this->documents = collect();
        $this->solrOutput = collect();
    }

    /**
     * Extrae información a partir de un string
     * @param string $text
     * @return mixed
     */
    public static function fromText(string $text)
    {
        $builder = app()->make(Extractor::class);
        $builder->text = $text;
        $builder->textAnalyzed = $builder->analyzeText($builder->text);
        $builder->sentences = $builder->getSentencesFromAnalyzedText($builder->textAnalyzed);

        foreach ($builder->sentences as $sentence) {
            $builder->extract($sentence);
        }

        return $builder;
    }

    /**
     * Extrae un Documento a partir de un array de items WLT.
     * Además de la tupla con ARG1, REL y ARG2, guarda todos los NP y NS que encuentre.
     * @param $items
     * @param null $arg1
     * @param null $nps
     */
    public function extract($wlts, $arg1 = null, $nps = null)
    {
        $document = [
            'arg1' => collect(),
            'rel' => collect(),
            'arg2' => collect(),
            'nps' => $nps ?: collect(),
            'ns' => collect(),
        ];

        // Contador de paréntesis o corchetes abiertos sin cerrar
        $pcC = 0;

        // Los símbolos que identifican la apertura de paréntesis o corchetes
        $pcA = [
            'Fpa',
            'Fca',
        ];

        // Los símbolos que identifican el cierre de paréntesis o corchetes
        $pcT = [
            'Fpt',
            'Fct',
        ];

        $verbIdx = -1;

        foreach ($wlts as $wltIdx => $wlt) {
            $tag = $wlt['tag'];

            if (in_array($tag, $pcA)) {
                $pcC++;
            }

            if (in_array($tag, $pcT)) {
                if ($pcC > 0) $pcC--;
            }

            if (preg_match('/^NP.+/u', $tag)) {
                // NP

                if (! $this->previousNp) {
                    // Éste NP será usado para las oraciones con sujeto tácito. Además se agregará a la lista
                    // de nps
                    $this->previousNp = $wlt;
                }

                $document['nps']->push($wlt);
            } else if (preg_match('/^N.+/u', $tag)) {
                $document['ns']->push($wlt);
            }

            if (preg_match('/^V.+/u', $tag) && $pcC == 0) {
                // Verbo que NO está dentro de paréntesis o corchetes

                if ($verbIdx == -1) {
                    $verbIdx = $wltIdx;

                    $document['rel']->push($wlt);

                    if ($wltIdx == 0) {
                        // Sujeto tácito

                        if ($arg1) {
                            $document['arg1']->push($arg1);
                        } else if ($this->previousNp) {
                            $document['arg1']->push([$this->previousNp]);

                            $document['nps']->push($wlt);
                        } else {
                            // No se pudo deducir ningún arg1
                        }
                    } else {
                        // Sujeto expreso

                        if ($arg1) {
                            $document['arg1']->push($arg1->merge(array_slice($wlts->toArray(), 0, $wltIdx)));
                        } else {
                            $document['arg1']->push(array_slice($wlts->toArray(), 0, $wltIdx));
                        }
                    }
                } else if ($wltIdx == $verbIdx + 1) {
                    // Es un verbo de más de una palabra. Se agrega en REL

                    $verbIdx = $wltIdx;

                    $document['rel']->push($wlt);
                }
            }
        }

        if ($verbIdx >= 0) {
            // A partir del verbo, y hacia el final de la oración, forma arg2
            foreach ($wlts as $wltIdx => $wlt) {
                if ($wltIdx > $verbIdx) {
                    $document['arg2']->push([
                        'word' => $wlt['word'],
                        'tag' => $wlt['tag'],
                        'lemma' => $wlt['lemma'],
                    ]);
                }
            }

            if (isset($document['arg1'][0])) $document['arg1'] = collect($document['arg1'][0]);

            if ($document['rel']->count() && $document['arg2']->count()) {
                // Sólo la agrego si tiene rel ^ arg2 (puede no tener arg1)

                $this->documents->push($document);
            }

            $this->extract($document['arg2'], $document['arg1']->merge($document['rel']), $document['nps']);
        }
    }

    /**
     * A partir de un texto analizado con Freeling, que contiene en cada línea: WORD LEMMA TAG PROB
     * genera oraciones que en vez de contener las palabras como string, contiene arrays denominados "Item WLT",
     * es decir, que contiene: WORD LEMMA TAG.
     * @param $analyzedText
     * @return \Illuminate\Support\Collection
     */
    public function getSentencesFromAnalyzedText($analyzedText)
    {
        $sentences = collect();
        $sentence = collect();

        $lines = preg_split("/((\r?\n)|(\r\n?))/", $analyzedText);

        foreach ($lines as $line) {
            if ($line == "\n" || $line == '') {
                // Termina una oración
                $sentences->push($sentence);
                $sentence = collect();
            } else {
                $line = explode(' ', $line);

                if (count($line) != 4) {
                    // Por alguna razón, Freeling no generó correctamente ésta línea,
                    // ya que no contiene los 4 elementos que debe contener:
                    // WORD LEMMA TAG PROB.
                    continue;
                }

                $sentence->push([
                    'word' => str_replace('_', ' ', $line[0]), // Limpio todos los "_" ya que luego generan problema al buscar en SOLR
                    'lemma' => $line[1],
                    'tag' => $line[2],
                ]);
            }
        }

        if ($sentence->isNotEmpty()) {
            $sentences->push($sentence);
        }

        return $sentences;
    }

    /**
     * Analiza un texto con Freeling, el cual devuelve por cada palabra o símbolo una línea que contiene:
     * `WORD LEMMA TAG PROB`
     * @param string $text
     * @return string
     */
    public function analyzeText(string $text)
    {
        return $this->freeling->analyze_text($text);
    }

    /**
     * Transforma un Documento para enviárselo a SOLR
     * @return $this
     */
    public function formatToSolr()
    {
        $this->solrOutput = collect();

        foreach ($this->documents as $document) {
            $arg1 = $this->cleanText($document['arg1']->pluck('word')->implode(' '));
            $rel = $this->cleanText($document['rel']->pluck('word')->implode(' '));
            $arg2 = $this->cleanText($document['arg2']->pluck('word')->implode(' '));

            if (! $arg1 && ! $rel && ! $arg2) {
                continue;
            }

            $sentence = $arg1 . ' ' . $rel . ' ' . $arg2;

            if ($this->save) {
                Document::create([
                    'arg1' => $arg1,
                    'rel' => $rel,
                    'arg2' => $arg2,

                    'site' => $this->site,
                    'url' => $this->url,

                    'page' => $this->page,
                    'title' => $this->title,
                    'subtitle' => $this->subtitle,

                    'nps' => join(', ', $document['nps']->pluck('word')->toArray()),
                    'ns' => join(', ', $document['ns']->pluck('word')->toArray()),
                    'synonymous' => join(', ', Synonymous::get($document['rel']->pluck('lemma')->implode(' '))->toArray()),
                    'relInf' => join(', ', $document['rel']->pluck('lemma')->toArray()),

                    'sentence' => $sentence,
                ]);
            }

            $this->solrOutput->push([
                'arg1' => $arg1,
                'rel' => $rel,
                'arg2' => $arg2,
                'url' => $this->url,
                'page' => $this->page,
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'sentence' => $sentence,
                'nps' => $document['nps']->pluck('word'),
                'ns' => $document['ns']->pluck('word'),
                'synonymous' => collect(Synonymous::get($document['rel']->pluck('lemma')->implode(' '))),
                'relInf' => $document['rel']->pluck('lemma'),
                'site' => $this->site,
            ]);
        }

        return $this;
    }

    /**
     * Envía los Documentos generados a SOLR
     * @return $this
     */
    public function exportToSolr()
    {
        $this->guzzle->post('', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($this->solrOutput),
        ]);

        return $this;
    }

    /**
     * Indica la URL de la cual se procesó el texto
     * @param string $url
     * @return $this
     */
    public function withUrl(string $url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Indica el nombre de la página de la cual se procesó el texto
     * @param string $page
     * @return $this
     */
    public function withPage(string $page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Indica el título de la página de la cual se procesó el texto
     * @param string $title
     * @return $this
     */
    public function withTitle(string $title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Indica el subtítulo de la página de la cual se procesó el texto
     * @param string $subtitle
     * @return $this
     */
    public function withSubtitle(string $subtitle)
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * Indica si debe guardar los documentos en la BD
     * @param bool $save
     * @return $this
     */
    public function withSave(bool $save)
    {
        $this->save = $save;

        return $this;
    }

    /**
     * Indica si debe exportar los Documentos a SOLR
     * @param bool $save
     * @return $this
     */
    public function withExport(bool $export)
    {
        $this->export = $export;

        return $this;
    }

    /**
     * Indica el sitio del cual fue procesado el texto. Puede ser:
     * @param string $site
     * @return $this
     */
    public function withSite(string $site)
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Elimina todo lo que está dentro de paréntesis o corchetes
     * @param $text
     */
    public function cleanText($text) {
        $text = preg_replace('#\s*\(.+\)\s*#U', ' ', $text);
        $text = preg_replace('#\s*\[.+\]\s*#U', ' ', $text);

        $text = str_replace('(', '', $text);
        $text = str_replace(')', '', $text);
        $text = str_replace('[', '', $text);
        $text = str_replace(']', '', $text);

        $text = preg_replace('!\s+!', ' ', $text); // Elimina múltiples espacios y deja sólo uno

        return $text;
    }
}