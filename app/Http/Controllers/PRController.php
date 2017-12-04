<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Extrae los Documentos de las oraciones de prueba, para calcular la precisión y el recall
 * Class PRController
 * @package App\Http\Controllers
 */
class PRController extends Controller
{
    public function index()
    {
        $sentences = $this->getSentences();
        $output = [];
        $total = 0;

        foreach ($sentences as $sentence) {
            $sentenceOutput = [];
            $sentenceOutput['sentence'] = $sentence;
            $sentenceOutput['documents'] = collect();
            $sentenceOutput['tagged'] = collect();

            $extracted = Extractor::fromText($sentence);

            foreach ($extracted->sentences as $wlts) {
                foreach ($wlts as $wlt) {
                    $tag = $wlt['tag'];

                    if (preg_match('/^V.+/u', $tag)) {
                        $sentenceOutput['tagged']->push('<b style="color:red;">' . $wlt['word'] . '</b>');
                    } else {
                        $sentenceOutput['tagged']->push($wlt['word']);
                    }
                }
            }

            $sentenceOutput['tagged'] = $sentenceOutput['tagged']->implode(' ');

            foreach ($extracted->documents as $document) {
                $document['arg1'] = $document['arg1']->pluck('word')->implode(' ');
                $document['rel'] = $document['rel']->pluck('word')->implode(' ');
                $document['arg2'] = $document['arg2']->pluck('word')->implode(' ');

                $sentenceOutput['documents']->push([
                    'arg1' => $document['arg1'],
                    'rel' => $document['rel'],
                    'arg2' => $document['arg2'],
                ]);

                $total++;
            }

            $sentenceOutput['subtotal'] = $sentenceOutput['documents']->count();

            $output[] = $sentenceOutput;
        }

        return view('sentences', [
            'sentences' => $output,
            'total' => $total,
        ]);
    }


    public function getSentences()
    {
        $txt = Storage::get('sentences.txt');

        $lines = collect(preg_split("/((\r?\n)|(\r\n?))/", $txt));

        return $lines;
    }
}