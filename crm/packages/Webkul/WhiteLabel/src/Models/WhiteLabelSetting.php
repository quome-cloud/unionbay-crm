<?php

namespace Webkul\WhiteLabel\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\WhiteLabel\Contracts\WhiteLabelSetting as WhiteLabelSettingContract;

class WhiteLabelSetting extends Model implements WhiteLabelSettingContract
{
    protected $table = 'white_label_settings';

    protected $fillable = [
        'app_name',
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'primary_color',
        'secondary_color',
        'accent_color',
        'email_sender_name',
        'support_url',
        'login_bg_image',
        'custom_css',
    ];
}
