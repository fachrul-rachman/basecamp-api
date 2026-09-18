<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap SuperAdmin
    |--------------------------------------------------------------------------
    |
    | Used ONLY by `php artisan db:seed --class=ProductionSeeder` to create
    | the first real SuperAdmin account on a fresh deployment. See
    | database/seeders/ProductionSeeder.php.
    |
    */

    'name' => env('SUPERADMIN_NAME', 'Super Admin'),
    'email' => env('SUPERADMIN_EMAIL'),
    'password' => env('SUPERADMIN_PASSWORD'),

];
