<?php

use App\Http\Controllers\Api\AccountManagerTaskController;
use App\Http\Controllers\Api\Admin\AccessController;
use App\Http\Controllers\Api\Admin\ConsultantSettingsController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PrintingRequestController as AdminPrintingRequestController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Catalog\PackageController;
use App\Http\Controllers\Api\Catalog\ServiceController;
use App\Http\Controllers\Api\Catalog\SupplierController;
use App\Http\Controllers\Api\Consultant\ConsultationController;
use App\Http\Controllers\Api\Customer\CustomerConversationController;
use App\Http\Controllers\Api\Customer\CustomerDashboardController;
use App\Http\Controllers\Api\Customer\CustomerFileController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\CustomerPaymentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HrDirectoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Payments\PayTabsReturnController;
use App\Http\Controllers\Api\Printing\PrintingRequestController;
use App\Http\Controllers\Api\SupportConversationController;
use App\Http\Controllers\Api\Webhooks\PayTabsWebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceFileController;
use App\Http\Controllers\Api\WorkspaceProjectController;
use App\Http\Controllers\Api\WorkspaceTaskController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::post('/webhooks/paytabs', [PayTabsWebhookController::class, 'handle']);
Route::match(['GET', 'POST'], '/payments/paytabs/return', [PayTabsReturnController::class, 'handle']);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:hebr-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:hebr-login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:hebr-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:hebr-password');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('account.active')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
        });
    });
});

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/{package}', [PackageController::class, 'show']);
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);

Route::prefix('consultations')->group(function (): void {
    Route::get('/config', [ConsultationController::class, 'config']);
    Route::post('/', [ConsultationController::class, 'store'])->middleware('throttle:hebr-consultations');
    Route::get('/{consultation}', [ConsultationController::class, 'show']);
    Route::post('/{consultation}/answers', [ConsultationController::class, 'answer'])->middleware('throttle:hebr-consultations');
    Route::post('/{consultation}/messages', [ConsultationController::class, 'message'])->middleware('throttle:hebr-consultations');
    Route::post('/{consultation}/reset', [ConsultationController::class, 'reset'])->middleware('throttle:hebr-consultations');
    Route::post('/{consultation}/lead', [ConsultationController::class, 'lead'])->middleware('throttle:hebr-consultations');
    Route::post('/{consultation}/events', [ConsultationController::class, 'event'])->middleware('throttle:hebr-consultations');
});

