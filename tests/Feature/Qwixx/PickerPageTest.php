<?php

declare(strict_types=1);

it('renders the picker with all three layouts', function () {
    $response = $this->get('/qwixx');

    $response->assertOk()
        ->assertSee('Classic')
        ->assertSee('Mixed Numbers')
        ->assertSee('Mixed Colors');
});

it('links every layout to its solo and duo game routes', function () {
    $response = $this->get('/qwixx');

    foreach (['classic', 'mixed-numbers', 'mixed-colors'] as $id) {
        $response->assertSee("/qwixx/play/$id/solo")->assertSee("/qwixx/play/$id/duo");
    }
});
