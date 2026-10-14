<?php

return [

    /*
    | Where `php artisan db:backup` writes its dumps, and how many to keep.
    | storage/app is git-ignored, so the dumps never end up in the repository.
    */

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    'keep' => (int) env('BACKUP_KEEP', 7),

    // XAMPP doesn't put mysqldump on the PATH; point this at
    // C:\xampp\mysql\bin\mysqldump.exe there.
    'mysqldump' => env('DB_DUMP_BINARY', 'mysqldump'),

];
