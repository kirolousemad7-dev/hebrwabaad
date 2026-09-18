<?php

use App\Http\Controllers\Api\AccountManagerTaskController;
use App\Http\Controllers\Api\Admin\AccessController;
use App\Http\Controllers\Api\Admin\CatalogAddonAdminController;
use App\Http\Controllers\Api\Admin\CatalogReadinessController;
use App\Http\Controllers\Api\Admin\ConsultantSettingsController;
use App\Http\Controllers\Api\Admin\ContactInquiryAdminController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\EventRequestAdminController;
use App\Http\Controllers\Api\Admin\EventTypeAdminController;
use App\Http\Controllers\Api\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PlatformSettingController;
use App\Http\Controllers\Api\Admin\PortfolioItemController as AdminPortfolioItemController;
use App\Http\Controllers\Api\Admin\PrintingCatalogAdminController;
use App\Http\Controllers\Api\Admin\PrintingRequestController as AdminPrintingRequestController;
use App\Http\Controllers\Api\Admin\RecommendationGoalAdminController;
use App\Http\Controllers\Api\Admin\SectorAdminController;
use App\Http\Controllers\Api\Admin\SeoPageController as AdminSeoPageController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Admin\SupplierAdminController;
use App\Http\Controllers\Api\Admin\SupplierCategoryController;
use App\Http\Controllers\Api\Admin\SupplierContactController;
use App\Http\Controllers\Api\Admin\SupplierDocumentController;
use App\Http\Controllers\Api\Admin\SupplierLifecycleController;
use App\Http\Controllers\Api\Admin\SupplierPortfolioAdminController;
use App\Http\Controllers\Api\Admin\SupplierProductAdminController;
use App\Http\Controllers\Api\Admin\SupplierReviewController;
use App\Http\Controllers\Api\Admin\SupplierServiceController;
use App\Http\Controllers\Api\Admin\SupplierTagController;
use App\Http\Controllers\Api\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\Admin\WorkReviewController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Calendar\CalendarController;
use App\Http\Controllers\Api\Catalog\CatalogAddonController;
use App\Http\Controllers\Api\Catalog\EventRequestController;
use App\Http\Controllers\Api\Catalog\PackageController;
use App\Http\Controllers\Api\Catalog\PublicPlatformSettingController;
use App\Http\Controllers\Api\Catalog\PublicPortfolioController;
use App\Http\Controllers\Api\Catalog\PublicPrintingCatalogController;
use App\Http\Controllers\Api\Catalog\PublicSeoController;
use App\Http\Controllers\Api\Catalog\PublicSitemapController;
use App\Http\Controllers\Api\Catalog\PublicTestimonialController;
use App\Http\Controllers\Api\Catalog\RecommendationGoalController;
use App\Http\Controllers\Api\Catalog\SectorController;
use App\Http\Controllers\Api\Catalog\ServiceController;
use App\Http\Controllers\Api\Catalog\SupplierController;
use App\Http\Controllers\Api\Consultant\ConsultationController;
use App\Http\Controllers\Api\ContactInquiryController;
use App\Http\Controllers\Api\ContentMediaController;
use App\Http\Controllers\Api\Crm\CrmActivityController;
use App\Http\Controllers\Api\Crm\CrmAssignmentRuleController;
use App\Http\Controllers\Api\Crm\CrmAuditController;
use App\Http\Controllers\Api\Crm\CrmCalendarController;
use App\Http\Controllers\Api\Crm\CrmCompanyController;
use App\Http\Controllers\Api\Crm\CrmContactController;
use App\Http\Controllers\Api\Crm\CrmCustomerController;
use App\Http\Controllers\Api\Crm\CrmDashboardController;
use App\Http\Controllers\Api\Crm\CrmFollowUpController;
use App\Http\Controllers\Api\Crm\CrmForecastController;
use App\Http\Controllers\Api\Crm\CrmInboxController;
use App\Http\Controllers\Api\Crm\CrmLeadController;
use App\Http\Controllers\Api\Crm\CrmLeadExtrasController;
use App\Http\Controllers\Api\Crm\CrmOpportunityController;
use App\Http\Controllers\Api\Crm\CrmPipelineController;
use App\Http\Controllers\Api\Crm\CrmQuotationController;
use App\Http\Controllers\Api\Crm\CrmReportController;
use App\Http\Controllers\Api\Crm\CrmSavedFilterController;
use App\Http\Controllers\Api\Crm\CrmSettingsController;
use App\Http\Controllers\Api\Crm\CrmTargetController;
use App\Http\Controllers\Api\Crm\CrmTeamController;
use App\Http\Controllers\Api\Customer\CustomerConversationController;
use App\Http\Controllers\Api\Customer\CustomerDashboardController;
use App\Http\Controllers\Api\Customer\CustomerFileController;
use App\Http\Controllers\Api\Customer\CustomerOrderController;
use App\Http\Controllers\Api\Customer\CustomerPaymentController;
use App\Http\Controllers\Api\Employee\EmployeeWorkController;
use App\Http\Controllers\Api\GoogleCalendar\GoogleCalendarOAuthController;
use App\Http\Controllers\Api\GoogleCalendar\TaskGoogleCalendarController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HrDirectoryController;
use App\Http\Controllers\Api\Media\MediaController;
use App\Http\Controllers\Api\Meetings\MeetingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Operations\ApprovalController;
use App\Http\Controllers\Api\Operations\BusinessCalendarController;
use App\Http\Controllers\Api\Operations\CustomerPortalAccessController;
use App\Http\Controllers\Api\Operations\DepartmentController;
use App\Http\Controllers\Api\Operations\InboundWebhookIntegrationController;
use App\Http\Controllers\Api\Operations\MyDayController;
use App\Http\Controllers\Api\Operations\NotificationPreferenceController;
use App\Http\Controllers\Api\Operations\OperationalSavedViewController;
use App\Http\Controllers\Api\Operations\OperationalSlaRuleController;
use App\Http\Controllers\Api\Operations\OperationsCommandCenterController;
use App\Http\Controllers\Api\Operations\OperationsExportController;
use App\Http\Controllers\Api\Operations\OperationsSettingsController;
use App\Http\Controllers\Api\Operations\OperationsUtilityController;
use App\Http\Controllers\Api\Operations\OutboundWebhookController;
use App\Http\Controllers\Api\Operations\PrintingCommunicationController;
use App\Http\Controllers\Api\Operations\PrintingDeliveryController;
use App\Http\Controllers\Api\Operations\PrintingOperationsController;
use App\Http\Controllers\Api\Operations\PrintingQuotationController;
use App\Http\Controllers\Api\Operations\PrintingQuotationEmailController;
use App\Http\Controllers\Api\Operations\ProjectMilestoneController;
use App\Http\Controllers\Api\Operations\ProjectWorkspaceController;
use App\Http\Controllers\Api\Operations\TaskCalendarLinkController;
use App\Http\Controllers\Api\Operations\UnifiedWorkController;
use App\Http\Controllers\Api\Operations\WorkflowAutomationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Payments\PayTabsReturnController;
use App\Http\Controllers\Api\Printing\PrintingRequestController;
use App\Http\Controllers\Api\PublicCrm\PublicQuotationController;
use App\Http\Controllers\Api\PublicPortal\PublicCustomerPortalController;
use App\Http\Controllers\Api\PublicPrinting\PublicPrintingCustomerApprovalController;
use App\Http\Controllers\Api\PublicPrinting\PublicPrintingQuotationController;
use App\Http\Controllers\Api\PublicPrinting\PublicPrintingTrackController;
use App\Http\Controllers\Api\Quotes\CommercialQuotationController;
use App\Http\Controllers\Api\Quotes\CustomerQuoteRequestController;
use App\Http\Controllers\Api\NeedsDiscovery\NeedsDiscoveryController;
use App\Http\Controllers\Api\Owner\OwnerRequirementController;
use App\Http\Controllers\Api\Quotes\OwnerQuoteRequestController;
use App\Http\Controllers\Api\Quotes\PublicCommercialQuotationController;
use App\Http\Controllers\Api\Quotes\QuotationSupplierSourcingController;
use App\Http\Controllers\Api\Supplier\SupplierAuthController;
use App\Http\Controllers\Api\Supplier\SupplierEmailVerificationController;
use App\Http\Controllers\Api\Supplier\SupplierPhoneVerificationController;
use App\Http\Controllers\Api\Supplier\SupplierPortalController;
use App\Http\Controllers\Api\Supplier\SupplierRegistrationController;
use App\Http\Controllers\Api\Supplier\SupplierSourcingController;
use App\Http\Controllers\Api\Supplier\SupplierWorkspaceController;
use App\Http\Controllers\Api\SupportConversationController;
use App\Http\Controllers\Api\Webhooks\InboundWebhookController;
use App\Http\Controllers\Api\Webhooks\PayTabsWebhookController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Http\Controllers\Api\WorkspaceFileController;
use App\Http\Controllers\Api\WorkspaceProjectController;
use App\Http\Controllers\Api\WorkspaceTaskController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::get('/google-calendar/callback', [GoogleCalendarOAuthController::class, 'callback'])
    ->middleware('throttle:hebr-login');

