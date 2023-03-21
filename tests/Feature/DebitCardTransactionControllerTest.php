<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // get /debit-card-transactions
        $user = $this->user;
        $debitCard = $this->debitCard;
        DebitCardTransaction::factory()->count(5)->create([
            'debit_card_id' => $debitCard->id,
        ]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $debitCard->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'amount',
                'currency_code'
            ],
        ]);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // get /debit-card-transactions
        $user = $this->user;
        $debitCard = $this->debitCard;
        $transactions = DebitCardTransaction::factory()->count(5)->create([
            'debit_card_id' => $debitCard->id,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ]);

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        $otherTransactions = DebitCardTransaction::factory()->count(5)->create([
            'debit_card_id' => $otherDebitCard->id,
            'currency_code' => DebitCardTransaction::CURRENCY_SGD
        ]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $debitCard->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertSee('IDR');
        $response->assertDontSee('SGD');
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // post /debit-card-transactions
        $user = $this->user;
        $debitCard = $this->debitCard;

        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 8888,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure([
            'amount',
            'currency_code'
        ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 8888,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // post /debit-card-transactions
        $user = $this->user;
        $debitCard = $this->debitCard;

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 8888,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $otherDebitCard->id,
            'amount' => 8888,
            'currency_code' => DebitCardTransaction::CURRENCY_IDR
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $user = $this->user;
        $debitCard = $this->debitCard;
        $transaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $debitCard->id,
        ]);

        $response = $this->getJson('/api/debit-card-transactions/' . $transaction->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'amount',
            'currency_code'
        ]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // get /debit-card-transactions/{debitCardTransaction}
        $user = $this->user;
        $debitCard = $this->debitCard;

        $otherUser = User::factory()->create();
        $otherDebitCard = DebitCard::factory()->create([
            'user_id' => $otherUser->id
        ]);
        $otherTransaction = DebitCardTransaction::factory()->create([
            'debit_card_id' => $otherDebitCard->id,
        ]);

        $response = $this->getJson('/api/debit-card-transactions/' . $otherTransaction->id);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
        
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    // Extra bonus for extra tests :)
}
