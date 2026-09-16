<?php

Route::get('/', function () {
    return view('app');
});

// Serve the React application for direct visits to client-side routes.
Route::get('/{path}', function () {
    return view('app');
})->where('path', '.*');
