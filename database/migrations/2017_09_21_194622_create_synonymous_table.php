<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSynonymousTable extends Migration
{
    public function up()
    {
        Schema::create('synonymous', function(Blueprint $table) {
            $table->increments('id');
            $table->string('verb');
            $table->text('synonymous');

            $table->index(['id', 'verb']);
        });
    }
}