Route::post('/webhooks/paytabs', [PayTabsWebhookController::class, 'handle']);
Route::post('/webhooks/inbound/{inboundWebhook}', [InboundWebhookController::class, 'handle'])
    ->middleware('throttle:hebr-inbound-webhooks');
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

Route::prefix('supplier')->group(function (): void {
    Route::post('/register', [SupplierRegistrationController::class, 'store'])->middleware('throttle:hebr-register');
    Route::post('/login', [SupplierAuthController::class, 'login'])->middleware('throttle:hebr-login');
    Route::post('/otp/request', [SupplierAuthController::class, 'requestOtp'])->middleware('throttle:hebr-supplier-otp');
    Route::post('/otp/verify', [SupplierAuthController::class, 'verifyOtp'])->middleware('throttle:hebr-login');
    Route::get('/email/verify', [SupplierEmailVerificationController::class, 'verify'])
        ->middleware('throttle:hebr-email-verify')
        ->name('supplier.email.verify');
});

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/{package}', [PackageController::class, 'show']);
Route::get('/sectors', [SectorController::class, 'index']);
Route::get('/sectors/{slug}', [SectorController::class, 'show']);
Route::get('/addons', [CatalogAddonController::class, 'index']);
Route::get('/recommendation-goals', [RecommendationGoalController::class, 'index']);
Route::get('/suppliers', [SupplierController::class, 'index']);
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
Route::get('/suppliers/{supplier}/portfolio', [SupplierController::class, 'portfolio']);
Route::get('/suppliers/{supplier}/products', [SupplierController::class, 'products']);
Route::get('/suppliers/{supplier}/products/{productSlug}', [SupplierController::class, 'product']);
Route::get('/seo/{page}', [PublicSeoController::class, 'show']);
Route::get('/portfolio', [PublicPortfolioController::class, 'index']);
Route::get('/portfolio/{slug}', [PublicPortfolioController::class, 'show']);
Route::get('/testimonials', [PublicTestimonialController::class, 'index']);
Route::get('/sitemap.xml', [PublicSitemapController::class, 'show']);
Route::get('/content-media/{uuid}', [ContentMediaController::class, 'show']);
Route::get('/platform-settings', [PublicPlatformSettingController::class, 'show']);
Route::get('/printing-catalog', [PublicPrintingCatalogController::class, 'index']);
Route::post('/contact', [ContactInquiryController::class, 'store'])->middleware('throttle:hebr-contact');

Route::prefix('needs-discovery')->middleware('throttle:hebr-contact')->group(function (): void {
    Route::get('/steps', [NeedsDiscoveryController::class, 'steps']);
    Route::post('/', [NeedsDiscoveryController::class, 'store'])->middleware('throttle:hebr-uploads');
});

Route::prefix('owner')->middleware([
    'auth:sanctum',
    'account.active',
    'role:OWNER,ADMIN_MANAGER,ACCOUNT_MANAGER,SALES_MANAGER',
])->group(function (): void {
    Route::get('/requirements', [OwnerRequirementController::class, 'index']);
    Route::get('/requirements/{requirement}', [OwnerRequirementController::class, 'show']);
    Route::patch('/requirements/{requirement}', [OwnerRequirementController::class, 'update']);
    Route::get('/requirements/{requirement}/attachments/{index}', [OwnerRequirementController::class, 'downloadAttachment']);
});

Route::prefix('public')->group(function (): void {
    Route::get('/quotations/{token}', [PublicQuotationController::class, 'show']);
    Route::post('/quotations/{token}/accept', [PublicQuotationController::class, 'accept']);
    Route::post('/quotations/{token}/reject', [PublicQuotationController::class, 'reject']);

    Route::middleware('throttle:hebr-public-quote')->group(function (): void {
        Route::get('/printing-quotations/{token}', [PublicPrintingQuotationController::class, 'show']);
        Route::post('/printing-quotations/{token}/accept', [PublicPrintingQuotationController::class, 'accept']);
        Route::post('/printing-quotations/{token}/reject', [PublicPrintingQuotationController::class, 'reject']);
        Route::post('/printing-quotations/{token}/checkout', [PublicPrintingQuotationController::class, 'checkout'])
            ->middleware('throttle:hebr-payments');
        Route::get('/payments/{payment}/status', [PublicPrintingQuotationController::class, 'paymentStatus'])
            ->middleware('throttle:hebr-payments');

        Route::get('/commercial-quotations/{token}', [PublicCommercialQuotationController::class, 'show']);
        Route::get('/commercial-quotations/{token}/pdf', [PublicCommercialQuotationController::class, 'pdf']);
        Route::post('/commercial-quotations/{token}/accept', [PublicCommercialQuotationController::class, 'accept']);
        Route::post('/commercial-quotations/{token}/reject', [PublicCommercialQuotationController::class, 'reject']);
        Route::post('/commercial-quotations/{token}/revision', [PublicCommercialQuotationController::class, 'requestRevision']);
        Route::post('/commercial-quotations/{token}/checkout', [PublicCommercialQuotationController::class, 'checkout'])
            ->middleware('throttle:hebr-payments');
        Route::get('/commercial-payments/{payment}/status', [PublicCommercialQuotationController::class, 'paymentStatus'])
            ->middleware('throttle:hebr-payments');
    });

    Route::middleware('throttle:hebr-public-track')->group(function (): void {
        Route::get('/printing-track/{token}', [PublicPrintingTrackController::class, 'show']);
        Route::get('/printing-approvals/{token}', [PublicPrintingCustomerApprovalController::class, 'show']);
        Route::post('/printing-approvals/{token}/approve', [PublicPrintingCustomerApprovalController::class, 'approve']);
        Route::post('/printing-approvals/{token}/reject', [PublicPrintingCustomerApprovalController::class, 'reject']);
    });

    Route::middleware('throttle:hebr-public-portal')->group(function (): void {
        Route::get('/portal/{token}', [PublicCustomerPortalController::class, 'show']);
        Route::get('/portal/{token}/quotations', [PublicCustomerPortalController::class, 'quotations']);
        Route::get('/portal/{token}/payments', [PublicCustomerPortalController::class, 'payments']);
        Route::get('/portal/{token}/printing', [PublicCustomerPortalController::class, 'printing']);
        Route::get('/portal/{token}/approvals', [PublicCustomerPortalController::class, 'approvals']);
        Route::post('/portal/{token}/approvals/{id}/decide', [PublicCustomerPortalController::class, 'decideApproval']);
        Route::get('/portal/{token}/documents/quotations/{id}/pdf', [PublicCustomerPortalController::class, 'quotationPdf']);
        Route::get('/portal/{token}/documents/payments/{id}/receipt', [PublicCustomerPortalController::class, 'paymentReceipt']);
    });

    Route::post('/portal/magic-link', [PublicCustomerPortalController::class, 'magicLink'])
        ->middleware('throttle:hebr-portal-magic-link');
});

