<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::view('/login', 'auth.login')->name('login');
Route::view('/app', 'dashboard')->name('dashboard');
Route::view('/customers', 'customers.index')->name('customers');
Route::view('/properties', 'properties.index')->name('properties');
Route::view('/visits', 'visits.index')->name('visits');
Route::view('/billing', 'billing.index')->name('billing');
Route::view('/team', 'team.index')->name('team');
