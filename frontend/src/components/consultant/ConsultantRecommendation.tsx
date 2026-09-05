import { Link } from 'react-router-dom'
import type { ConsultantPackageMatch, ConsultantRecommendations, ConsultantSession } from '../../types/api'
import { formatDuration, formatMoney } from '../../utils/catalog'
import { consultantCtaEventName, isLiveConsultantCta, READINESS_LABELS } from '../../utils/consultantCta'

type ConsultantRecommendationProps = {
  session: ConsultantSession
  onCta: (type: string, path: string) => void
  onShowLead: () => void
}

export function ConsultantRecommendation({ session, onCta, onShowLead }: ConsultantRecommendationProps) {
  const diagnosis = session.diagnosis
  const readiness = session.readiness
  const recommendations = session.recommendations

  if (!diagnosis && !recommendations) {
    return null
  }

  return (
    <section className="space-y-5" aria-labelledby="consultant-result-title">
      <h2 id="consultant-result-title" className="text-xl font-semibold">
        التشخيص والتوصية
      </h2>

      {diagnosis ? (
        <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-semibold">تشخيص النشاط</h3>
          <p className="text-sm leading-7 text-slate-700">{diagnosis.summary}</p>
          <div>
            <h4 className="mb-2 text-sm font-medium">التحديات الحالية</h4>
            <ol className="list-decimal space-y-1 pr-5 text-sm leading-7 text-slate-700">
              {diagnosis.challenges.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ol>
          </div>
          <div className="flex flex-wrap gap-2">
            {diagnosis.priorities.map((item) => (
              <span
                key={item.label}
                className={[
                  'rounded-full px-3 py-1 text-xs font-medium',
                  item.level === 'high' ? 'bg-amber-100 text-amber-950' : 'bg-slate-100 text-slate-700',
                ].join(' ')}
              >
                {item.level === 'high' ? 'أولوية عالية' : 'أولوية متوسطة'} — {item.label}
              </span>
            ))}
          </div>
        </article>
      ) : null}

      {readiness ? (
        <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-semibold">جاهزية النمو</h3>
          <p className="text-3xl font-semibold">{readiness.score} / 100</p>
          <ul className="grid gap-2 sm:grid-cols-2">
            {Object.entries(readiness.dimensions).map(([key, value]) => (
              <li key={key} className="flex items-center justify-between gap-3 text-sm">
                <span className="text-slate-600">{READINESS_LABELS[key] ?? key}</span>
                <span className="font-medium">{value}</span>
              </li>
            ))}
          </ul>
        </article>
      ) : null}

      {recommendations ? <RecommendationCards recommendations={recommendations} onCta={onCta} onShowLead={onShowLead} /> : null}
    </section>
  )
}

function RecommendationCards({
  recommendations,
  onCta,
  onShowLead,
}: {
  recommendations: ConsultantRecommendations
  onCta: (type: string, path: string) => void
  onShowLead: () => void
}) {
  return (
    <div className="space-y-4">
      {recommendations.best_match ? (
        <PackageMatchCard title="الأنسب لك" match={recommendations.best_match} onCta={onCta} />
      ) : null}
      {recommendations.alternative ? (
        <PackageMatchCard title="بديل" match={recommendations.alternative} onCta={onCta} />
      ) : null}

      {recommendations.services.length > 0 ? (
        <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-semibold">خدمات مناسبة من الكتالوج</h3>
          <ul className="space-y-3">
            {recommendations.services.map((service) => (
              <li key={service.slug} className="rounded-xl border border-slate-100 p-3">
                <p className="font-medium">{service.name}</p>
                {service.summary ? <p className="text-sm text-slate-600">{service.summary}</p> : null}
                <p className="mt-1 text-sm">{formatMoney(service.base_price, service.currency)}</p>
              </li>
            ))}
          </ul>
        </article>
      ) : null}

      {recommendations.printing ? (
        <article className="space-y-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="font-semibold">الطباعة والتغليف</h3>
          {recommendations.printing.starting_price != null ? (
            <p className="text-sm text-slate-600">
              سعر البداية للمنتج المختار: {formatMoney(recommendations.printing.starting_price, recommendations.printing.currency)}
            </p>
          ) : (
            <p className="text-sm text-slate-600">السعر يُحدَّد بعد اختيار المنتج أو طلب عرض سعر.</p>
          )}
        </article>
      ) : null}

      {recommendations.fallback ? (
        <article className="space-y-2 rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <h3 className="font-semibold">{recommendations.fallback.title}</h3>
          <p className="text-sm leading-7">{recommendations.fallback.message}</p>
        </article>
      ) : null}

      <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        {isLiveConsultantCta(recommendations.cta) ? (
          <Link
            to={recommendations.cta.path}
            onClick={() => onCta(consultantCtaEventName(recommendations.cta.type), recommendations.cta.path)}
            className="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          >
            {recommendations.cta.label}
          </Link>
        ) : null}
        <button
          type="button"
          onClick={onShowLead}
          className="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
        >
          تحدث مع مختص
        </button>
      </div>
    </div>
  )
}

function PackageMatchCard({
  title,
  match,
  onCta,
}: {
  title: string
  match: ConsultantPackageMatch
  onCta: (type: string, path: string) => void
}) {
  const duration = formatDuration(match.duration_days)

  return (
    <article className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-xs font-medium text-amber-800">{title}</p>
      <h3 className="text-lg font-semibold">{match.name}</h3>
      {match.description ? <p className="text-sm leading-7 text-slate-600">{match.description}</p> : null}
      <p className="text-2xl font-semibold">{formatMoney(match.final_price, match.currency)}</p>
      {duration ? <p className="text-sm text-slate-500">مدة التنفيذ: {duration}</p> : null}

      <div>
        <h4 className="mb-2 text-sm font-medium">لماذا هذه الباقة؟</h4>
        <ul className="list-disc space-y-1 pr-5 text-sm leading-7 text-slate-700">
          {match.reasons.map((reason) => (
            <li key={reason}>{reason}</li>
          ))}
        </ul>
      </div>

      {match.items.length > 0 ? (
        <div>
          <h4 className="mb-2 text-sm font-medium">ماذا تتضمن؟</h4>
          <ul className="space-y-1 text-sm text-slate-700">
            {match.items.map((item) => (
              <li key={`${item.service_id}-${item.quantity}`}>
                {item.service?.name ?? 'خدمة'}
                {item.quantity > 1 ? ` × ${item.quantity}` : ''}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {isLiveConsultantCta(match.cta) ? (
        <Link
          to={match.cta.path}
          onClick={() => onCta(consultantCtaEventName(match.cta.type), match.cta.path)}
          className="inline-flex rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white"
        >
          {match.cta.label}
        </Link>
      ) : null}
    </article>
  )
}
