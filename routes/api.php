<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\TrainingProgramController;
use App\Http\Controllers\Api\OsyProfileController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\MaximaReferralController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\MaximaDashboardController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\MaximaProgressController;
use App\Http\Controllers\Api\OsyProgressController;
use App\Http\Controllers\Api\DefaultRequirementController;

Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:2,120');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
   Route::post('/logout', [AuthController::class, 'logout']);
   Route::get('/user', [AuthController::class, 'user']);

   // SK side: recommendations
   Route::get('osy-recommendations', [OsyProfileController::class, 'allRecommendations']);
   Route::post('osy-recommendations/preview', [OsyProfileController::class, 'previewRecommendations']);
   Route::get('osy-profiles/{osyProfile}/recommendations', [OsyProfileController::class, 'recommendations']);
   

   // OSY side: the logged-in OSY's own data
   Route::get('/osy/progress', [OsyProgressController::class, 'index']);
   Route::get('/osy/recommendations', [OsyProfileController::class, 'myRecommendations']);
   Route::get('/osy/profile', [OsyProfileController::class, 'myProfile']);
   Route::put('/osy/profile', [OsyProfileController::class, 'updateMyProfile']);
   Route::put('/osy/password', [AuthController::class, 'updatePassword']);
   Route::post('/osy/profile/photo', [OsyProfileController::class, 'updateMyPhoto']);

   Route::apiResource('training-programs', TrainingProgramController::class);
   Route::apiResource('osy-profiles', OsyProfileController::class);
   Route::get('/notifications/referrals/{referral}', [NotificationController::class, 'referralDetail']);

   Route::get('/referrals/osy-options', [ReferralController::class, 'osyOptions']);
   Route::get('/referrals/program-options', [ReferralController::class, 'programOptions']);
   Route::get('/referrals', [ReferralController::class, 'index']);
   Route::post('/referrals', [ReferralController::class, 'store']);
   Route::delete('/referrals/{referral}', [ReferralController::class, 'destroy']);

   Route::get('/maxima/referrals', [MaximaReferralController::class, 'index']);
   Route::patch('/maxima/referrals/{referral}/status', [MaximaReferralController::class, 'updateStatus']);
   Route::get('/maxima/enrollees', [MaximaReferralController::class, 'enrollees']);
   Route::get('/maxima/dashboard', [MaximaDashboardController::class, 'index']);
   Route::get('/maxima/default-requirements', [DefaultRequirementController::class, 'index']);  
   Route::post('/maxima/default-requirements', [DefaultRequirementController::class, 'store']);
   Route::delete('/maxima/default-requirements/{defaultRequirement}', [DefaultRequirementController::class, 'destroy']);

   Route::get('/maxima/progress', [MaximaProgressController::class, 'index']);
   Route::get('/maxima/progress/{referral}', [MaximaProgressController::class, 'show']);
   Route::put('/maxima/progress/{referral}/details', [MaximaProgressController::class, 'updateDetails']);
   Route::post('/maxima/progress/{referral}/start', [MaximaProgressController::class, 'start']);
   Route::post('/maxima/progress/{referral}/complete', [MaximaProgressController::class, 'complete']);
   Route::post('/maxima/progress/{referral}/drop', [MaximaProgressController::class, 'drop']);
   Route::put('/maxima/progress/{referral}/outcome', [MaximaProgressController::class, 'updateOutcome']);
   Route::post('/maxima/progress/{referral}/attendance', [MaximaProgressController::class, 'saveAttendance']);
   Route::post('/maxima/progress/{referral}/notes', [MaximaProgressController::class, 'storeNote']);
   Route::post('/maxima/progress/{referral}/documents', [MaximaProgressController::class, 'storeDocument']);
   Route::delete('/maxima/progress/attendance/{record}', [MaximaProgressController::class, 'destroyAttendance']);
   Route::delete('/maxima/progress/notes/{note}', [MaximaProgressController::class, 'destroyNote']);
   Route::put('/maxima/progress/documents/{document}', [MaximaProgressController::class, 'updateDocument']);
   Route::delete('/maxima/progress/documents/{document}', [MaximaProgressController::class, 'destroyDocument']);
   Route::patch('/maxima/absence-concerns/{concern}', [MaximaProgressController::class, 'reviewConcern']);

   Route::get('/notifications', [NotificationController::class, 'index']);
   Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
   Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
   Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

   Route::get('/announcements', [AnnouncementController::class, 'index']);
   Route::post('/announcements', [AnnouncementController::class, 'store']);
   Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
   Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
});