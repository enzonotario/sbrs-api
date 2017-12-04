<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use Illuminate\Support\Facades\Storage;

/**
 * Perform a simple text replace
 * This should be used when the string does not contain HTML
 * (off by default)
 */
define('STR_HIGHLIGHT_SIMPLE', 1);

/**
 * Only match whole words in the string
 * (off by default)
 */
define('STR_HIGHLIGHT_WHOLEWD', 2);

/**
 * Case sensitive matching
 * (off by default)
 */
define('STR_HIGHLIGHT_CASESENS', 4);

/**
 * Overwrite links if matched
 * This should be used when the replacement string is a link
 * (off by default)
 */
define('STR_HIGHLIGHT_STRIPLINKS', 8);

/**
 * Se encarga de parsear la pregunta realizada por el usuario, enviársela a Solr
 * y devolver al usuario los resultados que Solr devuelve
 * Class SearchController
 * @package App\Http\Controllers
 */
class SearchController extends Controller
{
    protected $solarium;

    public function __construct(\Solarium\Client $solarium)
    {
        $this->solarium = $solarium;
    }

    public function search(SearchRequest $request)
    {
        $question = $this->cleanText($request->input('question'));

        $output = collect();

        $document = Extractor::fromText($question)
            ->formatToSolr()
            ->solrOutput
            ->first();

        if ($document) {
            $questionParsed = $document['arg1'] . ' ';
            $questionParsed .= $document['relInf']->implode(' ') . ' ';
            $questionParsed .= $document['arg2'];
        } else {
            $questionParsed = $question;
        }

        $output->push($this->queryDocument($document));
        $output->push($this->withFuzzyMatch($questionParsed, 1));
        $output->push($this->withFuzzyMatch($questionParsed, 0));

        $output = $this->removeDuplicatedSenentences($output->collapse());

        return response()->json([
            'data' => [
                'count' => $output->count(),
                'docs' => $output,
            ]
        ]);
    }

    public function withFuzzyMatch($question, $fuzzyMatch = 1)
    {
        $q = collect();

        foreach (explode(' ', $question) as $word) {
            $q->push($word . '~' . $fuzzyMatch);
        }

        return $this->query($q->implode(' '));
    }

    public function queryDocument($document)
    {
        $output = collect();

        $query = $this->solarium->createSelect();
        $query->createFilterQuery('synonymous')->setQuery($document['synonymous']->implode(', '));
        $query->createFilterQuery('nps')->setQuery($document['nps']->implode(', '));
        $query->createFilterQuery('ns')->setQuery($document['ns']->implode(', '));

        $questionParsed = $document['arg1'] . ' ';
        $questionParsed .= $document['relInf']->implode(' ') . ' ';
        $questionParsed .= $document['arg2'];

        $query->setQuery($questionParsed);

        $hl = $query->getHighlighting();
        $hl->setFields(['sentence']);
        $hl->setSimplePrefix('');
        $hl->setSimplePostfix('');

        $resultset = $this->solarium->select($query);
        $results = json_decode($resultset->getResponse()->getBody(), true)['response'];

        $highlighting = $resultset->getHighlighting();

        foreach ($results['docs'] as $result) {
            $resultHighlighted = $highlighting->getResults()[$result['id']];

            if (isset($resultHighlighted->getFields()['sentence'][0])) {
                $result['sentenceHighlighted'] = $this->str_highlight($result['sentence'], $resultHighlighted->getFields()['sentence'][0]);
            } else {
                $result['sentenceHighlighted'] = $result['sentence'];
            }

            $output->push($result);
        }

        return $output;
    }

