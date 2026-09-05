export type ApiSuccess<T> = {
  success: true
  data: T
}

export type ApiError = {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export type HealthData = {
  status: string
  service: string
}

export type AuthUser = {
  id: number
  name: string
  email: string
  role: string
  is_active?: boolean
  workspace?: string | null
}

export type AuthPayload = {
  user: AuthUser
  token: string
}

export const SERVICE_CATEGORIES = [
  'STRATEGY',
  'CONTENT',
  'PRODUCTION',
  'STORES',
  'CAMPAIGNS',
  'PRINTING',
  'OTHER',
] as const

export const PACKAGE_CATEGORIES = ['GENERAL', 'MARKETING', 'EVENTS'] as const

export const PRICING_MODES = ['FIXED', 'STARTING_FROM', 'QUOTE'] as const

export type ServiceCategory = (typeof SERVICE_CATEGORIES)[number]
export type PackageCategory = (typeof PACKAGE_CATEGORIES)[number]
export type PricingMode = (typeof PRICING_MODES)[number]

export type Service = {
  id: number
  name: string
  slug: string
  summary: string | null
  description: string | null
  category: ServiceCategory
  base_price: string
  currency: string
  pricing_mode: PricingMode
  pricing_label: string
  is_chargeable: boolean
  duration_days: number | null
  is_featured: boolean
  /** Only present for OWNER / ADMIN_MANAGER responses. */
  is_active?: boolean
  packages_count?: number
}

export type PackageItem = {
  id: number
  service_id: number
  quantity: number
  sort_order: number
  notes: string | null
  service?: Service
}

export type PackageTier = {
  id: number
  name: string
  slug: string
  description: string | null
  price: string | null
  currency: string
  duration_days: number | null
  revision_rounds: number | null
  deliverables: string[]
  sort_order: number
  is_priced: boolean
  /** Only present for OWNER / ADMIN_MANAGER responses. */
  is_active?: boolean
}

export type Package = {
  id: number
  name: string
  slug: string
  description: string | null
  audience: string | null
  deliverables: string[]
  category: PackageCategory
  price: string
  discount_amount: string
  final_price: string
  currency: string
  pricing_mode: PricingMode
  pricing_label: string
  is_chargeable: boolean
  duration_days: number | null
  revision_rounds: number | null
  is_featured: boolean
  sort_order: number
  items: PackageItem[]
  tiers: PackageTier[]
  /** Only present for OWNER / ADMIN_MANAGER responses. */
  is_active?: boolean
}

export type PrintingRequestStatus = 'PENDING'
export type PrintingPricingType = 'ESTIMATED' | 'QUOTE_REQUIRED' | 'QUOTE_READY'

export type PrintingRequestCustomer = {
  id: number
  name: string
  email: string
}

export type PrintingRequest = {
  id: number
  product_slug: string
  product_name: string
  width: string
  height: string
  dimension_unit: string
  shape: string
  material: string
  quantity: number
  printing_method: string
  finishing: string[]
  required_date: string
  notes: string | null
  status: PrintingRequestStatus
  filename: string
  pricing_type: PrintingPricingType | null
  estimated_price: string | null
  quoted_price: string | null
  pricing_notes: string | null
  quoted_at: string | null
  created_at: string
  customer?: PrintingRequestCustomer
  quoted_by?: { id: number; name: string } | null
}

export type SupplierPortfolioItem = {
  id: number
  title: string
  description: string
  image: string
  category: string
}

export type Supplier = {
  id: number
  name: string
  slug: string
  logo: string
  short_description: string
  description: string | null
  specialties: string[]
  services: string[]
  location: string
  featured: boolean
  portfolio_count: number
  portfolio_preview: SupplierPortfolioItem[]
  portfolio?: SupplierPortfolioItem[]
}

export type OwnerDashboardMetric = {
  available: boolean
  value: number | null
  reason?: string
  secondary: Record<string, number | string | Record<string, number>>
}

export type CustomerDashboardMetric = {
  available: boolean
  value?: number
  secondary?: Record<string, number>
}

export type UnavailableDomain = {
  available: false
  status: 'unavailable'
  reason: string
  message: string
}

export type CustomerProject = {
  id: number
  title: string
  description: string | null
  status: string
  started_at: string | null
  deadline: string | null
  account_manager: { id: number; name: string } | null
  progress: WorkspaceProjectProgress
  created_at: string | null
  updated_at: string | null
}

export type CustomerActivityItem = {
  id: string
  type: string
  title: string
  status: string
  occurred_at: string | null
  href: string | null
}

export const ORDER_STATUSES = [
  'RECEIVED',
  'CONFIRMED',
  'IN_PROGRESS',
  'REVIEW',
  'REVISION',
  'COMPLETED',
  'DELIVERED',
] as const

export type OrderStatus = (typeof ORDER_STATUSES)[number]

export type OrderTimelineStep = {
  status: OrderStatus | string
  label: string
  state: 'completed' | 'current' | 'pending'
  occurred_at: string | null
}

export type OrderRelated = {
  id: number
  title?: string
  name?: string
}

export type CustomerOrder = {
  id: number
  reference: string
  title: string
  description: string | null
  status: OrderStatus | string
  status_label: string
  progress: number
  created_at: string | null
  updated_at: string | null
  confirmed_at: string | null
  completed_at: string | null
  delivered_at: string | null
  project: { id: number; title: string } | null
  service: { id: number; name: string } | null
  package: { id: number; name: string; slug?: string } | null
  package_tier?: { id: number; name: string; slug: string } | null
  account_manager: { id: number; name: string } | null
  payable?: OrderPayable
  latest_payment?: CustomerPayment | null
  timeline: OrderTimelineStep[]
}

export type ManagedOrder = CustomerOrder & {
  allowed_transitions: { status: string; label: string }[]
  customer: { id: number; name: string; email: string } | null
  history?: {
    from_status: string | null
    to_status: string
    to_status_label: string
    changed_by: { id: number; name: string } | null
    created_at: string | null
  }[]
}

export type ManagedOrderListData = {
  items: ManagedOrder[]
  meta: EmployeeListMeta
}

export const CONVERSATION_STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'] as const

export type ConversationStatus = (typeof CONVERSATION_STATUSES)[number]

export type SupportMessage = {
  id: number
  body: string
  from_support: boolean
  sender: { id: number; name: string } | null
  created_at: string | null
}

export type SupportConversationPreview = {
  id: number
  body: string
  created_at: string | null
  from_support: boolean
}

export type SupportConversation = {
  id: number
  reference: string
  subject: string
  status: ConversationStatus
  status_label: string
  can_reply: boolean
  created_at: string | null
  updated_at: string | null
  last_message_at: string | null
  last_message: SupportConversationPreview | null
  order: { id: number; reference: string; title: string } | null
  project: { id: number; title: string } | null
  assignee: { id: number; name: string } | null
  messages?: SupportMessage[]
  messages_meta?: EmployeeListMeta
}

export type ManagedSupportConversation = SupportConversation & {
  allowed_transitions: { status: string; label: string }[]
  customer: { id: number; name: string; email: string } | null
}

export type ManagedSupportListData = {
  items: ManagedSupportConversation[]
  meta: EmployeeListMeta
}

export type OrderLookups = {
  customers: { id: number; name: string; email: string }[]
  projects: { id: number; title: string; customer_id: number }[]
  account_managers: { id: number; name: string }[]
}

export type CustomerDashboardData = {
  customer: {
    id: number
    name: string
    email: string
    created_at: string | null
  }
  summary: {
    projects: CustomerDashboardMetric
    requests: CustomerDashboardMetric
    in_progress: CustomerDashboardMetric
    needs_attention: CustomerDashboardMetric
    orders: CustomerDashboardMetric
    messages: CustomerDashboardMetric
    files: CustomerDashboardMetric
    notifications: CustomerDashboardMetric
  }
  projects: CustomerProject[]
  requests: PrintingRequest[]
  activity: CustomerActivityItem[]
  orders: CustomerOrder[]
  messages: SupportConversation[]
  files: { available: true; items: ManagedFileItem[] }
  notifications: { available: true; unread_count: number; items: PlatformNotification[] }
}

export type PlatformNotification = {
  id: string
  type: string
  title: string
  message: string
  href: string | null
  read_at: string | null
  created_at: string | null
  data: {
    order_id?: number
    order_reference?: string
    conversation_id?: number
    conversation_reference?: string
    task_id?: number
    project_id?: number
  }
}

export type NotificationListData = {
  items: PlatformNotification[]
  unread_count: number
  meta: EmployeeListMeta
}

export type ManagedFileItem = {
  id: number
  original_name: string
  mime_type: string
  extension: string
  size: number
  can_preview: boolean
  created_at: string | null
  project?: { id: number; title: string } | null
  order?: { id: number; reference: string } | null
  task?: { id: number; title: string } | null
  uploaded_by?: { id: number; name: string } | null
}

export type ManagedFileListData = {
  items: ManagedFileItem[]
  meta: EmployeeListMeta
}

export type OwnerDashboardActivityEntity = {
  type: string
  id: number
  label: string
  href: string | null
}

export type OwnerDashboardActivityItem = {
  id: string
  type: string
  title: string
  actor: { id: number; name: string } | null
  entity: OwnerDashboardActivityEntity
  status: string
  occurred_at: string | null
}

export type OwnerDashboardPendingRequest = {
  id: number
  product_name: string
  status: string
  pricing_type: PrintingPricingType | null
  created_at: string | null
  href: string
  customer: PrintingRequestCustomer | null
}

export type OwnerDashboardData = {
  overview: {
    revenue: OwnerDashboardMetric
    orders: OwnerDashboardMetric
    customers: OwnerDashboardMetric
    projects: OwnerDashboardMetric
    employees: OwnerDashboardMetric
    suppliers: OwnerDashboardMetric
    leads: OwnerDashboardMetric
    pending_requests: OwnerDashboardMetric
  }
  request_activity: Array<{ date: string; count: number }>
  pricing_breakdown: {
    unpriced: number
    estimated: number
    quote_required: number
    quote_ready: number
  }
  recent_activity: OwnerDashboardActivityItem[]
  pending_requests: OwnerDashboardPendingRequest[]
}

export type Employee = {
  id: number
  name: string
  email: string
  role: string
  workspace: string | null
  is_active: boolean
  created_at: string | null
  last_seen_at: string | null
}

export type EmployeeListMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type EmployeeListData = {
  items: Employee[]
  meta: EmployeeListMeta
  summary?: {
    total: number
    active: number
    inactive: number
  }
}

export type WorkspaceDomainStatus = {
  available: boolean
  status: 'ready' | 'unavailable' | string
}

export const TASK_PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'] as const
export const TASK_STATUSES = ['TODO', 'IN_PROGRESS', 'REVIEW', 'REVISION', 'COMPLETED'] as const
export const PROJECT_STATUSES = ['PLANNING', 'IN_PROGRESS', 'REVIEW', 'COMPLETED', 'CANCELLED'] as const

export type TaskPriority = (typeof TASK_PRIORITIES)[number]
export type TaskStatus = (typeof TASK_STATUSES)[number]

export type WorkspaceTaskPerson = {
  id: number
  name: string
  role?: string | null
}

export type WorkspaceProjectSummary = {
  id: number
  title: string
  status?: string | null
}

export type WorkspaceTask = {
  id: number
  project_id?: number | null
  title: string
  description: string | null
  priority: TaskPriority | string
  status: TaskStatus | string
  deadline: string | null
  is_overdue: boolean
  assigned_to: number
  created_by: number
  project?: WorkspaceProjectSummary | null
  assignee?: WorkspaceTaskPerson | null
  creator?: WorkspaceTaskPerson | null
  created_at: string | null
  updated_at: string | null
}

export type ProjectStatus = (typeof PROJECT_STATUSES)[number]

export type WorkspaceProjectProgress = {
  total: number
  todo: number
  in_progress: number
  review: number
  revision: number
  completed: number
  overdue: number
  percent: number
}

export type WorkspaceProject = {
  id: number
  title: string
  description: string | null
  status: ProjectStatus | string
  started_at: string | null
  deadline: string | null
  customer_id: number
  account_manager_id: number
  customer?: { id: number; name: string; email?: string | null } | null
  account_manager?: { id: number; name: string } | null
  progress: WorkspaceProjectProgress
  created_at: string | null
  updated_at: string | null
}

export type WorkspaceProjectListData = {
  items: WorkspaceProject[]
  meta: EmployeeListMeta
}

export type WorkspaceTaskListData = {
  items: WorkspaceTask[]
  meta: EmployeeListMeta
  summary?: {
    total: number
    in_progress: number
    completed: number
    overdue: number
  }
}

export type EmployeeWorkspaceData = {
  id: number
  name: string
  email: string
  role: string
  workspace: string | null
  is_active: boolean
  capabilities: string[]
  widgets: string[]
  domains?: Record<string, WorkspaceDomainStatus>
}

export type ConsultantOption = {
  id: string
  label: string
  description?: string
  icon?: string
}

export type ConsultantPrompt = {
  id: string
  title: string
  body?: string
  type: 'cards' | 'search_cards' | 'chips' | 'multi_chips' | 'text'
  searchable?: boolean
  skippable?: boolean
  placeholder?: string
  options?: ConsultantOption[]
}

export type ConsultantMessage = {
  role: 'ai' | 'user' | string
  text: string
  at?: string
}

export type ConsultantCta = {
  type: string
  label: string
  path: string
}

export type ConsultantPackageMatch = {
  kind: 'package'
  id: number
  slug: string
  name: string
  description: string | null
  category: PackageCategory | string
  price: number
  discount_amount: number
  final_price: number
  currency: string
  duration_days: number | null
  items: Array<{
    service_id: number
    quantity: number
    notes: string | null
    service?: { id: number; slug: string; name: string; category: string } | null
  }>
  reasons: string[]
  cta: ConsultantCta
}

export type ConsultantServiceMatch = {
  kind: 'service'
  id: number
  slug: string
  name: string
  summary: string | null
  category: string
  base_price: number
  currency: string
  duration_days: number | null
  cta: ConsultantCta
}

export type ConsultantRecommendations = {
  intent?: { primary: string; flags: string[] }
  best_match: ConsultantPackageMatch | null
  alternative: ConsultantPackageMatch | null
  services: ConsultantServiceMatch[]
  printing?: {
    kind: 'printing'
    product_slug: string | null
    requires_quote: boolean
    starting_price: number | null
    currency: string
    cta: ConsultantCta
  } | null
  cta: ConsultantCta
  fallback?: { title: string; message: string } | null
}

export type ConsultantSession = {
  token: string
  status: 'IN_PROGRESS' | 'COMPLETED' | 'ABANDONED' | string
  step: string
  progress: { current: number; total: number; percent: number }
  state: {
    help_mode: string | null
    business_category: string | null
    business_subtype: string | null
    business_name: string | null
    location: string | null
    branches: string | number | null
    goals: string[]
    needed_services: string[]
    unsure_needs: boolean
    budget: string | null
    timeline: string | null
    event_date: string | null
    has_website: boolean | string | null
    social_platforms: string[]
  }
  messages: ConsultantMessage[]
  prompt: ConsultantPrompt | null
  diagnosis: {
    summary: string
    challenges: string[]
    priorities: Array<{ label: string; level: string }>
  } | null
  readiness: { score: number; dimensions: Record<string, number> } | null
  recommendations: ConsultantRecommendations | null
  lead_captured: boolean
  enabled: boolean
}

export type ConsultantConfig = {
  enabled: boolean
  categories: Array<{
    id: string
    label: string
    icon: string
    subtypes: ConsultantOption[]
  }>
  goals: ConsultantOption[]
  service_needs: ConsultantOption[]
  budget_bands: ConsultantOption[]
  timelines: ConsultantOption[]
  printing_products: ConsultantOption[]
}

export const PAYMENT_METHODS = ['CARD', 'INSTAPAY', 'BANK_TRANSFER'] as const
export type PaymentMethod = (typeof PAYMENT_METHODS)[number]

export const PAYMENT_STATUSES = [
  'PENDING',
  'PROCESSING',
  'PAID',
  'FAILED',
  'CANCELLED',
  'PENDING_VERIFICATION',
  'REJECTED',
] as const
export type PaymentStatus = (typeof PAYMENT_STATUSES)[number]

export type OrderPayable = {
  available: boolean
  amount: string | null
  currency: string | null
  reason?: string | null
}

export type PaymentOrderSummary = {
  id: number
  reference: string
  title: string
  project?: { id: number; title: string } | null
}

export type CustomerPayment = {
  id: number
  amount: string
  currency: string
  payment_method: PaymentMethod | string
  payment_method_label: string
  status: PaymentStatus | string
  status_label: string
  provider: string
  provider_transaction_id: string | null
  reference_number: string | null
  payer_name: string | null
  failure_reason: string | null
  paid_at: string | null
  verified_at: string | null
  created_at: string | null
  updated_at: string | null
  checkout_url?: string | null
  order?: PaymentOrderSummary | null
  customer?: { id: number; name: string; email?: string } | null
}

export type OwnerPayment = CustomerPayment & {
  notes?: string | null
  verified_by?: { id: number; name: string } | null
  customer?: { id: number; name: string; email?: string } | null
}

export type PaymentListMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type PaymentRevenueSummary = {
  available: boolean
  value: number | null
  reason: string | null
  currency: string | null
  paid_count: number
  pending_count: number
  pending_verification_count: number
  failed_count: number
  rejected_count: number
}

export type OwnerPaymentListData = {
  items: OwnerPayment[]
  summary: PaymentRevenueSummary
  meta: PaymentListMeta
}

export type CustomerInstapaySettings = {
  enabled: boolean
  ready: boolean
  account_name: string | null
  bank_name: string | null
  account_number: string | null
  handle: string | null
  phone: string | null
  instructions: string | null
  notes: string | null
}

export type CustomerBankTransferSettings = {
  enabled: boolean
  ready: boolean
  bank_name: string | null
  account_name: string | null
  account_number: string | null
  iban: string | null
  swift: string | null
  branch: string | null
  instructions: string | null
  notes: string | null
}

export type CustomerPaymentSettings = {
  card: { enabled: boolean; configured: boolean }
  instapay: CustomerInstapaySettings
  bank_transfer: CustomerBankTransferSettings
}

/**
 * Owner-managed manual payment accounts. The card gateway is reported as a
 * status only: PayTabs credentials never leave the server environment.
 */
export type OwnerPaymentSettings = {
  card_enabled: boolean
  card_configured: boolean
  card_provider: string
  card_environment: string
  instapay_enabled: boolean
  instapay_ready: boolean
  instapay_account_name: string | null
  instapay_bank_name: string | null
  instapay_account_number: string | null
  instapay_handle: string | null
  instapay_phone: string | null
  instapay_instructions: string | null
  instapay_notes: string | null
  bank_transfer_enabled: boolean
  bank_transfer_ready: boolean
  bank_name: string | null
  bank_account_name: string | null
  bank_account_number: string | null
  bank_iban: string | null
  bank_swift: string | null
  bank_branch: string | null
  bank_instructions: string | null
  bank_notes: string | null
}
