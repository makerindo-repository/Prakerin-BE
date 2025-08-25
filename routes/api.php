<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityRegencyController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurriculumVitaeController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SectorController;
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



// Route::get('/internships/{slug}', [InternshipController::class, 'getSlug']);
// Route::get('internships', [InternshipController::class, 'index']);
// Route::get('internships/show/{id}', [InternshipController::class, 'show']);
// Route::get('/schools/name', [SchoolController::class, 'schoolName']);

Route::prefix('v1')->group(function () {

    // User
    Route::prefix('/users')
        ->controller(UserController::class)->group(function () {
            Route::post('/login', 'login');
            Route::post('/register', 'register');

            Route::middleware('auth:sanctum')->group(function () {

                Route::post('/logout', 'logout');
                Route::get('/profile', 'profile');
                Route::patch('/profile', 'updateProfile');
                Route::delete('/profile', 'deleteProfile');

                Route::middleware('abilities:admin-access')->group(function () {
                    Route::get('/', 'index');
                    Route::post('/', 'store');
                    Route::get('/{id}', 'show');
                    Route::patch('/{id}', 'update');
                    Route::delete('/{id}', 'destroy');
                });

            });

        });

    // Curriculum Vitae
    Route::prefix('/curriculum-vitaes')
        ->controller(CurriculumVitaeController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('abilities:student-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
                Route::get('/{id}/download', 'download');
            });
        });


    Route::middleware('auth:sanctum')->group(function () {



        // Sector
        Route::apiResource('sectors', SectorController::class)
            ->only(['store'])
            ->middleware('ability:admin-access,company-access');
        Route::apiResource('sectors', SectorController::class)
            ->only(['update', 'destroy'])
            ->middleware('abilities:admin-access');
        Route::apiResource('sectors', SectorController::class)
            ->only(['index'])
            ->withoutMiddleware('auth:sanctum');

        // Province
        Route::apiResource('provinces', ProvinceController::class)
            ->only(['store'])
            ->middleware('ability:admin-access,company-access');
        Route::apiResource('provinces', ProvinceController::class)
            ->only(['update', 'destroy'])
            ->middleware('abilities:admin-access');
        Route::apiResource('provinces', ProvinceController::class)
            ->only(['index'])
            ->withoutMiddleware('auth:sanctum');

        // City Regency
        Route::apiResource('city-regencies', CityRegencyController::class)
            ->only(['store'])
            ->middleware('ability:admin-access,company-access');
        Route::apiResource('city-regencies', CityRegencyController::class)
            ->only(['update', 'destroy'])
            ->middleware('abilities:admin-access');
        Route::apiResource('city-regencies', CityRegencyController::class)
            ->only(['index'])
            ->withoutMiddleware('auth:sanctum');

        // Field
        Route::apiResource('fields', FieldController::class)
            ->only(['store'])
            ->middleware('ability:admin-access,company-access');
        Route::apiResource('fields', FieldController::class)
            ->only(['update', 'destroy'])
            ->middleware('abilities:admin-access');
        Route::apiResource('fields', FieldController::class)
            ->only(['index'])
            ->withoutMiddleware('auth:sanctum');


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

});




