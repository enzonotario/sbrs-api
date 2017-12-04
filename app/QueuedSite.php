<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo QueuedSite
 * Class QueuedSite
 * @package App
 */
class QueuedSite extends Model
{
    protected $table = 'queuedSites';

    protected $fillable = [
        'url',
    ];

    public $timestamps = false;
}
