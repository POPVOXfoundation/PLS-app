<?php

test('home displays the public landing page', function () {
    $response = $this->get(route('home'));

    $response->assertOk()->assertSee('PLS');
});
