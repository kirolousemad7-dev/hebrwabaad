import { useRef, useState } from 'react'
import { deleteMedia, listEntityMedia, setPrimaryMedia, uploadMedia } from '../../services/media'
import { describeApiError } from '../../utils/errors'
import { resolveMediaUrl } from '../../utils/mediaUrl'
import { projectInitials } from '../../utils/workspaceProjects'

type ProjectCoverControlProps = {
  projectId: number
  title: string
  coverUrl?: string | null
  onUpdated: (url: string | null) => void
}

export function ProjectCoverControl({ projectId, title, coverUrl, onUpdated }: ProjectCoverControlProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [failed, setFailed] = useState(false)
  const image = resolveMediaUrl(coverUrl)

  async function upload(file: File | null) {
    if (!file) return
    setBusy(true)
    setError(null)
    try {
      const existing = await listEntityMedia('project', projectId)
      const previous = existing.data.items.find((item) => item.is_primary && item.is_image)
      const uploaded = await uploadMedia({ file, entityType: 'project', entityId: projectId, visibility: 'PUBLIC' })
      await setPrimaryMedia(uploaded.data.id)
      if (previous && previous.id !== uploaded.data.id) {
        await deleteMedia(previous.id)
      }
      setFailed(false)
      onUpdated(uploaded.data.url ?? null)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر حفظ صورة المشروع.'))
    } finally {
      setBusy(false)
      if (inputRef.current) inputRef.current.value = ''
    }
  }

  async function remove() {
    setBusy(true)
    setError(null)
    try {
      const existing = await listEntityMedia('project', projectId)
      const primary = existing.data.items.find((item) => item.is_primary && item.is_image)
      if (primary) {
        await deleteMedia(primary.id)
      }
      onUpdated(null)
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر إزالة صورة المشروع.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-2">
      {image && !failed ? (
        <img
          src={image}
          alt=""
          className="h-[72px] w-[72px] rounded-xl border border-slate-200 object-cover"
          onError={() => setFailed(true)}
        />
      ) : (
        <span className="inline-flex h-[72px] w-[72px] items-center justify-center rounded-xl border border-slate-200 bg-[#f4f6f8] text-sm font-semibold text-slate-700">
          {projectInitials(title)}
        </span>
      )}
      <div className="flex gap-1">
        <button
          type="button"
          disabled={busy}
          onClick={() => inputRef.current?.click()}
          className="rounded-md px-1 py-0.5 text-[11px] text-slate-500 underline-offset-2 hover:underline disabled:opacity-50"
        >
          {image && !failed ? 'استبدال' : 'رفع'}
        </button>
        {image && !failed ? (
          <button
            type="button"
            disabled={busy}
            onClick={() => void remove()}
            className="rounded-md px-1 py-0.5 text-[11px] text-red-700 underline-offset-2 hover:underline disabled:opacity-50"
          >
            إزالة
          </button>
        ) : null}
      </div>
      <input
        ref={inputRef}
        type="file"
        accept="image/png,image/jpeg,image/webp,image/gif"
        className="sr-only"
        onChange={(event) => void upload(event.target.files?.[0] ?? null)}
      />
      {error ? <p className="text-xs text-red-700">{error}</p> : null}
    </div>
  )
}
