<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SuperAdmin\SuperAdminCompanyController;
use App\Http\Controllers\SuperAdmin\SuperAdminSchoolController;
use App\Http\Controllers\SuperAdmin\SuperAdminStudentController;
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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/unauthorized', [AuthController::class, 'unauthorized'])->name('login');




// Route::get('/internships/{slug}', [InternshipController::class, 'getSlug']);
// Route::get('internships', [InternshipController::class, 'index']);
// Route::get('internships/show/{id}', [InternshipController::class, 'show']);
// Route::get('/schools/name', [SchoolController::class, 'schoolName']);

Route::middleware('auth:sanctum')->group(function () {
    //User
    Route::get('/users', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Route::apiResource('school', SchoolController::class);

    Route::middleware('ability:admin-access,school-access')->group(function () {
        Route::apiResource('students', StudentController::class);

    });

    // Route::apiResource('applications', ApplicationController::class)->middleware('abilities:student:access');
    // Route::apiResource('internships', InternshipController::class)->except('index', 'show');

    // Route::middleware('abilities:admin:access')->group(function () {
    //     Route::prefix('/super-admin')->group(function () {
    //         Route::apiResource('students', SuperAdminStudentController::class);
    //         Route::apiResource('schools', SuperAdminSchoolController::class);
    //         Route::apiResource('companies', SuperAdminCompanyController::class);
    //     });
    // });
});


