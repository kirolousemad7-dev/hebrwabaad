import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import { Link, useSearchParams } from 'react-router-dom'
import { CustomerMetricCard } from '../../components/customer/CustomerMetricCard'
import { CustomerProjectCard } from '../../components/customer/CustomerProjectCard'
import { CustomerOrderCard } from '../../components/orders/CustomerOrderCard'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardOverviewSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { useAuth } from '../../context/AuthContext'
import { useAsyncData } from '../../hooks/useAsyncData'
import { getCustomerDashboard } from '../../services/customerDashboard'
import { CUSTOMER_ACTIVITY_LABELS, CUSTOMER_CONSULTANT_CTA, customerInitials } from '../../utils/customerDashboard'
import { customerConversationPath, previewMessage, SUPPORT_COPY } from '../../utils/supportChat'
import { formatDashboardDateTime } from '../../utils/ownerDashboard'
import { PRINTING_PRICING_LABELS, PRINTING_STATUS_LABELS } from '../../utils/printingRequest'

export function CustomerHomePage() {
  const { user } = useAuth()
  const [params] = useSearchParams()
  const { state, reload } = useAsyncData(getCustomerDashboard)
  const intent = params.get('intent')
  const packageSlug = intent === 'order' ? params.get('package') : null
  const customEstimate = intent === 'custom'
  const printingCustom = intent === 'printing-custom'

  return (
    <section className="space-y-8">
      <header className="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="flex min-w-0 items-center gap-4">
          <span className="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-900 text-lg font-medium text-white">
            {customerInitials(user?.name)}
          </span>
          <div className="min-w-0 space-y-1">
            <p className="text-sm font-medium text-amber-800">أهلاً بك في مساحة عملك</p>
            <h1 className="text-2xl font-semibold">{user?.name}</h1>
            <p className="text-sm leading-7 text-slate-600">
              تابع مشاريعك وطلباتك وتواصل مع فريق HEBR من مكان واحد.
            </p>
          </div>
        </div>
        <Link
          to={CUSTOMER_CONSULTANT_CTA.path}
          className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          {CUSTOMER_CONSULTANT_CTA.label}
        </Link>
      </header>

      {printingCustom ? (
        <FeedbackBanner kind="warning">
          يمكنك الآن إرسال طلب طباعة مخصص من صفحة تخصيص المنتج.{' '}
          <Link to="/printing-packaging" className="underline">
            العودة إلى الطباعة والتغليف
          </Link>
        </FeedbackBanner>
      ) : null}

      {customEstimate ? (
        <FeedbackBanner kind="warning">
          استلمنا تقدير الباقة المخصّصة. إتمام الطلب والدفع سيُفعَّل لاحقًا.{' '}
          <Link to="/build-package" className="underline">
            العودة إلى التصميم
          </Link>
        </FeedbackBanner>
      ) : null}

      {packageSlug ? (
        <FeedbackBanner kind="warning">
          استلمنا رغبتك في طلب الباقة <span dir="ltr">{packageSlug}</span>. إتمام الطلب غير مفعّل بعد.{' '}
          <Link to="/packages" className="underline">
            كل الباقات
          </Link>
        </FeedbackBanner>
      ) : null}

      {state.status === 'loading' ? <DashboardOverviewSkeleton /> : null}

      {state.status === 'error' ? (
        <DashboardErrorState message="حدث خطأ أثناء تحميل البيانات. حاول مرة أخرى." onRetry={() => void reload()} />
      ) : null}

      {state.status === 'ready' ? (
        <ReadyDashboard data={state.data} />
      ) : null}
    </section>
  )
}

