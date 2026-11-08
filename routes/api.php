<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BarangayProgressController;
use App\Http\Controllers\Api\BarangayReportController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DefaultRequirementController;
use App\Http\Controllers\Api\MaximaDashboardController;
use App\Http\Controllers\Api\MaximaProgressController;
use App\Http\Controllers\Api\MaximaReferralController;
use App\Http\Controllers\Api\MaximaReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OsyProfileController;
use App\Http\Controllers\Api\OsyProgressController;
use App\Http\Controllers\Api\OsyReferralController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TrainingProgramController;
use App\Http\Controllers\Api\BarangayDashboardController;
use App\Http\Controllers\Api\OsyAccountController;


/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:2,120');
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

   // Auth
   Route::post('/logout', [AuthController::class, 'logout']);
   Route::get('/user', [AuthController::class, 'user']);

   // SK registers OSY accounts (make sure register() checks the role is SK)
   Route::post('/register', [AuthController::class, 'register']);

   // Shared resources
   Route::apiResource('training-programs', TrainingProgramController::class);
   Route::apiResource('osy-profiles', OsyProfileController::class);
   Route::post('/osy-profiles/{osyProfile}/reset-password', [OsyAccountController::class, 'resetPassword'])->middleware('throttle:10,1');

   // SK side: recommendations
   Route::get('/osy-recommendations', [OsyProfileController::class, 'allRecommendations']);
   Route::post('/osy-recommendations/preview', [OsyProfileController::class, 'previewRecommendations']);
   Route::get('/osy-profiles/{osyProfile}/recommendations', [OsyProfileController::class, 'recommendations']);

   // SK side: referrals
   Route::get('/referrals/osy-options', [ReferralController::class, 'osyOptions']);
   Route::get('/referrals/program-options', [ReferralController::class, 'programOptions']);
   Route::get('/referrals', [ReferralController::class, 'index']);
   Route::post('/referrals', [ReferralController::class, 'store']);
   Route::delete('/referrals/{referral}', [ReferralController::class, 'destroy']);

   // OSY side: the logged-in OSY's own data
   Route::prefix('osy')->group(function () {
      Route::get('/progress', [OsyProgressController::class, 'index']);
      Route::post('/progress/{referral}/absence-reasons', [OsyProgressController::class, 'submitAbsenceReason']);
      Route::get('/recommendations', [OsyProfileController::class, 'myRecommendations']);
      Route::get('/profile', [OsyProfileController::class, 'myProfile']);
      Route::put('/profile', [OsyProfileController::class, 'updateMyProfile']);
      Route::post('/profile/photo', [OsyProfileController::class, 'updateMyPhoto']);
      Route::put('/password', [AuthController::class, 'updatePassword']);
      Route::get('/referrals', [OsyReferralController::class, 'index']);
   });

   // Maxima (TESDA) side
   Route::prefix('maxima')->group(function () {
      Route::get('/dashboard', [MaximaDashboardController::class, 'index']);
      Route::get('/reports', [MaximaReportController::class, 'index']);

      // Referrals & enrollees
      Route::get('/referrals', [MaximaReferralController::class, 'index']);
      Route::patch('/referrals/{referral}/status', [MaximaReferralController::class, 'updateStatus']);
      Route::get('/enrollees', [MaximaReferralController::class, 'enrollees']);

      // Default requirements
      Route::get('/default-requirements', [DefaultRequirementController::class, 'index']);
      Route::post('/default-requirements', [DefaultRequirementController::class, 'store']);
      Route::delete('/default-requirements/{defaultRequirement}', [DefaultRequirementController::class, 'destroy']);

      // Progress monitoring
      Route::prefix('progress')->controller(MaximaProgressController::class)->group(function () {
         Route::get('/', 'index');
         Route::get('/{referral}', 'show');

         // Training lifecycle
         Route::put('/{referral}/details', 'updateDetails');
         Route::post('/{referral}/start', 'start');
         Route::post('/{referral}/complete', 'complete');
         Route::post('/{referral}/drop', 'drop');
         Route::put('/{referral}/outcome', 'updateOutcome');

         // Attendance
         Route::post('/{referral}/attendance', 'saveAttendance');
         Route::delete('/attendance/{record}', 'destroyAttendance');

         // Notes
         Route::post('/{referral}/notes', 'storeNote');
         Route::delete('/notes/{note}', 'destroyNote');

         // Documents
         Route::post('/{referral}/documents', 'storeDocument');
         Route::put('/documents/{document}', 'updateDocument');
         Route::delete('/documents/{document}', 'destroyDocument');

         // Assessments
         Route::post('/{referral}/assessments', 'storeAssessment');
         Route::delete('/assessments/{assessment}', 'destroyAssessment');

         // Conduct
         Route::post('/{referral}/conduct', 'storeConduct');
         Route::delete('/conduct/{conduct}', 'destroyConduct');

         // Concerns
         Route::post('/{referral}/concerns', 'storeConcern');
         Route::delete('/concerns/{concern}', 'destroyConcern');
      });
   });

   // Barangay side
   Route::prefix('barangay')->group(function () {
      Route::get('/dashboard', [BarangayDashboardController::class, 'index']);  
      Route::get('/training-monitoring', [BarangayProgressController::class, 'index']);
      Route::get('/training-monitoring/{referral}', [BarangayProgressController::class, 'show']);
      Route::post('/training-monitoring/{referral}/request-reason', [BarangayProgressController::class, 'requestReason']);
      Route::patch('/concerns/{concern}', [BarangayProgressController::class, 'reviewConcern']);
      Route::get('/reports', [BarangayReportController::class, 'index']);
   });

   // Notifications
   Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
      Route::get('/', 'index');
      Route::get('/unread-count', 'unreadCount');
      Route::get('/referrals/{referral}', 'referralDetail');
      Route::post('/read-all', 'markAllAsRead');
      Route::post('/{id}/read', 'markAsRead');
   });

   // Announcements
   Route::prefix('announcements')->controller(AnnouncementController::class)->group(function () {
      Route::get('/', 'index');
      Route::post('/', 'store');
      Route::put('/{announcement}', 'update');
      Route::delete('/{announcement}', 'destroy');
   });
});