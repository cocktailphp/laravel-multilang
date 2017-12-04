<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available locales/languages
    |--------------------------------------------------------------------------
    |
    | Available locales for routing
    |
     */
    'locales'           => [
        'en' => [
            'name'             => 'English',
            'native_name'      => 'English',
            'locale'           => 'en', // ISO 639-1
            'canonical_locale' => 'en_GB', // ISO 3166-1
            'full_locale'      => 'en_GB.UTF-8',
        ],

        // Add yours here
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback locale/language
    |--------------------------------------------------------------------------
    |
    | Fallback locale for routing
    |
     */
    'default_locale'    => 'en',

    /*
    |--------------------------------------------------------------------------
    | Set Carbon locale
    |--------------------------------------------------------------------------
    |
    | Call Carbon::setLocale($locale) and set current locale in middleware
    |
     */
    'set_carbon_locale' => true,

    /*
    |--------------------------------------------------------------------------
    | Set System locale
    |--------------------------------------------------------------------------
    |
    | Call setlocale(LC_ALL, $locale) and set current locale in middleware
    |
     */
    'set_system_locale' => true,

    /*
    |--------------------------------------------------------------------------
    | Exclude segments from redirect
    |--------------------------------------------------------------------------
    |
    | Exclude segments from redirects in the middleware
    |
     */
    'exclude_segments'  => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache parameters
    |
     */
    'cache'             => [
        'enabled'  => true,
        'store'    => env('CACHE_DRIVER', 'default'),
        'lifetime' => 1440,
    ],

    /*
    |--------------------------------------------------------------------------
    | DB Configuration
    |--------------------------------------------------------------------------
    |
    | DB parameters
    |
     */
    'db'                => [
        'autosave'    => true, // Autosave missing texts in the database. Only when environment is local
        'connection'  => env('DB_CONNECTION', 'default'),
        'texts_table' => 'texts',
    ],

];
