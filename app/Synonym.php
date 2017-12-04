<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Synonym
 * Class Synonym
 * @package App
 */
class Synonym extends Model
{
    protected $table = 'synonymous';

    protected $fillable = [
        'verb',
        'synonymous',
    ];

    public $timestamps = false;
}
