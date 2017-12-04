<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVisitedSitesTable extends Migration
{
    public function up()
    {
        Schema::create('visitedSites', function (Blueprint $table) {
            $table->string('url');

            $table->index('url');
        });
    }
}