Route::prefix('calendar')->middleware([
    'auth:sanctum',
    'account.active',
    'role:OWNER,ADMIN_MANAGER,ACCOUNT_MANAGER,HR,SALES_MANAGER,SALES_REPRESENTATIVE,WEB_DEVELOPER,GRAPHIC_DESIGNER,VIDEO_EDITOR,MARKETING_SPECIALIST,EVENT_SPECIALIST,PRINTING_SPECIALIST,MEDIA_BUYER',
])->group(function (): void {
    Route::get('/', [CalendarController::class, 'index']);
    Route::get('/summary', [CalendarController::class, 'summary']);
    Route::get('/assignees', [CalendarController::class, 'assignees']);
    Route::get('/tasks', [CalendarController::class, 'tasks']);
    Route::get('/workload', [CalendarController::class, 'workload']);
    Route::post('/conflicts', [CalendarController::class, 'conflicts']);
    Route::post('/bulk', [CalendarController::class, 'bulk']);
    Route::get('/templates', [CalendarController::class, 'templatesIndex']);
    Route::post('/templates', [CalendarController::class, 'templatesStore']);
    Route::get('/settings', [CalendarController::class, 'settingsShow']);
    Route::put('/settings', [CalendarController::class, 'settingsUpdate']);
    Route::get('/saved-filters', [CalendarController::class, 'savedFiltersIndex']);
    Route::post('/saved-filters', [CalendarController::class, 'savedFiltersStore']);
    Route::delete('/saved-filters/{savedFilter}', [CalendarController::class, 'savedFiltersDestroy']);
    Route::get('/export.ics', [CalendarController::class, 'exportIcs']);
    Route::put('/comments/{comment}', [CalendarController::class, 'commentsUpdate']);
    Route::delete('/comments/{comment}', [CalendarController::class, 'commentsDestroy']);
    Route::post('/', [CalendarController::class, 'store']);
    Route::get('/{calendarItem}', [CalendarController::class, 'show']);
    Route::put('/{calendarItem}', [CalendarController::class, 'update']);
    Route::delete('/{calendarItem}', [CalendarController::class, 'destroy']);
    Route::post('/{calendarItem}/complete', [CalendarController::class, 'complete']);
    Route::post('/{calendarItem}/reschedule', [CalendarController::class, 'reschedule']);
    Route::post('/{calendarItem}/duplicate', [CalendarController::class, 'duplicate']);
    Route::put('/{calendarItem}/checklist', [CalendarController::class, 'checklist']);
    Route::get('/{calendarItem}/activities', [CalendarController::class, 'activities']);
    Route::get('/{calendarItem}/ics', [CalendarController::class, 'downloadIcs']);
    Route::get('/{calendarItem}/comments', [CalendarController::class, 'commentsIndex']);
    Route::post('/{calendarItem}/comments', [CalendarController::class, 'commentsStore']);
    Route::get('/{calendarItem}/files', [CalendarController::class, 'filesIndex']);
    Route::post('/{calendarItem}/files', [CalendarController::class, 'filesStore'])->middleware('throttle:hebr-uploads');
    Route::delete('/{calendarItem}/files/{file}', [CalendarController::class, 'filesDetach']);
});

