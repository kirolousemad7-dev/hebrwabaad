<?php

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\InvoiceIssuedMail;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForStaff(User $actor, array $filters = []): LengthAwarePaginator
    {
        $this->assertCanView($actor);

        $query = Invoice::query()
            ->with(['customer:id,name,email', 'company:id,name'])
            ->withCount('items')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($builder) use ($q): void {
                $builder->where('number', 'like', '%'.$q.'%')
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%'));
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('issue_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('issue_date', '<=', $filters['to']);
        }

        return $query->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginateForCustomer(User $customer, array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Void->value])
            ->withCount('items')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 50));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Invoice
    {
        $this->assertCanManage($actor);

        return DB::transaction(function () use ($actor, $data): Invoice {
            $invoice = Invoice::query()->create([
                'number' => $this->nextNumber(),
                'customer_id' => $data['customer_id'],
                'crm_company_id' => $data['crm_company_id'] ?? null,
                'commercial_quotation_id' => $data['commercial_quotation_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'created_by' => $actor->id,
                'status' => InvoiceStatus::Draft,
                'currency' => $data['currency'] ?? 'SAR',
                'due_date' => $data['due_date'] ?? now()->addDays(14)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'subtotal' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'amount_paid' => 0,
                'amount_due' => 0,
            ]);

            $this->syncItems($invoice, $data['items'] ?? []);
            $this->recalculate($invoice);
            $this->recordEvent($invoice, $actor, 'created');

            return $this->load($invoice);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(User $actor, Invoice $invoice, array $data): Invoice
    {
        $this->assertCanManage($actor);

        if (! $invoice->statusEnum()->isEditable()) {
            throw ValidationException::withMessages([
                'invoice' => ['Only draft invoices can be updated.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $invoice, $data): Invoice {
            $invoice->fill([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'crm_company_id' => array_key_exists('crm_company_id', $data) ? $data['crm_company_id'] : $invoice->crm_company_id,
                'commercial_quotation_id' => array_key_exists('commercial_quotation_id', $data) ? $data['commercial_quotation_id'] : $invoice->commercial_quotation_id,
                'order_id' => array_key_exists('order_id', $data) ? $data['order_id'] : $invoice->order_id,
                'project_id' => array_key_exists('project_id', $data) ? $data['project_id'] : $invoice->project_id,
                'currency' => $data['currency'] ?? $invoice->currency,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'terms' => array_key_exists('terms', $data) ? $data['terms'] : $invoice->terms,
                'internal_notes' => array_key_exists('internal_notes', $data) ? $data['internal_notes'] : $invoice->internal_notes,
            ])->save();

            if (array_key_exists('items', $data)) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $data['items'] ?? []);
            }

            $this->recalculate($invoice);
            $this->recordEvent($invoice, $actor, 'updated');

            return $this->load($invoice->fresh());
        });
    }

    public function issue(User $actor, Invoice $invoice): Invoice
    {
        $this->assertCanIssue($actor);

        if ($invoice->statusEnum() !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'invoice' => ['Only draft invoices can be issued.'],
            ]);
        }

        if ($invoice->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Add at least one line item before issuing.'],
            ]);
        }

        $this->recalculate($invoice);

        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
        ]);

        $this->recordEvent($invoice, $actor, 'issued', [
            'total' => (string) $invoice->total,
        ]);

        return $this->load($invoice->fresh());
    }

    public function send(User $actor, Invoice $invoice): Invoice
    {
        $this->assertCanIssue($actor);

        $status = $invoice->statusEnum();
        if (! in_array($status, [InvoiceStatus::Issued, InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Issue the invoice before sending it.'],
            ]);
        }

        $rawToken = $invoice->public_token_hash ? null : Str::random(64);
        $updates = [
            'status' => $status === InvoiceStatus::Issued ? InvoiceStatus::Sent : $status,
            'sent_at' => now(),
        ];

        if ($rawToken !== null) {
            $updates['public_token_hash'] = Invoice::hashToken($rawToken);
            $updates['public_token_hint'] = substr($rawToken, -8);
        }

        $invoice->update($updates);
        $this->recordEvent($invoice, $actor, 'sent');

        $customer = $invoice->customer;
        if ($customer?->email) {
            Mail::to($customer->email)->queue(new InvoiceIssuedMail($invoice->fresh(['items']) ?? $invoice, (string) $customer->name));
        }

        return $this->load($invoice->fresh());
    }

    public function cancel(User $actor, Invoice $invoice): Invoice
    {
        $this->assertCanCancel($actor);

        if ($invoice->statusEnum()->isTerminal() && $invoice->statusEnum() !== InvoiceStatus::Paid) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice cannot be cancelled.'],
            ]);
        }

        if ($invoice->statusEnum() === InvoiceStatus::Paid) {
            throw ValidationException::withMessages([
                'invoice' => ['Paid invoices cannot be cancelled; void instead if needed.'],
            ]);
        }

        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->recordEvent($invoice, $actor, 'cancelled');

        return $this->load($invoice->fresh());
    }

    public function void(User $actor, Invoice $invoice): Invoice
    {
        $this->assertCanCancel($actor);

        if ($invoice->statusEnum() === InvoiceStatus::Void) {
            return $this->load($invoice);
        }

        $invoice->update([
            'status' => InvoiceStatus::Void,
            'voided_at' => now(),
        ]);

        $this->recordEvent($invoice, $actor, 'voided');

        return $this->load($invoice->fresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(User $actor, Invoice $invoice, array $data): Invoice
    {
        if ($actor->role?->canRecordInvoicePayment() !== true) {
            abort(403);
        }

        if (! $invoice->statusEnum()->isPayable()) {
            throw ValidationException::withMessages([
                'invoice' => ['Payments can only be recorded on issued invoices.'],
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must be greater than zero.'],
            ]);
        }

        $due = round((float) $invoice->amount_due, 2);
        if ($amount > $due + 0.009) {
            throw ValidationException::withMessages([
                'amount' => ['Payment exceeds amount due.'],
            ]);
        }

        $method = PaymentMethod::from((string) $data['payment_method']);

        return DB::transaction(function () use ($actor, $invoice, $data, $amount, $method): Invoice {
            Payment::query()->create([
                'customer_id' => $invoice->customer_id,
                'order_id' => $invoice->order_id,
                'commercial_quotation_id' => $invoice->commercial_quotation_id,
                'invoice_id' => $invoice->id,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $invoice->currency,
                'payment_method' => $method,
                'status' => PaymentStatus::Paid,
                'provider' => $method->provider(),
                'reference_number' => $data['reference_number'] ?? null,
                'payer_name' => $data['payer_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => now(),
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ]);

            $paid = round((float) $invoice->amount_paid + $amount, 2);
            $total = round((float) $invoice->total, 2);
            $due = max(0, round($total - $paid, 2));

            $status = match (true) {
                $due <= 0.009 => InvoiceStatus::Paid,
                $paid > 0 => InvoiceStatus::PartiallyPaid,
                default => $invoice->statusEnum(),
            };

            $invoice->update([
                'amount_paid' => number_format($paid, 2, '.', ''),
                'amount_due' => number_format($due, 2, '.', ''),
                'status' => $status,
            ]);

            $this->recordEvent($invoice, $actor, 'payment_recorded', [
                'amount' => number_format($amount, 2, '.', ''),
                'status' => $status->value,
            ]);

            return $this->load($invoice->fresh());
        });
    }

    public function markOverdue(): int
    {
        $count = 0;

        Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value,
            ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$count): void {
                foreach ($invoices as $invoice) {
                    $invoice->update(['status' => InvoiceStatus::Overdue]);
                    $this->recordEvent($invoice, null, 'overdue');
                    $count++;
                }
            });

        return $count;
    }

    public function loadForStaff(User $actor, Invoice $invoice): Invoice
    {
        $this->assertCanView($actor);

        return $this->load($invoice);
    }

    public function loadForCustomer(User $customer, Invoice $invoice): Invoice
    {
        if (! $invoice->belongsToCustomer($customer) || ! $invoice->statusEnum()->isVisibleToCustomer()) {
            abort(404);
        }

        return $this->load($invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function staffPayload(Invoice $invoice): array
    {
        $invoice = $this->load($invoice);

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->statusEnum()->value,
            'status_label' => $invoice->statusEnum()->labelAr(),
            'currency' => $invoice->currency,
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'email' => $invoice->customer->email,
            ] : null,
            'company' => $invoice->company ? [
                'id' => $invoice->company->id,
                'name' => $invoice->company->name,
            ] : null,
            'commercial_quotation_id' => $invoice->commercial_quotation_id,
            'order_id' => $invoice->order_id,
            'project_id' => $invoice->project_id,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'subtotal' => (string) $invoice->subtotal,
            'discount_amount' => (string) $invoice->discount_amount,
            'tax_amount' => (string) $invoice->tax_amount,
            'total' => (string) $invoice->total,
            'amount_paid' => (string) $invoice->amount_paid,
            'amount_due' => (string) $invoice->amount_due,
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'internal_notes' => $invoice->internal_notes,
            'issued_at' => $invoice->issued_at?->toIso8601String(),
            'sent_at' => $invoice->sent_at?->toIso8601String(),
            'cancelled_at' => $invoice->cancelled_at?->toIso8601String(),
            'voided_at' => $invoice->voided_at?->toIso8601String(),
            'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                'id' => $item->id,
                'service_id' => $item->service_id,
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount_amount' => (string) $item->discount_amount,
                'tax_amount' => (string) $item->tax_amount,
                'line_total' => (string) $item->line_total,
                'sort_order' => $item->sort_order,
            ])->all(),
            'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method?->value,
                'status' => $payment->status?->value,
                'reference_number' => $payment->reference_number,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->all(),
            'events' => $invoice->events->take(50)->map(fn (InvoiceEvent $event): array => [
                'id' => $event->id,
                'event' => $event->event,
                'meta' => $event->meta,
                'actor_id' => $event->actor_id,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->all(),
            'created_at' => $invoice->created_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Customer-safe payload — never includes internal notes, supplier, or cost fields.
     *
     * @return array<string, mixed>
     */
    public function customerPayload(Invoice $invoice): array
    {
        $staff = $this->staffPayload($invoice);

        unset(
            $staff['internal_notes'],
            $staff['events'],
            $staff['project_id'],
        );

        return $staff;
    }

    public function renderPdf(Invoice $invoice, string $format = 'pdf'): Response|string
    {
        $payload = $this->customerPayload($invoice);
        $html = $this->renderHtml($payload);

        if ($format !== 'html' && class_exists(Pdf::class)) {
            try {
                return Pdf::loadHTML($html)->download($invoice->number.'.pdf');
            } catch (\Throwable) {
                // fall through to HTML
            }
        }

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $index => $row) {
            $qty = round((float) ($row['quantity'] ?? 0), 2);
            $unit = round((float) ($row['unit_price'] ?? 0), 2);
            $discount = round((float) ($row['discount_amount'] ?? 0), 2);
            $tax = round((float) ($row['tax_amount'] ?? 0), 2);
            $line = max(0, round(($qty * $unit) - $discount + $tax, 2));

            $invoice->items()->create([
                'service_id' => $row['service_id'] ?? null,
                'description' => (string) ($row['description'] ?? ''),
                'quantity' => number_format($qty, 2, '.', ''),
                'unit_price' => number_format($unit, 2, '.', ''),
                'discount_amount' => number_format($discount, 2, '.', ''),
                'tax_amount' => number_format($tax, 2, '.', ''),
                'line_total' => number_format($line, 2, '.', ''),
                'sort_order' => $index,
            ]);
        }
    }

    private function recalculate(Invoice $invoice): void
    {
        $invoice->load('items');
        $subtotal = round((float) $invoice->items->sum(fn (InvoiceItem $item) => (float) $item->quantity * (float) $item->unit_price), 2);
        $discount = round((float) $invoice->items->sum('discount_amount'), 2);
        $tax = round((float) $invoice->items->sum('tax_amount'), 2);
        $total = max(0, round($subtotal - $discount + $tax, 2));
        $paid = round((float) $invoice->amount_paid, 2);
        $due = max(0, round($total - $paid, 2));

        $invoice->update([
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_amount' => number_format($discount, 2, '.', ''),
            'tax_amount' => number_format($tax, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'amount_due' => number_format($due, 2, '.', ''),
        ]);
    }

    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'INV-'.$year.'-';

        $latest = Invoice::query()
            ->withTrashed()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function recordEvent(Invoice $invoice, ?User $actor, string $event, ?array $meta = null): void
    {
        $invoice->events()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'meta' => $meta,
        ]);
    }

    private function load(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'customer:id,name,email,phone',
            'company:id,name',
            'items',
            'payments',
            'events' => fn ($q) => $q->limit(80),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderHtml(array $payload): string
    {
        $app = e((string) config('app.name', 'حبر وأبعاد'));
        $number = e((string) $payload['number']);
        $customer = e((string) ($payload['customer']['name'] ?? ''));
        $total = e((string) $payload['total']);
        $currency = e((string) $payload['currency']);
        $due = e((string) ($payload['due_date'] ?? ''));
        $rows = '';
        foreach ($payload['items'] as $item) {
            $rows .= '<tr>'
                .'<td>'.e((string) $item['description']).'</td>'
                .'<td>'.e((string) $item['quantity']).'</td>'
                .'<td>'.e((string) $item['unit_price']).'</td>'
                .'<td>'.e((string) $item['line_total']).'</td>'
                .'</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>فاتورة {$number}</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;color:#222">
  <h1>{$app}</h1>
  <h2>فاتورة {$number}</h2>
  <p>العميل: {$customer}</p>
  <p>تاريخ الاستحقاق: {$due}</p>
  <table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead><tr><th>الوصف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
  <p><strong>الإجمالي: {$total} {$currency}</strong></p>
</body>
</html>
HTML;
    }

    private function assertCanView(User $actor): void
    {
        if ($actor->role?->canViewInvoices() !== true) {
            abort(403);
        }
    }

    private function assertCanManage(User $actor): void
    {
        if ($actor->role?->canManageInvoices() !== true) {
            abort(403);
        }
    }

    private function assertCanIssue(User $actor): void
    {
        if ($actor->role?->canIssueInvoices() !== true) {
            abort(403);
        }
    }

    private function assertCanCancel(User $actor): void
    {
        if ($actor->role?->canCancelInvoices() !== true) {
            abort(403);
        }
    }
}
