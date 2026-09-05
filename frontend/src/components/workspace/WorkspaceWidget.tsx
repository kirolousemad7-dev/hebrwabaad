type WorkspaceWidgetProps = {
  title: string
  children: React.ReactNode
  className?: string
  compact?: boolean
}

export function WorkspaceWidget({ title, children, className, compact }: WorkspaceWidgetProps) {
  const padding = compact ? 'p-4' : 'p-5'

  return (
    <article className={['min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white shadow-sm', padding, className]
      .filter(Boolean)
      .join(' ')}
    >
      <h2 className="text-base font-semibold">{title}</h2>
      {children}
    </article>
  )
}

export function WorkspaceWidgetGrid({ children }: { children: React.ReactNode }) {
  return <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 xl:max-w-6xl">{children}</div>
}