Route::prefix('operations')->middleware([
    'auth:sanctum',
    'account.active',
    'role:OWNER,ADMIN_MANAGER,ACCOUNT_MANAGER,HR,SALES_MANAGER,SALES_REPRESENTATIVE,WEB_DEVELOPER,GRAPHIC_DESIGNER,VIDEO_EDITOR,MARKETING_SPECIALIST,EVENT_SPECIALIST,PRINTING_SPECIALIST,MEDIA_BUYER',
])->group(function (): void {
    Route::get('/departments/options', [DepartmentController::class, 'options']);
    Route::post('/departments/assign-employee', [DepartmentController::class, 'assignEmployee']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);
    Route::put('/departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);

    Route::get('/projects', [ProjectWorkspaceController::class, 'index']);
    Route::get('/projects/{project}/workspace', [ProjectWorkspaceController::class, 'workspace']);
    Route::put('/projects/{project}/members', [ProjectWorkspaceController::class, 'syncMembers']);
    Route::get('/projects/{project}/calendar-items', [ProjectWorkspaceController::class, 'calendarItems']);
    Route::get('/projects/{project}/timeline', [ProjectWorkspaceController::class, 'timeline']);
    Route::get('/projects/{project}/milestones', [ProjectMilestoneController::class, 'index']);
    Route::post('/projects/{project}/milestones', [ProjectMilestoneController::class, 'store']);
    Route::put('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'update']);
    Route::delete('/projects/{project}/milestones/{milestone}', [ProjectMilestoneController::class, 'destroy']);

    Route::get('/saved-views', [OperationalSavedViewController::class, 'index']);
    Route::post('/saved-views', [OperationalSavedViewController::class, 'store']);
    Route::get('/saved-views/{savedView}', [OperationalSavedViewController::class, 'show']);
    Route::put('/saved-views/{savedView}', [OperationalSavedViewController::class, 'update']);
    Route::delete('/saved-views/{savedView}', [OperationalSavedViewController::class, 'destroy']);
    Route::post('/saved-views/{savedView}/pin', [OperationalSavedViewController::class, 'pin']);

    Route::get('/sla-rules', [OperationalSlaRuleController::class, 'index']);
    Route::post('/sla-rules', [OperationalSlaRuleController::class, 'store']);
    Route::put('/sla-rules/{slaRule}', [OperationalSlaRuleController::class, 'update']);
    Route::delete('/sla-rules/{slaRule}', [OperationalSlaRuleController::class, 'destroy']);

    Route::get('/business-calendars', [BusinessCalendarController::class, 'index']);
    Route::post('/business-calendars', [BusinessCalendarController::class, 'store']);
    Route::post('/business-calendars/ensure-default', [BusinessCalendarController::class, 'ensureDefault']);
    Route::get('/business-calendars/{businessCalendar}', [BusinessCalendarController::class, 'show']);
    Route::put('/business-calendars/{businessCalendar}', [BusinessCalendarController::class, 'update']);
    Route::delete('/business-calendars/{businessCalendar}', [BusinessCalendarController::class, 'destroy']);
    Route::post('/business-calendars/{businessCalendar}/holidays', [BusinessCalendarController::class, 'storeHoliday']);
    Route::post('/business-calendars/{businessCalendar}/holidays/import', [BusinessCalendarController::class, 'importHolidays']);
    Route::post('/business-calendars/{businessCalendar}/holidays/copy-year', [BusinessCalendarController::class, 'copyYear']);
    Route::delete('/business-calendars/{businessCalendar}/holidays/{holiday}', [BusinessCalendarController::class, 'destroyHoliday']);

    Route::get('/webhooks', [OutboundWebhookController::class, 'index']);
    Route::post('/webhooks', [OutboundWebhookController::class, 'store']);
    Route::get('/webhooks/{webhook}', [OutboundWebhookController::class, 'show']);
    Route::put('/webhooks/{webhook}', [OutboundWebhookController::class, 'update']);
    Route::delete('/webhooks/{webhook}', [OutboundWebhookController::class, 'destroy']);
    Route::post('/webhooks/{webhook}/test', [OutboundWebhookController::class, 'test']);
    Route::get('/webhooks/{webhook}/deliveries', [OutboundWebhookController::class, 'deliveries']);

    Route::get('/inbound-webhooks', [InboundWebhookIntegrationController::class, 'index']);
    Route::post('/inbound-webhooks', [InboundWebhookIntegrationController::class, 'store']);
    Route::get('/inbound-webhooks/{inboundWebhook}', [InboundWebhookIntegrationController::class, 'show']);
    Route::put('/inbound-webhooks/{inboundWebhook}', [InboundWebhookIntegrationController::class, 'update']);
    Route::delete('/inbound-webhooks/{inboundWebhook}', [InboundWebhookIntegrationController::class, 'destroy']);
    Route::get('/inbound-webhooks/{inboundWebhook}/receipts', [InboundWebhookIntegrationController::class, 'receipts']);

    Route::get('/automations', [WorkflowAutomationController::class, 'index']);
    Route::post('/automations', [WorkflowAutomationController::class, 'store']);
    Route::post('/automations/templates/seed', [WorkflowAutomationController::class, 'seedTemplates']);
    Route::get('/automations/{automation}', [WorkflowAutomationController::class, 'show']);
    Route::put('/automations/{automation}', [WorkflowAutomationController::class, 'update']);
    Route::delete('/automations/{automation}', [WorkflowAutomationController::class, 'destroy']);
    Route::post('/automations/{automation}/activate', [WorkflowAutomationController::class, 'activate']);
    Route::post('/automations/{automation}/deactivate', [WorkflowAutomationController::class, 'deactivate']);
    Route::post('/automations/{automation}/dry-run', [WorkflowAutomationController::class, 'dryRun']);
    Route::get('/automations/{automation}/runs', [WorkflowAutomationController::class, 'runs']);

    Route::get('/printing', [PrintingOperationsController::class, 'index']);
    Route::get('/printing/summary', [PrintingOperationsController::class, 'summary']);
    Route::get('/printing/board', [PrintingOperationsController::class, 'board']);
    Route::post('/printing/{printing_request}/status', [PrintingOperationsController::class, 'updateStatus']);
    Route::patch('/printing/{printing_request}/status', [PrintingOperationsController::class, 'updateStatus']);
    Route::post('/printing/{printing_request}/assign', [PrintingOperationsController::class, 'assign']);
    Route::patch('/printing/{printing_request}/assign', [PrintingOperationsController::class, 'assign']);
    Route::get('/printing/{printing_request}/history', [PrintingOperationsController::class, 'history']);
    Route::patch('/printing/{printing_request}/delivery', [PrintingOperationsController::class, 'updateDelivery']);
    Route::post('/printing/{printing_request}/deliveries', [PrintingDeliveryController::class, 'store']);
    Route::post('/printing-deliveries/{printing_delivery}/mark-delivered', [PrintingDeliveryController::class, 'markDelivered']);
    Route::get('/printing-requests/{printing_request}/communications', [PrintingCommunicationController::class, 'index']);
    Route::post('/customers/{user}/portal-access', [CustomerPortalAccessController::class, 'store']);
    Route::post('/customers/{user}/portal-access/resend-email', [CustomerPortalAccessController::class, 'resendEmail'])
        ->middleware('throttle:hebr-portal-resend');
    Route::delete('/customers/{user}/portal-access', [CustomerPortalAccessController::class, 'destroy']);

    Route::get('/printing-quotations', [PrintingQuotationController::class, 'index']);
    Route::post('/printing-quotations', [PrintingQuotationController::class, 'store']);
    Route::get('/printing-quotations/request/{printing_request}/eligibility', [PrintingQuotationController::class, 'eligibility']);
    Route::get('/printing-quotations/request/{printing_request}/approvals', [PrintingQuotationController::class, 'listApprovals']);
    Route::post('/printing-quotations/request/{printing_request}/approvals', [PrintingQuotationController::class, 'storeApproval']);
    Route::get('/printing-quotations/{printing_quotation}', [PrintingQuotationController::class, 'show']);
    Route::patch('/printing-quotations/{printing_quotation}', [PrintingQuotationController::class, 'update']);
    Route::post('/printing-quotations/{printing_quotation}/send', [PrintingQuotationController::class, 'send']);
    Route::post('/printing-quotations/{printing_quotation}/email', [PrintingQuotationEmailController::class, 'store'])
        ->middleware('throttle:hebr-quote-email');
    Route::post('/printing-quotations/{printing_quotation}/revise', [PrintingQuotationController::class, 'revise']);
    Route::get('/printing-quotations/{printing_quotation}/pdf', [PrintingQuotationController::class, 'pdf']);
    Route::get('/printing-quotations/{printing_quotation}/timeline', [PrintingQuotationController::class, 'timeline']);
    Route::post('/printing-quotations/{printing_quotation}/payments', [PrintingQuotationController::class, 'storePayment']);
    Route::get('/printing-quotations/{printing_quotation}/payments/{payment}/receipt', [PrintingQuotationController::class, 'paymentReceipt']);

    Route::get('/quote-requests', [OwnerQuoteRequestController::class, 'index']);
    Route::get('/quote-requests/summary', [OwnerQuoteRequestController::class, 'summary']);
    Route::get('/quote-requests/{quote_request}', [OwnerQuoteRequestController::class, 'show']);
    Route::patch('/quote-requests/{quote_request}', [OwnerQuoteRequestController::class, 'update']);
    Route::post('/quote-requests/{quote_request}/start-review', [OwnerQuoteRequestController::class, 'startReview']);
    Route::post('/quote-requests/{quote_request}/assign', [OwnerQuoteRequestController::class, 'assign']);
    Route::post('/quote-requests/{quote_request}/request-information', [OwnerQuoteRequestController::class, 'requestInformation']);
    Route::post('/quote-requests/{quote_request}/cancel', [OwnerQuoteRequestController::class, 'cancel']);
    Route::post('/quote-requests/{quote_request}/quotations', [OwnerQuoteRequestController::class, 'createQuotation']);

    Route::get('/commercial-quotations', [CommercialQuotationController::class, 'index']);
    Route::get('/commercial-quotations/{commercial_quotation}', [CommercialQuotationController::class, 'show']);
    Route::patch('/commercial-quotations/{commercial_quotation}', [CommercialQuotationController::class, 'update']);
    Route::post('/commercial-quotations/{commercial_quotation}/send', [CommercialQuotationController::class, 'send']);
    Route::post('/commercial-quotations/{commercial_quotation}/revise', [CommercialQuotationController::class, 'revise']);
    Route::get('/commercial-quotations/{commercial_quotation}/preview', [CommercialQuotationController::class, 'preview']);
    Route::get('/commercial-quotations/{commercial_quotation}/pdf', [CommercialQuotationController::class, 'pdf']);

    Route::get('/sourcing/suppliers', [QuotationSupplierSourcingController::class, 'suppliers']);
    Route::get('/commercial-quotations/{commercial_quotation}/sourcing', [QuotationSupplierSourcingController::class, 'index']);
    Route::post('/commercial-quotations/{commercial_quotation}/items/{item}/supplier-quotes', [QuotationSupplierSourcingController::class, 'requestQuote']);
    Route::get('/commercial-quotation-items/{item}/supplier-quotes/compare', [QuotationSupplierSourcingController::class, 'compare']);
    Route::post('/supplier-quotes/{quote}/under-review', [QuotationSupplierSourcingController::class, 'markUnderReview']);
    Route::post('/supplier-quotes/{quote}/select', [QuotationSupplierSourcingController::class, 'select']);
    Route::post('/supplier-quotes/{quote}/reject', [QuotationSupplierSourcingController::class, 'reject']);
    Route::post('/supplier-quotes/{quote}/replace', [QuotationSupplierSourcingController::class, 'replace']);

    Route::get('/command-center', [OperationsCommandCenterController::class, 'show']);
    Route::get('/my-day', [MyDayController::class, 'show']);

    Route::get('/settings', [OperationsSettingsController::class, 'show']);
    Route::put('/settings', [OperationsSettingsController::class, 'update']);

    Route::get('/export/work.csv', [OperationsExportController::class, 'work']);
    Route::get('/export/printing.csv', [OperationsExportController::class, 'printing']);
    Route::get('/export/printing-quotations.csv', [OperationsExportController::class, 'printingQuotations']);
    Route::get('/export/sla.csv', [OperationsExportController::class, 'sla']);

    Route::get('/work/focus', [UnifiedWorkController::class, 'focus']);
    Route::get('/work/kanban', [UnifiedWorkController::class, 'kanban']);
    Route::get('/work', [UnifiedWorkController::class, 'index']);
    Route::post('/work/task/{task}/link-calendar', [TaskCalendarLinkController::class, 'linkFromTask']);
    Route::post('/work/calendar/{calendarItem}/link-task', [TaskCalendarLinkController::class, 'linkFromCalendar']);
    Route::delete('/work/links/{task}', [TaskCalendarLinkController::class, 'unlink']);
    Route::get('/work/{work}', [UnifiedWorkController::class, 'show'])->where('work', '.*');
    Route::post('/work/{work}/complete', [UnifiedWorkController::class, 'complete'])->where('work', '.*');
    Route::post('/work/{work}/assign', [UnifiedWorkController::class, 'assign'])->where('work', '.*');
    Route::post('/work/{work}/priority', [UnifiedWorkController::class, 'priority'])->where('work', '.*');
    Route::post('/work/{work}/status', [UnifiedWorkController::class, 'status'])->where('work', '.*');
    Route::post('/work/{work}/reschedule', [UnifiedWorkController::class, 'reschedule'])->where('work', '.*');
    Route::post('/work/{work}/start', [UnifiedWorkController::class, 'start'])->where('work', '.*');

    Route::get('/approvals/inbox', [ApprovalController::class, 'inbox']);
    Route::get('/approvals/mine', [ApprovalController::class, 'mine']);
    Route::post('/approvals', [ApprovalController::class, 'store']);
    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject']);

    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);

    Route::get('/search', [OperationsUtilityController::class, 'search']);
    Route::get('/payment-capabilities', [OperationsUtilityController::class, 'paymentCapabilities']);
    Route::get('/payments/reconciliation', [OperationsUtilityController::class, 'paymentsReconciliation']);
    Route::get('/insights/printing-funnel', [OperationsUtilityController::class, 'printingFunnel']);
    Route::get('/insights/printing', [OperationsUtilityController::class, 'printingInsights']);
    Route::get('/insights/payments', [OperationsUtilityController::class, 'paymentInsights']);
    Route::get('/insights/payment-funnel', [OperationsUtilityController::class, 'paymentFunnel']);
    Route::get('/insights', [OperationsUtilityController::class, 'insights']);
    Route::get('/customers/{user}/printing-history', [OperationsUtilityController::class, 'customerPrintingHistory']);
    Route::get('/team-dashboard', [OperationsUtilityController::class, 'teamDashboard']);
    Route::post('/attention/snooze', [OperationsUtilityController::class, 'snoozeAttention']);
});

Route::prefix('crm')->middleware([
    'auth:sanctum',
    'account.active',
    'role:OWNER,ADMIN_MANAGER,SALES_MANAGER,SALES_REPRESENTATIVE',
])->group(function (): void {
    Route::get('/dashboard', [CrmDashboardController::class, 'show']);
    Route::get('/team', [CrmTeamController::class, 'index']);
    Route::get('/inbox', [CrmInboxController::class, 'index']);
    Route::post('/inbox/mark-read', [CrmInboxController::class, 'markRead']);

    Route::get('/leads', [CrmLeadController::class, 'index']);
    Route::post('/leads', [CrmLeadController::class, 'store']);
    Route::get('/leads/duplicates', [CrmLeadController::class, 'duplicates']);
    Route::post('/leads/import', [CrmLeadExtrasController::class, 'import']);
    Route::post('/leads/bulk', [CrmLeadExtrasController::class, 'bulk']);
    Route::post('/leads/merge', [CrmLeadExtrasController::class, 'mergeLeads']);
    Route::get('/leads/{lead}', [CrmLeadController::class, 'show']);
    Route::put('/leads/{lead}', [CrmLeadController::class, 'update']);
    Route::patch('/leads/{lead}/assign', [CrmLeadController::class, 'assign']);
    Route::patch('/leads/{lead}/stage', [CrmLeadController::class, 'stage']);
    Route::post('/leads/{lead}/convert', [CrmLeadController::class, 'convert']);
    Route::post('/leads/{lead}/lose', [CrmLeadController::class, 'lose']);

    Route::get('/export/{entity}', [CrmLeadExtrasController::class, 'export']);

    Route::get('/companies', [CrmCompanyController::class, 'index']);
    Route::post('/companies', [CrmCompanyController::class, 'store']);
    Route::get('/companies/{company}', [CrmCompanyController::class, 'show']);
    Route::put('/companies/{company}', [CrmCompanyController::class, 'update']);
    Route::delete('/companies/{company}', [CrmCompanyController::class, 'destroy']);
    Route::post('/companies/{company}/archive', [CrmCompanyController::class, 'archive']);

    Route::get('/contacts', [CrmContactController::class, 'index']);
    Route::post('/contacts', [CrmContactController::class, 'store']);
    Route::get('/contacts/{contact}', [CrmContactController::class, 'show']);
    Route::put('/contacts/{contact}', [CrmContactController::class, 'update']);

    Route::get('/targets/progress', [CrmTargetController::class, 'progress']);
    Route::get('/targets', [CrmTargetController::class, 'index']);
    Route::post('/targets', [CrmTargetController::class, 'store']);
    Route::put('/targets/{target}', [CrmTargetController::class, 'update']);
    Route::delete('/targets/{target}', [CrmTargetController::class, 'destroy']);

    Route::get('/forecast', [CrmForecastController::class, 'show']);
    Route::get('/calendar', [CrmCalendarController::class, 'index']);

    Route::get('/saved-filters', [CrmSavedFilterController::class, 'index']);
    Route::post('/saved-filters', [CrmSavedFilterController::class, 'store']);
    Route::delete('/saved-filters/{savedFilter}', [CrmSavedFilterController::class, 'destroy']);

    Route::get('/customers/{user}', [CrmCustomerController::class, 'show']);
    Route::get('/audit-logs', [CrmAuditController::class, 'index']);

    Route::get('/opportunities', [CrmOpportunityController::class, 'index']);
    Route::post('/opportunities', [CrmOpportunityController::class, 'store']);
    Route::get('/opportunities/{opportunity}', [CrmOpportunityController::class, 'show']);
    Route::patch('/opportunities/{opportunity}/stage', [CrmOpportunityController::class, 'stage']);

    Route::get('/follow-ups', [CrmFollowUpController::class, 'index']);
    Route::post('/follow-ups', [CrmFollowUpController::class, 'store']);
    Route::post('/follow-ups/{followUp}/complete', [CrmFollowUpController::class, 'complete']);
    Route::patch('/follow-ups/{followUp}/reschedule', [CrmFollowUpController::class, 'reschedule']);
    Route::post('/follow-ups/{followUp}/cancel', [CrmFollowUpController::class, 'cancel']);

    Route::get('/activities', [CrmActivityController::class, 'index']);
    Route::post('/activities', [CrmActivityController::class, 'store']);

    Route::get('/quotations', [CrmQuotationController::class, 'index']);
    Route::post('/quotations', [CrmQuotationController::class, 'store']);
    Route::get('/quotations/{quotation}', [CrmQuotationController::class, 'show']);
    Route::patch('/quotations/{quotation}/status', [CrmQuotationController::class, 'status']);
    Route::get('/quotations/{quotation}/pdf', [CrmQuotationController::class, 'pdf']);
    Route::post('/quotations/{quotation}/approve', [CrmQuotationController::class, 'approve']);
    Route::post('/quotations/{quotation}/reject', [CrmQuotationController::class, 'reject']);
    Route::post('/quotations/{quotation}/send', [CrmQuotationController::class, 'send']);

    Route::get('/pipeline/stages', [CrmPipelineController::class, 'stages']);
    Route::put('/pipeline/stages/reorder', [CrmPipelineController::class, 'reorder']);

    Route::get('/reports/sources', [CrmReportController::class, 'sources']);
    Route::get('/reports/reps', [CrmReportController::class, 'reps']);
    Route::get('/reports/lost-reasons', [CrmReportController::class, 'lostReasons']);
    Route::get('/reports/services', [CrmReportController::class, 'services']);

    Route::get('/settings', [CrmSettingsController::class, 'index']);
    Route::put('/settings/config', [CrmSettingsController::class, 'updateConfig']);
    Route::post('/settings/sources', [CrmSettingsController::class, 'storeSource']);
    Route::put('/settings/sources/{source}', [CrmSettingsController::class, 'updateSource']);
    Route::post('/settings/stages', [CrmSettingsController::class, 'storeStage']);
    Route::put('/settings/stages/{stage}', [CrmSettingsController::class, 'updateStage']);
    Route::post('/settings/lost-reasons', [CrmSettingsController::class, 'storeLostReason']);
    Route::put('/settings/lost-reasons/{reason}', [CrmSettingsController::class, 'updateLostReason']);
    Route::post('/settings/tags', [CrmSettingsController::class, 'storeTag']);
    Route::put('/settings/tags/{tag}', [CrmSettingsController::class, 'updateTag']);

    Route::get('/assignment-rules', [CrmAssignmentRuleController::class, 'index']);
    Route::post('/assignment-rules', [CrmAssignmentRuleController::class, 'store']);
    Route::put('/assignment-rules/{assignmentRule}', [CrmAssignmentRuleController::class, 'update']);
    Route::delete('/assignment-rules/{assignmentRule}', [CrmAssignmentRuleController::class, 'destroy']);
});

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

    Route::post('/content-media', [ContentMediaController::class, 'store'])->middleware('throttle:hebr-uploads');

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

    Route::get('/meetings/providers', [MeetingController::class, 'providers']);
    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::patch('/meetings/{meeting}', [MeetingController::class, 'update']);
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);

    Route::get('/media/meta', [MediaController::class, 'entityTypes']);
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:hebr-uploads');
    Route::get('/media/{media}', [MediaController::class, 'show']);
    Route::post('/media/{media}', [MediaController::class, 'update'])->middleware('throttle:hebr-uploads');
    Route::patch('/media/{media}', [MediaController::class, 'update']);
    Route::post('/media/{media}/duplicate', [MediaController::class, 'duplicate']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);
    Route::get('/media/{media}/download', [MediaController::class, 'download']);
    Route::get('/media/{media}/preview', [MediaController::class, 'preview']);

    Route::get('/google-calendar/status', [GoogleCalendarOAuthController::class, 'status']);
    Route::post('/google-calendar/connect', [GoogleCalendarOAuthController::class, 'connect']);
    Route::delete('/google-calendar/disconnect', [GoogleCalendarOAuthController::class, 'disconnect']);
    Route::patch('/google-calendar/settings', [GoogleCalendarOAuthController::class, 'updateSettings']);
    Route::get('/workspace/tasks/{task}/google-calendar', [TaskGoogleCalendarController::class, 'status']);
    Route::post('/workspace/tasks/{task}/google-calendar/enable', [TaskGoogleCalendarController::class, 'enable']);
    Route::post('/workspace/tasks/{task}/google-calendar/sync', [TaskGoogleCalendarController::class, 'sync']);
    Route::post('/workspace/tasks/{task}/google-calendar/disable', [TaskGoogleCalendarController::class, 'disable']);
    Route::put('/workspace/tasks/{task}/reminders', [TaskGoogleCalendarController::class, 'updateReminders']);
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