Route::middleware(['auth:sanctum', 'account.active', 'role:OWNER,ACCOUNT_MANAGER'])->group(function (): void {
    Route::get('/orders/lookups', [OrderController::class, 'lookups']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    Route::get('/support/conversations', [SupportConversationController::class, 'index']);
    Route::get('/support/conversations/{conversation}', [SupportConversationController::class, 'show']);
    Route::post('/support/conversations/{conversation}/messages', [SupportConversationController::class, 'storeMessage'])
        ->middleware('throttle:hebr-messages');
    Route::patch('/support/conversations/{conversation}/status', [SupportConversationController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/workspace/files', [WorkspaceFileController::class, 'index']);
    Route::post('/workspace/files', [WorkspaceFileController::class, 'store'])->middleware('throttle:hebr-uploads');
    Route::get('/workspace/files/{file}', [WorkspaceFileController::class, 'show']);
    Route::get('/workspace/files/{file}/download', [WorkspaceFileController::class, 'download']);
    Route::get('/workspace/files/{file}/preview', [WorkspaceFileController::class, 'preview']);

    Route::get('/workspace', [WorkspaceController::class, 'show']);
    Route::get('/workspace/developer', [WorkspaceController::class, 'developer']);
    Route::get('/workspace/designer', [WorkspaceController::class, 'designer']);
    Route::get('/workspace/marketing', [WorkspaceController::class, 'marketing']);
    Route::get('/workspace/event', [WorkspaceController::class, 'event']);
    Route::get('/workspace/printing', [WorkspaceController::class, 'printing']);
    Route::get('/workspace/media-buyer', [WorkspaceController::class, 'mediaBuyer']);
    Route::get('/workspace/video-editor', [WorkspaceController::class, 'videoEditor']);
    Route::get('/workspace/account-manager', [WorkspaceController::class, 'accountManager']);
    Route::get('/workspace/hr', [WorkspaceController::class, 'hr']);
    Route::get('/workspace/projects', [WorkspaceProjectController::class, 'index']);
    Route::get('/workspace/projects/{project}', [WorkspaceProjectController::class, 'show']);
    Route::get('/workspace/projects/{project}/tasks', [WorkspaceProjectController::class, 'tasks']);
    Route::get('/workspace/tasks', [WorkspaceTaskController::class, 'index']);
    Route::get('/workspace/tasks/{task}', [WorkspaceTaskController::class, 'show']);
    Route::patch('/workspace/tasks/{task}/status', [WorkspaceTaskController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:ACCOUNT_MANAGER'])->group(function (): void {
    Route::post('/workspace/projects', [WorkspaceProjectController::class, 'store']);
    Route::put('/workspace/projects/{project}', [WorkspaceProjectController::class, 'update']);
    Route::get('/workspace/account-manager/customers', [WorkspaceProjectController::class, 'customers']);
    Route::get('/workspace/account-manager/tasks', [AccountManagerTaskController::class, 'index']);
    Route::post('/workspace/account-manager/tasks', [AccountManagerTaskController::class, 'store']);
    Route::put('/workspace/account-manager/tasks/{task}', [AccountManagerTaskController::class, 'update']);
    Route::get('/workspace/account-manager/assignees', [AccountManagerTaskController::class, 'assignees']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:HR'])->group(function (): void {
    Route::get('/workspace/hr/employees', [HrDirectoryController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:CUSTOMER'])->group(function (): void {
    Route::get('/printing-requests', [PrintingRequestController::class, 'index']);
    Route::post('/printing-requests', [PrintingRequestController::class, 'store'])->middleware('throttle:hebr-uploads');
    Route::get('/printing-requests/{printing_request}', [PrintingRequestController::class, 'show']);
    Route::get('/printing-requests/{printing_request}/file', [PrintingRequestController::class, 'file']);

    Route::get('/customer/dashboard', [CustomerDashboardController::class, 'show']);
    Route::get('/customer/projects', [CustomerDashboardController::class, 'projects']);
    Route::get('/customer/projects/{project}', [CustomerDashboardController::class, 'project']);
    Route::get('/customer/orders', [CustomerOrderController::class, 'index']);
    Route::post('/customer/orders', [CustomerOrderController::class, 'storePackage'])->middleware('throttle:hebr-payments');
    Route::get('/customer/orders/{order}', [CustomerOrderController::class, 'show']);

    Route::get('/customer/payments', [CustomerPaymentController::class, 'index']);
    Route::get('/customer/payments/settings', [CustomerPaymentController::class, 'settings']);
    Route::post('/customer/payments', [CustomerPaymentController::class, 'store'])->middleware('throttle:hebr-payments');
    Route::get('/customer/payments/{payment}', [CustomerPaymentController::class, 'show']);
    Route::post('/customer/payments/{payment}/card', [CustomerPaymentController::class, 'card'])->middleware('throttle:hebr-payments');
    Route::post('/customer/payments/{payment}/manual-transfer', [CustomerPaymentController::class, 'manualTransfer'])->middleware('throttle:hebr-payments');

    Route::get('/customer/conversations', [CustomerConversationController::class, 'index']);
    Route::post('/customer/conversations', [CustomerConversationController::class, 'store'])
        ->middleware('throttle:hebr-messages');
    Route::get('/customer/conversations/{conversation}', [CustomerConversationController::class, 'show']);
    Route::post('/customer/conversations/{conversation}/messages', [CustomerConversationController::class, 'storeMessage'])
        ->middleware('throttle:hebr-messages');

    Route::get('/customer/files', [CustomerFileController::class, 'index']);
    Route::post('/customer/files', [CustomerFileController::class, 'store'])->middleware('throttle:hebr-uploads');
    Route::get('/customer/files/{file}', [CustomerFileController::class, 'show']);
    Route::get('/customer/files/{file}/download', [CustomerFileController::class, 'download']);
    Route::get('/customer/files/{file}/preview', [CustomerFileController::class, 'preview']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::middleware('role:OWNER')->group(function (): void {
        Route::get('/test', [AccessController::class, 'test']);
        Route::get('/dashboard', [DashboardController::class, 'show']);
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::get('/employees/{user}', [EmployeeController::class, 'show']);
        Route::put('/employees/{user}', [EmployeeController::class, 'update']);
        Route::patch('/employees/{user}/status', [EmployeeController::class, 'setStatus']);
        Route::get('/consultant', [ConsultantSettingsController::class, 'show']);
        Route::patch('/consultant', [ConsultantSettingsController::class, 'update']);
        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/revenue', [AdminPaymentController::class, 'revenue']);
        Route::get('/payments/settings', [AdminPaymentController::class, 'settings']);
        Route::patch('/payments/settings', [AdminPaymentController::class, 'updateSettings']);
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);
        Route::post('/payments/{payment}/verify', [AdminPaymentController::class, 'verify']);
        Route::post('/payments/{payment}/reject', [AdminPaymentController::class, 'reject']);
    });

    Route::middleware('role:OWNER,ADMIN_MANAGER')->group(function (): void {
        Route::apiResource('services', AdminServiceController::class);
        Route::apiResource('packages', AdminPackageController::class);
    });

    Route::middleware('role:OWNER,ADMIN_MANAGER,PRINTING_SPECIALIST')->group(function (): void {
        Route::get('/printing-requests', [AdminPrintingRequestController::class, 'index']);
        Route::get('/printing-requests/{printing_request}', [AdminPrintingRequestController::class, 'show']);
        Route::get('/printing-requests/{printing_request}/file', [AdminPrintingRequestController::class, 'file']);
        Route::patch('/printing-requests/{printing_request}/pricing', [AdminPrintingRequestController::class, 'setEstimatedPrice']);
        Route::patch('/printing-requests/{printing_request}/request-quote', [AdminPrintingRequestController::class, 'requestQuote']);
        Route::patch('/printing-requests/{printing_request}/quote', [AdminPrintingRequestController::class, 'provideQuote']);
    });
});
