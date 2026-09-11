import { Link } from 'react-router-dom'
import { useCallback, useEffect, useState } from 'react'
import {
  DashboardEmptyState,
  DashboardErrorState,
  DashboardPanelSkeleton,
  DashboardSection,
} from '../../components/owner/DashboardSection'
import { FeedbackBanner } from '../../components/ui/FeedbackBanner'
import {
  addBusinessCalendarHoliday,
  copyBusinessCalendarHolidaysYear,
  deleteBusinessCalendarHoliday,
  ensureDefaultBusinessCalendar,
  exportOperationsCsv,
  getBusinessCalendar,
  getBusinessCalendars,
  getOperationsSettings,
  importBusinessCalendarHolidays,
  updateBusinessCalendar,
  updateOperationsSettings,
  type BusinessCalendar,
  type BusinessCalendarHoliday,
  type OperationsSettings,
} from '../../services/operations'
import { describeApiError } from '../../utils/errors'

const fieldClass =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900'

const WEEKDAY_LABELS = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت']

export function OwnerOperationsSettingsPage() {
  const [settings, setSettings] = useState<OperationsSettings | null>(null)
  const [calendars, setCalendars] = useState<BusinessCalendar[]>([])
  const [selected, setSelected] = useState<BusinessCalendar | null>(null)
  const [holidays, setHolidays] = useState<BusinessCalendarHoliday[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [approachingDays, setApproachingDays] = useState('2')
  const [savingPrint, setSavingPrint] = useState(false)
  const [holidayDate, setHolidayDate] = useState('')
  const [holidayName, setHolidayName] = useState('')
  const [savingHoliday, setSavingHoliday] = useState(false)
  const [holidayCsv, setHolidayCsv] = useState('')
  const [importBusy, setImportBusy] = useState(false)
  const [copyFromYear, setCopyFromYear] = useState(String(new Date().getFullYear() - 1))
  const [copyToYear, setCopyToYear] = useState(String(new Date().getFullYear()))
  const [copyBusy, setCopyBusy] = useState(false)
  const [exporting, setExporting] = useState<string | null>(null)

  const loadCalendarDetail = useCallback(async (id: number) => {
    const response = await getBusinessCalendar(id)
    setSelected(response.data)
    setHolidays(response.data.holidays ?? [])
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [settingsRes, calendarsRes] = await Promise.all([
        getOperationsSettings().catch(() => null),
        getBusinessCalendars().catch(() => ({ data: { items: [] as BusinessCalendar[] } })),
      ])

      if (settingsRes) {
        setSettings(settingsRes.data)
        setApproachingDays(String(settingsRes.data.printing_approaching_days ?? 2))
      } else {
        setSettings(null)
      }

      const list = calendarsRes.data.items ?? []
      setCalendars(list)

      const preferred =
        settingsRes?.data.business_calendar ??
        list.find((row) => row.is_default) ??
        list[0] ??
        null

      if (preferred) {
        await loadCalendarDetail(preferred.id)
      } else {
        setSelected(null)
        setHolidays([])
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحميل إعدادات التشغيل.'))
    } finally {
      setLoading(false)
    }
  }, [loadCalendarDetail])

  useEffect(() => {
    void load()
  }, [load])

  async function handleEnsureDefault() {
    setError(null)
    try {
      const response = await ensureDefaultBusinessCalendar()
      setNotice('تم تجهيز التقويم الافتراضي.')
      setSelected(response.data)
      setHolidays(response.data.holidays ?? [])
      await load()
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تجهيز التقويم الافتراضي.'))
    }
  }

  async function handleSavePrinting() {
    const days = Number(approachingDays)
    if (!Number.isFinite(days) || days < 0) {
      setError('عدد أيام الاقتراب غير صالح.')
      return
    }
    setSavingPrint(true)
    setError(null)
    try {
      const response = await updateOperationsSettings({
        printing_approaching_days: days,
        default_business_calendar_id: selected?.id ?? undefined,
      })
      setSettings(response.data)
      setNotice('تم حفظ إعدادات الطباعة.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ الإعدادات.'))
    } finally {
      setSavingPrint(false)
    }
  }

  async function handleToggleWorkingDay(day: number) {
    if (!selected) return
    const current = selected.working_days ?? []
    const next = current.includes(day) ? current.filter((value) => value !== day) : [...current, day].sort()
    if (next.length === 0) {
      setError('يجب اختيار يوم عمل واحد على الأقل.')
      return
    }
    try {
      const response = await updateBusinessCalendar(selected.id, { working_days: next })
      setSelected(response.data)
      setHolidays(response.data.holidays ?? holidays)
      setNotice('تم تحديث أيام العمل.')
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر تحديث أيام العمل.'))
    }
  }

  async function handleAddHoliday() {
    if (!selected || !holidayDate || !holidayName.trim()) {
      setError('تاريخ واسم العطلة مطلوبان.')
      return
    }
    setSavingHoliday(true)
    setError(null)
    try {
      await addBusinessCalendarHoliday(selected.id, {
        date: holidayDate,
        name: holidayName.trim(),
      })
      setHolidayDate('')
      setHolidayName('')
      setNotice('تمت إضافة العطلة.')
      await loadCalendarDetail(selected.id)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إضافة العطلة.'))
    } finally {
      setSavingHoliday(false)
    }
  }

  async function handleDeleteHoliday(holiday: BusinessCalendarHoliday) {
    if (!selected) return
    try {
      await deleteBusinessCalendarHoliday(selected.id, holiday.id)
      setNotice('تم حذف العطلة.')
      await loadCalendarDetail(selected.id)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حذف العطلة.'))
    }
  }

  async function handleImportHolidays(preview: boolean) {
    if (!selected || !holidayCsv.trim()) {
      setError('الصق محتوى CSV (عمودا date و name) أولاً.')
      return
    }
    setImportBusy(true)
    setError(null)
    try {
      const response = await importBusinessCalendarHolidays(selected.id, {
        csv: holidayCsv,
        preview,
      })
      const imported = response.data.imported ?? 0
      const validCount = response.data.valid?.length ?? 0
      const invalidCount = response.data.invalid?.length ?? 0
      if (preview) {
        setNotice(`معاينة: ${validCount.toLocaleString('ar-SA')} صالح · ${invalidCount.toLocaleString('ar-SA')} مرفوض.`)
      } else {
        setNotice(`تم استيراد ${imported.toLocaleString('ar-SA')} عطلة.`)
        setHolidayCsv('')
        await loadCalendarDetail(selected.id)
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر استيراد العطل.'))
    } finally {
      setImportBusy(false)
    }
  }

  async function handleCopyYear() {
    if (!selected) return
    const fromYear = Number(copyFromYear)
    const toYear = Number(copyToYear)
    if (!Number.isInteger(fromYear) || !Number.isInteger(toYear) || fromYear === toYear) {
      setError('سنوات النسخ غير صالحة.')
      return
    }
    setCopyBusy(true)
    setError(null)
    try {
      const response = await copyBusinessCalendarHolidaysYear(selected.id, {
        from_year: fromYear,
        to_year: toYear,
      })
      setNotice(`تم نسخ ${(response.data.copied ?? 0).toLocaleString('ar-SA')} عطلة إلى ${toYear}.`)
      await loadCalendarDetail(selected.id)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر نسخ سنة العطل.'))
    } finally {
      setCopyBusy(false)
    }
  }

  async function handleExport(entity: 'work' | 'printing' | 'sla') {
    setExporting(entity)
    try {
      await exportOperationsCsv(entity)
      setNotice(`تم تنزيل ${entity}.csv`)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر التصدير.'))
    } finally {
      setExporting(null)
    }
  }

  return (
    <section className="space-y-6">
      <header>
        <h1 className="text-2xl font-semibold text-slate-900">إعدادات التشغيل</h1>
        <p className="mt-1 text-sm text-slate-600">ساعات العمل، تنبيهات الطباعة، وروابط التكاملات.</p>
      </header>

      {notice ? <FeedbackBanner kind="success">{notice}</FeedbackBanner> : null}
      {error ? <FeedbackBanner kind="error">{error}</FeedbackBanner> : null}

      {loading ? <DashboardPanelSkeleton label="جاري تحميل الإعدادات..." /> : null}
      {!loading && error && !settings && calendars.length === 0 ? (
        <DashboardErrorState message={error} onRetry={() => void load()} />
      ) : null}

      {!loading ? (
        <>
          <DashboardSection
            title="ساعات العمل"
            description="تقويم الأعمال والعطل الرسمية."
            action={
              <button type="button" onClick={() => void handleEnsureDefault()} className="text-sm underline">
                تجهيز الافتراضي
              </button>
            }
          >
            {calendars.length === 0 && !selected ? (
              <DashboardEmptyState
                title="لا تقويم أعمال بعد."
                description="اضغط تجهيز الافتراضي لإنشاء تقويم."
              />
            ) : (
              <div className="space-y-4">
                {calendars.length > 1 ? (
                  <label className="block text-xs text-slate-500">
                    التقويم
                    <select
                      value={selected?.id ?? ''}
                      onChange={(event) => {
                        const id = Number(event.target.value)
                        if (id) void loadCalendarDetail(id)
                      }}
                      className={`mt-1 max-w-sm ${fieldClass}`}
                    >
                      {calendars.map((row) => (
                        <option key={row.id} value={row.id}>
                          {row.name}
                          {row.is_default ? ' (افتراضي)' : ''}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : null}

                {selected ? (
                  <>
                    <div className="grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
                      <p>
                        المنطقة الزمنية: <span className="font-medium">{selected.timezone}</span>
                      </p>
                      <p>
                        ساعات العمل:{' '}
                        <span className="font-medium">
                          {selected.work_start} — {selected.work_end}
                        </span>
                      </p>
                    </div>
                    <div>
                      <p className="mb-2 text-xs text-slate-500">أيام العمل</p>
                      <div className="flex flex-wrap gap-2">
                        {WEEKDAY_LABELS.map((label, day) => {
                          const active = (selected.working_days ?? []).includes(day)
                          return (
                            <button
                              key={label}
                              type="button"
                              onClick={() => void handleToggleWorkingDay(day)}
                              className={`rounded-full px-3 py-1.5 text-sm ${
                                active ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white'
                              }`}
                            >
                              {label}
                            </button>
                          )
                        })}
                      </div>
                    </div>

                    <div className="border-t border-slate-100 pt-4">
                      <h3 className="text-sm font-semibold text-slate-800">العطل</h3>
                      <div className="mt-2 flex flex-wrap gap-2">
                        <input
                          type="date"
                          value={holidayDate}
                          onChange={(event) => setHolidayDate(event.target.value)}
                          className={fieldClass + ' max-w-[180px]'}
                        />
                        <input
                          value={holidayName}
                          onChange={(event) => setHolidayName(event.target.value)}
                          placeholder="اسم العطلة"
                          className={fieldClass + ' max-w-xs'}
                        />
                        <button
                          type="button"
                          disabled={savingHoliday}
                          onClick={() => void handleAddHoliday()}
                          className="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
                        >
                          إضافة
                        </button>
                      </div>
                      {holidays.length === 0 ? (
                        <p className="mt-2 text-xs text-slate-500">لا عطل مسجّلة.</p>
                      ) : (
                        <ul className="mt-3 space-y-1">
                          {holidays.map((holiday) => (
                            <li
                              key={holiday.id}
                              className="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm"
                            >
                              <span>
                                {holiday.date} · {holiday.name}
                              </span>
                              <button
                                type="button"
                                className="text-xs text-red-700 underline"
                                onClick={() => void handleDeleteHoliday(holiday)}
                              >
                                حذف
                              </button>
                            </li>
                          ))}
                        </ul>
                      )}

                      <div className="mt-4 space-y-2 border-t border-slate-100 pt-4">
                        <h4 className="text-xs font-semibold text-slate-700">استيراد CSV</h4>
                        <textarea
                          value={holidayCsv}
                          onChange={(event) => setHolidayCsv(event.target.value)}
                          placeholder={'date,name\n2026-01-01,رأس السنة'}
                          className={`min-h-24 font-mono text-xs ${fieldClass}`}
                          dir="ltr"
                        />
                        <div className="flex flex-wrap gap-2">
                          <button
                            type="button"
                            disabled={importBusy}
                            onClick={() => void handleImportHolidays(true)}
                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50"
                          >
                            معاينة
                          </button>
                          <button
                            type="button"
                            disabled={importBusy}
                            onClick={() => void handleImportHolidays(false)}
                            className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white disabled:opacity-50"
                          >
                            استيراد
                          </button>
                        </div>
                      </div>

                      <div className="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4">
                        <label className="text-xs text-slate-500">
                          من سنة
                          <input
                            type="number"
                            value={copyFromYear}
                            onChange={(event) => setCopyFromYear(event.target.value)}
                            className={`mt-1 w-24 ${fieldClass}`}
                          />
                        </label>
                        <label className="text-xs text-slate-500">
                          إلى سنة
                          <input
                            type="number"
                            value={copyToYear}
                            onChange={(event) => setCopyToYear(event.target.value)}
                            className={`mt-1 w-24 ${fieldClass}`}
                          />
                        </label>
                        <button
                          type="button"
                          disabled={copyBusy}
                          onClick={() => void handleCopyYear()}
                          className="rounded-lg border border-slate-300 px-3 py-2 text-xs disabled:opacity-50"
                        >
                          نسخ سنة العطل
                        </button>
                      </div>
                    </div>
                  </>
                ) : null}
              </div>
            )}
          </DashboardSection>

          <DashboardSection
            title="PayTabs"
            description="حالة الإعداد بدون أسرار. راجع دليل التشغيل قبل التحويل للإنتاج."
          >
            {settings?.paytabs_config ? (
              <div className="space-y-3 text-sm">
                <dl className="grid gap-2 sm:grid-cols-2">
                  <div>
                    <dt className="text-xs text-slate-500">Configured</dt>
                    <dd className="font-medium">
                      {settings.paytabs_config.configured ? 'نعم' : 'لا'}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">Environment</dt>
                    <dd className="font-medium" dir="ltr">
                      {settings.paytabs_config.environment ?? '—'}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">Host</dt>
                    <dd className="font-medium" dir="ltr">
                      {settings.paytabs_config.base_host ?? '—'}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">Profile hint</dt>
                    <dd className="font-medium" dir="ltr">
                      {settings.paytabs_config.profile_id_hint
                        ? `…${settings.paytabs_config.profile_id_hint}`
                        : '—'}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">Server key</dt>
                    <dd className="font-medium">
                      {settings.paytabs_config.has_server_key ? 'مضبوط' : 'غير مضبوط'}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-slate-500">Callback / Return</dt>
                    <dd className="font-medium">
                      {settings.paytabs_config.callback_url_ok ? 'OK' : 'تحقق'} /{' '}
                      {settings.paytabs_config.return_url_ok ? 'OK' : 'تحقق'}
                    </dd>
                  </div>
                </dl>
                {(settings.paytabs_config.issues?.length ?? 0) > 0 ? (
                  <ul className="list-disc space-y-1 pe-5 text-xs text-amber-900">
                    {settings.paytabs_config.issues?.map((issue) => (
                      <li key={issue}>{issue}</li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-xs text-emerald-800">لا مشاكل ظاهرة في الإعداد.</p>
                )}
                {settings.paytabs_callback_metrics ? (
                  <p className="text-xs text-slate-600" dir="ltr">
                    callbacks: received {settings.paytabs_callback_metrics.callback_received ?? 0} ·
                    verified {settings.paytabs_callback_metrics.verified ?? 0} · rejected{' '}
                    {settings.paytabs_callback_metrics.rejected ?? 0} · mismatch{' '}
                    {settings.paytabs_callback_metrics.mismatch ?? 0} · duplicate{' '}
                    {settings.paytabs_callback_metrics.duplicate ?? 0}
                  </p>
                ) : null}
                <p className="text-xs text-slate-500">
                  الدليل:{' '}
                  <span dir="ltr" className="font-mono">
                    {settings.paytabs_docs_path ?? 'backend/docs/paytabs-go-live.md'}
                  </span>
                </p>
              </div>
            ) : (
              <p className="text-sm text-slate-500">حالة PayTabs غير متاحة من الإعدادات حالياً.</p>
            )}
          </DashboardSection>

          <DashboardSection title="الطباعة" description="تنبيه اقتراب موعد التسليم المطلوب.">
            <label className="block max-w-xs text-xs text-slate-500">
              أيام الاقتراب
              <input
                type="number"
                min={0}
                value={approachingDays}
                onChange={(event) => setApproachingDays(event.target.value)}
                className={`mt-1 ${fieldClass}`}
              />
            </label>
            {settings ? (
              <p className="mt-2 text-xs text-slate-500">
                Webhooks: {String(settings.webhook_count ?? '—')} · فشل أتمتة 24س:{' '}
                {String(settings.automation_failure_24h ?? '—')}
              </p>
            ) : (
              <p className="mt-2 text-xs text-amber-800">
                واجهة الإعدادات غير متاحة بعد — يمكن ضبط أيام الاقتراب عند توفر API.
              </p>
            )}
            <button
              type="button"
              disabled={savingPrint}
              onClick={() => void handleSavePrinting()}
              className="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50"
            >
              حفظ
            </button>
          </DashboardSection>

          <DashboardSection title="روابط سريعة">
            <ul className="flex flex-wrap gap-3 text-sm">
              <li>
                <Link to="/owner/automations" className="underline">
                  الأتمتة
                </Link>
              </li>
              <li>
                <Link to="/owner/notification-preferences" className="underline">
                  تفضيلات الإشعارات
                </Link>
              </li>
              <li>
                <Link to="/owner/integrations/webhooks" className="underline">
                  Webhooks
                </Link>
              </li>
              <li>
                <Link to="/owner/integrations/inbound-webhooks" className="underline">
                  Inbound Webhooks
                </Link>
              </li>
              <li>
                <Link to="/owner/printing-quotations" className="underline">
                  عروض أسعار الطباعة
                </Link>
              </li>
              <li>
                <Link to="/owner/operations-insights" className="underline">
                  تقارير التشغيل
                </Link>
              </li>
            </ul>
            <div className="mt-4 flex flex-wrap gap-2">
              {(['work', 'printing', 'sla'] as const).map((entity) => (
                <button
                  key={entity}
                  type="button"
                  disabled={exporting === entity}
                  onClick={() => void handleExport(entity)}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs disabled:opacity-50"
                >
                  تصدير {entity}.csv
                </button>
              ))}
            </div>
          </DashboardSection>
        </>
      ) : null}
    </section>
  )
}
