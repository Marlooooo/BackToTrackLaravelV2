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



Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:2,120');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
   Route::post('/logout', [AuthController::class, 'logout']);
   Route::get('/user', [AuthController::class, 'user']);

   Route::post('osy-recommendations/preview', [OsyProfileController::class, 'previewRecommendations']);
   Route::get('osy-profiles/{osyProfile}/recommendations', [OsyProfileController::class, 'recommendations']);

   Route::apiResource('training-programs', TrainingProgramController::class);
   Route::apiResource('osy-profiles', OsyProfileController::class);

   Route::get('/referrals/osy-options', [ReferralController::class, 'osyOptions']);
   Route::get('/referrals/program-options', [ReferralController::class, 'programOptions']);
   Route::get('/referrals', [ReferralController::class, 'index']);
   Route::post('/referrals', [ReferralController::class, 'store']);
   Route::delete('/referrals/{referral}', [ReferralController::class, 'destroy']);


   Route::get('/maxima/referrals', [MaximaReferralController::class, 'index']);
   Route::patch('/maxima/referrals/{referral}/status', [MaximaReferralController::class, 'updateStatus']);
   Route::get('/maxima/enrollees', [MaximaReferralController::class, 'enrollees']);
   Route::get('/maxima/dashboard', [MaximaDashboardController::class, 'index']);

   
   Route::get('/notifications', [NotificationController::class, 'index']);
   Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
   Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
   Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

   Route::get('/announcements', [AnnouncementController::class, 'index']);
   Route::post('/announcements', [AnnouncementController::class, 'store']);
   Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
   Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
});