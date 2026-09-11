import { useCallback, useEffect, useRef, useState } from 'react'
import { describeApiError } from '../utils/errors'

export type AsyncState<T> =
  | { status: 'loading' }
  | { status: 'ready'; data: T }
  | { status: 'error'; message: string }

export function useAsyncData<T>(loader: () => Promise<{ data: T }>, deps: unknown[] = []) {
  const loaderRef = useRef(loader)
  loaderRef.current = loader

  const [state, setState] = useState<AsyncState<T>>({ status: 'loading' })

  const reload = useCallback(async () => {
    setState({ status: 'loading' })

    try {
      const response = await loaderRef.current()
      setState({ status: 'ready', data: response.data })
    } catch (caught) {
      setState({
        status: 'error',
        message: describeApiError(caught, 'تعذر تحميل البيانات.'),
      })
    }
  }, [])

  useEffect(() => {
    void reload()
    // eslint-disable-next-line react-hooks/exhaustive-deps -- caller-controlled refresh deps
  }, [reload, ...deps])

  return { state, reload }
}
