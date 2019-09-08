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

    private $wrong_json_format_str = '%s is NOT a Correct Json Format string! <hr> Please input a correct json format string. eg. use \\\\ instead of \, use " instead of \' , no comma for the last property<hr>Please make  { not at the begging or make  } not at the end if the input is not a json string';

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

        //sort by asc to override with high priority value
        $settings = LaravelCmsSetting::where('enabled', 1)
            ->orderBy('sort_value', 'asc')
            ->orderBy('id', 'asc')
            ->get(['param_name', 'param_value', 'category', 'page_id']);

        $config_ary = [];
        foreach ($settings as $s) {
            if (trim($s['category']) != '' && trim($s['param_name']) != '') {
                $param_value = trim($s['param_value']);
                if (substr($param_value, 0, 1) == '{' && substr($param_value, -1) == '}') {
                    $param_value = json_decode($param_value, TRUE);
                }
                $config_ary[trim($s['category'])][trim($s['param_name'])] = $param_value;
            }
        }
        //$this->helper->debug($config_ary);

        $config_str = "<?php \n# This file automatically generated by Laravel CMS, do not edit it manually \n\n return " . var_export($config_ary, true) . "; \n";

        $helper = $this->helper;

        if ( strpos($config_str, '__(') !== false ){
            // replace __(str) to locale language string
            $config_str = preg_replace_callback(
                "/__\((.*)\)/U",
                function($matches) use($helper){
                    $str = trim(str_replace(['\\','\'','"'],'',$matches[1]));
                    return addslashes($helper->t($str));
                },
                $config_str);
        }

        if ( strpos($config_str, 'ROUTE(') !== false ){
            // replace __(str) to locale language string
            $config_str = preg_replace_callback(
                "/ROUTE\((.*)\)/U",
                function($matches) {
                    $route_name = trim(str_replace(['\\','\'','"'],'',$matches[1]));
                    if ( \Route::has($route_name) ){
                        return route($route_name, [], false);
                    } else {
                        return '#route_not_defined_'.$matches[1];
                    }
                },
                $config_str);
        }

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

        $data['settings'] = LaravelCmsSetting::orderBy('sort_value', 'desc')->orderBy('id', 'desc')->get();

        $data['helper'] = $this->helper;

        $data['categories'] =  $this->helper->getCmsSetting('categories.cms_setting_categories');
        if (!$data['categories']) {
            $data['categories'] = [];
        }

        //$this->helper->debug($data['categories']);

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

        $must_json = $form_data['category'] == 'plugins';
        if (!$this->helper->correctJsonFormat($form_data['param_value'], $must_json)) {
            exit(sprintf($this->wrong_json_format_str, 'Param Value'));
        }

        $must_json = trim($form_data['input_attribute']) != '';
        if (!$this->helper->correctJsonFormat($form_data['input_attribute'], $must_json)) {
            exit(sprintf($this->wrong_json_format_str, 'Input Attribute'));
        }

        $rs = new LaravelCmsSetting;
        foreach ($rs->fillable as $field) {
            if (isset($form_data[$field])) {
                $rs->$field = trim($form_data[$field]);
            }
        }
        $rs->save();

        $this->updateConfigFile();

        if ($form_data['return_to_the_list']) {

            return redirect()->route(
                'LaravelCmsAdminSettings.index',
                ['category' => $rs->category]
            );
        }
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

        $must_json = $form_data['category'] == 'plugins';
        if (!$this->helper->correctJsonFormat($form_data['param_value'], $must_json)) {
            exit(sprintf($this->wrong_json_format_str, 'Param Value'));
        }
        $must_json = trim($form_data['input_attribute']) != '';
        if (!$this->helper->correctJsonFormat($form_data['input_attribute'], $must_json)) {
            exit(sprintf($this->wrong_json_format_str, 'Input Attribute'));
        }

        $data['setting'] = $setting->update($form_data);

        $this->updateConfigFile();

        if ($form_data['return_to_the_list']) {
            return redirect()->route(
                'LaravelCmsAdminSettings.index',
                ['category' => $form_data['category']]
            );
        }
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