function ReadyDashboard({ data }: { data: Awaited<ReturnType<typeof getCustomerDashboard>>['data'] }) {
  const activeLabel =
    data.summary.projects.secondary?.active != null
      ? `${data.summary.projects.secondary.active.toLocaleString('ar-SA')} نشط`
      : null

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <CustomerMetricCard
          title="مشاريعي"
          metric={data.summary.projects}
          emptyLabel="لا توجد مشاريع حاليًا"
          secondaryLabel={activeLabel}
        />
        <CustomerMetricCard
          title="طلباتي التجارية"
          metric={data.summary.orders}
          emptyLabel="لا توجد طلبات حتى الآن"
        />
        <CustomerMetricCard
          title="طلباتي"
          metric={data.summary.requests}
          emptyLabel="لا توجد طلبات طباعة بعد"
        />
        <CustomerMetricCard
          title="قيد التنفيذ"
          metric={data.summary.in_progress}
          emptyLabel="لا يوجد عمل جارٍ"
        />
        <CustomerMetricCard
          title="يحتاج انتباهك"
          metric={data.summary.needs_attention}
          emptyLabel="لا يوجد إجراء مطلوب"
        />
        <CustomerMetricCard
          title="المحادثات النشطة"
          metric={data.summary.messages}
          emptyLabel={SUPPORT_COPY.dashboardEmpty}
        />
        <CustomerMetricCard
          title="الملفات"
          metric={data.summary.files}
          emptyLabel="لا توجد ملفات بعد"
        />
        <CustomerMetricCard
          title="إشعارات غير مقروءة"
          metric={data.summary.notifications}
          emptyLabel="لا توجد إشعارات جديدة"
        />
      </div>

      <article className="flex min-w-0 flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-1">
          <h2 className="font-semibold">{CUSTOMER_CONSULTANT_CTA.heading}</h2>
          <p className="text-sm text-slate-700">{CUSTOMER_CONSULTANT_CTA.body}</p>
        </div>
        <Link
          to={CUSTOMER_CONSULTANT_CTA.path}
          className="inline-flex shrink-0 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white"
        >
          {CUSTOMER_CONSULTANT_CTA.label}
        </Link>
      </article>

      <DashboardSection
        title="مشاريعي"
        description="المشاريع المرتبطة بحسابك فقط."
        action={
          <Link to="/dashboard/projects" className="text-sm underline">
            كل المشاريع
          </Link>
        }
      >
        {data.projects.length === 0 ? (
          <DashboardEmptyState
            title="لا توجد مشاريع حاليًا"
            description="عندما يتم إنشاء مشروع لك سيظهر هنا."
            action={
              <Link to="/services" className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 text-sm text-white">
                استكشف خدمات HEBR
              </Link>
            }
          />
        ) : (
          <ul className="grid gap-4 lg:grid-cols-2">
            {data.projects.map((project) => (
              <li key={project.id}>
                <CustomerProjectCard project={project} />
              </li>
            ))}
          </ul>
        )}
      </DashboardSection>

      <DashboardSection
        title="طلباتي"
        description="تتبع حالة طلباتك من الاستلام حتى التسليم."
        action={
          <Link to="/dashboard/orders" className="text-sm underline">
            كل الطلبات
          </Link>
        }
      >
        {data.orders.length === 0 ? (
          <DashboardEmptyState
            title="لا توجد طلبات حتى الآن."
            description="ابدأ من الخدمات أو الباقات أو المستشار الذكي."
            action={
              <div className="flex flex-wrap justify-center gap-2">
                <Link to="/services" className="inline-flex min-h-11 items-center rounded-lg bg-slate-900 px-4 text-sm text-white">
                  استكشف الخدمات
                </Link>
                <Link to="/packages" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm">
                  استكشف الباقات
                </Link>
                <Link to="/consultant" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm">
                  ابدأ مع المستشار الذكي
                </Link>
              </div>
            }
          />
        ) : (
          <ul className="grid gap-4 lg:grid-cols-2">
            {data.orders.map((order) => (
              <li key={order.id}>
                <CustomerOrderCard order={order} />
              </li>
            ))}
          </ul>
        )}
      </DashboardSection>

      <DashboardSection
        title="طلباتي"
        action={
          <Link to="/customer/printing-requests" className="text-sm underline">
            كل الطلبات
          </Link>
        }
      >
        {data.requests.length === 0 ? (
          <DashboardEmptyState
            title="لا توجد طلبات طباعة بعد"
            description="يمكنك إرسال طلب من كتالوج الطباعة."
            action={
              <Link to="/printing-packaging" className="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 text-sm">
                طلب طباعة
              </Link>
            }
          />
        ) : (
          <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {data.requests.map((request) => (
              <li key={request.id} className="flex min-w-0 flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0">
                  <p className="font-medium">{request.product_name}</p>
                  <p className="text-sm text-slate-500">
                    {PRINTING_STATUS_LABELS[request.status] ?? request.status}
                    {request.pricing_type ? ` · ${PRINTING_PRICING_LABELS[request.pricing_type]}` : ''}
                  </p>
                </div>
                <Link to={`/customer/printing-requests/${request.id}`} className="text-sm underline">
                  التفاصيل
                </Link>
              </li>
            ))}
          </ul>
        )}
      </DashboardSection>

      <DashboardSection title="النشاط الأخير">
        {data.activity.length === 0 ? (
          <DashboardEmptyState title="لا يوجد نشاط بعد" description="سيظهر هنا ما يحدث على مشاريعك وطلباتك واستشاراتك." />
        ) : (
          <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {data.activity.map((item) => (
              <li key={item.id}>
                <Link to={item.href ?? '/dashboard'} className="block px-4 py-3 hover:bg-slate-50">
                  <div className="flex flex-wrap justify-between gap-2">
                    <p className="text-sm font-medium">{CUSTOMER_ACTIVITY_LABELS[item.type] ?? item.type}</p>
                    <p className="text-xs text-slate-500">{formatDashboardDateTime(item.occurred_at)}</p>
                  </div>
                  <p className="mt-1 text-sm text-slate-700">{item.title}</p>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </DashboardSection>

      <DashboardSection
        title="الرسائل"
        description="محادثاتك مع فريق دعم HEBR."
        action={
          <Link to="/dashboard/messages" className="text-sm underline">
            كل المحادثات
          </Link>
        }
      >
        {data.messages.length === 0 ? (
          <DashboardEmptyState title={SUPPORT_COPY.dashboardEmpty} description="ابدأ محادثة وسيصلك رد من الفريق المختص." />
        ) : (
          <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
            {data.messages.map((conversation) => (
              <li key={conversation.id}>
                <Link to={customerConversationPath(conversation.id)} className="block px-4 py-3 hover:bg-slate-50">
                  <div className="flex flex-wrap justify-between gap-2">
                    <p className="text-xs text-slate-500" dir="ltr">
                      {conversation.reference}
                    </p>
                    <p className="text-xs text-slate-500">{conversation.status_label}</p>
                  </div>
                  <p className="mt-1 font-medium">{conversation.subject}</p>
                  {conversation.last_message?.body ? (
                    <p className="mt-1 text-sm text-slate-600">{previewMessage(conversation.last_message.body)}</p>
                  ) : null}
                </Link>
              </li>
            ))}
          </ul>
        )}
        <div className="mt-3">
          <Link to="/dashboard/messages/new" className="inline-flex rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white">
            {data.messages.length === 0 ? SUPPORT_COPY.dashboardCta : SUPPORT_COPY.listEmptyCta}
          </Link>
        </div>
      </DashboardSection>

      <div className="grid gap-4 lg:grid-cols-2">
        <DashboardSection
          title="الملفات"
          action={
            <Link to="/dashboard/files" className="text-sm underline">
              كل الملفات
            </Link>
          }
        >
          {data.files.items.length === 0 ? (
            <DashboardEmptyState title="لا توجد ملفات بعد." description="ارفع ملفات المشروع أو الطلب من صفحة الملفات." />
          ) : (
            <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
              {data.files.items.map((file) => (
                <li key={file.id} className="flex min-w-0 flex-wrap items-center justify-between gap-3 px-4 py-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium">{file.original_name}</p>
                    <p className="text-sm text-slate-500">
                      {file.extension.toUpperCase()} · {file.project?.title ?? file.order?.reference ?? '—'}
                    </p>
                  </div>
                  <Link to="/dashboard/files" className="text-sm underline">
                    التفاصيل
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </DashboardSection>

        <DashboardSection
          title="الإشعارات"
          action={
            <Link to="/dashboard/notifications" className="text-sm underline">
              كل الإشعارات
            </Link>
          }
        >
          {data.notifications.items.length === 0 ? (
            <DashboardEmptyState title="لا توجد إشعارات جديدة." description="ستظهر هنا تحديثات الطلبات والرسائل." />
          ) : (
            <ul className="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
              {data.notifications.items.map((notification) => (
                <li key={notification.id}>
                  <Link
                    to={notification.href ?? '/dashboard/notifications'}
                    className={`block px-4 py-3 hover:bg-slate-50 ${notification.read_at ? '' : 'bg-amber-50'}`}
                  >
                    <p className="font-medium">{notification.title}</p>
                    <p className="mt-1 text-sm text-slate-600">{notification.message}</p>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </DashboardSection>
      </div>

      <DashboardSection title="الحساب">
        <article className="rounded-2xl border border-slate-200 bg-white p-5">
          <p className="font-medium">{data.customer.name}</p>
          <p className="mt-1 text-sm text-slate-600" dir="ltr">
            {data.customer.email}
          </p>
          <Link to="/dashboard/profile" className="mt-3 inline-block text-sm underline">
            عرض الحساب
          </Link>
        </article>
      </DashboardSection>
    </>
  )
}
