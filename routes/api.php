<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use App\Models\School;
use Illuminate\Http\Request;
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



//Authentication routes
Route::get('/unauthorized', [AuthController::class, 'unauthorized'])->name('login');




// Route::get('/internships/{slug}', [InternshipController::class, 'getSlug']);
// Route::get('internships', [InternshipController::class, 'index']);
// Route::get('internships/show/{id}', [InternshipController::class, 'show']);
// Route::get('/schools/name', [SchoolController::class, 'schoolName']);

Route::middleware('auth:sanctum')->group(function () {

    //User
    Route::controller(UserController::class)->group(function () {
        Route::prefix('/users')->group(function () {
            Route::apiResource('/', UserController::class);
            Route::get('/logout', 'logout');
            Route::withoutMiddleware('auth:sanctum')->group(function () {
                Route::post('/login', 'login');
                Route::post('/register', 'register');
            });
        });
    });



    Route::controller(StudentController::class)->group(function () {
        Route::get('/students/count', 'studentCount');
    });
    // Route::apiResource('school', SchoolController::class);

    // Route::middleware('ability:admin-access,school-access')->group(function () {

    // });



    Route::controller(InternshipController::class)->group(function () {
        Route::get('/internships/count', 'internshipCount');
    });

    // Route::middleware('ability:admin-access,school-access, ')->group(function () {

    Route::controller(CompanyController::class)->group(function () {
        Route::get('/companies/count', 'companyCount');
    });

    // });

    Route::apiResource('students', StudentController::class);
    Route::apiResource('companies', CompanyController::class)->except(['store', 'update', 'destroy']);


    // Route::apiResource('applications', ApplicationController::class)->middleware('abilities:student:access');
    // Route::apiResource('internships', InternshipController::class)->except('index', 'show');

});


