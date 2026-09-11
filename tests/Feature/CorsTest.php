<?php

test('cross-origin requests to the API receive CORS headers', function () {
    $response = $this->withHeaders(['Origin' => 'https://example.com'])
        ->postJson('/api/v1/login', ['email' => 'nobody@example.com', 'password' => 'wrong']);

    $response->assertHeader('Access-Control-Allow-Origin', '*');
});
