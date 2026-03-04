<?php

namespace Webkul\WhiteLabel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Webkul\WhiteLabel\Repositories\WhiteLabelSettingRepository;

class WhiteLabelController extends Controller
{
    public function __construct(
        protected WhiteLabelSettingRepository $settingRepository
    ) {}

    public function index(): JsonResponse
    {
        $settings = $this->settingRepository->getSettings();

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name'          => 'sometimes|string|max:255',
            'primary_color'     => 'sometimes|string|max:7',
            'secondary_color'   => 'sometimes|string|max:7',
            'accent_color'      => 'sometimes|string|max:7',
            'email_sender_name' => 'sometimes|string|max:255',
            'support_url'       => 'sometimes|nullable|url|max:255',
            'custom_css'        => 'sometimes|nullable|string|max:10000',
            'logo'              => 'sometimes|nullable|image|max:2048',
            'logo_dark'         => 'sometimes|nullable|image|max:2048',
            'favicon'           => 'sometimes|nullable|image|max:1024',
            'login_bg'          => 'sometimes|nullable|image|max:4096',
        ]);

        $settings = $this->settingRepository->getSettings();

        // Handle file uploads
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('white-label', 'public');
            $validated['logo_url'] = '/storage/'.$path;
        }

        if ($request->hasFile('logo_dark')) {
            $path = $request->file('logo_dark')->store('white-label', 'public');
            $validated['logo_dark_url'] = '/storage/'.$path;
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('white-label', 'public');
            $validated['favicon_url'] = '/storage/'.$path;
        }

        if ($request->hasFile('login_bg')) {
            $path = $request->file('login_bg')->store('white-label', 'public');
            $validated['login_bg_image'] = '/storage/'.$path;
        }

        // Remove file keys from validated data
        unset($validated['logo'], $validated['logo_dark'], $validated['favicon'], $validated['login_bg']);

        $settings->update($validated);

        return response()->json([
            'message' => 'White label settings updated successfully.',
            'data'    => $settings->fresh(),
        ]);
    }

    public function css(): \Illuminate\Http\Response
    {
        $settings = $this->settingRepository->getSettings();

        $css = ":root {\n";
        $css .= "  --wl-primary-color: {$settings->primary_color};\n";
        $css .= "  --wl-secondary-color: {$settings->secondary_color};\n";
        $css .= "  --wl-accent-color: {$settings->accent_color};\n";
        $css .= "}\n";

        if ($settings->custom_css) {
            $css .= "\n".$settings->custom_css;
        }

        return response($css, 200, ['Content-Type' => 'text/css']);
    }
}
