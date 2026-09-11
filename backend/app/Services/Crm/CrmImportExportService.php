<?php

namespace App\Services\Crm;

use App\Enums\CrmLeadPriority;
use App\Enums\CrmLeadStatus;
use App\Enums\UserRole;
use App\Models\CrmCompany;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmOpportunity;
use App\Models\CrmPipelineStage;
use App\Models\CrmQuotation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmImportExportService
{
    public function __construct(private readonly CrmLeadService $leads) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array{created: int, duplicates: list<array<string, mixed>>, leads: list<CrmLead>}
     */
    public function importLeads(User $actor, UploadedFile $file, array $options = []): array
    {
        $rows = $this->readRows($file);
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => ['Import file is empty.']]);
        }

        $mapping = $options['mapping'] ?? [
            'full_name' => 'full_name',
            'email' => 'email',
            'phone' => 'phone',
            'company_name' => 'company_name',
            'whatsapp' => 'whatsapp',
            'notes' => 'notes',
        ];

        $created = [];
        $duplicates = [];
        $stage = CrmPipelineStage::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

        DB::transaction(function () use ($actor, $rows, $mapping, $stage, &$created, &$duplicates): void {
            foreach ($rows as $index => $row) {
                $data = [
                    'full_name' => $this->mapped($row, $mapping, 'full_name'),
                    'email' => $this->mapped($row, $mapping, 'email'),
                    'phone' => $this->mapped($row, $mapping, 'phone'),
                    'company_name' => $this->mapped($row, $mapping, 'company_name'),
                    'whatsapp' => $this->mapped($row, $mapping, 'whatsapp'),
                    'notes' => $this->mapped($row, $mapping, 'notes'),
                ];

                if (! is_string($data['full_name']) || trim($data['full_name']) === '') {
                    continue;
                }

                $dupes = $this->leads->findDuplicates(
                    is_string($data['phone']) ? $data['phone'] : null,
                    is_string($data['email']) ? $data['email'] : null,
                    is_string($data['whatsapp']) ? $data['whatsapp'] : null,
                );

                if ($dupes->isNotEmpty()) {
                    $duplicates[] = [
                        'row' => $index + 2,
                        'full_name' => $data['full_name'],
                        'matches' => $dupes->pluck('reference')->all(),
                    ];

                    continue;
                }

                $created[] = $this->leads->createLead($actor, [
                    ...$data,
                    'stage_id' => $stage->id,
                    'status' => CrmLeadStatus::New->value,
                    'priority' => CrmLeadPriority::Medium->value,
                ]);
            }
        });

        return [
            'created' => count($created),
            'duplicates' => $duplicates,
            'leads' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(User $actor, string $entity, string $format = 'csv', array $filters = []): StreamedResponse
    {
        $rows = match ($entity) {
            'leads' => $this->exportLeads($actor, $filters),
            'opportunities' => $this->exportOpportunities($actor, $filters),
            'companies' => $this->exportCompanies($actor, $filters),
            'contacts' => $this->exportContacts($actor, $filters),
            'quotations' => $this->exportQuotations($actor, $filters),
            default => throw ValidationException::withMessages(['entity' => ['Unsupported export entity.']]),
        };

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($rows === []) {
            $sheet->fromArray([['message'], ['No records']], null, 'A1');
        } else {
            $headers = array_keys($rows[0]);
            $sheet->fromArray([$headers], null, 'A1');
            $sheet->fromArray(array_map('array_values', $rows), null, 'A2');
        }

        $filename = 'crm-'.$entity.'-'.now()->format('Ymd-His').'.'.($format === 'xlsx' ? 'xlsx' : 'csv');
        $writer = $format === 'xlsx' ? new Xlsx($spreadsheet) : new CsvWriter($spreadsheet);
        $contentType = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $contentType]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function exportLeads(User $actor, array $filters): array
    {
        $query = CrmLead::query()->with(['assignee:id,name', 'stage:id,name'])->whereNull('archived_at');

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('assigned_to', $actor->id);
        } elseif (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->limit(5000)->get()->map(fn (CrmLead $lead): array => [
            'reference' => $lead->reference,
            'full_name' => $lead->full_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company_name' => $lead->company_name,
            'status' => $lead->status instanceof \BackedEnum ? $lead->status->value : $lead->status,
            'stage' => $lead->stage?->name,
            'assignee' => $lead->assignee?->name,
            'deal_value' => $lead->deal_value,
            'created_at' => $lead->created_at?->toDateTimeString(),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function exportOpportunities(User $actor, array $filters): array
    {
        $query = CrmOpportunity::query()->whereNull('archived_at');
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('assigned_to', $actor->id);
        }

        return $query->latest()->limit(5000)->get()->map(fn (CrmOpportunity $item): array => [
            'reference' => $item->reference,
            'name' => $item->name,
            'deal_value' => $item->deal_value,
            'probability' => $item->probability,
            'expected_close_at' => $item->expected_close_at?->toDateString(),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function exportCompanies(User $actor, array $filters): array
    {
        $query = CrmCompany::query()->where('status', '!=', 'deleted');
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where('assigned_to', $actor->id);
        }

        return $query->latest()->limit(5000)->get()->map(fn (CrmCompany $item): array => [
            'name' => $item->name,
            'email' => $item->email,
            'phone' => $item->phone,
            'city' => $item->city,
            'status' => $item->status,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function exportContacts(User $actor, array $filters): array
    {
        $query = CrmContact::query()->with('company:id,name,assigned_to');
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->whereHas('company', fn ($q) => $q->where('assigned_to', $actor->id));
        }

        return $query->latest()->limit(5000)->get()->map(fn (CrmContact $item): array => [
            'name' => $item->name,
            'email' => $item->email,
            'phone' => $item->phone,
            'company' => $item->company?->name,
            'job_title' => $item->job_title,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function exportQuotations(User $actor, array $filters): array
    {
        $query = CrmQuotation::query()->with('lead:id,reference,assigned_to');
        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->where(function ($inner) use ($actor): void {
                $inner->where('created_by', $actor->id)
                    ->orWhereHas('lead', fn ($leads) => $leads->where('assigned_to', $actor->id));
            });
        }

        return $query->latest()->limit(5000)->get()->map(fn (CrmQuotation $item): array => [
            'number' => $item->number,
            'status' => $item->status instanceof \BackedEnum ? $item->status->value : $item->status,
            'total' => $item->total,
            'lead' => $item->lead?->reference,
            'created_at' => $item->created_at?->toDateTimeString(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw ValidationException::withMessages(['file' => ['Unable to read upload.']]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls', 'csv'], true) && class_exists(IOFactory::class)) {
            $spreadsheet = IOFactory::load($path);
            $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            if ($sheetRows === []) {
                return [];
            }

            $headers = array_map(fn ($h) => Str::snake(trim((string) $h)), array_shift($sheetRows) ?? []);
            $rows = [];
            foreach ($sheetRows as $row) {
                if ($this->rowEmpty($row)) {
                    continue;
                }
                $assoc = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $assoc[$header] = $row[$i] ?? null;
                }
                $rows[] = $assoc;
            }

            return $rows;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['Unable to open CSV.']]);
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $headers = array_map(fn ($h) => Str::snake(trim((string) $h)), $headers);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($this->rowEmpty($row)) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$i] ?? null;
            }
            $rows[] = $assoc;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $mapping
     */
    private function mapped(array $row, array $mapping, string $field): mixed
    {
        $key = $mapping[$field] ?? $field;

        return $row[$key] ?? null;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
