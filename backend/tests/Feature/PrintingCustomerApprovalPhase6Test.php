<?php

namespace Tests\Feature;

use App\Enums\PrintingCustomerApprovalStatus;
use App\Enums\PrintingCustomerApprovalType;
use App\Enums\PrintingPaymentPolicy;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Models\PrintingCustomerApproval;
use App\Models\PrintingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingCustomerApprovalPhase6Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    /**
     * @return array{specialist: User, request: PrintingRequest}
     */
    private function requestWithAcceptedNonePolicy(): array
    {
        $specialist = User::factory()->printingSpecialist()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'pricing_type' => PrintingPricingType::QuoteReady,
            'quoted_price' => '400.00',
            'status' => PrintingRequestStatus::Pending,
            'assigned_to' => $specialist->id,
        ]);

        $id = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations', [
                'printing_request_id' => $request->id,
                'payment_policy' => PrintingPaymentPolicy::None->value,
            ])
            ->json('data.id');

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/'.$id.'/send')
            ->json('data.public_token');

        $this->postJson('/api/public/printing-quotations/'.$token.'/accept')->assertOk();

        return compact('specialist', 'request');
    }

    public function test_pending_checkpoint_blocks_eligibility_and_in_progress(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->requestWithAcceptedNonePolicy();

        $created = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/request/'.$request->id.'/approvals', [
                'type' => PrintingCustomerApprovalType::Design->value,
                'title' => 'اعتماد التصميم',
            ])
            ->assertCreated();

        $publicToken = $created->json('data.public_token');
        $this->assertIsString($publicToken);

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonFragment(['customer_approvals_pending']);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->postJson('/api/public/printing-approvals/'.$publicToken.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', PrintingCustomerApprovalStatus::Approved->value);

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', true);

        $this->asUser($specialist)
            ->postJson('/api/operations/printing/'.$request->id.'/status', [
                'status' => PrintingRequestStatus::InProgress->value,
            ])
            ->assertOk();
    }

    public function test_reject_checkpoint_blocks_eligibility(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->requestWithAcceptedNonePolicy();

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/request/'.$request->id.'/approvals', [
                'type' => PrintingCustomerApprovalType::Final->value,
                'title' => 'اعتماد نهائي',
            ])
            ->json('data.public_token');

        $this->postJson('/api/public/printing-approvals/'.$token.'/reject', [
            'notes' => 'Wrong size',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingCustomerApprovalStatus::Rejected->value);

        $this->assertDatabaseHas('printing_customer_approvals', [
            'printing_request_id' => $request->id,
            'status' => PrintingCustomerApprovalStatus::Rejected->value,
        ]);

        $this->asUser($specialist)
            ->getJson('/api/operations/printing-quotations/request/'.$request->id.'/eligibility')
            ->assertOk()
            ->assertJsonPath('data.eligible', false)
            ->assertJsonFragment(['customer_approvals_incomplete']);
    }

    public function test_invalid_approval_token(): void
    {
        $this->getJson('/api/public/printing-approvals/'.str_repeat('x', 64))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_token_hash_is_not_stored_raw(): void
    {
        ['specialist' => $specialist, 'request' => $request] = $this->requestWithAcceptedNonePolicy();

        $token = $this->asUser($specialist)
            ->postJson('/api/operations/printing-quotations/request/'.$request->id.'/approvals', [
                'type' => PrintingCustomerApprovalType::Size->value,
                'title' => 'اعتماد المقاس',
            ])
            ->json('data.public_token');

        $this->assertDatabaseMissing('printing_customer_approvals', [
            'public_token_hash' => $token,
        ]);
        $this->assertDatabaseHas('printing_customer_approvals', [
            'printing_request_id' => $request->id,
            'public_token_hash' => PrintingCustomerApproval::hashToken($token),
        ]);
    }
}
