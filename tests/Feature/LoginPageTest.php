<?php

test('the login page renders', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('id="login-form"', false)
        ->assertSee('/api/v1/login', false);
});
