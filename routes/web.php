<?php

use App\Http\Controllers\WordpressCompatController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/abflussverstopfung', 'abflussverstopfung')->name('abflussverstopfung');
Route::view('/unsere-preisliste', 'unsere-preisliste')->name('unsere-preisliste');
Route::view('/ol-heizungen', 'ol-heizungen')->name('ol-heizungen');
Route::view('/warmepumpen', 'warmepumpen')->name('warmepumpen');
Route::view('/austria-email-thermenwartung', 'austria-email-thermenwartung')->name('austria-email-thermenwartung');
Route::view('/buderus-thermenwartung', 'buderus-thermenwartung')->name('buderus-thermenwartung');
Route::view('/bosch-thermenwartung', 'bosch-thermenwartung')->name('bosch-thermenwartung');
Route::view('/baxi-thermenwartung', 'baxi-thermenwartung')->name('baxi-thermenwartung');
Route::view('/vaillant-thermenwartung', 'vaillant-thermenwartung')->name('vaillant-thermenwartung');
Route::view('/hoval-thermenwartung', 'hoval-thermenwartung')->name('hoval-thermenwartung');
Route::view('/rapido-thermenwartung', 'rapido-thermenwartung')->name('rapido-thermenwartung');
Route::view('/junkers-thermenwartung', 'junkers-thermenwartung')->name('junkers-thermenwartung');
Route::view('/saunier-duval-thermenwartung', 'saunier-duval-thermenwartung')->name('saunier-duval-thermenwartung');
Route::view('/vissmann-thermenwartung', 'vissmann-thermenwartung')->name('vissmann-thermenwartung');
Route::view('/loblich-thermenwartung', 'loblich-thermenwartung')->name('loblich-thermenwartung');
Route::view('/sieger-thermenwartung', 'sieger-thermenwartung')->name('sieger-thermenwartung');
Route::view('/nordgas-thermenwartung', 'nordgas-thermenwartung')->name('nordgas-thermenwartung');
Route::view('/gebe-thermenwartung', 'gebe-thermenwartung')->name('gebe-thermenwartung');
Route::view('/windhager-thermenwartung', 'windhager-thermenwartung')->name('windhager-thermenwartung');
Route::view('/wolf-thermenwartung', 'wolf-thermenwartung')->name('wolf-thermenwartung');
Route::view('/impressum', 'impressum')->name('impressum');
Route::view('/kontakt', 'kontakt')->name('kontakt');

Route::post('/elementor-form-submit', [WordpressCompatController::class, 'elementorFormSubmit'])->name('elementor.form.submit');

Route::get('/sitemap.xml', function () {
    return response()->view('sitemap-xml')->header('Content-Type', 'application/xml');
});

Route::get('/wp-admin/admin-ajax.php', [WordpressCompatController::class, 'adminAjax']);

Route::get('/wp-content/uploads/complianz/css/banner-{bannerId}-{type}.css', [WordpressCompatController::class, 'complianzBannerCss'])
    ->whereNumber('bannerId')
    ->where('type', '[A-Za-z0-9_-]+');

Route::get('/wp-content/plugins/{plugin}/assets/{path}', [WordpressCompatController::class, 'pluginAssetRedirect'])
    ->where('plugin', 'elementor|elementor-pro')
    ->where('path', '.*');

Route::prefix('/wp-json')->group(function () {
    Route::get('/', [WordpressCompatController::class, 'apiRoot']);
    Route::get('/wp/v2/pages/{pageId}', [WordpressCompatController::class, 'page'])->whereNumber('pageId');
    Route::get('/oembed/1.0/embed', [WordpressCompatController::class, 'oembed']);

    Route::prefix('/complianz/v1')->group(function () {
        Route::get('/banner', [WordpressCompatController::class, 'complianzBanner']);
        Route::get('/cookie_data', [WordpressCompatController::class, 'complianzCookieData']);
        Route::get('/manage_consent_html', [WordpressCompatController::class, 'complianzManageConsentHtml']);
        Route::get('/consent-area/{postId}/{blockId}', [WordpressCompatController::class, 'complianzConsentArea'])
            ->whereNumber('postId')
            ->whereNumber('blockId');
        Route::post('/track', [WordpressCompatController::class, 'complianzTrack']);
    });

    Route::prefix('/contact-form-7/v1')->group(function () {
        Route::get('/contact-forms/{formId}/feedback/schema', [WordpressCompatController::class, 'contactFormSchema'])
            ->whereNumber('formId');
        Route::post('/contact-forms/{formId}/feedback', [WordpressCompatController::class, 'contactFormFeedback'])
            ->whereNumber('formId');
    });
});
