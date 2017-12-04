<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Document
 * Class Document
 * @package App
 */
class Document extends Model
{
    protected $table = 'documents';

    protected $fillable = [
        'arg1',
        'rel',
        'arg2',
        'site',
        'url',
        'page',
        'title',
        'subtitle',
        'nps',
        'ns',
        'synonymous',
        'relInf',
        'sentence',
    ];

    public $timestamps = false;
}
