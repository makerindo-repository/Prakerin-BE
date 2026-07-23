<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CityRegencyController;
use App\Http\Controllers\CommentPrakerinController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\CurriculumVitaeController;
use App\Http\Controllers\CvGeneratorController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\DurationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InternshipApplicationController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\JobOpeningController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\MajorController;
use App\Http\Controllers\MouController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\ReportTaskController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SaveJobOpeningController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AiAnalyticsController;
use App\Http\Controllers\AwardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\PreInternshipClassController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\SubscriptionAdminController;
use App\Http\Controllers\Admin\RevenueController;
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
    // Admin Dashboard
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'getDashboardData'])
        ->middleware(['auth:sanctum', 'abilities:admin-access']);
    Route::prefix('dev')
        ->controller(DevController::class)
        ->group(function () {
            Route::post('/', 'devFeed');
            Route::post('/generate-cv', [CvGeneratorController::class, 'generate'])->middleware('auth:sanctum');
            Route::post('/download-cv', [PdfController::class, 'generateCv'])->middleware('auth:sanctum');
        });

    Route::prefix('comment-prakerins')
        ->controller(CommentPrakerinController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('abilities:admin-access')->group(function () {
                Route::post('/', 'store');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

    Route::prefix('partners')
        ->controller(PartnerController::class)
        ->group(function () {
            Route::get('/', 'index');
        });

    Route::prefix('partners')
        ->controller(PartnerController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('abilities:admin-access')->group(function () {
                Route::post('/', 'store');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

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
                Route::post('/generate', 'generateScenario');
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

    // Contacts (Hubungi Kami)
    Route::prefix('/contacts')
        ->controller(ContactController::class)
        ->group(function () {
            Route::post('/', 'store');
            Route::get('/user/{email}', 'checkReplies');
            
            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::get('/', 'index');
                    Route::get('/{id}', 'show');
                    Route::post('/{id}/reply', 'reply');
                });
            });
        });

    // Guides (Panduan)
    Route::prefix('/guides')
        ->controller(GuideController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            
            Route::middleware('auth:sanctum')->group(function () {
                Route::middleware('abilities:admin-access')->group(function () {
                    Route::post('/', 'store');
                    Route::get('/admin/all', 'adminAll');
                    Route::patch('/{id}', 'update');
                    Route::delete('/{id}', 'destroy');
                });
            });
        });

    // Pre-Internship Classes (Kelas Pra Magang)
    Route::prefix('/pre-internship-classes')
        ->controller(PreInternshipClassController::class)
        ->group(function () {
            Route::get('/', 'index');
            
            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/', 'store');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
                Route::post('/{id}/enroll', 'enroll');
                Route::get('/{id}/enrollments', 'classEnrollments');
            });
        });

    Route::prefix('/pre-internship-enrollments')
        ->controller(PreInternshipClassController::class)
        ->group(function () {
            Route::middleware('auth:sanctum')->group(function () {
                Route::delete('/{id}', 'drop');
                Route::get('/{id}/attendance', 'attendanceRecord');
            });
        });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/my-pre-internship-classes', [PreInternshipClassController::class, 'myClasses']);
        Route::post('/class-attendance', [PreInternshipClassController::class, 'markAttendance']);
    });

    // Mentors (Guru Pembimbing)
    Route::prefix('/mentors')
        ->controller(MentorController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/candidates', 'candidates');
            Route::get('/{id}', 'show');
            
            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/', 'store');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

    Route::prefix('/mentor-assignments')
        ->controller(MentorController::class)
        ->group(function () {
            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/', 'assign');
                Route::get('/', 'assignments');
                Route::patch('/{id}/end', 'endAssignment');
            });
        });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/my-mentor', [MentorController::class, 'myMentor']);
    });



    // User
    Route::prefix('/users')
        ->controller(UserController::class)
        ->group(function () {
            Route::post('/login', 'login');
            Route::post('/register', 'register');

            Route::get('/email/verify/{id}/{hash}', 'verifyEmail')->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

            Route::middleware('auth:sanctum')->group(function () {
                Route::post('/logout', 'logout');
                Route::get('/profile', 'profile');
                Route::get('/me/permissions', 'myPermissions'); // GET current user's roles & permissions
                Route::patch('/profile', 'updateProfile');
                Route::delete('/profile', 'deleteProfile');
                Route::get('/count', 'count');

                Route::middleware('abilities:school-access')->group(function () {
                    Route::get('/student/summary', 'studentSummary');
                    Route::get('/student/import/template', 'importStudentTemplate');
                    Route::post('/student/import', 'importStudent');
                });

                Route::middleware('ability:admin-access,school-access')->group(function () {
                    Route::post('/', 'store');
                });

                Route::middleware('abilities:admin-access')->group(function () {
                    Route::delete('/{id}', 'destroy');
                });

                Route::patch('/{id}', 'updateProfile');

                Route::middleware('ability:admin-access,student-access,school-access,company-access')->group(function () {
                    Route::get('/{id}', 'show');
                });
            });

            // Public routes — TANPA auth, taruh /{id} dan / di paling akhir
            Route::get('/', 'index');
        });

    // Curriculum Vitae
    Route::prefix('/curriculum-vitaes')
        ->controller(CurriculumVitaeController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access,admin-access')->group(function () {
                Route::post('/generate-cv', [CvGeneratorController::class, 'generate']);
            });
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

    // AI Analytics
    Route::prefix('/ai-analytics')
        ->controller(AiAnalyticsController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::middleware('ability:student-access,admin-access')->group(function () {
                Route::post('/', 'analyze');
                Route::get('/latest', 'latest');
                Route::get('/', 'index');
                Route::delete('/{id}', 'destroy');
                Route::get('/{id}/pdf', 'downloadPdf');
                Route::post('/search', 'aiSearch');
            });
            Route::middleware('ability:admin-access')->group(function () {
                Route::patch('/{id}/review', 'review');
            });
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
                Route::patch('/{idInternshipApplication}/{idTest}', 'updateTestPassed');
                Route::get('/{id}', 'show');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
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
                Route::get('/count', 'count');
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
            Route::get('/ulasan', 'rate');
            Route::get('/check', 'check');
            Route::get('/rating', 'rating');
            Route::get('/{id}', 'show');
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


    // Job Positions (job position catalog data for charts — formerly /roles)
    Route::prefix('job-positions')
        ->controller(JobPositionController::class)
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

    // System: RBAC Role & Permission Management (super_admin only)
    Route::prefix('system')
        ->controller(RolePermissionController::class)
        ->middleware(['auth:sanctum', 'abilities:admin-access'])
        ->group(function () {
            Route::get('/roles', 'index');                                      // GET  all roles + their permissions
            Route::get('/permissions', 'getAllPermissions');                    // GET  all available permissions
            Route::put('/roles/{roleName}/permissions', 'updateRolePermissions'); // PUT  sync permissions to role
        });

    // Internship
    Route::prefix('internships')
        ->controller(InternshipController::class)
        ->middleware('auth:sanctum')
        ->group(function () {

            Route::middleware('ability:company-access')->group(function () {
                Route::get('/', 'index');
                Route::get("/count", "count");
                Route::post('/', 'store');
                Route::get('/{id}', 'show');
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

        // School Custom Report Template
        Route::prefix('school')->controller(SchoolController::class)->group(function () {
            Route::middleware('ability:school-access')->group(function () {
                Route::get('/report-template', 'getReportTemplate');
                Route::post('/report-template', 'updateReportTemplate');
            });
        });

        // P2 Reports
        Route::prefix('reports')->controller(ReportController::class)->group(function () {
            Route::middleware('ability:admin-access,student-access,school-access')->group(function () {
                Route::post('/ai-summary', 'generateAiSummary');
            });
            Route::middleware('abilities:admin-access')->group(function () {
                Route::get('/internship-stats', 'internshipStats');
                Route::get('/student-progress', 'studentProgress');
                Route::get('/company-performance', 'companyPerformance');
                Route::post('/export', 'export');
            });
        });

        // P2 Scheduled Reports
        Route::prefix('scheduled-reports')->controller(ReportController::class)->group(function () {
            Route::middleware('abilities:admin-access')->group(function () {
                Route::get('/', 'listScheduledReports');
                Route::post('/', 'storeScheduledReport');
                Route::patch('/{id}', 'updateScheduledReport');
                Route::delete('/{id}', 'deleteScheduledReport');
                Route::post('/{id}/run-now', 'runScheduledReport');
            });
        });

        // P2 Activity Logs
        Route::prefix('activity-logs')->controller(ActivityLogController::class)->group(function () {
            Route::middleware('abilities:admin-access')->group(function () {
                Route::get('/', 'index');
                Route::get('/stats', 'stats');
            });
        });

        // System Settings
        Route::prefix('settings')->controller(SettingController::class)->group(function () {
            // Subset publik, boleh dibaca semua role yang login (bukan cuma admin).
            Route::get('/public', 'publicSettings');

            Route::middleware('ability:admin-access')->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'update');
                Route::post('/test-smtp', 'testSmtp');
                Route::post('/test-ai', 'testAiKey');
            });
        });

        // P2 Awards & Student Awards
        // Halaman "Penghargaan" dipakai bareng oleh company & school (bukan cuma
        // admin), jadi listing katalog award boleh dibaca oleh ketiganya. Buat
        // lencana baru & assign/cabut dari siswa tetap boleh dilakukan company
        // maupun school (mereka yang paling tahu performa siswa/mitra magangnya).
        Route::prefix('awards')->controller(AwardController::class)->group(function () {
            Route::middleware('ability:admin-access,company-access,school-access')->group(function () {
                Route::post('/', 'store');
                Route::get('/', 'index');
                Route::patch('/{id}', 'update');
                Route::delete('/{id}', 'destroy');
            });
        });

        Route::prefix('student-awards')->controller(AwardController::class)->group(function () {
            Route::middleware('ability:admin-access,company-access,school-access')->group(function () {
                Route::post('/', 'assign');
                Route::delete('/{id}', 'removeAssignment');
            });
        });
    });

    // Public P2 routes (outside auth:sanctum)
    Route::get('/awards/leaderboard', [AwardController::class, 'leaderboard']);
    Route::get('/awards/{id}', [AwardController::class, 'show']);
    Route::get('/students/{studentId}/awards', [AwardController::class, 'studentAwards']);
    Route::get('/student-awards/{id}/certificate', [AwardController::class, 'printCertificate']);

    // ─── Inbox / Notifications ───────────────────────────────────────────────
    Route::prefix('inbox')
        ->controller(InboxController::class)
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/unread-count', 'unreadCount');
            Route::patch('/mark-all-read', 'markAllRead');
            Route::patch('/{id}/read', 'markRead');
        });

    // User notification preference settings
    Route::patch('/users/notification-settings', [UserController::class, 'updateNotificationSettings'])
        ->middleware('auth:sanctum');

    // WhatsApp test connection (admin only)
    Route::post('/settings/test-whatsapp', [SettingController::class, 'testWhatsApp'])
        ->middleware(['auth:sanctum', 'ability:admin-access']);

    // ─── Subscription (user-facing) ───────────────────────────────────────
    Route::prefix('subscriptions')
        ->middleware('auth:sanctum')
        ->group(function () {
            Route::get('/user/{userId}', [SubscriptionController::class, 'getUserSubscription']);
            Route::post('/create-payment', [SubscriptionController::class, 'createPayment']);
            Route::get('/payment-status/{invoiceId}', [SubscriptionController::class, 'getPaymentStatus']);
        });

    // ─── Admin: Subscription Tier Management ──────────────────────────────
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'ability:admin-access'])
        ->group(function () {
            Route::get('/subscriptions/list', [SubscriptionAdminController::class, 'list']);
            Route::post('/subscriptions/toggle', [SubscriptionAdminController::class, 'toggleTier']);

            Route::get('/revenue/dashboard', [RevenueController::class, 'dashboard']);
            Route::get('/revenue/accounts', [RevenueController::class, 'accounts']);
        });
});

// ─── Public Webhooks (outside v1 prefix, no auth — provider calls these) ───
Route::prefix('webhooks')->group(function () {
    Route::post('/whatsapp/status', [WebhookController::class, 'whatsappStatus']);
    Route::post('/xendit', [WebhookController::class, 'handleXenditWebhook']);
});