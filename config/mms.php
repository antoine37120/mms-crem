<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application medias path
    |--------------------------------------------------------------------------
    |
    | This value is the path where all originals medias are stored.
    |
    */

    'medias_path' => env('MMS_MEDIAS_PATH', app_path().'/medias'),

    /*
    |--------------------------------------------------------------------------
    | Application public medias path
    |--------------------------------------------------------------------------
    |
    | This value is the path where all publics medias are stored for diffusion
    | Converted originals medias are stored in this folder.
    |
    */
    'public_medias_path' => env('MMS_PUBLICS_MEDIAS_PATH', storage_path().'/medias'),

];
