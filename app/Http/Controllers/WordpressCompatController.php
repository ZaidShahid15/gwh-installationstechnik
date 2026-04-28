<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WordpressCompatController extends Controller
{
    public function apiRoot(): Response
    {
        return response([
            'name' => 'GWH-Installationstechnik',
            'description' => 'Local WordPress compatibility layer',
            'url' => url('/'),
            'home' => url('/'),
            'namespaces' => [
                'complianz/v1',
                'contact-form-7/v1',
                'wp/v2',
                'oembed/1.0',
            ],
        ]);
    }

    public function page(int $pageId): Response
    {
        return response([
            'id' => $pageId,
            'link' => url()->current(),
            'type' => 'page',
        ]);
    }

    public function oembed(Request $request): Response
    {
        $requestedUrl = $request->query('url', url('/'));

        if ($request->query('format') === 'xml') {
            $xml = sprintf(
                '<?xml version="1.0" encoding="UTF-8"?><oembed><version>1.0</version><type>link</type><provider_name>GWH-Installationstechnik</provider_name><provider_url>%s</provider_url><title>%s</title><author_name>%s</author_name><author_url>%s</author_url></oembed>',
                e(url('/')),
                e('GWH-Installationstechnik'),
                e('GWH-Installationstechnik'),
                e($requestedUrl)
            );

            return response($xml, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
        }

        return response([
            'version' => '1.0',
            'type' => 'link',
            'provider_name' => 'GWH-Installationstechnik',
            'provider_url' => url('/'),
            'title' => 'GWH-Installationstechnik',
            'author_name' => 'GWH-Installationstechnik',
            'author_url' => $requestedUrl,
        ]);
    }

    public function adminAjax(): Response
    {
        return response('', 204);
    }

    public function complianzBanner(): Response
    {
        return response([
            'region' => 'eu',
            'version' => '7.4.1',
            'banner_version' => '30',
            'user_banner_id' => '1',
            'cookie_path' => '/',
            'cookie_domain' => '',
            'set_cookies_on_root' => '0',
            'current_policy_id' => '36',
        ]);
    }

    public function complianzCookieData(): Response
    {
        return response([]);
    }

    public function complianzManageConsentHtml(): Response
    {
        return response()->json('');
    }

    public function complianzConsentArea(int $postId, int $blockId): Response
    {
        return response()->json('');
    }

    public function complianzTrack(): Response
    {
        return response('', 204);
    }

    public function complianzBannerCss(string $bannerId, string $type): Response
    {
        $css = <<<'CSS'
.cmplz-cookiebanner {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 99999;
    max-width: 420px;
    width: calc(100% - 32px);
    padding: 20px;
    border-radius: 14px;
    background: rgba(17, 17, 17, 0.96);
    color: #ffffff;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
}

.cmplz-cookiebanner a,
.cmplz-cookiebanner .cmplz-title,
.cmplz-cookiebanner .cmplz-message,
.cmplz-cookiebanner .cmplz-category-title,
.cmplz-cookiebanner .cmplz-description,
.cmplz-cookiebanner .cmplz-link {
    color: #ffffff;
}

.cmplz-cookiebanner .cmplz-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}

.cmplz-cookiebanner .cmplz-btn {
    appearance: none;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 999px;
    background: transparent;
    color: #ffffff;
    cursor: pointer;
    font: inherit;
    padding: 10px 18px;
}

.cmplz-cookiebanner .cmplz-btn.cmplz-accept,
.cmplz-cookiebanner .cmplz-btn.cmplz-save-preferences {
    background: #d91f26;
    border-color: #d91f26;
}

.cmplz-cookiebanner.cmplz-hidden,
.cmplz-cookiebanner.cmplz-dismissed,
.cmplz-hidden {
    display: none !important;
}

.cmplz-cookiebanner.cmplz-show {
    display: block !important;
}

.cmplz-manage-consent {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 99998;
}

.cmplz-manage-consent.cmplz-show {
    display: inline-flex !important;
}

.cmplz-manage-consent.cmplz-dismissed {
    display: inline-flex !important;
}

@media (max-width: 767px) {
    .cmplz-cookiebanner,
    .cmplz-manage-consent {
        right: 16px;
        left: 16px;
        bottom: 16px;
        width: auto;
        max-width: none;
    }
}
CSS;

        return response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    }

    public function elementorFormSubmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'form_fields.email' => ['required', 'email'],
            'form_fields.name' => ['nullable', 'string', 'max:255'],
            'form_fields.message' => ['nullable', 'string', 'max:5000'],
            'referer_title' => ['nullable', 'string', 'max:255'],
            'origin_url' => ['nullable', 'url'],
        ]);

        $formFields = $validated['form_fields'] ?? [];
        $senderName = trim((string) ($formFields['name'] ?? ''));
        $senderEmail = trim((string) ($formFields['email'] ?? ''));
        $senderMessage = trim((string) ($formFields['message'] ?? ''));
        $websiteName = 'GWH-Installationstechnik';
        $websiteUrl = url('/');
        $pageTitle = trim((string) ($validated['referer_title'] ?? 'Kontaktformular'));
        $originUrl = (string) ($validated['origin_url'] ?? url()->previous() ?? url('/'));

        if (! str_starts_with($originUrl, url('/'))) {
            $originUrl = url('/');
        }

        $subject = $websiteName . ' | New Form Submission';
        $html = view('emails.contact-form', [
            'websiteName' => $websiteName,
            'websiteUrl' => $websiteUrl,
            'pageTitle' => $pageTitle,
            'senderName' => $senderName !== '' ? $senderName : 'Not provided',
            'senderEmail' => $senderEmail,
            'senderMessage' => $senderMessage !== '' ? nl2br(e($senderMessage)) : 'No message provided.',
        ])->render();

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $websiteName . ' <no-reply@' . (parse_url($websiteUrl, PHP_URL_HOST) ?: 'localhost') . '>',
            'Reply-To: ' . ($senderName !== '' ? $senderName . ' ' : '') . '<' . $senderEmail . '>',
        ];

        $sent = false;

        set_error_handler(static function (): bool {
            return true;
        });

        try {
            $sent = mail('ast.mediainternational@gmail.com', $subject, $html, implode("\r\n", $headers));
        } finally {
            restore_error_handler();
        }

        if (! $sent) {
            return redirect($originUrl)
                ->withInput()
                ->with('elementor_form_error', 'The message could not be sent. Please try again.');
        }

        return redirect($originUrl)->with('elementor_form_success', 'Your message has been sent successfully.');
    }

    public function contactFormSchema(int $formId): Response
    {
        return response([
            'into' => '#wpcf7-f' . $formId . '-o1',
            'status' => 'init',
            'message' => '',
        ]);
    }

    public function contactFormFeedback(int $formId): Response
    {
        return response([
            'contact_form_id' => $formId,
            'status' => 'mail_sent',
            'message' => 'Message sent.',
            'posted_data_hash' => null,
        ]);
    }

    public function pluginAssetRedirect(string $plugin, string $path): RedirectResponse
    {
        $allowedPlugins = ['elementor', 'elementor-pro'];

        abort_unless(in_array($plugin, $allowedPlugins, true), 404);

        return redirect()->away("https://gwh-installationstechnik.at/wp-content/plugins/{$plugin}/assets/{$path}", 302);
    }
}
