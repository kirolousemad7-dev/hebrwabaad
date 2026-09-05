<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public const MESSAGE_MAX_LENGTH = 5000;

    /**
     * @return list<string>
     */
    public function eagerLoad(): array
    {
        return ['customer', 'assignee', 'order', 'project', 'latestMessage.sender'];
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function forCustomer(User $customer): Collection
    {
        return Conversation::query()
            ->where('customer_id', $customer->id)
            ->with($this->eagerLoad())
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        $query = Conversation::query()->with($this->eagerLoad());
        $this->scopeVisibleTo($query, $user);
        $this->applyFilters($query, $filters);

        return $query->paginate($this->perPage($filters));
    }

    public function load(Conversation $conversation): Conversation
    {
        return $conversation->load($this->eagerLoad());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForCustomer(User $customer, array $attributes): Conversation
    {
        $order = $this->assertOwnedOrder($customer, $attributes['order_id'] ?? null);
        $project = $this->assertOwnedProject($customer, $attributes['project_id'] ?? null);

        $existing = $this->existingOpenThread($customer, $order, $project);
        if ($existing !== null) {
            $body = $this->normalizeBody($attributes['message'] ?? null);
            if ($body !== null && $existing->allowsMessages()) {
                $this->storeMessage($existing, $customer, $body);
                app(PlatformNotifier::class)->supportMessagePosted($existing->fresh(), $customer);
            }

            return $this->load($existing->fresh());
        }

        $subject = trim((string) ($attributes['subject'] ?? ''));
        if ($subject === '') {
            $subject = $this->defaultSubject($order, $project);
        }

        $conversation = Conversation::query()->create([
            'reference' => 'TMP-'.Str::ulid(),
            'subject' => $subject,
            'status' => ConversationStatus::Open,
            'customer_id' => $customer->id,
            'assigned_to' => $order?->account_manager_id ?? $project?->account_manager_id,
            'order_id' => $order?->id,
            'project_id' => $project?->id,
            'last_message_at' => now(),
        ]);

        $conversation->update([
            'reference' => sprintf('HEBR-CS-%06d', $conversation->id),
        ]);

        $body = $this->normalizeBody($attributes['message'] ?? null);
        if ($body !== null) {
            $this->storeMessage($conversation, $customer, $body);
            app(PlatformNotifier::class)->supportMessagePosted($conversation->fresh(), $customer);
        }

        return $this->load($conversation->refresh());
    }

    /**
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginateMessages(Conversation $conversation, array $filters): LengthAwarePaginator
    {
        $perPage = $this->messagePerPage($filters);
        $query = $conversation->messages()->with('sender')->orderBy('id');
        $requested = isset($filters['page']) ? (int) $filters['page'] : null;

        if ($requested === null || $requested < 1) {
            $total = (clone $query)->toBase()->getCountForPagination();
            $requested = max(1, (int) ceil($total / $perPage));
        }

        return $query->paginate($perPage, ['*'], 'page', $requested);
    }

    public function addMessage(User $actor, Conversation $conversation, string $body): Conversation
    {
        $normalized = $this->normalizeBody($body);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'message' => ['Message cannot be empty.'],
            ]);
        }

        if (! $conversation->allowsMessages()) {
            throw ValidationException::withMessages([
                'message' => ['This conversation is closed.'],
            ]);
        }

        $this->storeMessage($conversation, $actor, $normalized);
        app(PlatformNotifier::class)->supportMessagePosted($conversation, $actor);

        if (
            $actor->role instanceof UserRole
            && $actor->role->canManageSupport()
            && $conversation->statusEnum() === ConversationStatus::Open
        ) {
            $conversation->update(['status' => ConversationStatus::InProgress]);
        }

        if (
            $actor->role === UserRole::AccountManager
            && $conversation->assigned_to === null
        ) {
            $conversation->update(['assigned_to' => $actor->id]);
        }

        return $this->load($conversation->fresh());
    }

    public function transition(User $actor, Conversation $conversation, ConversationStatus $next): Conversation
    {
        $current = $conversation->statusEnum();

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ['This conversation cannot move to the requested status.'],
            ]);
        }

        $conversation->update(['status' => $next]);

        return $this->load($conversation->fresh());
    }

    private function storeMessage(Conversation $conversation, User $actor, string $body): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $actor->id,
            'body' => $body,
        ]);

        $conversation->update(['last_message_at' => $message->created_at ?? now()]);

        return $message;
    }

    private function existingOpenThread(User $customer, ?Order $order, ?Project $project): ?Conversation
    {
        $query = Conversation::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', ConversationStatus::Closed->value);

        if ($order !== null) {
            return $query->where('order_id', $order->id)->latest('id')->first();
        }

        if ($project !== null) {
            return $query->where('project_id', $project->id)->whereNull('order_id')->latest('id')->first();
        }

        return null;
    }

    private function defaultSubject(?Order $order, ?Project $project): string
    {
        if ($order !== null) {
            return 'استفسار عن الطلب '.$order->reference;
        }

        if ($project !== null) {
            return 'استفسار عن المشروع '.$project->title;
        }

        return 'طلب دعم';
    }

    private function assertOwnedOrder(User $customer, mixed $orderId): ?Order
    {
        if ($orderId === null || $orderId === '') {
            return null;
        }

        $order = Order::query()->find((int) $orderId);

        if ($order === null || $order->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'order_id' => ['Selected order is not available.'],
            ]);
        }

        return $order;
    }

    private function assertOwnedProject(User $customer, mixed $projectId): ?Project
    {
        if ($projectId === null || $projectId === '') {
            return null;
        }

        $project = Project::query()->find((int) $projectId);

        if ($project === null || $project->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'project_id' => ['Selected project is not available.'],
            ]);
        }

        return $project;
    }

    public function normalizeBody(mixed $body): ?string
    {
        if (! is_string($body)) {
            return null;
        }

        $withoutBlocks = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $body) ?? $body;
        $normalized = trim(strip_tags($withoutBlocks));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    private function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === UserRole::Owner) {
            return;
        }

        $query->where(function (Builder $inner) use ($user): void {
            $inner->where('assigned_to', $user->id)
                ->orWhereHas('order', fn (Builder $order) => $order->where('account_manager_id', $user->id))
                ->orWhereHas('project', fn (Builder $project) => $project->where('account_manager_id', $user->id));
        });
    }

    /**
     * @param  Builder<Conversation>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('reference', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            });
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, array_column(ConversationStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $query->orderByDesc('last_message_at')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 15), 50));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function messagePerPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 50), 100));
    }
}
