<?php

test('guests can open the public PLSAssist landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('PLSAssist helps parliaments review whether legislation works after adoption');
});
