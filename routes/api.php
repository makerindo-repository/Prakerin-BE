<?php

use App\Http\Controllers\CityRegencyController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CurriculumVitaeController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InternshipApplicationController;
use App\Http\Controllers\JobOpeningController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\SaveJobOpeningController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
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

    // Curriculum Vitae Done
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
                Route::get('/{id}/preview', 'preview');
                Route::get('/{id}/download', 'download');

            });
        });

    Route::prefix('/internship-applications')
        ->controller(InternshipApplicationController::class)
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::middleware('abilities:student-access')->group(function () {
                Route::get('/', 'index');
                Route::get('/count', 'count');
            });

        });

    Route::prefix('/job-openings')
        ->controller(JobOpeningController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:company-access,student-access')->group(function () {
                Route::get('/', 'index');
            });
        });

    Route::prefix('/save-job-openings')
        ->controller(SaveJobOpeningController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::delete('/{id}', 'destroy');
            });
        });

    Route::prefix('/task')
        ->controller(TaskController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access')->group(function () {
                Route::get('/', 'index');
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


        Route::controller(CompanyController::class)->group(function () {
            Route::get('/companies/count', 'companyCount');
        });

        Route::apiResource('students', StudentController::class);
        Route::apiResource('companies', CompanyController::class)->except(['store', 'update', 'destroy']);


    });

});




