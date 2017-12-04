<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->increments('id');
            $table->longText('arg1');
            $table->longText('rel');
            $table->longText('arg2');

            $table->longText('site');
            $table->longText('url');
            $table->longText('page');
            $table->longText('title');
            $table->longText('subtitle');

            $table->longText('nps');
            $table->longText('ns');
            $table->longText('synonymous');
            $table->longText('relInf');

            $table->longText('sentence');
        });
    }
}
