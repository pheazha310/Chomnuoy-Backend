<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AbaPayWayController;
use App\Http\Controllers\Api\AuthControllerRegister;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignImageController;
use App\Http\Controllers\Api\CampaignUpdateController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DonationStatusHistoryController;
use App\Http\Controllers\Api\MaterialItemController;
use App\Http\Controllers\Api\MaterialPickupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationDocumentController;
use App\Http\Controllers\Api\OrganizationHistoryController;
use App\Http\Controllers\Api\OrganizationVerificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserCredentialController;
use App\Http\Controllers\Api\UserHistoryController;
use App\Http\Controllers\Api\UserRoleController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthControllerRegister::class, 'register']);
Route::post('/auth/login', [AuthControllerRegister::class, 'login']);
Route::post('/auth/change-password', [AuthControllerRegister::class, 'changePassword']);
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'chomnuoy-backend',
    ]);
});

Route::get('/users/by-email', [UserController::class, 'findByEmail']);
Route::apiResource('users', UserController::class);
Route::post('/users/{id}/last-seen', [UserController::class, 'updateLastSeen']);
Route::apiResource('roles', RoleController::class);
Route::apiResource('user_roles', UserRoleController::class);
Route::apiResource('user_credentials', UserCredentialController::class);
Route::apiResource('user_history', UserHistoryController::class);
Route::get('/organizations/by-email', [OrganizationController::class, 'findByEmail']);
Route::apiResource('organizations', OrganizationController::class);
Route::apiResource('organization_verifications', OrganizationVerificationController::class);
Route::apiResource('organization_history', OrganizationHistoryController::class);
Route::apiResource('organization_document', OrganizationDocumentController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('donations', DonationController::class);
Route::apiResource('donation_status_history', DonationStatusHistoryController::class);
Route::apiResource('material_items', MaterialItemController::class);
Route::apiResource('material_pickups', MaterialPickupController::class);
Route::apiResource('payment_methods', PaymentMethodController::class);
Route::apiResource('payments', PaymentController::class);
Route::apiResource('reviews', ReviewController::class);
Route::get('notifications/stream', [NotificationController::class, 'stream']);
Route::apiResource('notifications', NotificationController::class);
Route::apiResource('audit_logs', AuditLogController::class);
Route::apiResource('report', ReportController::class);
Route::apiResource('campaigns', CampaignController::class);
Route::get('campaigns/{campaign}/donations', [CampaignController::class, 'donations']);
Route::get('campaigns/{campaign}/velocity', [CampaignController::class, 'velocity']);
Route::apiResource('campaign_image', CampaignImageController::class);
Route::apiResource('campaign_update', CampaignUpdateController::class);