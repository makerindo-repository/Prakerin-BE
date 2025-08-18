<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
  $path = public_path('swagger/index.html');
  if (!File::exists($path)) {
    abort(404);
  }
  return Response::file($path);
});


Route::get('/docs/openapi.yaml', function () {
  $path = storage_path('docs/openapi.yaml');
  if (!File::exists($path)) {
    abort(404);
  }
  return Response::file($path);
});