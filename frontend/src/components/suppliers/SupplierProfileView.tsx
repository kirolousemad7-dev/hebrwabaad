type ProfileSection = {
  id: string
  title: string
  body?: React.ReactNode
}

type SupplierProfileViewProps = {
  name: string
  logo?: string | null
  coverImage?: string | null
  shortDescription?: string | null
  statusBadges?: React.ReactNode
  sections: ProfileSection[]
  actions?: React.ReactNode
}

export function SupplierProfileView({
  name,
  logo,
  coverImage,
  shortDescription,
  statusBadges,
  sections,
  actions,
}: SupplierProfileViewProps) {
  return (
    <div className="space-y-6">
      <header className="overflow-hidden rounded-3xl border bg-white shadow-sm">
        <div
          className="h-36 bg-gradient-to-l from-slate-200 via-slate-100 to-white"
          style={coverImage ? { backgroundImage: `url(${coverImage})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
        />
        <div className="flex flex-col gap-4 px-5 pb-5 pt-4 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex items-end gap-4">
            {logo ? (
              <img src={logo} alt="" className="-mt-12 h-20 w-20 rounded-2xl border bg-white object-cover shadow" />
            ) : (
              <div className="-mt-12 flex h-20 w-20 items-center justify-center rounded-2xl border bg-slate-50 text-sm text-slate-500 shadow">شعار</div>
            )}
            <div>
              <h1 className="text-2xl font-semibold text-slate-900">{name}</h1>
              {shortDescription ? <p className="mt-1 max-w-2xl text-sm text-slate-600">{shortDescription}</p> : null}
              {statusBadges ? <div className="mt-2 flex flex-wrap gap-2">{statusBadges}</div> : null}
            </div>
          </div>
          {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
        </div>
      </header>

      <nav className="flex flex-wrap gap-2">
        {sections.map((section) => (
          <a key={section.id} href={`#${section.id}`} className="rounded-full border bg-white px-3 py-1.5 text-xs text-slate-700">
            {section.title}
          </a>
        ))}
      </nav>

      <div className="grid gap-4">
        {sections.map((section) => (
          <section key={section.id} id={section.id} className="scroll-mt-24 rounded-2xl border bg-white p-5 shadow-sm">
            <h2 className="mb-3 text-lg font-semibold text-slate-900">{section.title}</h2>
            <div className="text-sm leading-7 text-slate-700">{section.body ?? <p className="text-slate-500">لا توجد بيانات بعد.</p>}</div>
          </section>
        ))}
      </div>
    </div>
  )
}

export function ProfileChip({ children }: { children: React.ReactNode }) {
  return <span className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700">{children}</span>
}
