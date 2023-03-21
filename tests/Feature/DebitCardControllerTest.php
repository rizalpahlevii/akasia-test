<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User model.
     *
     * @var User
     */
    protected User $user;

    /**
     * Test customer can see a list of debit cards.
     *
     * @return void
     */
    public function testCustomerCanSeeAListOfDebitCards()
    {
        // get /debit-cards
        $user = $this->user;
        DebitCard::factory()->count(5)->create([
            'user_id' => $user->id,
        ]);
        $response = $this->getJson('/api/debit-cards');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ],
        ]);

        $response->assertJsonCount(
            $user->debitCards()->active()->count(),
            $key = null
        );
    }

    /**
     * Test customer cannot see a list of debit cards of other customers.
     *
     * @return void
     */
    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // get /debit-cards
        $user1 = $this->user;
        $user2 = User::factory()->create();
        $user1DebitCards = DebitCard::factory()->count(2)->active()->create([
            'user_id' => $user1->id,
        ]);
        $user2DebitCards = DebitCard::factory()->count(3)->active()->create([
            'user_id' => $user2->id,
        ]);

        $response = $this->getJson('/api/debit-cards');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'number',
                'type',
                'expiration_date',
                'is_active',
            ],
        ]);

        $response->assertSee($user1DebitCards->pluck('number')->toArray());
        $response->assertDontSee($user2DebitCards->pluck('number')->toArray());
    }

    /**
     * Test customer can create a debit card.
     *
     * @return void
     */
    public function testCustomerCanCreateADebitCard()
    {
        // post /debit-cards
        $user = $this->user;
        $response = $this->postJson('/api/debit-cards', [
            'type' => 'VISA',
        ]);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure([
            'id',
            'number',
            'type',
            'expiration_date',
            'is_active',
        ]);
        $this->assertDatabaseHas('debit_cards', [
            'user_id' => $user->id,
            'number' => $response->json('number'),
            'type' => 'VISA',
        ]);
    }

    /**
     * Test customer can see a single debit card details.
     *
     * @return void
     */
    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $user = $this->user;
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id,
        ]);
        // get api/debit-cards/{debitCard}
        $response = $this->getJson('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'id',
            'number',
            'type',
            'expiration_date',
            'is_active',
        ]);
        $response->assertSee($debitCard->number);
    }

    /**
     * Test customer cannot see a single debit card details
     *
     * @return void
     */
    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        // get api/debit-cards/{debitCard}
        $user = $this->user;
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/debit-cards/' . $debitCard->id . '-error');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test customer can activate a debit card.
     *
     * @return void
     */
    public function testCustomerCanActivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $user = $this->user;
        $debitCard = DebitCard::factory()->expired()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'id',
            'number',
            'type',
            'expiration_date',
            'is_active',
        ]);
        $response->assertJson([
            'is_active' => true,
        ]);
        $this->assertTrue($debitCard->fresh()->is_active);
    }

    /**
     * Test customer can deactivate a debit card.
     *
     * @return void
     */
    public function testCustomerCanDeactivateADebitCard()
    {
        // put api/debit-cards/{debitCard}
        $user = $this->user;
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => false,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'id',
            'number',
            'type',
            'expiration_date',
            'is_active',
        ]);
        $response->assertJson([
            'is_active' => false,
        ]);
    }

    /**
     * Test customer cannot update a debit card with wrong validation.
     *
     * @return void
     */
    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // put api/debit-cards/{debitCard}
        $user = $this->user;
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->putJson('/api/debit-cards/' . $debitCard->id, [
            'is_active' => 'error',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'is_active',
            ],
        ]);

        $response->assertJson([
            'errors' => [
                'is_active' => [
                    'The is active field must be true or false.',
                ],
            ],
        ]);
    }

    /**
     * Test customer can delete a debit card.
     *
     * @return void
     */
    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
        $user = $this->user;
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertNotNull($debitCard->fresh()->deleted_at);

    }

    /**
     * Test customer cannot delete a debit card with transaction.
     *
     * @return void
     */
    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
        $user = $this->user;
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $user->id,
        ]);
        $transactions = DebitCardTransaction::factory()->count(10)->create([
            'debit_card_id' => $debitCard->id,
        ]);

        $response = $this->deleteJson('/api/debit-cards/' . $debitCard->id);
        $response->assertStatus(Response::HTTP_FORBIDDEN);

    }

    // Extra bonus for extra tests :)

    /**
     * Set up the test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }
}
