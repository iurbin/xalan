<?php

test('frontend routes render the login and main application views', function () {
    $this->get('/login')->assertOk()->assertSee('Welcome back');
    $this->get('/app')->assertOk()->assertSee('Your service at a glance');
    $this->get('/customers')->assertOk()->assertSee('Customer directory');
    $this->get('/properties')->assertOk()->assertSee('SERVICE LOCATIONS');
    $this->get('/visits')->assertOk()->assertSee('Visit schedule');
    $this->get('/billing')->assertOk()->assertSee('Customer invoice history');
    $this->get('/team')->assertOk()->assertSee('Team members');
});
