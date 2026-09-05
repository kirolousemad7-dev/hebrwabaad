import { FormEvent, useMemo, useState } from 'react'
import type { ConsultantOption, ConsultantPrompt as Prompt } from '../../types/api'

type ConsultantPromptProps = {
  prompt: Prompt
  busy: boolean
  onAnswer: (value: unknown) => void
  onSkip?: () => void
}

export function ConsultantPrompt({ prompt, busy, onAnswer, onSkip }: ConsultantPromptProps) {
  const [query, setQuery] = useState('')
  const [selected, setSelected] = useState<string[]>([])
  const [text, setText] = useState('')

  const options = useMemo(() => {
    const all = prompt.options ?? []
    if (!prompt.searchable || query.trim() === '') {
      return all
    }

    const needle = query.trim()

    return all.filter((option) => option.label.includes(needle) || option.id.includes(needle))
  }, [prompt.options, prompt.searchable, query])

  function toggle(id: string) {
    setSelected((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]))
  }

  function submitMulti() {
    if (selected.length === 0) {
      return
    }
    onAnswer(selected)
  }

  function submitText(event: FormEvent) {
    event.preventDefault()
    if (text.trim() === '') {
      return
    }
    onAnswer(text.trim())
  }

  return (
    <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="consultant-prompt-title">
      <div className="space-y-1">
        <h2 id="consultant-prompt-title" className="text-lg font-semibold">
          {prompt.title}
        </h2>
        {prompt.body ? <p className="text-sm leading-7 text-slate-600">{prompt.body}</p> : null}
      </div>

      {prompt.searchable ? (
        <label className="block space-y-1 text-sm">
          <span className="text-slate-600">بحث</span>
          <input
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            className="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            placeholder="ابحث عن التصنيف..."
          />
        </label>
      ) : null}

      {prompt.type === 'text' ? (
        <form className="flex flex-col gap-2 sm:flex-row" onSubmit={submitText}>
          <label className="sr-only" htmlFor="consultant-text-answer">
            {prompt.title}
          </label>
          <input
            id="consultant-text-answer"
            value={text}
            onChange={(event) => setText(event.target.value)}
            placeholder={prompt.placeholder ?? 'اكتب إجابتك'}
            className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          />
          <button
            type="submit"
            disabled={busy || text.trim() === ''}
            className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
          >
            متابعة
          </button>
        </form>
      ) : null}

      {prompt.type === 'multi_chips' ? (
        <div className="space-y-3">
          <OptionGrid
            options={options}
            selected={selected}
            multiple
            onSelect={toggle}
            disabled={busy}
          />
          <button
            type="button"
            disabled={busy || selected.length === 0}
            onClick={submitMulti}
            className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
          >
            متابعة
          </button>
        </div>
      ) : null}

      {prompt.type === 'chips' || prompt.type === 'cards' || prompt.type === 'search_cards' ? (
        <OptionGrid
          options={options}
          selected={[]}
          onSelect={(id) => onAnswer(id)}
          disabled={busy}
          cards={prompt.type !== 'chips'}
        />
      ) : null}

      {options.length === 0 && prompt.type !== 'text' ? (
        <p className="text-sm text-slate-500">لا توجد نتائج مطابقة.</p>
      ) : null}

      {prompt.skippable && onSkip ? (
        <button
          type="button"
          disabled={busy}
          onClick={onSkip}
          className="text-sm text-slate-600 underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-50"
        >
          تخطي
        </button>
      ) : null}
    </section>
  )
}

function OptionGrid({
  options,
  selected,
  onSelect,
  disabled,
  multiple = false,
  cards = false,
}: {
  options: ConsultantOption[]
  selected: string[]
  onSelect: (id: string) => void
  disabled: boolean
  multiple?: boolean
  cards?: boolean
}) {
  return (
    <ul className={cards ? 'grid gap-3 sm:grid-cols-2' : 'flex flex-wrap gap-2'}>
      {options.map((option) => {
        const isSelected = selected.includes(option.id)

        return (
          <li key={option.id} className="min-w-0">
            <button
              type="button"
              disabled={disabled}
              aria-pressed={multiple ? isSelected : undefined}
              onClick={() => onSelect(option.id)}
              className={[
                'w-full min-w-0 text-start focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-50',
                cards
                  ? 'rounded-2xl border px-4 py-3 shadow-sm'
                  : 'rounded-full border px-3 py-2 text-sm',
                isSelected || (!multiple && false)
                  ? 'border-slate-900 bg-slate-900 text-white'
                  : 'border-slate-300 bg-white text-slate-800 hover:border-slate-400',
                isSelected ? 'border-slate-900 bg-slate-900 text-white' : '',
              ].join(' ')}
            >
              <span className="block font-medium">{option.label}</span>
              {option.description ? <span className="mt-1 block text-sm opacity-80">{option.description}</span> : null}
            </button>
          </li>
        )
      })}
    </ul>
  )
}
