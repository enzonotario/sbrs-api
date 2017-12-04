<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo VisitedSite
 * Class VisitedSite
 * @package App
 */
class VisitedSite extends Model
{
    protected $table = 'visitedSites';

    protected $fillable = [
        'url',
    ];

    public $timestamps = false;
}
