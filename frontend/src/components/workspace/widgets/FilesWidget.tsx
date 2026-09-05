import { Link } from 'react-router-dom'
import { useAsyncData } from '../../../hooks/useAsyncData'
import { getManagedFiles } from '../../../services/files'
import { FILE_COPY, fileContextLabel, formatFileSize } from '../../../utils/files'
import { WorkspaceEmptyState, WorkspaceErrorState } from '../WorkspaceStatus'
import { WorkspaceWidget } from '../WorkspaceWidget'

export function FilesWidget() {
  const { state, reload } = useAsyncData(() => getManagedFiles('workspace', '?per_page=5'))

  return (
    <WorkspaceWidget title={FILE_COPY.title}>
      {state.status === 'loading' ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-busy="true" /> : null}
      {state.status === 'error' ? <WorkspaceErrorState message={state.message} onRetry={() => void reload()} /> : null}
      {state.status === 'ready' && state.data.items.length === 0 ? (
        <WorkspaceEmptyState title={FILE_COPY.empty} description="ستظهر هنا ملفات المشاريع والمهام المصرّح لك بها." />
      ) : null}
      {state.status === 'ready' && state.data.items.length > 0 ? (
        <ul className="space-y-2 text-sm">
          {state.data.items.map((file) => (
            <li key={file.id} className="rounded-xl border border-slate-200 p-3">
              <p className="truncate font-medium">{file.original_name}</p>
              <p className="mt-1 text-slate-600">
                {formatFileSize(file.size)}
                <span className="mx-1 text-slate-400" aria-hidden="true">
                  ·
                </span>
                {fileContextLabel(file)}
              </p>
            </li>
          ))}
        </ul>
      ) : null}
      <Link
        to="/workspace/files"
        className="inline-flex min-h-11 items-center text-sm underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
      >
        عرض كل الملفات
      </Link>
    </WorkspaceWidget>
  )
}
