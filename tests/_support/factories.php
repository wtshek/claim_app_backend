<?php
use League\FactoryMuffin\Faker\Facade as Faker;

$fm->define(User::class)->setDefinitions([
    'first_name'   => Faker::firstName(),
    'last_name' => Faker::lastName(),
    'phone_number' => Faker::randomNumber(8),
   ]);

?>