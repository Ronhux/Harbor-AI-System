<?php

namespace Tests\Feature;

use App\Models\FcaRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public FCA association search.
 *
 * A prospective applicant has no account yet, so the lookup they need must work before login.
 * At the same time this endpoint is the only FCA route reachable anonymously, so it is locked
 * down to the three fields a search result actually needs and tested to leak nothing else.
 */
class FcaPublicAssociationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_reads_registry_without_workflow_or_reviewer_assignment(): void
    {
        config(['mao_workflows.fca_applications_enabled' => false, 'mao_workflows.fca_reviewer_user_ids' => []]);
        $admin = \App\Models\User::create(['first_name'=>'Registry','last_name'=>'Admin','email'=>'registry-admin@test.local','password_hash'=>bcrypt('test-only'),'user_type'=>'Admin','is_active'=>true]);
        $this->actingAs($admin, 'sanctum')->getJson('/api/fca/registry-records?limit=50')
            ->assertOk()->assertJsonPath('count',4)->assertJsonPath('verified_count',0);
        $this->postJson('/api/fca/review/1', ['decision'=>'approve'])->assertNotFound();
        $admin->forceFill(['is_active'=>false])->save();
        $this->actingAs($admin->fresh(), 'sanctum')->getJson('/api/fca/registry-records')->assertForbidden();
    }


    protected function setUp(): void
    {
        parent::setUp();

        $rows = [
            [1, 'Binalan Agricultural Cooperative', 'Binalan, Aparri, Cagayan', 'Proceso Eslabon'],
            [2, 'Binalan Communal Irrigators Association', 'Binalan, Aparri, Cagayan', 'Jomelito Ragudo'],
            [3, 'Sanja Ybanag Farmers Association', 'Sanja, Aparri, Cagayan', 'Rogelio Pichay'],
            [4, 'Northern Aparri Irrigators Association', 'Northern, Aparri, Cagayan', 'Felix Maruzzo'],
        ];

        // FcaRegistry is deliberately fully guarded, so the fixture writes through the query
        // builder rather than relaxing the model.
        foreach ($rows as $row) {
            \Illuminate\Support\Facades\DB::table('fca_registry')->insert([
                'source_record_key' => 'test-dir-' . $row[0],
                'official_name' => $row[1],
                'address' => $row[2],
                'president_name' => $row[3],
                'registry_source' => 'test source',
                'directory_verification_status' => 'pending',
                'procurement_eligibility_status' => 'not_assessed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** THE CORE FIX: a visitor with no account must be able to search. */
    public function test_search_works_without_an_account(): void
    {
        $response = $this->getJson('/api/fca/associations?q=Binalan');

        $response->assertOk();
        $this->assertSame(2, $response->json('count'));
        $response->assertJsonPath('data.0.official_name', 'Binalan Agricultural Cooperative');
    }

    public function test_initial_suggestions_are_returned_with_no_term(): void
    {
        $this->getJson('/api/fca/associations')
            ->assertOk()
            ->assertJsonPath('count', 4);
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->getJson('/api/fca/associations?q=SANJA')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.id', 3);
    }

    public function test_search_matches_the_address(): void
    {
        $this->getJson('/api/fca/associations?q=Northern')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.official_name', 'Northern Aparri Irrigators Association');
    }

    public function test_partial_names_match(): void
    {
        $this->getJson('/api/fca/associations?q=nanappatan')
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->getJson('/api/fca/associations?q=Communal')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_an_unmatched_term_returns_an_empty_list_not_an_error(): void
    {
        $this->getJson('/api/fca/associations?q=zzzznomatch')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);
    }

    /** Only identifier, name and address may leave the server anonymously. */
    public function test_the_public_payload_exposes_no_other_field(): void
    {
        $response = $this->getJson('/api/fca/associations?q=Sanja')->assertOk();
        $row = $response->json('data.0');

        $this->assertSame(['id', 'official_name', 'address'], array_keys($row));

        $body = $response->getContent();
        foreach ([
            'president_name', 'Rogelio Pichay',
            'contact_number',
            'directory_verification_status',
            'procurement_eligibility_status',
            'source_record_key',
            'registry_source',
            'procurement_evidence_reference',
            'last_verified_at',
            'remarks',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, (string) $body, $forbidden . ' must not be exposed');
        }
    }

    /** A search must never be able to widen the match with a LIKE wildcard or an injection. */
    public function test_wildcards_and_injection_attempts_do_not_widen_the_match(): void
    {
        foreach (['%', '_', "x' OR 1=1 -- ", '%%'] as $hostile) {
            $response = $this->getJson('/api/fca/associations?q=' . urlencode($hostile))->assertOk();
            $this->assertSame(0, $response->json('count'), 'hostile term widened the match: ' . $hostile);
        }
    }

    public function test_the_result_set_is_capped(): void
    {
        // A hostile client cannot ask for an unbounded list.
        $this->getJson('/api/fca/associations?limit=1000')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Enter a search term of up to 120 characters.');

        $this->getJson('/api/fca/associations?limit=25')->assertOk();
    }

    public function test_an_over_long_term_is_rejected_with_a_readable_message(): void
    {
        $this->getJson('/api/fca/associations?q=' . str_repeat('a', 200))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Enter a search term of up to 120 characters.');
    }

    /** Nothing on this route may perform a write. */
    public function test_the_public_search_never_writes(): void
    {
        $before = FcaRegistry::count();
        $this->getJson('/api/fca/associations')->assertOk();
        $this->assertSame($before, FcaRegistry::count());
        $this->assertSame(0, FcaRegistry::whereNotNull('last_verified_at')->count());
        $this->assertSame(0, FcaRegistry::where('directory_verification_status', 'verified')->count());
    }

    /** Every other FCA route must stay closed to an anonymous visitor. */
    public function test_protected_fca_routes_reject_an_anonymous_visitor(): void
    {
        foreach ([
            '/api/fca/registry-records',
            '/api/fca/directory',
            '/api/fca/review/pending',
            '/api/fca/applications/1',
            '/api/fca/evidence/11111111-1111-4111-8111-111111111111',
        ] as $path) {
            $this->getJson($path)->assertUnauthorized($path . ' must require authentication');
        }
    }

    public function test_a_protected_write_action_rejects_an_anonymous_visitor(): void
    {
        $this->postJson('/api/fca/review/1', ['decision' => 'approve'])->assertUnauthorized();
        $this->postJson('/api/fca/evidence', [])->assertUnauthorized();
        $this->postJson('/api/fca/directory/1/applications', [])->assertUnauthorized();
    }

    /** An authenticated applicant does not gain access to the administrative registry view. */
    public function test_an_account_holder_does_not_get_the_administrative_registry(): void
    {
        $user = \App\Models\User::create([
            'first_name' => 'Ana', 'last_name' => 'Rizal',
            'email' => 'ana.rizal@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180000001', 'user_type' => 'Farmer', 'is_active' => true,
        ]);

        // The public search still works for a signed-in farmer ...
        $this->actingAs($user, 'sanctum')->getJson('/api/fca/associations?q=Binalan')->assertOk();

        // ... but the administrative registry view stays closed to them. While the workflow
        // gate is off it fails closed with 404 before authorization is even reached; with the
        // workflow enabled it is rejected with 403. Either way it is never 200.
        $closed = $this->actingAs($user, 'sanctum')->getJson('/api/fca/registry-records');
        $this->assertContains(
            $closed->status(),
            [403, 404],
            'the administrative registry must never be readable by a farmer'
        );
    }

    /** With the workflow enabled, the failure is an explicit authorization refusal. */
    public function test_with_the_workflow_enabled_a_farmer_is_refused_the_registry(): void
    {
        config(['mao_workflows.fca_applications_enabled' => true]);

        $user = \App\Models\User::create([
            'first_name' => 'Ben', 'last_name' => 'Cruz',
            'email' => 'ben.cruz@test.local', 'password_hash' => bcrypt('test-only'),
            'contact_number' => '09180000002', 'user_type' => 'Farmer', 'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fca/registry-records')
            ->assertForbidden();

        // The public search is unaffected by the workflow gate either way.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/fca/associations?q=Binalan')
            ->assertOk();
    }

    /**
     * The public lookup must not 404 just because the official registration workflow is
     * unavailable. Reading a public list of association names and addresses is not that
     * workflow, and a closed workflow must not break a page that needs no approval.
     */
    public function test_the_public_search_survives_a_disabled_fca_workflow(): void
    {
        config(['mao_workflows.fca_applications_enabled' => false]);

        $this->getJson('/api/fca/associations?q=Binalan')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }
}