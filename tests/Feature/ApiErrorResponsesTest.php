<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiErrorResponsesTest extends TestCase
{
    public function test_unauthenticated_api_call_returns_a_readable_401_instead_of_a_500(): void
    {
        foreach (['/api/notifications', '/api/buyer/demands', '/api/buyer/orders'] as $uri) {
            $response = $this->getJson($uri);

            $response->assertStatus(401)->assertJsonStructure(['message']);

            $this->assertIsString($response->json('message'));
            $this->assertNotSame('', trim($response->json('message')));
        }
    }
    public function test_browser_and_wildcard_accept_headers_cannot_turn_auth_denial_into_html_500():void
    {
        foreach (['*/*','text/html',''] as $accept) {
            $this->get('/api/session',['Accept'=>$accept])->assertUnauthorized()->assertJsonStructure(['message']);
        }
    }
}