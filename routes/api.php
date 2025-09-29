<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CityRegencyController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\CurriculumVitaeController;
use App\Http\Controllers\DurationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InternshipApplicationController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\JobOpeningController;
use App\Http\Controllers\MajorController;
use App\Http\Controllers\MouController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ReportTaskController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaveJobOpeningController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomepageController;
use Illuminate\Support\Facades\Route;

// Route::get('/docs', function () {
//     $path = public_path('swagger/index.html');
//     if (!File::exists($path)) {
//         abort(404);
//     }
//     return Response::file($path);
// });


// Route::get('/docs/openapi.yaml', function () {
//     $path = storage_path('docs/openapi.yaml');
//     if (!File::exists($path)) {
//         abort(404);
//     }
//     return Response::file($path);
// });




Route::prefix('v1')->group(function () {

    // Test
    Route::prefix('/tests')
        ->controller(TestController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('abilities:company-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

    // Majors
    Route::prefix('/majors')
        ->controller(MajorController::class)
        ->group(function () {
            Route::get('/', 'index');

            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::post('/', 'store');
                    Route::patch('/{id}', 'update');
                    Route::delete('/{id}', 'destroy');
                });
            });
        });

    //Hompage
    Route::prefix('/homepages')
        ->controller(HomepageController::class)
        ->group(function () {
            Route::get('/', 'index');

            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::patch('/', 'update');
                });
            });
        });

    //Contact us    
    Route::prefix('/contact-us')
        ->controller(ContactUsController::class)
        ->group(function () {
            Route::post('/', 'store');
            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::get('/', 'index');
                    Route::patch('/{id}', 'update');
                    Route::delete('/{id}', 'destroy');
                });
            });
        });


    // User
    Route::prefix('/users')
        ->controller(UserController::class)
        ->group(function () {
            Route::post('/login', 'login');
            Route::post('/register', 'register');

            Route::get('/email/verify/{id}/{hash}', 'verifyEmail')->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

            Route::get('/', 'index');



            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/logout', 'logout');
                Route::get('/profile', 'profile');
                Route::patch('/profile', 'updateProfile');
                Route::delete('/profile', 'deleteProfile');
                Route::get("/count", "count");


                Route::patch('/{id}', 'update');
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::delete('/{id}', 'destroy');
                });

                Route::middleware('abilities:school-access')->group(function () {
                    Route::get('/student/summary', 'studentSummary');
                    Route::get('/student/import/template', 'importStudentTemplate');
                    Route::post('/student/import', 'importStudent');
                });

                Route::middleware('ability:admin-access,school-access')->group(function () {
                    Route::post('/', 'store');
                });

                Route::middleware('ability:admin-access,student-access,school-access,company-access')->group(function () {
                    Route::get('/{id}', 'show');
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

            });
            Route::get('/{id}/download', 'download');

            Route::get('/{id}/preview', 'preview');

        });

    // Internship Application
    Route::prefix('/internship-applications')
        ->controller(InternshipApplicationController::class)
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::middleware('abilities:student-access')->group(function () {
                Route::post('/', 'store');
                Route::get('/count', 'count');
            });

            Route::middleware('abilities:company-access')->group(function () {
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'delete');
            });

            Route::middleware('ability:student-access,company-access')->group(function () {
                Route::get('/', 'index');
            });
        });

    // Job Opening 
    Route::prefix('/job-openings')
        ->controller(JobOpeningController::class)
        ->group(function () {
            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('abilities:company-access')->group(function () {
                    Route::post('/', 'store');
                    Route::patch('/{id}', 'update');
                    Route::delete('/{id}', 'destroy');
                });

                Route::middleware("ability:school-access,company-access")->group(function () {
                    Route::get('/count', 'count');
                });
            });
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
        });

    // Save Job Opening
    Route::prefix('/save-job-openings')
        ->controller(SaveJobOpeningController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
            });
        });

    // Task
    Route::prefix('/tasks')
        ->controller(TaskController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access,company-access')->group(function () {
                Route::get('/', 'index');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
            });

            Route::middleware('ability:company-access')->group(function () {
                Route::post('/', 'store');
                Route::delete('/{id}', 'destroy');
            });
        });

    // Report Task
    Route::prefix('/report-tasks')
        ->controller(ReportTaskController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access,company-access')->group(function () {
                Route::get('/{id}', 'show');
                Route::post('/{id}', 'store');
            });
        });

    // Certificate
    Route::prefix('/certificates')
        ->controller(CertificateController::class)
        ->group(function () {
            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('ability:student-access')->group(function () {
                    Route::get('/', 'index');
                    Route::get('/{id}/preview', 'preview');
                    Route::get('/{id}/download', 'download');
                });

                Route::middleware('ability:company-access,school-access')->group(function () {
                    Route::get('/count', 'count');
                });
            });
            Route::get('/{id}', 'show');
        });

    // Feedback
    Route::prefix('/feedbacks')
        ->controller(FeedbackController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::delete('/{id}', 'destroy');
            Route::get('/check', 'check');
            Route::get('/rating', 'rating');
        });

    // Duration
    Route::prefix('/durations')
        ->controller(DurationController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::middleware("auth:sanctum")->group(function () {
                Route::middleware('ability:admin-access,company-access')->group(function () {
                    Route::post('/', 'store');
                });
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::delete('/{id}', 'destroy');
                    Route::patch('/{id}', 'update');
                });
            });
        });


    // Mou
    Route::prefix('mous')
        ->controller(MouController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:school-access,company-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });

            Route::get('/{id}/download', 'download');
            Route::get('/{id}/preview', 'preview');
        });


    // Role
    Route::prefix('roles')
        ->controller(RoleController::class)
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::middleware('ability:admin-access,company-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
            });

            Route::middleware('abilities:admin-access')->group(function () {
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

    // Internship
    Route::prefix('internships')
        ->controller(InternshipController::class)
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::middleware('ability:company-access')->group(function () {
                Route::get('/', 'index');
                Route::get("/count", "count");
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

    Route::prefix('achievements')
        ->controller(AchievementController::class)
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::get('/count', 'count');
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




