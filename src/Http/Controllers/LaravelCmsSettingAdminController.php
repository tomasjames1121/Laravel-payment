<?php

namespace AlexStack\LaravelCms\Http\Controllers;

use Illuminate\Http\Request;
use AlexStack\LaravelCms\Models\LaravelCmsSetting;
use AlexStack\LaravelCms\Models\LaravelCmsFile;
use AlexStack\LaravelCms\Helpers\LaravelCmsHelper;
use Auth;
use App\Http\Controllers\Controller;
use DB;

class LaravelCmsSettingAdminController extends Controller
{
    private $user = null;

    public $helper;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['web', 'auth']); // TODO: must be admin
        $this->helper = new LaravelCmsHelper;
    }

    public function checkUser()
    {
        // return true;
        if (!$this->user) {
            $this->user = $this->helper->hasPermission();
        }
    }

    public function updateConfigFile()
    {
        $this->checkUser();

        //return 'settings';
        $settings = LaravelCmsSetting::where('enabled', 1)->orderBy('id', 'desc')->get(['param_name', 'param_value', 'category', 'page_id']);
        $config_ary = [];
        foreach ($settings as $s) {
            if (trim($s['category']) != '' && trim($s['param_name']) != '') {
                $config_ary[trim($s['category'])][trim($s['param_name'])] = trim($s['param_value']);
            }
        }
        $config_str = "<?php \n# This file automatically generated by Laravel CMS, do not edit it manually \n\n return " . var_export($config_ary, true) . "; \n";

        $config_file = storage_path('app/laravel-cms/settings.php');

        if (!file_exists(dirname($config_file))) {
            mkdir(dirname($config_file), 0755);
        }

        return file_put_contents($config_file, $config_str);

        //return $config_str;
    }

    public function index()
    {
        $this->checkUser();

        $data['settings'] = LaravelCmsSetting::orderBy('id', 'desc')->get();

        $data['helper'] = $this->helper;

        return view('laravel-cms::' . config('laravel-cms.template_backend_dir') .  '.setting-list', $data);
    }

    public function edit($id)
    {
        $this->checkUser();


        $data['setting'] = LaravelCmsSetting::find($id);

        $data['helper'] = $this->helper;

        return view('laravel-cms::' . config('laravel-cms.template_backend_dir') .  '.setting-edit', $data);
    }

    public function create()
    {
        $this->checkUser();


        $data['helper'] = $this->helper;

        return view('laravel-cms::' . config('laravel-cms.template_backend_dir') .  '.setting-create', $data);
    }


    public function store(Request $request)
    {
        $this->checkUser();

        $form_data = $request->all();
        $form_data['user_id'] = $this->user->id ?? null;


        $rs = new LaravelCmsSetting;
        foreach ($rs->fillable as $field) {
            if (isset($form_data[$field])) {
                $rs->$field = trim($form_data[$field]);
            }
        }
        $rs->save();

        $this->updateConfigFile();

        return redirect()->route(
            'LaravelCmsAdminSettings.edit',
            ['id' => $rs->id]
        );
    }

    public function update(Request $request)
    {
        $this->checkUser();

        $form_data = $request->all();
        $form_data['id'] = $request->setting;

        $setting = LaravelCmsSetting::find($form_data['id']);

        unset($form_data['_method']);
        unset($form_data['_token']);


        $data['setting'] = $setting->update($form_data);

        $this->updateConfigFile();


        return back()->withInput();
    }

    public function destroy(Request $request, $id)
    {
        $this->checkUser();
        $rs = LaravelCmsSetting::find($id)->delete();

        return redirect()->route(
            'LaravelCmsAdminSettings.index'
        );
    }
}
