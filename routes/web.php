<?php

use App\Support\Pages;

// Every page in resources/pages.json, or welcome at / when there is none.
app(Pages::class)->routes();
