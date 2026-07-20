<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kurt\Modules\I18n\Http\Controllers\UiController;

/*
|--------------------------------------------------------------------------
| i18n UI shell
|--------------------------------------------------------------------------
|
| The bundled translation-manager UI, registered only when
| I18N_HTTP_MODE=ui (see I18nServiceProvider::registerUi()). It is served under
| the "i18n.route" prefix with the "web" middleware group and the module's
| environment/gate guard. The single-page app it boots talks to the JSON REST
| API registered from routes/api.php under the "i18n.http.prefix".
|
*/

Route::get('/', [UiController::class, 'index'])->name('i18n.index');
