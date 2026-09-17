import { Route, Routes } from 'react-router-dom'
import { CustomerLayout } from '../layouts/CustomerLayout'
import { EmployeeLayout } from '../layouts/EmployeeLayout'
import { LandingLayout } from '../layouts/LandingLayout'
import { OwnerLayout } from '../layouts/OwnerLayout'
import { PrintingOperationsLayout } from '../layouts/PrintingOperationsLayout'
import { PublicLayout } from '../layouts/PublicLayout'
import { SupplierLayout } from '../layouts/SupplierLayout'
import { CustomerHomePage } from '../pages/customer/CustomerHomePage'
import { CustomerFilesPage } from '../pages/customer/CustomerFilesPage'
import { CustomerMessagesPage } from '../pages/customer/CustomerMessagesPage'
import { CustomerNewConversationPage } from '../pages/customer/CustomerNewConversationPage'
import { CustomerNotificationsPage } from '../pages/customer/CustomerNotificationsPage'
import { CustomerOrderDetailPage } from '../pages/customer/CustomerOrderDetailPage'
import { CustomerPackageOrderPage } from '../pages/customer/CustomerPackageOrderPage'
import { CustomerPayPage } from '../pages/customer/CustomerPayPage'
import { CustomerOrdersPage } from '../pages/customer/CustomerOrdersPage'
import { CustomerPrintingRequestDetailPage } from '../pages/customer/CustomerPrintingRequestDetailPage'
import { CustomerPrintingRequestsPage } from '../pages/customer/CustomerPrintingRequestsPage'
import { CustomerQuoteRequestDetailPage } from '../pages/customer/CustomerQuoteRequestDetailPage'
import { CustomerQuoteRequestsPage } from '../pages/customer/CustomerQuoteRequestsPage'
import { RequestQuotePage } from '../pages/customer/RequestQuotePage'
import { CustomerProfilePage } from '../pages/customer/CustomerProfilePage'
import { CustomerProjectDetailPage } from '../pages/customer/CustomerProjectDetailPage'
import { CustomerProjectsPage } from '../pages/customer/CustomerProjectsPage'
import { ConsultantPage } from '../pages/ConsultantPage'
import { AboutPage } from '../pages/AboutPage'
import { ContactPage } from '../pages/ContactPage'
import { EventsPage } from '../pages/EventsPage'
import { HomePage } from '../pages/HomePage'
import { PortfolioPage } from '../pages/PortfolioPage'
import { PortfolioDetailPage } from '../pages/PortfolioDetailPage'
import { SectorsPage } from '../pages/SectorsPage'
import { SectorDetailPage } from '../pages/SectorDetailPage'
import { LoginPage } from '../pages/LoginPage'
import { ForgotPasswordPage } from '../pages/ForgotPasswordPage'
import { ResetPasswordPage } from '../pages/ResetPasswordPage'
import { NotFoundPage } from '../pages/NotFoundPage'
import { OwnerEmployeesPage } from '../pages/owner/OwnerEmployeesPage'
import { OwnerFilesPage } from '../pages/owner/OwnerFilesPage'
import { OwnerCalendarPage } from '../pages/owner/OwnerCalendarPage'
import { OwnerHomePage } from '../pages/owner/OwnerHomePage'
import { OwnerNotificationsPage } from '../pages/owner/OwnerNotificationsPage'
import { OwnerOrderDetailPage } from '../pages/owner/OwnerOrderDetailPage'
import { OwnerOrdersPage } from '../pages/owner/OwnerOrdersPage'
import { OwnerPaymentDetailPage } from '../pages/owner/OwnerPaymentDetailPage'
import { OwnerPaymentsPage } from '../pages/owner/OwnerPaymentsPage'
import { OwnerPaymentSettingsPage } from '../pages/owner/OwnerPaymentSettingsPage'
import { OwnerSupportPage } from '../pages/owner/OwnerSupportPage'
import { OwnerMarketingPage } from '../pages/owner/OwnerMarketingPage'
import { OwnerPackagesPage } from '../pages/owner/OwnerPackagesPage'
import { OwnerSeoPage } from '../pages/owner/OwnerSeoPage'
import { OwnerCatalogControlPage } from '../pages/owner/OwnerCatalogControlPage'
import { OwnerServicesPage } from '../pages/owner/OwnerServicesPage'
import { OwnerSupplierDetailPage } from '../pages/owner/OwnerSupplierDetailPage'
import { OwnerSupplierCategoriesPage } from '../pages/owner/OwnerSupplierCategoriesPage'
import { OwnerSupplierNewPage } from '../pages/owner/OwnerSupplierNewPage'
import { OwnerSupplierReviewsPage } from '../pages/owner/OwnerSupplierReviewsPage'
import { OwnerTagsPage } from '../pages/owner/OwnerTagsPage'
import { OwnerSuppliersPage } from '../pages/owner/OwnerSuppliersPage'
import { OwnerWorkReviewsPage } from '../pages/owner/OwnerWorkReviewsPage'
import { OwnerDepartmentsPage } from '../pages/owner/OwnerDepartmentsPage'
import { OwnerProjectsPage } from '../pages/owner/OwnerProjectsPage'
import { OwnerProjectWorkspacePage } from '../pages/owner/OwnerProjectWorkspacePage'
import { OwnerAutomationsPage } from '../pages/owner/OwnerAutomationsPage'
import { OwnerApprovalsPage } from '../pages/owner/OwnerApprovalsPage'
import { OwnerOperationsInsightsPage } from '../pages/owner/OwnerOperationsInsightsPage'
import { OwnerNotificationPreferencesPage } from '../pages/owner/OwnerNotificationPreferencesPage'
import { OwnerWorkPage } from '../pages/owner/OwnerWorkPage'
import { OwnerPrintingOpsPage } from '../pages/owner/OwnerPrintingOpsPage'
import { OwnerPrintingQuotationsPage } from '../pages/owner/OwnerPrintingQuotationsPage'
import { OwnerQuoteRequestDetailPage } from '../pages/owner/OwnerQuoteRequestDetailPage'
import { OwnerQuoteRequestsPage } from '../pages/owner/OwnerQuoteRequestsPage'
import { OwnerCommercialQuotationEditorPage } from '../pages/owner/OwnerCommercialQuotationEditorPage'
import { OwnerPaymentsReconciliationPage } from '../pages/owner/OwnerPaymentsReconciliationPage'
import { OwnerIntegrationsWebhooksPage } from '../pages/owner/OwnerIntegrationsWebhooksPage'
import { OwnerInboundWebhooksPage } from '../pages/owner/OwnerInboundWebhooksPage'
import { OwnerOperationsSettingsPage } from '../pages/owner/OwnerOperationsSettingsPage'
import { OwnerPlatformSettingsPage } from '../pages/owner/OwnerPlatformSettingsPage'
import { OwnerPrintingCatalogPage } from '../pages/owner/OwnerPrintingCatalogPage'
import { CustomerPortalPage } from '../pages/CustomerPortalPage'
import { PaymentResultPage } from '../pages/PaymentResultPage'
import { PublicPrintingQuotePage } from '../pages/PublicPrintingQuotePage'
import { PublicPrintingTrackPage } from '../pages/PublicPrintingTrackPage'
import { PublicCommercialQuotationPage } from '../pages/public/PublicCommercialQuotationPage'
import { EmployeeWorkspacePage } from '../pages/employee/EmployeeWorkspacePage'
import { EmployeeWorkPage } from '../pages/employee/EmployeeWorkPage'
import { TeamDashboardPage } from '../pages/employee/TeamDashboardPage'
import { WorkspaceFilesPage } from '../pages/employee/WorkspaceFilesPage'
import { WorkspaceNotificationsPage } from '../pages/employee/WorkspaceNotificationsPage'
import { WorkspaceOrderDetailPage } from '../pages/employee/WorkspaceOrderDetailPage'
import { WorkspaceOrdersPage } from '../pages/employee/WorkspaceOrdersPage'
import { WorkspaceSupportPage } from '../pages/employee/WorkspaceSupportPage'
import { HrDirectoryPage } from '../pages/employee/HrDirectoryPage'
import { WorkspaceProjectDetailPage } from '../pages/employee/WorkspaceProjectDetailPage'
import { WorkspaceProjectsPage } from '../pages/employee/WorkspaceProjectsPage'
import { WorkspaceTaskDetailPage } from '../pages/employee/WorkspaceTaskDetailPage'
import { GoogleCalendarSettingsPage } from '../pages/employee/GoogleCalendarSettingsPage'
import { WorkspaceTasksPage } from '../pages/employee/WorkspaceTasksPage'
import { PackagesPage } from '../pages/PackagesPage'
import { PrintingRequestReviewPage } from '../pages/PrintingRequestReviewPage'
import { PrintingRequestsPage } from '../pages/PrintingRequestsPage'
import { RegisterPage } from '../pages/RegisterPage'
import { ServicesPage } from '../pages/ServicesPage'
import { MarketingPackagesPage } from '../pages/MarketingPackagesPage'
import { EventPackagesPage } from '../pages/EventPackagesPage'
import { BuildPackagePage } from '../pages/BuildPackagePage'
import { PrintingPackagingPage } from '../pages/PrintingPackagingPage'
import { PrintingCustomizePage } from '../pages/PrintingCustomizePage'
import { SuppliersPage } from '../pages/SuppliersPage'
import { SupplierDetailPage } from '../pages/SupplierDetailPage'
import { SupplierProductPage } from '../pages/SupplierProductPage'
import { SupplierHomePage } from '../pages/supplier/SupplierHomePage'
import { SupplierLoginPage } from '../pages/supplier/SupplierLoginPage'
import { SupplierNotificationsPage } from '../pages/supplier/SupplierNotificationsPage'
import {
  SupplierContactsPage,
  SupplierDashboardPage,
  SupplierDocumentsPage,
  SupplierModulePlaceholderPage,
  SupplierServicesPage,
} from '../pages/supplier/SupplierPortalPages'
import { SupplierProfilePage } from '../pages/supplier/SupplierProfilePage'
import { SupplierRegisterPage } from '../pages/supplier/SupplierRegisterPage'
import { SupplierSettingsPage } from '../pages/supplier/SupplierSettingsPage'
import { SupplierSourcingPage } from '../pages/supplier/SupplierSourcingPage'
import { LoginCodePage } from '../pages/verification/LoginCodePage'
import { VerifyEmailPage } from '../pages/verification/VerifyEmailPage'
import { VerifyPhonePage } from '../pages/verification/VerifyPhonePage'
import { CrmDashboardPage } from '../pages/crm/CrmDashboardPage'
import { CrmLeadsPage } from '../pages/crm/CrmLeadsPage'
import { CrmLeadDetailPage } from '../pages/crm/CrmLeadDetailPage'
import { CrmPipelinePage } from '../pages/crm/CrmPipelinePage'
import { CrmFollowUpsPage } from '../pages/crm/CrmFollowUpsPage'
import { CrmQuotationsPage } from '../pages/crm/CrmQuotationsPage'
import { CrmReportsPage } from '../pages/crm/CrmReportsPage'
import { CrmSettingsPage } from '../pages/crm/CrmSettingsPage'
import { CrmTeamPage } from '../pages/crm/CrmTeamPage'
import { CrmCompaniesPage } from '../pages/crm/CrmCompaniesPage'
import { CrmCompanyDetailPage } from '../pages/crm/CrmCompanyDetailPage'
import { CrmContactsPage } from '../pages/crm/CrmContactsPage'
import { CrmCalendarPage } from '../pages/crm/CrmCalendarPage'
import { CrmTargetsPage } from '../pages/crm/CrmTargetsPage'
import { CrmForecastPage } from '../pages/crm/CrmForecastPage'
import { CrmInboxPage } from '../pages/crm/CrmInboxPage'
import { CrmAuditPage } from '../pages/crm/CrmAuditPage'
import { CrmCustomer360Page } from '../pages/crm/CrmCustomer360Page'
import { CrmOpportunitiesPage } from '../pages/crm/CrmOpportunitiesPage'
import { PublicQuotationPage } from '../pages/PublicQuotationPage'
import { CrmLayout } from '../layouts/CrmLayout'
import { CATALOG_MANAGER_ROLES, CRM_ROLES, PRINTING_OPERATIONS_ROLES } from '../utils/roles'
import { EMPLOYEE_WORKSPACE_ROLES } from '../utils/staff'
import { ProtectedRoute } from './ProtectedRoute'
import { PublicRoute } from './PublicRoute'
import { RoleProtectedRoute } from './RoleProtectedRoute'

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<LandingLayout />}>
        <Route path="/" element={<HomePage />} />
      </Route>

      <Route element={<PublicLayout />}>
        <Route path="/consultant" element={<ConsultantPage />} />
        <Route path="/services" element={<ServicesPage />} />
        <Route path="/packages" element={<PackagesPage />} />
        <Route path="/sectors" element={<SectorsPage />} />
        <Route path="/sectors/:slug" element={<SectorDetailPage />} />
        <Route path="/events" element={<EventsPage />} />
        <Route path="/portfolio" element={<PortfolioPage />} />
        <Route path="/portfolio/:slug" element={<PortfolioDetailPage />} />
        <Route path="/about" element={<AboutPage />} />
        <Route path="/contact" element={<ContactPage />} />
        <Route path="/marketing-packages" element={<MarketingPackagesPage />} />
        <Route path="/event-packages" element={<EventPackagesPage />} />
        <Route path="/printing-packaging" element={<PrintingPackagingPage />} />
        <Route path="/suppliers" element={<SuppliersPage />} />
        <Route path="/suppliers/:slug" element={<SupplierDetailPage />} />
        <Route path="/suppliers/:slug/products/:productSlug" element={<SupplierProductPage />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/printing/customize/:slug" element={<PrintingCustomizePage />} />
          <Route path="/request-quote" element={<RequestQuotePage />} />
        </Route>
        <Route path="/build-package" element={<BuildPackagePage />} />
        <Route element={<PublicRoute />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/supplier/login" element={<SupplierLoginPage />} />
          <Route path="/supplier/register" element={<SupplierRegisterPage />} />
          <Route path="/login/code" element={<LoginCodePage />} />
          <Route path="/verify-email" element={<VerifyEmailPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
        </Route>
      </Route>

      <Route path="/q/:token" element={<PublicPrintingQuotePage />} />
      <Route path="/cq/:token" element={<PublicCommercialQuotationPage />} />
      <Route path="/track/:token" element={<PublicPrintingTrackPage />} />
      <Route path="/portal/:token" element={<CustomerPortalPage />} />
      <Route path="/payment/result" element={<PaymentResultPage />} />
      <Route path="/crm/q/:token" element={<PublicQuotationPage />} />

      <Route element={<RoleProtectedRoute roles={['CUSTOMER']} />}>
        <Route element={<CustomerLayout />}>
          <Route path="/dashboard" element={<CustomerHomePage />} />
          <Route path="/customer" element={<CustomerHomePage />} />
          <Route path="/dashboard/projects" element={<CustomerProjectsPage />} />
          <Route path="/dashboard/projects/:projectId" element={<CustomerProjectDetailPage />} />
          <Route path="/dashboard/orders" element={<CustomerOrdersPage />} />
          <Route path="/dashboard/packages/:slug/order" element={<CustomerPackageOrderPage />} />
          <Route path="/dashboard/orders/:orderId/pay" element={<CustomerPayPage />} />
          <Route path="/dashboard/orders/:orderId" element={<CustomerOrderDetailPage />} />
          <Route path="/dashboard/quote-requests" element={<CustomerQuoteRequestsPage />} />
          <Route path="/dashboard/quote-requests/:id" element={<CustomerQuoteRequestDetailPage />} />
          <Route path="/dashboard/messages" element={<CustomerMessagesPage />} />
          <Route path="/dashboard/messages/new" element={<CustomerNewConversationPage />} />
          <Route path="/dashboard/messages/:conversationId" element={<CustomerMessagesPage />} />
          <Route path="/dashboard/files" element={<CustomerFilesPage />} />
          <Route path="/dashboard/notifications" element={<CustomerNotificationsPage />} />
          <Route path="/dashboard/profile" element={<CustomerProfilePage />} />
          <Route path="/customer/printing-requests" element={<CustomerPrintingRequestsPage />} />
          <Route path="/customer/printing-requests/:id" element={<CustomerPrintingRequestDetailPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={[...EMPLOYEE_WORKSPACE_ROLES]} />}>
        <Route element={<EmployeeLayout />}>
          <Route path="/workspace" element={<EmployeeWorkspacePage />} />
          <Route path="/workspace/calendar" element={<OwnerCalendarPage />} />
          <Route path="/workspace/work" element={<OwnerWorkPage basePath="/workspace" />} />
          <Route path="/workspace/portfolio" element={<EmployeeWorkPage />} />
          <Route path="/workspace/team" element={<TeamDashboardPage />} />
          <Route path="/workspace/tasks" element={<WorkspaceTasksPage />} />
          <Route path="/workspace/tasks/:taskId" element={<WorkspaceTaskDetailPage />} />
          <Route path="/workspace/settings/google-calendar" element={<GoogleCalendarSettingsPage />} />
          <Route path="/workspace/files" element={<WorkspaceFilesPage />} />
          <Route path="/workspace/notifications" element={<WorkspaceNotificationsPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={[...EMPLOYEE_WORKSPACE_ROLES].filter((role) => role !== 'HR')} />}>
        <Route element={<EmployeeLayout />}>
          <Route path="/workspace/projects" element={<WorkspaceProjectsPage />} />
          <Route path="/workspace/projects/:projectId" element={<WorkspaceProjectDetailPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={['ACCOUNT_MANAGER']} />}>
        <Route element={<EmployeeLayout />}>
          <Route path="/workspace/orders" element={<WorkspaceOrdersPage />} />
          <Route path="/workspace/orders/:orderId" element={<WorkspaceOrderDetailPage />} />
          <Route path="/workspace/support" element={<WorkspaceSupportPage />} />
          <Route path="/workspace/support/:conversationId" element={<WorkspaceSupportPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={['HR']} />}>
        <Route element={<EmployeeLayout />}>
          <Route path="/workspace/directory" element={<HrDirectoryPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={PRINTING_OPERATIONS_ROLES} />}>
        <Route element={<PrintingOperationsLayout />}>
          <Route path="/printing-requests" element={<PrintingRequestsPage />} />
          <Route path="/printing-requests/:id" element={<PrintingRequestReviewPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={PRINTING_OPERATIONS_ROLES} />}>
        <Route element={<OwnerLayout />}>
          <Route path="/owner/printing-quotations" element={<OwnerPrintingQuotationsPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={['OWNER']} />}>
        <Route element={<OwnerLayout />}>
          <Route path="/owner" element={<OwnerHomePage />} />
          <Route path="/owner/calendar" element={<OwnerCalendarPage />} />
          <Route path="/owner/employees" element={<OwnerEmployeesPage />} />
          <Route path="/owner/orders" element={<OwnerOrdersPage />} />
          <Route path="/owner/orders/:orderId" element={<OwnerOrderDetailPage />} />
          <Route path="/owner/payments" element={<OwnerPaymentsPage />} />
          <Route path="/owner/payments/reconciliation" element={<OwnerPaymentsReconciliationPage />} />
          <Route path="/owner/payments/settings" element={<OwnerPaymentSettingsPage />} />
          <Route path="/owner/payments/:paymentId" element={<OwnerPaymentDetailPage />} />
          <Route path="/owner/support" element={<OwnerSupportPage />} />
          <Route path="/owner/support/:conversationId" element={<OwnerSupportPage />} />
          <Route path="/owner/files" element={<OwnerFilesPage />} />
          <Route path="/owner/notifications" element={<OwnerNotificationsPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={CATALOG_MANAGER_ROLES} />}>
        <Route element={<OwnerLayout />}>
          <Route path="/owner/departments" element={<OwnerDepartmentsPage />} />
          <Route path="/owner/work" element={<OwnerWorkPage basePath="/owner" />} />
          <Route path="/owner/printing-ops" element={<OwnerPrintingOpsPage />} />
          <Route path="/owner/quote-requests" element={<OwnerQuoteRequestsPage />} />
          <Route path="/owner/quote-requests/:id" element={<OwnerQuoteRequestDetailPage />} />
          <Route path="/owner/commercial-quotations/:id" element={<OwnerCommercialQuotationEditorPage />} />
          <Route path="/owner/projects" element={<OwnerProjectsPage />} />
          <Route path="/owner/projects/:projectId" element={<OwnerProjectWorkspacePage />} />
          <Route path="/owner/automations" element={<OwnerAutomationsPage />} />
          <Route path="/owner/approvals" element={<OwnerApprovalsPage />} />
          <Route path="/owner/operations-insights" element={<OwnerOperationsInsightsPage />} />
          <Route path="/owner/integrations/webhooks" element={<OwnerIntegrationsWebhooksPage />} />
          <Route path="/owner/integrations/inbound-webhooks" element={<OwnerInboundWebhooksPage />} />
          <Route path="/owner/operations-settings" element={<OwnerOperationsSettingsPage />} />
          <Route path="/owner/settings" element={<OwnerPlatformSettingsPage />} />
          <Route path="/owner/printing-catalog" element={<OwnerPrintingCatalogPage />} />
          <Route path="/owner/notification-preferences" element={<OwnerNotificationPreferencesPage />} />
          <Route path="/owner/services" element={<OwnerServicesPage />} />
          <Route path="/owner/packages" element={<OwnerPackagesPage />} />
          <Route path="/owner/catalog-control" element={<OwnerCatalogControlPage />} />
          <Route path="/owner/seo" element={<OwnerSeoPage />} />
          <Route path="/owner/marketing" element={<OwnerMarketingPage />} />
          <Route path="/owner/work-reviews" element={<OwnerWorkReviewsPage />} />
          <Route path="/owner/suppliers" element={<OwnerSuppliersPage />} />
          <Route path="/owner/suppliers/new" element={<OwnerSupplierNewPage />} />
          <Route path="/owner/suppliers/:id" element={<OwnerSupplierDetailPage />} />
          <Route path="/owner/supplier-categories" element={<OwnerSupplierCategoriesPage />} />
          <Route path="/owner/tags" element={<OwnerTagsPage />} />
          <Route path="/owner/supplier-reviews" element={<OwnerSupplierReviewsPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={['SUPPLIER']} />}>
        <Route element={<SupplierLayout />}>
          <Route path="/supplier" element={<SupplierDashboardPage />} />
          <Route path="/supplier/content" element={<SupplierHomePage />} />
          <Route path="/supplier/profile" element={<SupplierProfilePage />} />
          <Route path="/verify-phone" element={<VerifyPhonePage />} />
          <Route path="/supplier/services" element={<SupplierServicesPage />} />
          <Route path="/supplier/products" element={<SupplierHomePage />} />
          <Route path="/supplier/portfolio" element={<SupplierHomePage />} />
          <Route path="/supplier/documents" element={<SupplierDocumentsPage />} />
          <Route path="/supplier/contacts" element={<SupplierContactsPage />} />
          <Route path="/supplier/quotations" element={<SupplierSourcingPage />} />
          <Route path="/supplier/sourcing" element={<SupplierSourcingPage />} />
          <Route path="/supplier/projects" element={<SupplierModulePlaceholderPage title="المشاريع" description="مشاريع التنفيذ المرتبطة بالمورد." />} />
          <Route path="/supplier/tasks" element={<SupplierModulePlaceholderPage title="المهام" description="مهام التنفيذ المرتبطة بالمورد." />} />
          <Route path="/supplier/calendar" element={<SupplierModulePlaceholderPage title="التقويم" description="مواعيد المورد التشغيلية." />} />
          <Route path="/supplier/messages" element={<SupplierModulePlaceholderPage title="الرسائل" description="مراسلات المورد مع المنصة." />} />
          <Route path="/supplier/notifications" element={<SupplierNotificationsPage />} />
          <Route path="/supplier/settings" element={<SupplierSettingsPage />} />
        </Route>
      </Route>

      <Route element={<RoleProtectedRoute roles={[...CRM_ROLES]} />}>
        <Route element={<CrmLayout />}>
          <Route path="/crm" element={<CrmDashboardPage />} />
          <Route path="/crm/inbox" element={<CrmInboxPage />} />
          <Route path="/crm/leads" element={<CrmLeadsPage />} />
          <Route path="/crm/leads/:id" element={<CrmLeadDetailPage />} />
          <Route path="/crm/companies" element={<CrmCompaniesPage />} />
          <Route path="/crm/companies/:id" element={<CrmCompanyDetailPage />} />
          <Route path="/crm/contacts" element={<CrmContactsPage />} />
          <Route path="/crm/opportunities" element={<CrmOpportunitiesPage />} />
          <Route path="/crm/pipeline" element={<CrmPipelinePage />} />
          <Route path="/crm/follow-ups" element={<CrmFollowUpsPage />} />
          <Route path="/crm/calendar" element={<CrmCalendarPage />} />
          <Route path="/crm/work-calendar" element={<OwnerCalendarPage />} />
          <Route path="/crm/quotations" element={<CrmQuotationsPage />} />
          <Route path="/crm/targets" element={<CrmTargetsPage />} />
          <Route path="/crm/forecast" element={<CrmForecastPage />} />
          <Route path="/crm/customers/:id" element={<CrmCustomer360Page />} />
          <Route path="/crm/reports" element={<CrmReportsPage />} />
          <Route path="/crm/team" element={<CrmTeamPage />} />
          <Route path="/crm/audit" element={<CrmAuditPage />} />
          <Route path="/crm/settings" element={<CrmSettingsPage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
