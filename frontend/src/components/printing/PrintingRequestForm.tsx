import { FormEvent, useId, useMemo, useState } from 'react'
import { FeedbackBanner } from '../ui/FeedbackBanner'
import type { PrintingProduct } from '../../utils/printingProducts'
import { ApiRequestError } from '../../services/api'
import { createPrintingRequest } from '../../services/printingRequests'
import type { PrintingRequest } from '../../types/api'
import {
  isAllowedPrintingFile,
  PRINTING_FILE_ACCEPT,
  PRINTING_FILE_MAX_BYTES,
  PRINTING_FINISHING_LABELS,
  PRINTING_FINISHINGS,
  PRINTING_METHOD_LABELS,
  PRINTING_METHODS,
  PRINTING_REQUEST_FIELD_LABELS,
  PRINTING_SHAPE_LABELS,
  PRINTING_SHAPES,
  PRINTING_UNIT_LABELS,
  PRINTING_UNITS,
  riyadhTodayInputValue,
  type PrintingFinishing,
  type PrintingMethod,
  type PrintingShape,
  type PrintingUnit,
} from '../../utils/printingRequest'

const fieldClass =
  'w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:bg-slate-50'

type PrintingRequestFormProps = {
  product: PrintingProduct
  onSuccess: (request: PrintingRequest) => void
}

function fieldMessage(errors: Record<string, string[]>, name: string): string | undefined {
  return errors[name]?.[0]
}

