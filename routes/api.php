<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\StudentController;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/unauthorized', [AuthController::class, 'unauthorized'])->name('login');


Route::get('/internships/{slug}', [InternshipController::class, 'getSlug']);
Route::get('internships', [InternshipController::class, 'index']);
Route::get('internships/show/{id}', [InternshipController::class, 'show']);
Route::get('/school_name', [SchoolController::class, 'school_name']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');



    Route::apiResource('school', SchoolController::class);
    Route::apiResource('students', StudentController::class);
    Route::apiResource('applications', ApplicationController::class)->middleware('abilities:student:access');
    Route::apiResource('internships', InternshipController::class)->except('index', 'show');
});


