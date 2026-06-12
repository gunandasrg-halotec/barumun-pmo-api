<?php

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    
     return view('welcome');
});
Route::get(
    '/swagger',
    fn() => view('swagger-ui')
);


Route::get(
    '/rapidoc',
    fn() => view('rapidoc.rapidoc')
);
Route::get(
    '/rapidoc/pdf',
    fn() => view('rapidoc.rapidocpdf')
);
