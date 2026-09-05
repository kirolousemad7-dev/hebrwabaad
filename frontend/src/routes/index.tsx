import { Route, Routes } from 'react-router-dom'
import { CustomerLayout } from '../layouts/CustomerLayout'
import { EmployeeLayout } from '../layouts/EmployeeLayout'
import { OwnerLayout } from '../layouts/OwnerLayout'
import { PrintingOperationsLayout } from '../layouts/PrintingOperationsLayout'
import { PublicLayout } from '../layouts/PublicLayout'
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
import { CustomerProfilePage } from '../pages/customer/CustomerProfilePage'
import { CustomerProjectDetailPage } from '../pages/customer/CustomerProjectDetailPage'
import { CustomerProjectsPage } from '../pages/customer/CustomerProjectsPage'
import { ConsultantPage } from '../pages/ConsultantPage'
import { HomePage } from '../pages/HomePage'
import { LoginPage } from '../pages/LoginPage'
import { ForgotPasswordPage } from '../pages/ForgotPasswordPage'
import { ResetPasswordPage } from '../pages/ResetPasswordPage'
import { NotFoundPage } from '../pages/NotFoundPage'
import { OwnerEmployeesPage } from '../pages/owner/OwnerEmployeesPage'
import { OwnerFilesPage } from '../pages/owner/OwnerFilesPage'
import { OwnerHomePage } from '../pages/owner/OwnerHomePage'
import { OwnerNotificationsPage } from '../pages/owner/OwnerNotificationsPage'
import { OwnerOrderDetailPage } from '../pages/owner/OwnerOrderDetailPage'
import { OwnerOrdersPage } from '../pages/owner/OwnerOrdersPage'
import { OwnerPaymentDetailPage } from '../pages/owner/OwnerPaymentDetailPage'
import { OwnerPaymentsPage } from '../pages/owner/OwnerPaymentsPage'
import { OwnerPaymentSettingsPage } from '../pages/owner/OwnerPaymentSettingsPage'
import { OwnerSupportPage } from '../pages/owner/OwnerSupportPage'
import { OwnerPackagesPage } from '../pages/owner/OwnerPackagesPage'
import { OwnerServicesPage } from '../pages/owner/OwnerServicesPage'
import { EmployeeWorkspacePage } from '../pages/employee/EmployeeWorkspacePage'
import { WorkspaceFilesPage } from '../pages/employee/WorkspaceFilesPage'
import { WorkspaceNotificationsPage } from '../pages/employee/WorkspaceNotificationsPage'
import { WorkspaceOrderDetailPage } from '../pages/employee/WorkspaceOrderDetailPage'
import { WorkspaceOrdersPage } from '../pages/employee/WorkspaceOrdersPage'
import { WorkspaceSupportPage } from '../pages/employee/WorkspaceSupportPage'
import { HrDirectoryPage } from '../pages/employee/HrDirectoryPage'
import { WorkspaceProjectDetailPage } from '../pages/employee/WorkspaceProjectDetailPage'
import { WorkspaceProjectsPage } from '../pages/employee/WorkspaceProjectsPage'
import { WorkspaceTaskDetailPage } from '../pages/employee/WorkspaceTaskDetailPage'
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
import { CATALOG_MANAGER_ROLES, PRINTING_OPERATIONS_ROLES } from '../utils/roles'
import { EMPLOYEE_WORKSPACE_ROLES } from '../utils/staff'
import { ProtectedRoute } from './ProtectedRoute'
import { PublicRoute } from './PublicRoute'
import { RoleProtectedRoute } from './RoleProtectedRoute'

export function AppRoutes() {
  return (
    <Routes>
      <Route element={<PublicLayout />}>
        <Route path="/" element={<HomePage />} />
        <Route path="/consultant" element={<ConsultantPage />} />
        <Route path="/services" element={<ServicesPage />} />
        <Route path="/packages" element={<PackagesPage />} />
        <Route path="/marketing-packages" element={<MarketingPackagesPage />} />
        <Route path="/event-packages" element={<EventPackagesPage />} />
        <Route path="/printing-packaging" element={<PrintingPackagingPage />} />
        <Route path="/suppliers" element={<SuppliersPage />} />
        <Route path="/suppliers/:slug" element={<SupplierDetailPage />} />
        <Route element={<ProtectedRoute />}>
          <Route path="/printing/customize/:slug" element={<PrintingCustomizePage />} />
        </Route>
        <Route path="/build-package" element={<BuildPackagePage />} />
        <Route element={<PublicRoute />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
        </Route>
      </Route>

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
          <Route path="/workspace/tasks" element={<WorkspaceTasksPage />} />
          <Route path="/workspace/tasks/:taskId" element={<WorkspaceTaskDetailPage />} />
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

      <Route element={<RoleProtectedRoute roles={['OWNER']} />}>
        <Route element={<OwnerLayout />}>
          <Route path="/owner" element={<OwnerHomePage />} />
          <Route path="/owner/employees" element={<OwnerEmployeesPage />} />
          <Route path="/owner/orders" element={<OwnerOrdersPage />} />
          <Route path="/owner/orders/:orderId" element={<OwnerOrderDetailPage />} />
          <Route path="/owner/payments" element={<OwnerPaymentsPage />} />
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
          <Route path="/owner/services" element={<OwnerServicesPage />} />
          <Route path="/owner/packages" element={<OwnerPackagesPage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
