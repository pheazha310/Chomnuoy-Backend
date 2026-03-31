<?php

use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BakongTransactionController;
use App\Http\Controllers\Api\AdminProfileController;
use App\Http\Controllers\Api\AuthControllerRegister;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignImageController;
use App\Http\Controllers\Api\CampaignUpdateController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DonationStatusHistoryController;
use App\Http\Controllers\Api\DonationTrendController;
use App\Http\Controllers\Api\MaterialItemController;
use App\Http\Controllers\Api\MaterialBreakdownController;
use App\Http\Controllers\Api\MaterialPickupController;
use App\Http\Controllers\Api\MaterialProvinceDistributionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationDocumentController;
use App\Http\Controllers\Api\OrganizationHistoryController;
use App\Http\Controllers\Api\OrganizationMetricsCacheController;
use App\Http\Controllers\Api\OrganizationMetricsController;
use App\Http\Controllers\Api\OrganizationReportsController;
use App\Http\Controllers\Api\OrganizationVerificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProvinceContributionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\RegionalMapMarkerController;
use App\Http\Controllers\Api\RegionalMetricController;
use App\Http\Controllers\Api\RegionalProjectStatusController;
use App\Http\Controllers\Api\RegionalTopProvinceController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SourceBreakdownController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserCredentialController;
use App\Http\Controllers\Api\UserHistoryController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\ProfileController;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function (): JsonResponse {
    $organizations = Organization::query()->orderByDesc('id')->get();

    return response()->json([
        'status' => 'ok',
        'service' => 'chomnuoy-backend',
        'payload' => [
            'organizations' => $organizations,
        ],
        'organizations' => $organizations,
    ]);
});

Route::post('/auth/register', [AuthControllerRegister::class, 'register']);
Route::post('/auth/login', [AuthControllerRegister::class, 'login']);
Route::post('/auth/change-password', [AuthControllerRegister::class, 'changePassword']);
Route::post('/auth/google/token', [SocialAuthController::class, 'googleTokenLogin']);
Route::get('/files/{path}', function (string $path) {
    $disk = Storage::disk('public');

    abort_unless($disk->exists($path), 404);

    return response()->file($disk->path($path));
})->where('path', '.*');
Route::get('/admin/profile/{user}', [AdminProfileController::class, 'show']);
Route::post('/admin/profile/{user}', [AdminProfileController::class, 'update']);
Route::post('/admin/profile/{user}/password', [AdminProfileController::class, 'updatePassword']);
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
Route::get('/auth/providers/status', [SocialAuthController::class, 'status']);
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
Route::get('organization_metrics/summary', [OrganizationMetricsController::class, 'summary']);
Route::get('organization_metrics/cache', [OrganizationMetricsController::class, 'cached']);
Route::apiResource('organization_metrics_cache', OrganizationMetricsCacheController::class);
Route::get('organization_reports/transactions', [OrganizationReportsController::class, 'transactions']);
Route::get('organization_reports/financial_summary', [OrganizationReportsController::class, 'financialSummary']);
Route::get('organization_reports/material_summary', [OrganizationReportsController::class, 'materialSummary']);
Route::get('organization_reports/source_breakdown', [OrganizationReportsController::class, 'sourceBreakdown']);
Route::get('organization_reports/financial_transactions', [OrganizationReportsController::class, 'financialTransactions']);
Route::apiResource('province_contributions', ProvinceContributionController::class);
Route::apiResource('source_breakdowns', SourceBreakdownController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('donations', DonationController::class);
Route::apiResource('donation_status_history', DonationStatusHistoryController::class);
Route::apiResource('donation_trends', DonationTrendController::class);
Route::apiResource('material_items', MaterialItemController::class);
Route::apiResource('material_breakdowns', MaterialBreakdownController::class);
Route::apiResource('material_pickups', MaterialPickupController::class);
Route::apiResource('material_province_distributions', MaterialProvinceDistributionController::class);
Route::apiResource('payment_methods', PaymentMethodController::class);
Route::apiResource('payments', PaymentController::class);
Route::apiResource('reviews', ReviewController::class);
Route::apiResource('regional_metrics', RegionalMetricController::class);
Route::apiResource('regional_top_provinces', RegionalTopProvinceController::class);
Route::apiResource('regional_project_statuses', RegionalProjectStatusController::class);
Route::apiResource('regional_map_markers', RegionalMapMarkerController::class);
Route::get('notifications/stream', [NotificationController::class, 'stream']);
Route::apiResource('notifications', NotificationController::class);
Route::apiResource('audit_logs', AuditLogController::class);
Route::get('report/admin-dashboard', [ReportController::class, 'adminDashboard']);
Route::apiResource('report', ReportController::class);
Route::apiResource('campaigns', CampaignController::class);
Route::get('campaigns/{campaign}/donations', [CampaignController::class, 'donations']);
Route::get('campaigns/{campaign}/velocity', [CampaignController::class, 'velocity']);
Route::apiResource('campaign_image', CampaignImageController::class);
Route::apiResource('campaign_update', CampaignUpdateController::class);
Route::post('bakong/transactions', [BakongTransactionController::class, 'store']);
Route::post('bakong/transactions/{tranId}/verify', [BakongTransactionController::class, 'verify']);
Route::post('/payment/generate', [PaymentController::class, 'generateQR']);

// Profile Routes
Route::get('/profile/me', [ProfileController::class, 'me']);
Route::put('/profile/me', [ProfileController::class, 'updateMe']);
Route::post('/profile/me/password', [ProfileController::class, 'updatePassword']);
Route::post('/profile/me/avatar', [ProfileController::class, 'uploadAvatar']);
Route::get('/profile/{user}', [ProfileController::class, 'show']);
Route::put('/profile/{user}', [ProfileController::class, 'update']);
Route::post('/profile/{user}/password', [ProfileController::class, 'updatePassword']);
Route::post('/profile/{user}/avatar', [ProfileController::class, 'uploadAvatar']);
Route::post('/profile/{user}/activities', [ProfileController::class, 'addActivity']);
Route::delete('/profile/{user}/activities/{activity}', [ProfileController::class, 'deleteActivity']);

// Admin Settings Routes
Route::get('admin/settings', [AdminSettingsController::class, 'index']);
Route::get('admin/settings/section/{section}', [AdminSettingsController::class, 'getBySection']);
Route::get('admin/settings/{key}', [AdminSettingsController::class, 'show']);
Route::post('admin/settings', [AdminSettingsController::class, 'store']);
Route::put('admin/settings/{key}', [AdminSettingsController::class, 'update']);
Route::delete('admin/settings/{key}', [AdminSettingsController::class, 'destroy']);
