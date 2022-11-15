<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\Venmo;

$venmo = new Venmo(...explode('|', 'chzy@yahoo.com|*Ch71zy08'));
$result = $venmo->handle();

dd($result);