export function PrintingRequestForm({ product, onSuccess }: PrintingRequestFormProps) {
  const formId = useId()
  const today = useMemo(() => riyadhTodayInputValue(), [])
  const materials = product.materials.length > 0 ? product.materials : ['خامة مخصصة']

  const [width, setWidth] = useState('')
  const [height, setHeight] = useState('')
  const [unit, setUnit] = useState<PrintingUnit>('CM')
  const [shape, setShape] = useState<PrintingShape>('RECTANGLE')
  const [material, setMaterial] = useState(materials[0])
  const [quantity, setQuantity] = useState('1')
  const [method, setMethod] = useState<PrintingMethod>('DIGITAL')
  const [finishing, setFinishing] = useState<PrintingFinishing[]>(['NONE'])
  const [file, setFile] = useState<File | null>(null)
  const [fileKey, setFileKey] = useState(0)
  const [requiredDate, setRequiredDate] = useState(today)
  const [notes, setNotes] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})

  function setFieldError(name: string, message: string) {
    setFieldErrors((current) => ({ ...current, [name]: [message] }))
  }

  function toggleFinishing(value: PrintingFinishing) {
    setFinishing((current) => {
      if (current.includes(value)) {
        return current.filter((item) => item !== value)
      }

      return [...current, value]
    })
  }

  function handleFileChange(next: File | null) {
    setFieldErrors((current) => {
      const rest = { ...current }
      delete rest.file
      return rest
    })

    if (!next) {
      setFile(null)
      setFileKey((current) => current + 1)
      return
    }

    if (!isAllowedPrintingFile(next)) {
      setFile(null)
      setFileKey((current) => current + 1)
      setFieldError('file', 'صيغة الملف غير مدعومة. استخدم PDF أو صورة أو ZIP.')
      return
    }

    if (next.size > PRINTING_FILE_MAX_BYTES) {
      setFile(null)
      setFileKey((current) => current + 1)
      setFieldError('file', 'حجم الملف أكبر من 10 ميغابايت.')
      return
    }

    setFile(next)
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setFormError(null)

    const nextErrors: Record<string, string[]> = {}
    const parsedQuantity = Number.parseInt(quantity, 10)

    if (width.trim() === '' || Number(width) <= 0) {
      nextErrors.width = ['أدخل عرضاً أكبر من صفر.']
    }

    if (height.trim() === '' || Number(height) <= 0) {
      nextErrors.height = ['أدخل ارتفاعاً أكبر من صفر.']
    }

    if (!Number.isInteger(parsedQuantity) || parsedQuantity < 1) {
      nextErrors.quantity = ['الكمية يجب أن تكون رقماً صحيحاً وألا تقل عن 1.']
    }

    if (finishing.length === 0) {
      nextErrors.finishing = ['اختر تشطيباً واحداً على الأقل.']
    }

    if (!file) {
      nextErrors.file = ['أرفق ملف التصميم.']
    }

    if (requiredDate < today) {
      nextErrors.required_date = ['تاريخ التسليم لا يمكن أن يكون في الماضي.']
    }

    if (Object.keys(nextErrors).length > 0) {
      setFieldErrors(nextErrors)
      return
    }

    setFieldErrors({})
    setSubmitting(true)

    try {
      const response = await createPrintingRequest({
        productSlug: product.slug,
        productName: product.name,
        width,
        height,
        dimensionUnit: unit,
        shape,
        material,
        quantity: String(parsedQuantity),
        printingMethod: method,
        finishing,
        file: file as File,
        requiredDate,
        notes,
      })
      onSuccess(response.data)
    } catch (caught) {
      if (caught instanceof ApiRequestError) {
        setFieldErrors(caught.body?.errors ?? {})
        setFormError(
          caught.status === 401
            ? 'يلزم تسجيل الدخول لإرسال الطلب.'
            : caught.status === 403
              ? 'طلب الطباعة المخصصة متاح لحسابات العملاء فقط.'
              : caught.body?.errors
                ? 'راجع الحقول المحددة ثم أعد المحاولة.'
                : 'تعذر إرسال الطلب. حاول مرة أخرى.',
        )
      } else {
        setFormError('تعذر إرسال الطلب. حاول مرة أخرى.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" noValidate>
      {formError ? <FeedbackBanner kind="error">{formError}</FeedbackBanner> : null}

      <input type="hidden" name="product_slug" value={product.slug} />
      <input type="hidden" name="product_name" value={product.name} />

      <fieldset className="grid gap-4 sm:grid-cols-3">
        <legend className="mb-2 text-sm font-medium text-slate-800">الأبعاد</legend>
        <div className="space-y-1">
          <label htmlFor={`${formId}-width`} className="block text-sm">
            العرض <span className="text-red-700">*</span>
          </label>
          <input
            id={`${formId}-width`}
            type="number"
            inputMode="decimal"
            min="0.1"
            step="0.1"
            required
            value={width}
            onChange={(event) => setWidth(event.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldMessage(fieldErrors, 'width'))}
            aria-describedby={fieldMessage(fieldErrors, 'width') ? `${formId}-width-error` : undefined}
          />
          {fieldMessage(fieldErrors, 'width') ? (
            <p id={`${formId}-width-error`} className="text-sm text-red-700">
              {fieldMessage(fieldErrors, 'width')}
            </p>
          ) : null}
        </div>
        <div className="space-y-1">
          <label htmlFor={`${formId}-height`} className="block text-sm">
            الارتفاع <span className="text-red-700">*</span>
          </label>
          <input
            id={`${formId}-height`}
            type="number"
            inputMode="decimal"
            min="0.1"
            step="0.1"
            required
            value={height}
            onChange={(event) => setHeight(event.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldMessage(fieldErrors, 'height'))}
            aria-describedby={fieldMessage(fieldErrors, 'height') ? `${formId}-height-error` : undefined}
          />
          {fieldMessage(fieldErrors, 'height') ? (
            <p id={`${formId}-height-error`} className="text-sm text-red-700">
              {fieldMessage(fieldErrors, 'height')}
            </p>
          ) : null}
        </div>
        <div className="space-y-1">
          <label htmlFor={`${formId}-unit`} className="block text-sm">
            الوحدة <span className="text-red-700">*</span>
          </label>
          <select
            id={`${formId}-unit`}
            value={unit}
            onChange={(event) => setUnit(event.target.value as PrintingUnit)}
            className={fieldClass}
          >
            {PRINTING_UNITS.map((value) => (
              <option key={value} value={value}>
                {PRINTING_UNIT_LABELS[value]}
              </option>
            ))}
          </select>
        </div>
      </fieldset>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1">
          <label htmlFor={`${formId}-shape`} className="block text-sm">
            الشكل <span className="text-red-700">*</span>
          </label>
          <select
            id={`${formId}-shape`}
            value={shape}
            onChange={(event) => setShape(event.target.value as PrintingShape)}
            className={fieldClass}
          >
            {PRINTING_SHAPES.map((value) => (
              <option key={value} value={value}>
                {PRINTING_SHAPE_LABELS[value]}
              </option>
            ))}
          </select>
        </div>
        <div className="space-y-1">
          <label htmlFor={`${formId}-material`} className="block text-sm">
            الخامة <span className="text-red-700">*</span>
          </label>
          <select
            id={`${formId}-material`}
            value={material}
            onChange={(event) => setMaterial(event.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldMessage(fieldErrors, 'material'))}
            aria-describedby={fieldMessage(fieldErrors, 'material') ? `${formId}-material-error` : undefined}
          >
            {materials.map((value) => (
              <option key={value} value={value}>
                {value}
              </option>
            ))}
          </select>
          {fieldMessage(fieldErrors, 'material') ? (
            <p id={`${formId}-material-error`} className="text-sm text-red-700">
              {fieldMessage(fieldErrors, 'material')}
            </p>
          ) : null}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-1">
          <label htmlFor={`${formId}-quantity`} className="block text-sm">
            الكمية <span className="text-red-700">*</span>
          </label>
          <input
            id={`${formId}-quantity`}
            type="number"
            inputMode="numeric"
            min={1}
            step={1}
            required
            value={quantity}
            onChange={(event) => setQuantity(event.target.value)}
            className={fieldClass}
            aria-invalid={Boolean(fieldMessage(fieldErrors, 'quantity'))}
            aria-describedby={fieldMessage(fieldErrors, 'quantity') ? `${formId}-quantity-error` : undefined}
          />
          {fieldMessage(fieldErrors, 'quantity') ? (
            <p id={`${formId}-quantity-error`} className="text-sm text-red-700">
              {fieldMessage(fieldErrors, 'quantity')}
            </p>
          ) : null}
        </div>
        <div className="space-y-1">
          <label htmlFor={`${formId}-method`} className="block text-sm">
            طريقة الطباعة <span className="text-red-700">*</span>
          </label>
          <select
            id={`${formId}-method`}
            value={method}
            onChange={(event) => setMethod(event.target.value as PrintingMethod)}
            className={fieldClass}
          >
            {PRINTING_METHODS.map((value) => (
              <option key={value} value={value}>
                {PRINTING_METHOD_LABELS[value]}
              </option>
            ))}
          </select>
        </div>
      </div>

      <fieldset>
        <legend className="mb-2 text-sm font-medium text-slate-800">
          التشطيب <span className="text-red-700">*</span>
        </legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {PRINTING_FINISHINGS.map((value) => (
            <label key={value} className="flex min-w-0 items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={finishing.includes(value)}
                onChange={() => toggleFinishing(value)}
                className="h-4 w-4 rounded border-slate-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
              />
              <span>{PRINTING_FINISHING_LABELS[value]}</span>
            </label>
          ))}
        </div>
        {fieldMessage(fieldErrors, 'finishing') ? (
          <p className="mt-2 text-sm text-red-700">{fieldMessage(fieldErrors, 'finishing')}</p>
        ) : null}
      </fieldset>

      <div className="space-y-2">
        <label htmlFor={`${formId}-file`} className="block text-sm">
          ملف التصميم <span className="text-red-700">*</span>
        </label>
        <input
          id={`${formId}-file`}
          key={fileKey}
          type="file"
          accept={PRINTING_FILE_ACCEPT}
          onChange={(event) => handleFileChange(event.target.files?.[0] ?? null)}
          className="block w-full min-w-0 text-sm file:me-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
          aria-invalid={Boolean(fieldMessage(fieldErrors, 'file'))}
          aria-describedby={`${formId}-file-help${fieldMessage(fieldErrors, 'file') ? ` ${formId}-file-error` : ''}`}
        />
        <p id={`${formId}-file-help`} className="text-xs text-slate-500">
          PDF، JPG، PNG، WebP، SVG، أو ZIP. الحد الأقصى 10 ميغابايت.
        </p>
        {file ? (
          <div className="flex flex-wrap items-center gap-3 text-sm">
            <p>الملف المحدد: {file.name}</p>
            <button
              type="button"
              onClick={() => handleFileChange(null)}
              className="underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900"
            >
              إزالة الملف
            </button>
          </div>
        ) : null}
        {fieldMessage(fieldErrors, 'file') ? (
          <p id={`${formId}-file-error`} className="text-sm text-red-700">
            {fieldMessage(fieldErrors, 'file')}
          </p>
        ) : null}
      </div>

      <div className="space-y-1">
        <label htmlFor={`${formId}-date`} className="block text-sm">
          تاريخ التسليم المطلوب <span className="text-red-700">*</span>
        </label>
        <input
          id={`${formId}-date`}
          type="date"
          required
          min={today}
          value={requiredDate}
          onChange={(event) => setRequiredDate(event.target.value)}
          className={fieldClass}
          aria-invalid={Boolean(fieldMessage(fieldErrors, 'required_date'))}
          aria-describedby={fieldMessage(fieldErrors, 'required_date') ? `${formId}-date-error` : undefined}
        />
        {fieldMessage(fieldErrors, 'required_date') ? (
          <p id={`${formId}-date-error`} className="text-sm text-red-700">
            {fieldMessage(fieldErrors, 'required_date')}
          </p>
        ) : null}
      </div>

      <div className="space-y-1">
        <label htmlFor={`${formId}-notes`} className="block text-sm">
          ملاحظات إضافية
        </label>
        <textarea
          id={`${formId}-notes`}
          rows={4}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          className={fieldClass}
        />
      </div>

      {Object.keys(fieldErrors).some((key) => !['width', 'height', 'quantity', 'material', 'finishing', 'file', 'required_date'].includes(key)) ? (
        <FeedbackBanner kind="error">
          <ul className="list-inside list-disc space-y-1">
            {Object.entries(fieldErrors)
              .filter(([key]) => !['width', 'height', 'quantity', 'material', 'finishing', 'file', 'required_date'].includes(key))
              .map(([key, messages]) => (
                <li key={key}>
                  {PRINTING_REQUEST_FIELD_LABELS[key] ?? key}: {messages.join(' ')}
                </li>
              ))}
          </ul>
        </FeedbackBanner>
      ) : null}

      <button
        type="submit"
        disabled={submitting}
        className="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 disabled:opacity-60 sm:w-auto"
      >
        {submitting ? 'جاري إرسال الطلب...' : 'إرسال طلب الطباعة'}
      </button>
    </form>
  )
}
