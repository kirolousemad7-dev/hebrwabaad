<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmCompany;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CrmCompanyService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmCompany>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmCompany::query()
            ->with(['assignee:id,name,email', 'source:id,name,slug'])
            ->withCount(['contacts', 'leads', 'opportunities']);

        $this->scopeVisibleTo($query, $actor);

        if (($filters['status'] ?? null) !== 'all') {
            $query->where('status', $filters['status'] ?? 'active');
        }

        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('city', 'like', $term);
            });
        }

        return $query->latest()->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 50)));
    }

    public function load(CrmCompany $company): CrmCompany
    {
        return $company->load([
            'assignee:id,name,email',
            'source:id,name,slug',
            'contacts',
            'leads:id,company_id,reference,full_name,status,deal_value,assigned_to',
            'opportunities:id,company_id,reference,name,deal_value,probability',
        ])->loadCount(['contacts', 'leads', 'opportunities']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): CrmCompany
    {
        $company = CrmCompany::query()->create([
            'name' => $data['name'],
            'industry' => $data['industry'] ?? null,
            'website' => $data['website'] ?? null,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'company_size' => $data['company_size'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? ($actor->role === UserRole::SalesRepresentative ? $actor->id : null),
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);

        return $this->load($company);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, CrmCompany $company, array $data): CrmCompany
    {
        $this->assertVisible($actor, $company);
        $company->fill(collect($data)->only([
            'name', 'industry', 'website', 'country', 'city', 'address',
            'phone', 'email', 'company_size', 'source_id', 'assigned_to', 'notes', 'status',
        ])->all());
        $company->save();

        return $this->load($company->fresh() ?? $company);
    }

    public function archive(User $actor, CrmCompany $company): CrmCompany
    {
        $this->assertVisible($actor, $company);
        $company->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        return $this->load($company->fresh() ?? $company);
    }

    public function assertVisible(User $actor, CrmCompany $company): CrmCompany
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return $this->load($company);
        }

        if ((int) $company->assigned_to === (int) $actor->id) {
            return $this->load($company);
        }

        throw new AuthorizationException('You do not have access to this company.');
    }

    /**
     * @param  Builder<CrmCompany>  $query
     */
    private function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($actor->role instanceof UserRole && $actor->role->canManageCrmTeam()) {
            return;
        }

        $query->where('assigned_to', $actor->id);
    }
}
