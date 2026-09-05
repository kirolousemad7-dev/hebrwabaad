type CustomerUnavailableCardProps = {
  title: string
  message: string
}

export function CustomerUnavailableCard({ title, message }: CustomerUnavailableCardProps) {
  return (
    <article className="min-w-0 rounded-2xl border border-dashed border-slate-300 bg-white p-5">
      <h3 className="font-semibold">{title}</h3>
      <p className="mt-2 text-sm leading-7 text-slate-600">{message}</p>
    </article>
  )
}