Route::middleware(['auth:sanctum', 'account.active'])->group(function (): void {
    Route::get('/employee/work', [EmployeeWorkController::class, 'index']);
    Route::post('/employee/work', [EmployeeWorkController::class, 'store']);
    Route::get('/employee/work/{work}', [EmployeeWorkController::class, 'show']);
    Route::put('/employee/work/{work}', [EmployeeWorkController::class, 'update']);
    Route::post('/employee/work/{work}/submit', [EmployeeWorkController::class, 'submit']);
    Route::post('/employee/work/{work}/resubmit', [EmployeeWorkController::class, 'submit']);
    Route::delete('/employee/work/{work}', [EmployeeWorkController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:SUPPLIER'])->group(function (): void {
    Route::get('/supplier/dashboard', [SupplierPortalController::class, 'dashboard']);
    Route::get('/supplier/completion', [SupplierPortalController::class, 'completion']);
    Route::get('/supplier/settings', [SupplierPortalController::class, 'settings']);

    Route::get('/supplier/email/status', [SupplierEmailVerificationController::class, 'status']);
    Route::post('/supplier/email/resend', [SupplierEmailVerificationController::class, 'resend'])
        ->middleware('throttle:hebr-email-verify-resend');

    Route::post('/supplier/phone/otp/request', [SupplierPhoneVerificationController::class, 'requestCode'])
        ->middleware('throttle:hebr-phone-otp');
    Route::post('/supplier/phone/otp/verify', [SupplierPhoneVerificationController::class, 'verify'])
        ->middleware('throttle:hebr-login');

    Route::get('/supplier/contacts', [SupplierPortalController::class, 'contacts']);
    Route::post('/supplier/contacts', [SupplierPortalController::class, 'storeContact']);
    Route::put('/supplier/contacts/{contact}', [SupplierPortalController::class, 'updateContact']);
    Route::delete('/supplier/contacts/{contact}', [SupplierPortalController::class, 'destroyContact']);

    Route::get('/supplier/services', [SupplierPortalController::class, 'services']);
    Route::post('/supplier/services', [SupplierPortalController::class, 'storeService']);
    Route::put('/supplier/services/{service}', [SupplierPortalController::class, 'updateService']);
    Route::delete('/supplier/services/{service}', [SupplierPortalController::class, 'destroyService']);

    Route::get('/supplier/documents', [SupplierPortalController::class, 'documents']);
    Route::post('/supplier/documents', [SupplierPortalController::class, 'storeDocument']);
    Route::delete('/supplier/documents/{document}', [SupplierPortalController::class, 'destroyDocument']);

    Route::get('/supplier/profile', [SupplierWorkspaceController::class, 'profile']);
    Route::put('/supplier/profile', [SupplierWorkspaceController::class, 'updateProfile']);
    Route::post('/supplier/profile/submit', [SupplierWorkspaceController::class, 'submitProfile']);
    Route::post('/supplier/profile/resubmit', [SupplierWorkspaceController::class, 'submitProfile']);
    Route::get('/supplier/content', [SupplierWorkspaceController::class, 'content']);
    Route::post('/supplier/content/portfolio', [SupplierWorkspaceController::class, 'storePortfolio']);
    Route::put('/supplier/content/portfolio/{item}', [SupplierWorkspaceController::class, 'updatePortfolio']);
    Route::post('/supplier/content/portfolio/{item}/submit', [SupplierWorkspaceController::class, 'submitPortfolio']);
    Route::post('/supplier/content/portfolio/{item}/resubmit', [SupplierWorkspaceController::class, 'submitPortfolio']);
    Route::post('/supplier/content/products', [SupplierWorkspaceController::class, 'storeProduct']);
    Route::put('/supplier/content/products/{product}', [SupplierWorkspaceController::class, 'updateProduct']);
    Route::post('/supplier/content/products/{product}/submit', [SupplierWorkspaceController::class, 'submitProduct']);
    Route::post('/supplier/content/products/{product}/resubmit', [SupplierWorkspaceController::class, 'submitProduct']);

    Route::get('/supplier/sourcing-requests', [SupplierSourcingController::class, 'index']);
    Route::get('/supplier/sourcing-requests/{quote}', [SupplierSourcingController::class, 'show']);
    Route::post('/supplier/sourcing-requests/{quote}/respond', [SupplierSourcingController::class, 'respond']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:HR'])->group(function (): void {
    Route::get('/workspace/hr/employees', [HrDirectoryController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'account.active', 'role:CUSTOMER'])->group(function (): void {
    Route::get('/printing-requests', [PrintingRequestController::class, 'index']);
    Route::post('/printing-requests', [PrintingRequestController::class, 'store'])->middleware('throttle:hebr-uploads');
    Route::get('/printing-requests/{printing_request}', [PrintingRequestController::class, 'show']);
    Route::get('/printing-requests/{printing_request}/file', [PrintingRequestController::class, 'file']);
    Route::post('/printing-requests/{printing_request}/reorder', [PrintingRequestController::class, 'reorder']);

    Route::post('/event-requests', [EventRequestController::class, 'store']);

    Route::get('/customer/quote-requests', [CustomerQuoteRequestController::class, 'index']);
    Route::post('/customer/quote-requests', [CustomerQuoteRequestController::class, 'store'])->middleware('throttle:hebr-uploads');
    Route::get('/customer/quote-requests/{quote_request}', [CustomerQuoteRequestController::class, 'show']);
    Route::post('/customer/quote-requests/{quote_request}/respond', [CustomerQuoteRequestController::class, 'respond']);
    Route::get('/customer/commercial-quotations/{commercial_quotation}/pdf', [CustomerQuoteRequestController::class, 'quotationPdf']);

    Route::get('/customer/dashboard', [CustomerDashboardController::class, 'show']);
    Route::get('/customer/projects', [CustomerDashboardController::class, 'projects']);
    Route::get('/customer/projects/{project}', [CustomerDashboardController::class, 'project']);
    Route::get('/customer/orders', [CustomerOrderController::class, 'index']);
    Route::post('/customer/orders', [CustomerOrderController::class, 'storePackage'])->middleware('throttle:hebr-payments');
    Route::post('/customer/orders/custom-package', [CustomerOrderController::class, 'storeCustomPackage'])->middleware('throttle:hebr-payments');
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
        Route::post('/payments/{payment}/reconcile', [AdminPaymentController::class, 'reconcile']);
        Route::get('/payments/{payment}/refunds', [AdminPaymentController::class, 'refunds']);
        Route::post('/payments/{payment}/refunds', [AdminPaymentController::class, 'storeRefund']);
        Route::post('/payment-refunds/{refund}/confirm-manual', [AdminPaymentController::class, 'confirmManualRefund']);

        Route::apiResource('sectors', SectorAdminController::class);
        Route::apiResource('addons', CatalogAddonAdminController::class)->parameters(['addons' => 'addon']);
        Route::apiResource('recommendation-goals', RecommendationGoalAdminController::class);
        Route::get('/catalog-readiness', [CatalogReadinessController::class, 'show']);
        Route::get('/event-requests', [EventRequestAdminController::class, 'index']);
        Route::patch('/event-requests/{event_request}', [EventRequestAdminController::class, 'update']);
    });

    Route::middleware('role:OWNER,ADMIN_MANAGER')->group(function (): void {
        Route::get('/platform-settings', [PlatformSettingController::class, 'show']);
        Route::put('/platform-settings', [PlatformSettingController::class, 'update']);
        Route::post('/platform-settings/brand-assets', [PlatformSettingController::class, 'uploadBrandAsset']);
        Route::get('/printing-catalog', [PrintingCatalogAdminController::class, 'index']);
        Route::post('/printing-catalog/products', [PrintingCatalogAdminController::class, 'storeProduct']);
        Route::put('/printing-catalog/products/{printingProduct}', [PrintingCatalogAdminController::class, 'updateProduct']);
        Route::post('/printing-catalog/categories', [PrintingCatalogAdminController::class, 'storeCategory']);
        Route::put('/printing-catalog/categories/{printingProductCategory}', [PrintingCatalogAdminController::class, 'updateCategory']);
        Route::post('/printing-catalog/options', [PrintingCatalogAdminController::class, 'storeOption']);
        Route::put('/printing-catalog/options/{printingProductOption}', [PrintingCatalogAdminController::class, 'updateOption']);
        Route::get('/event-types', [EventTypeAdminController::class, 'index']);
        Route::post('/event-types', [EventTypeAdminController::class, 'store']);
        Route::put('/event-types/{eventType}', [EventTypeAdminController::class, 'update']);
        Route::apiResource('services', AdminServiceController::class);
        Route::apiResource('packages', AdminPackageController::class);
        Route::get('/seo', [AdminSeoPageController::class, 'index']);
        Route::get('/seo/{page}', [AdminSeoPageController::class, 'show']);
        Route::put('/seo/{page}', [AdminSeoPageController::class, 'update']);
        Route::get('/portfolio', [AdminPortfolioItemController::class, 'index']);
        Route::post('/portfolio', [AdminPortfolioItemController::class, 'store']);
        Route::put('/portfolio/{portfolioItem}', [AdminPortfolioItemController::class, 'update']);
        Route::delete('/portfolio/{portfolioItem}', [AdminPortfolioItemController::class, 'destroy']);
        Route::get('/testimonials', [AdminTestimonialController::class, 'index']);
        Route::post('/testimonials', [AdminTestimonialController::class, 'store']);
        Route::put('/testimonials/{testimonial}', [AdminTestimonialController::class, 'update']);
        Route::delete('/testimonials/{testimonial}', [AdminTestimonialController::class, 'destroy']);
        Route::get('/contact-inquiries', [ContactInquiryAdminController::class, 'index']);
        Route::get('/work-reviews', [WorkReviewController::class, 'index']);
        Route::get('/work-reviews/{work}', [WorkReviewController::class, 'show']);
        Route::post('/work-reviews/{work}/approve-publish', [WorkReviewController::class, 'approvePublish']);
        Route::post('/work-reviews/{work}/reject', [WorkReviewController::class, 'reject']);
        Route::post('/work-reviews/{work}/request-changes', [WorkReviewController::class, 'requestChanges']);
        Route::post('/work-reviews/{work}/unpublish', [WorkReviewController::class, 'unpublish']);
        Route::post('/work-reviews/{work}/archive', [WorkReviewController::class, 'archive']);
        Route::get('/suppliers', [SupplierAdminController::class, 'index']);
        Route::post('/suppliers', [SupplierAdminController::class, 'store']);
        Route::get('/suppliers/{supplier}', [SupplierAdminController::class, 'show']);
        Route::put('/suppliers/{supplier}', [SupplierAdminController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierAdminController::class, 'destroy']);
        Route::post('/suppliers/{supplier}/activate', [SupplierAdminController::class, 'activate']);
        Route::post('/suppliers/{supplier}/deactivate', [SupplierAdminController::class, 'deactivate']);
        Route::post('/suppliers/{supplier}/publish', [SupplierAdminController::class, 'publish']);
        Route::post('/suppliers/{supplier}/unpublish', [SupplierAdminController::class, 'unpublish']);
        Route::post('/suppliers/{supplier}/approve', [SupplierLifecycleController::class, 'approve']);
        Route::post('/suppliers/{supplier}/reject', [SupplierLifecycleController::class, 'reject']);
        Route::post('/suppliers/{supplier}/suspend', [SupplierLifecycleController::class, 'suspend']);
        Route::post('/suppliers/{supplier}/block', [SupplierLifecycleController::class, 'block']);
        Route::post('/suppliers/{supplier}/request-changes', [SupplierLifecycleController::class, 'requestChanges']);
        Route::post('/suppliers/{supplier}/verify', [SupplierLifecycleController::class, 'verify']);
        Route::put('/suppliers/{supplier}/locked-fields', [SupplierLifecycleController::class, 'lockFields']);

        Route::get('/suppliers/{supplier}/contacts', [SupplierContactController::class, 'index']);
        Route::post('/suppliers/{supplier}/contacts', [SupplierContactController::class, 'store']);
        Route::put('/suppliers/{supplier}/contacts/{contact}', [SupplierContactController::class, 'update']);
        Route::delete('/suppliers/{supplier}/contacts/{contact}', [SupplierContactController::class, 'destroy']);

        Route::get('/suppliers/{supplier}/services/export', [SupplierServiceController::class, 'export']);
        Route::post('/suppliers/{supplier}/services/import', [SupplierServiceController::class, 'import']);
        Route::get('/suppliers/{supplier}/services', [SupplierServiceController::class, 'index']);
        Route::post('/suppliers/{supplier}/services', [SupplierServiceController::class, 'store']);
        Route::put('/suppliers/{supplier}/services/{service}', [SupplierServiceController::class, 'update']);
        Route::delete('/suppliers/{supplier}/services/{service}', [SupplierServiceController::class, 'destroy']);
        Route::post('/suppliers/{supplier}/services/{service}/duplicate', [SupplierServiceController::class, 'duplicate']);
        Route::post('/suppliers/{supplier}/services/{service}/archive', [SupplierServiceController::class, 'archive']);

        Route::get('/suppliers/{supplier}/products/export', [SupplierProductAdminController::class, 'export']);
        Route::post('/suppliers/{supplier}/products/import', [SupplierProductAdminController::class, 'import']);
        Route::get('/suppliers/{supplier}/products', [SupplierProductAdminController::class, 'index']);
        Route::post('/suppliers/{supplier}/products', [SupplierProductAdminController::class, 'store']);
        Route::put('/suppliers/{supplier}/products/{product}', [SupplierProductAdminController::class, 'update']);
        Route::delete('/suppliers/{supplier}/products/{product}', [SupplierProductAdminController::class, 'destroy']);
        Route::post('/suppliers/{supplier}/products/{product}/duplicate', [SupplierProductAdminController::class, 'duplicate']);
        Route::post('/suppliers/{supplier}/products/{product}/archive', [SupplierProductAdminController::class, 'archive']);

        Route::get('/suppliers/{supplier}/portfolio', [SupplierPortfolioAdminController::class, 'index']);
        Route::post('/suppliers/{supplier}/portfolio', [SupplierPortfolioAdminController::class, 'store']);
        Route::put('/suppliers/{supplier}/portfolio/{item}', [SupplierPortfolioAdminController::class, 'update']);
        Route::delete('/suppliers/{supplier}/portfolio/{item}', [SupplierPortfolioAdminController::class, 'destroy']);

        Route::get('/suppliers/{supplier}/documents', [SupplierDocumentController::class, 'index']);
        Route::post('/suppliers/{supplier}/documents', [SupplierDocumentController::class, 'store']);
        Route::delete('/suppliers/{supplier}/documents/{document}', [SupplierDocumentController::class, 'destroy']);

        Route::get('/supplier-categories', [SupplierCategoryController::class, 'index']);
        Route::post('/supplier-categories', [SupplierCategoryController::class, 'store']);
        Route::put('/supplier-categories/{category}', [SupplierCategoryController::class, 'update']);
        Route::delete('/supplier-categories/{category}', [SupplierCategoryController::class, 'destroy']);

        Route::get('/tags', [SupplierTagController::class, 'index']);
        Route::post('/tags', [SupplierTagController::class, 'store']);
        Route::put('/tags/{tag}', [SupplierTagController::class, 'update']);
        Route::delete('/tags/{tag}', [SupplierTagController::class, 'destroy']);

        Route::get('/supplier-reviews', [SupplierReviewController::class, 'index']);
        Route::post('/supplier-reviews/{type}/{id}/approve-publish', [SupplierReviewController::class, 'approvePublish']);
        Route::post('/supplier-reviews/{type}/{id}/reject', [SupplierReviewController::class, 'reject']);
        Route::post('/supplier-reviews/{type}/{id}/request-changes', [SupplierReviewController::class, 'requestChanges']);
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
