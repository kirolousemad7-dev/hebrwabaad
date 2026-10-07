import { formatEnglishAmount } from '../../utils/catalog'
import { riyalGlyphSupported } from '../../utils/riyalMark'

type SarAmountProps = {
  amount: string | number
  className?: string
  struck?: boolean
}

export function SarAmount({ amount, className = '', struck = false }: SarAmountProps) {
  const formatted = formatEnglishAmount(amount)
  const glyph = riyalGlyphSupported()

  return (
    <span
      dir="ltr"
      className={[
        'inline-flex items-baseline gap-1 whitespace-nowrap tabular-nums',
        struck ? 'text-red-600 line-through' : '',
        className,
      ].join(' ')}
    >
      <span>{formatted}</span>
      <span className="inline-flex items-center leading-none" title="ريال سعودي">
        {glyph ? (
          <>
            <span aria-hidden="true">⃁</span>
            <span className="sr-only"> SAR</span>
          </>
        ) : (
          <span>SAR</span>
        )}
      </span>
    </span>
  )
}
