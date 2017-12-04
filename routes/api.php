<?php

Route::post('search', 'SearchController@search');
Route::post('extractor', 'ExtractorController@extract');
Route::get('corpus', 'CorpusController@get');
