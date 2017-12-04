<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Se encarga de devolver los distintos directorios y archivos que forman el corpus
 * Class CorpusController
 * @package App\Http\Controllers
 */
class CorpusController extends Controller
{
    public function get() {
        return response()->json([
            'data' => array_merge($this->getNode(), ['name' => '/']),
        ]);
    }

    public function getOne($name) {
        return response()->json([
            'data' => [
                'directories' => [
                    'data' => $this->getDirectories($name),
                ],
                'files' => [
                    'data' => $this->getFiles($name),
                ],
            ],
        ]);
    }

    public function getNode($name = null) {
        $output = collect();

        foreach ($this->getDirectories($name) as $directory) {
            $o = collect();
            $directories = $this->getDirectories($directory['name']);

            foreach ($directories as $d) {
                $o->push([
                    'name' => $d['name'],
                    'children' => $this->getNode($d['name']),
                ]);
            }

            $files = $this->getFiles($directory['name']);
            foreach ($files as $f) {
                $o->push([
                    'name' => $f['name'],
                    'isFile' => true,
                ]);
            }

            $output->push([
                'name' => $directory['name'],
                'children' => $o,
            ]);
        }

        if (! count($output)) return null;

        return ['children' => $output];
    }

    public function getDirectories($name = '') {
        $directories = collect();

        foreach (Storage::disk('corpus')->directories($name) as $directory) {
            $directories->push(['name' => $directory]);
        }

        return $directories;
    }

    public function getFiles($directory = null) {
        $files = collect();

        foreach (Storage::disk('corpus')->files($directory) as $directory) {
            $files->push(['name' => $directory]);
        }

        return $files;
    }
}