<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kurt\Modules\I18n\Http\Controllers\Api\CatalogController;
use Kurt\Modules\I18n\Http\Controllers\Api\ExportController;
use Kurt\Modules\I18n\Http\Controllers\Api\ImportController;
use Kurt\Modules\I18n\Http\Controllers\Api\JsonTranslationController;
use Kurt\Modules\I18n\Http\Controllers\Api\LocaleController;
use Kurt\Modules\I18n\Http\Controllers\Api\MissingKeyReportController;
use Kurt\Modules\I18n\Http\Controllers\Api\PhpGroupController;
use Kurt\Modules\I18n\Http\Controllers\Api\TranslateMissingController;
use Kurt\Modules\I18n\Http\Controllers\UiController;

Route::get('/', [UiController::class, 'index'])->name('i18n.index');

Route::get('/api/catalog', CatalogController::class)->name('i18n.catalog');

Route::get('/api/json', [JsonTranslationController::class, 'show'])->name('i18n.json.show');
Route::patch('/api/json', [JsonTranslationController::class, 'update'])->name('i18n.json.update');

Route::get('/api/php/{group}', [PhpGroupController::class, 'show'])->where('group', '.*')->name('i18n.php.show');
Route::patch('/api/php/{group}', [PhpGroupController::class, 'update'])->where('group', '.*')->name('i18n.php.update');

Route::post('/api/locales', [LocaleController::class, 'store'])->name('i18n.locales.store');

Route::get('/api/report/missing', MissingKeyReportController::class)->name('i18n.report.missing');

Route::get('/api/export', ExportController::class)->name('i18n.export');
Route::post('/api/import', ImportController::class)->name('i18n.import');

Route::post('/api/translate-missing', TranslateMissingController::class)->name('i18n.translate-missing');
