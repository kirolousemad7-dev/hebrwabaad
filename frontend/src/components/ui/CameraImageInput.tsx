import { useState } from 'react'
import { uploadOwnerMarketingMedia } from '../../services/marketingCms'
import { uploadContentMedia } from '../../services/work'
import { describeApiError } from '../../utils/errors'

type CameraImageInputProps = {
  onUploaded: (url: string) => void
  source?: 'owner' | 'content'
}

export function CameraImageInput({ onUploaded, source = 'owner' }: CameraImageInputProps) {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function upload(file: File | null) {
    if (!file) {
      return
    }

    setBusy(true)
    setError(null)
    try {
      if (source === 'content') {
        const uploaded = await uploadContentMedia(file, 'cover')
        if (uploaded.data.url) {
          onUploaded(uploaded.data.url)
        }
      } else {
        const body = new FormData()
        body.append('file', file)
        body.append('alt_text', file.name)
        body.append('title', file.name)
        const uploaded = await uploadOwnerMarketingMedia(body)
        if (uploaded.data.url) {
          onUploaded(uploaded.data.url)
        }
      }
    } catch (caught) {
      setError(describeApiError(caught, 'تعذر رفع الصورة.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-1">
      <div className="flex flex-wrap gap-3 text-sm">
        <label className="block">
          <span className="mb-1 block text-slate-600">من المعرض</span>
          <input
            type="file"
            accept="image/*"
            className="block w-full text-sm"
            disabled={busy}
            onChange={(event) => void upload(event.target.files?.[0] ?? null)}
          />
        </label>
        <label className="block">
          <span className="mb-1 block text-slate-600">تصوير من الكاميرا</span>
          <input
            type="file"
            accept="image/*"
            capture="environment"
            className="block w-full text-sm"
            disabled={busy}
            onChange={(event) => void upload(event.target.files?.[0] ?? null)}
          />
        </label>
      </div>
      {busy ? <p className="text-xs text-slate-500">جاري رفع الصورة...</p> : null}
      {error ? <p className="text-xs text-red-700">{error}</p> : null}
    </div>
  )
}
