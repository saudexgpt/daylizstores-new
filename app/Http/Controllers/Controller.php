<?php

namespace App\Http\Controllers;

use App\ActivityLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UserResource;
use App\IssueTicket;
use App\Laravue\Models\Role;
use App\Laravue\Models\User;
use App\Location;
use App\Models\Setting\Setting;
use App\Models\Setting\Tax;
use App\Models\Stock\Item;
use App\Models\Stock\ItemMedia;
use Notification;
use App\Notifications\AuditTrail;
use Intervention\Image\Facades\Image;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $user;
    public $can_make_order;
    public $states = [
        "Abia",
        "Adamawa",
        "Akwa Ibom",
        "Anambra",
        "Bauchi",
        "Bayelsa",
        "Benue",
        "Borno",
        "Cross River",
        "Delta",
        "Ebonyi",
        "Edo",
        "Ekiti",
        "Enugu",
        "FCT - Abuja",
        "Gombe",
        "Imo",
        "Jigawa",
        "Kaduna",
        "Kano",
        "Katsina",
        "Kebbi",
        "Kogi",
        "Kwara",
        "Lagos",
        "Nasarawa",
        "Niger",
        "Ogun",
        "Ondo",
        "Osun",
        "Oyo",
        "Plateau",
        "Rivers",
        "Sokoto",
        "Taraba",
        "Yobe",
        "Zamfara"
    ];
    public $colors = [
        'AliceBlue' => '#F0F8FF',
        'AntiqueWhite' => '#FAEBD7',
        'Aqua' => '#00FFFF',
        'Aquamarine' => '#7FFFD4',
        'Azure' => '#F0FFFF',
        'Beige' => '#F5F5DC',
        'Bisque' => '#FFE4C4',
        'Black' => '#000000',
        'BlanchedAlmond' => '#FFEBCD',
        'Blue' => '#0000FF',
        'BlueViolet' => '#8A2BE2',
        'Brown' => '#A52A2A',
        'BurlyWood' => '#DEB887',
        'CadetBlue' => '#5F9EA0',
        'Chartreuse' => '#7FFF00',
        'Chocolate' => '#D2691E',
        'Coral' => '#FF7F50',
        'CornflowerBlue' => '#6495ED',
        'Cornsilk' => '#FFF8DC',
        'Crimson' => '#DC143C',
        'Cyan' => '#00FFFF',
        'DarkBlue' => '#00008B',
        'DarkCyan' => '#008B8B',
        'DarkGoldenRod' => '#B8860B',
        'DarkGray' => '#A9A9A9',
        'DarkGrey' => '#A9A9A9',
        'DarkGreen' => '#006400',
        'DarkKhaki' => '#BDB76B',
        'DarkMagenta' => '#8B008B',
        'DarkOliveGreen' => '#556B2F',
        'DarkOrange' => '#FF8C00',
        'DarkOrchid' => '#9932CC',
        'DarkRed' => '#8B0000',
        'DarkSalmon' => '#E9967A',
        'DarkSeaGreen' => '#8FBC8F',
        'DarkSlateBlue' => '#483D8B',
        'DarkSlateGray' => '#2F4F4F',
        'DarkSlateGrey' => '#2F4F4F',
        'DarkTurquoise' => '#00CED1',
        'DarkViolet' => '#9400D3',
        'DeepPink' => '#FF1493',
        'DeepSkyBlue' => '#00BFFF',
        'DimGray' => '#696969',
        'DimGrey' => '#696969',
        'DodgerBlue' => '#1E90FF',
        'FireBrick' => '#B22222',
        'FloralWhite' => '#FFFAF0',
        'ForestGreen' => '#228B22',
        'Fuchsia' => '#FF00FF',
        'Gainsboro' => '#DCDCDC',
        'GhostWhite' => '#F8F8FF',
        'Gold' => '#FFD700',
        'GoldenRod' => '#DAA520',
        'Gray' => '#808080',
        'Grey' => '#808080',
        'Green' => '#008000',
        'GreenYellow' => '#ADFF2F',
        'HoneyDew' => '#F0FFF0',
        'HotPink' => '#FF69B4',
        'IndianRed' => '#CD5C5C',
        'Indigo' => '#4B0082',
        'Ivory' => '#FFFFF0',
        'Khaki' => '#F0E68C',
        'Lavender' => '#E6E6FA',
        'LavenderBlush' => '#FFF0F5',
        'LawnGreen' => '#7CFC00',
        'LemonChiffon' => '#FFFACD',
        'LightBlue' => '#ADD8E6',
        'LightCoral' => '#F08080',
        'LightCyan' => '#E0FFFF',
        'LightGoldenRodYellow' => '#FAFAD2',
        'LightGray' => '#D3D3D3',
        'LightGrey' => '#D3D3D3',
        'LightGreen' => '#90EE90',
        'LightPink' => '#FFB6C1',
        'LightSalmon' => '#FFA07A',
        'LightSeaGreen' => '#20B2AA',
        'LightSkyBlue' => '#87CEFA',
        'LightSlateGray' => '#778899',
        'LightSlateGrey' => '#778899',
        'LightSteelBlue' => '#B0C4DE',
        'LightYellow' => '#FFFFE0',
        'Lime' => '#00FF00',
        'LimeGreen' => '#32CD32',
        'Linen' => '#FAF0E6',
        'Magenta' => '#FF00FF',
        'Maroon' => '#800000',
        'MediumAquaMarine' => '#66CDAA',
        'MediumBlue' => '#0000CD',
        'MediumOrchid' => '#BA55D3',
        'MediumPurple' => '#9370DB',
        'MediumSeaGreen' => '#3CB371',
        'MediumSlateBlue' => '#7B68EE',
        'MediumSpringGreen' => '#00FA9A',
        'MediumTurquoise' => '#48D1CC',
        'MediumVioletRed' => '#C71585',
        'MidnightBlue' => '#191970',
        'MintCream' => '#F5FFFA',
        'MistyRose' => '#FFE4E1',
        'Moccasin' => '#FFE4B5',
        'NavajoWhite' => '#FFDEAD',
        'Navy' => '#000080',
        'OldLace' => '#FDF5E6',
        'Olive' => '#808000',
        'OliveDrab' => '#6B8E23',
        'Orange' => '#FFA500',
        'OrangeRed' => '#FF4500',
        'Orchid' => '#DA70D6',
        'PaleGoldenRod' => '#EEE8AA',
        'PaleGreen' => '#98FB98',
        'PaleTurquoise' => '#AFEEEE',
        'PaleVioletRed' => '#DB7093',
        'PapayaWhip' => '#FFEFD5',
        'PeachPuff' => '#FFDAB9',
        'Peru' => '#CD853F',
        'Pink' => '#FFC0CB',
        'Plum' => '#DDA0DD',
        'PowderBlue' => '#B0E0E6',
        'Purple' => '#800080',
        'RebeccaPurple' => '#663399',
        'Red' => '#FF0000',
        'RosyBrown' => '#BC8F8F',
        'RoyalBlue' => '#4169E1',
        'SaddleBrown' => '#8B4513',
        'Salmon' => '#FA8072',
        'SandyBrown' => '#F4A460',
        'SeaGreen' => '#2E8B57',
        'SeaShell' => '#FFF5EE',
        'Sienna' => '#A0522D',
        'Silver' => '#C0C0C0',
        'SkyBlue' => '#87CEEB',
        'SlateBlue' => '#6A5ACD',
        'SlateGray' => '#708090',
        'SlateGrey' => '#708090',
        'Snow' => '#FFFAFA',
        'SpringGreen' => '#00FF7F',
        'SteelBlue' => '#4682B4',
        'Tan' => '#D2B48C',
        'Teal' => '#008080',
        'Thistle' => '#D8BFD8',
        'Tomato' => '#FF6347',
        'Turquoise' => '#40E0D0',
        'Violet' => '#EE82EE',
        'Wheat' => '#F5DEB3',
        'White' => '#FFFFFF',
        'WhiteSmoke' => '#F5F5F5',
        'Yellow' => '#FFFF00',
        'YellowGreen' => '#9ACD32',
    ];

    public function __construct()
    {
        // $this->can_make_order = false;
        // $this->middleware('guest')->except('logout');
        // $this->checkForNegativeTransitProduct();
        // $this->resetPartialInvoices();
    }
    public function uploadFile2(Request $request)
    {
        if ($request->file('file') != null && $request->file('file')->isValid()) {
            $this->validate($request, [
                'file' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
            ]);
            $image = $request->file('file');
            $imgFile = Image::make($image->getRealPath());
            $imgFile->resize(null, 240, function ($constraint) {
                $constraint->aspectRatio();
            });

            $name = time() . '.' . $image->getClientOriginalExtension();
            $folder = "storage/items";
            $avatar = $imgFile->storeAs($folder, $name, 'public');
            $link = '/' . $avatar;
            $media = new ItemMedia();
            $media->link = $link;
            $media->save();
            return response()->json(['media_id' => $media->id], 200);
        }
    }
    public function uploadFile(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|image|mimes:jpg,jpeg,png,gif|max:5120',
        ]);
        $image = $request->file('file');
        // The extension comes from the file's real content, never the client-supplied
        // name — otherwise a validated image uploaded as "x.php" would be saved
        // under a .php name inside the public folder.
        $extension = in_array($image->extension(), ['jpg', 'jpeg', 'png', 'gif'], true) ? $image->extension() : 'jpg';
        $name = randomcode() . time() . '.' . $extension;
        $folder = "/storage/items";
        $thumbnail_folder = $folder . "/thumbnails";
        $thumbnail_path = portalPulicPath($thumbnail_folder);

        // save thumbnail image
        $imgFile = Image::make($image->getRealPath());
        $imgFile->resize(250, 170)->save($thumbnail_path . '/' . $name);
        // $imgFile->resize(250, 152, function ($constraint) {
        //     $constraint->aspectRatio();
        // })->save($thumbnail_path . '/' . $name);
        //keep original file
        $imgFileOriginal = Image::make($image->getRealPath());
        $destinationPath = portalPulicPath($folder);
        $imgFileOriginal->resize(600, null, function ($constraint) {
            $constraint->aspectRatio();
        })->save($destinationPath . '/' . $name);

        // $image->move($destinationPath, $name);

        $media = new ItemMedia();
        $media->link = $folder . '/' . $name;
        $media->thumbnail = $thumbnail_folder . '/' . $name;
        $media->save();

        return response()->json(['media_id' => $media->id], 200);
    }

    public function setUser()
    {
        $this->user = new UserResource(Auth::user());
    }

    public function getUser()
    {
        $this->setUser();

        return $this->user;
    }
    public function fetchNecessayParams()
    {
        // What the public storefront needs (footer, checkout). This endpoint
        // is unauthenticated, so nothing else may be added here.
        $params = [
            'company_name' => $this->settingValue('company_name'),
            'currency' => $this->settingValue('currency'),
            'account_details' => $this->settingValue('account_details'),
            'terms_and_conditions' => $this->settingValue('terms_and_conditions'),
            'states' => $this->states,
            'can_make_order' => $this->settingEnabled('can_make_order'),
            // The warning's text, shown to the customer before they submit.
            'pickup_warning' => $this->settingValue('pickup_warning'),
            'online_payment_enabled' => $this->settingEnabled('online_payment_enabled'),
        ];

        // Admin/staff screens additionally need the catalogue (including
        // disabled items), roles, warehouses, etc. — only for signed-in users.
        if (Auth::guard('api')->check()) {
            $params += [
                'all_locations' => Location::get(),
                'company_contact' => $this->settingValue('company_contact'),
                'warehouses' => [],
                'items' => Item::with(['price'])->orderBy('name')->get(),
                'genders' => ['Men', 'Ladies', 'Boys', 'Girls'],
                'all_roles' => Role::orderBy('name')->select('name')->get(),
                'order_statuses' => ['Pending', 'CARP', 'On Transit', 'Delivered', 'Cancelled'],
                'colors' => $this->colors,
            ];
        }

        return response()->json(['params' => $params]);
    }
    public function settingValue($key)
    {
        return Setting::where('key', $key)->first()->value;
    }
    /**
     * Whether an on/off setting is switched on. Settings are stored as text,
     * so a bare (bool) cast is wrong — the string "false" is truthy in PHP.
     * A missing row counts as off.
     */
    public function settingEnabled($key)
    {
        $setting = Setting::where('key', $key)->first();

        return $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : false;
    }
    public function generalSettings()
    {
        $settings = Setting::get();
        return response()->json(compact('settings'), 200);
    }
    public function nextReceiptNo($key_prefix = 'invoice')
    {
        $prefix = $this->settingValue($key_prefix . '_number_prefix');
        $no_of_digits = $this->settingValue($key_prefix . '_number_digit');
        $next_no = $this->settingValue($key_prefix . '_number_next');

        $digit_of_next_no = strlen($next_no);
        $unused_digit = $no_of_digits - $digit_of_next_no;
        $zeros = '';
        for ($i = 1; $i <= $unused_digit; $i++) {
            $zeros .= '0';
        }

        $invoice_no = $prefix . $zeros . $next_no;
        return $invoice_no;
    }

    public function incrementReceiptNo($key_prefix = 'invoice')
    {
        $next_no = $this->settingValue($key_prefix . '_number_next');
        $setting = Setting::where('key', $key_prefix . '_number_next')->first();
        $setting->value = $next_no + 1;
        $setting->save();
        return $setting;
    }

    public function logUserActivity($title, $description, $roles = [])
    {
        // $user = $this->getUser();
        // if ($role) {
        //     $role->notify(new AuditTrail($title, $description));
        // }
        // return $user->notify(new AuditTrail($title, $description));
        // send notification to admin at all times
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', '=', 'admin'); // this is the role id inside of this callback
        })->get();

        // if (in_array('assistant admin', $roles)) {
        //     $assistant_admin = User::whereHas('roles', function ($query) {
        //         $query->where('name', '=', 'assistant admin'); // this is the role id inside of this callback
        //     })->get();
        //     $users = $users->merge($assistant_admin);
        // }
        // if (in_array('warehouse manager', $roles)) {
        //     $warehouse_managers = User::whereHas('roles', function ($query) {
        //         $query->where('name', '=', 'warehouse manager'); // this is the role id inside of this callback
        //     })->get();
        //     $users = $users->merge($warehouse_managers);
        // }
        // if (in_array('stock officer', $roles)) {
        // $stock_officers = User::whereHas('roles', function ($query) use ($roles) {
        //     // $query->where('name', '=', 'stock officer'); // this is the role id inside of this callback
        //     $query->whereIn('name', $roles);
        // })->get();
        // $users = $users->merge($stock_officers);
        // }
        // if (in_array('warehouse auditor', $roles)) {
        //     $auditors = User::whereHas('roles', function ($query) {
        //         $query->where('name', '=', 'warehouse auditor'); // this is the role id inside of this callback
        //     })->get();
        //     $users = $users->merge($auditors);
        // }
        // var_dump($users);
        $notification = new AuditTrail($title, $description);
        return Notification::send($users->unique(), $notification);
        // $activity_log = new ActivityLog();
        // $activity_log->user_id = $user->id;
        // $activity_log->action = $action;
        // $activity_log->user_type = $user->roles[0]->name;
        // $activity_log->save();
    }
    public function getUniqueNo($prefix, $next_no)
    {
        $no_of_digits = 5;

        $digit_of_next_no = strlen($next_no);
        $unused_digit = $no_of_digits - $digit_of_next_no;
        $zeros = '';
        for ($i = 1; $i <= $unused_digit; $i++) {
            $zeros .= '0';
        }

        return $prefix . $zeros . $next_no;
    }
}