    public function query($text)
    {
        $output = collect();

        $query = $this->solarium->createSelect();
        $query->setQuery($text);

        $hl = $query->getHighlighting();
        $hl->setFields(['sentence']);
        $hl->setSimplePrefix('');
        $hl->setSimplePostfix('');

        $resultset = $this->solarium->select($query);
        $results = json_decode($resultset->getResponse()->getBody(), true)['response'];

        $highlighting = $resultset->getHighlighting();

        foreach ($results['docs'] as $result) {
            $resultHighlighted = $highlighting->getResults()[$result['id']];

            if (isset($resultHighlighted->getFields()['sentence'][0])) {
                $result['sentenceHighlighted'] = $this->str_highlight($result['sentence'], $resultHighlighted->getFields()['sentence'][0]);
            } else {
                $result['sentenceHighlighted'] = $result['sentence'];
            }

            $output->push($result);
        }

        return $output;
    }

    public function removeDuplicatedSenentences($collection)
    {
        $results = collect();
        $idx = 0;

        foreach ($collection as $result) {
            $result['sentence'] = trim($result['sentence']);
            $result['sentence'] = preg_replace('!\s+!', ' ', $result['sentence']); // Elimina múltiples espacios y deja sólo uno

            if (! $results->where('sentence', $result['sentence'])->first()) {
                if ($similar = $results->where('page', $result['page'])->first()) {
                    $result['idx'] = $similar['idx'];
                    $results->push($result);
                } else {
                    $result['idx'] = $idx++;
                    $results->push($result);
                }
            }
        }

        $output = collect();

        for ($i = 0; $i <= $results->count(); $i++) {
            $similarResults = $results->where('idx', $i);

            if (! $similarResults->count()) continue;

            $out = $similarResults->first();

            if ($similarResults->count() > 1) {
                $c = 0;
                $out['sentenceHighlighted'] = '';
                foreach ($similarResults as $similarResult) {
                    if ($c++ != 0) $out['sentenceHighlighted'] .= "<br><br><br>";
                    $out['sentenceHighlighted'] .= $similarResult['sentenceHighlighted'];
                }
            }

            $output->push($out);
        }

        return $output;
    }

    /**
     * Highlight a string in text without corrupting HTML tags
     * http://www.aidanlister.com/2004/04/highlighting-a-search-string-in-html-text/
     *
     * @author      Aidan Lister <aidan@php.net>
     * @version     3.1.1
     * @link        http://aidanlister.com/2004/04/highlighting-a-search-string-in-html-text/
     * @param       string          $text           Haystack - The text to search
     * @param       array|string    $needle         Needle - The string to highlight
     * @param       bool            $options        Bitwise set of options
     * @param       array           $highlight      Replacement string
     * @return      Text with needle highlighted
     */
    function str_highlight($text, $needle, $options = null, $highlight = null)
    {
        // Default highlighting
        if ($highlight === null) {
            $highlight = '<strong>\1</strong>';
        }

        // Select pattern to use
        if ($options & STR_HIGHLIGHT_SIMPLE) {
            $pattern = '#(%s)#';
            $sl_pattern = '#(%s)#';
        } else {
            $pattern = '#(?!<.*?)(%s)(?![^<>]*?>)#';
            $sl_pattern = '#<a\s(?:.*?)>(%s)</a>#';
        }

        // Case sensitivity
        if (! ($options & STR_HIGHLIGHT_CASESENS)) {
            $pattern .= 'i';
            $sl_pattern .= 'i';
        }

        $needle = (array) $needle;
        foreach ($needle as $needle_s) {
            $needle_s = preg_quote($needle_s);

            // Escape needle with optional whole word check
            if ($options & STR_HIGHLIGHT_WHOLEWD) {
                $needle_s = '\b' . $needle_s . '\b';
            }

            // Strip links
            if ($options & STR_HIGHLIGHT_STRIPLINKS) {
                $sl_regex = sprintf($sl_pattern, $needle_s);
                $text = preg_replace($sl_regex, '\1', $text);
            }

            $regex = sprintf($pattern, $needle_s);
            $text = preg_replace($regex, $highlight, $text);
        }

        return $text;
    }

    public function cleanText(string $text)
    {
        $text = str_replace('¿', '', $text);
        $text = str_replace('?', '', $text);

        return $text;
    }
}