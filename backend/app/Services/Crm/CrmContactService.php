<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmCompany;
use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CrmContactService
{
    public function __construct(private readonly CrmCompanyService $companies) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmContact>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmContact::query()->with([
            'company:id,name,assigned_to,status',
            'lead:id,reference,full_name',
            'customer:id,name,email',
        ]);

        if (! ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam())) {
            $query->whereHas('company', fn (Builder $companies) => $companies->where('assigned_to', $actor->id));
        }

        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('whatsapp', 'like', $term);
            });
        }

        if (! empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }

        return $query->latest()->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): CrmContact
    {
        $company = CrmCompany::query()->findOrFail((int) $data['company_id']);
        $this->companies->assertVisible($actor, $company);

        if (($data['is_primary'] ?? false) === true) {
            CrmContact::query()->where('company_id', $company->id)->update(['is_primary' => false]);
        }

        $contact = CrmContact::query()->create([
            'company_id' => $company->id,
            'lead_id' => $data['lead_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        return $contact->load(['company', 'lead', 'customer']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, CrmContact $contact, array $data): CrmContact
    {
        $contact->loadMissing('company');
        if ($contact->company === null) {
            throw ValidationException::withMessages(['contact' => ['Contact company is missing.']]);
        }
        $this->companies->assertVisible($actor, $contact->company);

        if (($data['is_primary'] ?? false) === true) {
            CrmContact::query()
                ->where('company_id', $contact->company_id)
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->fill(collect($data)->only([
            'lead_id', 'customer_id', 'name', 'phone', 'whatsapp', 'email',
            'job_title', 'department', 'is_primary', 'notes',
        ])->all());
        $contact->save();

        return $contact->fresh(['company', 'lead', 'customer']) ?? $contact;
    }

    public function assertVisible(User $actor, CrmContact $contact): CrmContact
    {
        $contact->loadMissing('company');
        if ($contact->company === null) {
            throw new AuthorizationException('You do not have access to this contact.');
        }

        $this->companies->assertVisible($actor, $contact->company);

        return $contact->load(['company', 'lead', 'customer']);
    }
}
