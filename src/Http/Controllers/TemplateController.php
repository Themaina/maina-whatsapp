<?php

namespace Maina888\Whatsapp\Http\Controllers;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Setting\Facades\Setting;
use Maina888\Whatsapp\Models\WhatsappTemplate;
use Maina888\Whatsapp\Services\WhatsappService;
use Maina888\Whatsapp\Supports\TemplateRegistry;
use Maina888\Whatsapp\Supports\WhatsappSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class TemplateController extends BaseController
{
    public function index()
    {
        $this->pageTitle(trans('plugins/whatsapp::whatsapp.templates.title'));

        $definitions = TemplateRegistry::all();

        $mappings = [];

        foreach ($definitions as $key => $definition) {
            $mappings[$key] = WhatsappSettings::templateMapping($key, $definition);
        }

        $metaTemplates = WhatsappTemplate::query()->orderBy('name')->orderBy('language')->get();

        $groups = collect($definitions)->groupBy('group', true);

        return view('plugins/whatsapp::templates.index', compact('definitions', 'mappings', 'metaTemplates', 'groups'));
    }

    public function update(Request $request): BaseHttpResponse
    {
        $request->validate([
            'templates' => ['nullable', 'array'],
            'templates.*.name' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9_]*$/'],
            'templates.*.language' => ['nullable', 'string', 'max:15', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'templates.*.enabled' => ['nullable', 'in:0,1'],
        ]);

        $definitions = TemplateRegistry::all();
        $input = (array) $request->input('templates', []);

        foreach ($definitions as $key => $definition) {
            $row = (array) Arr::get($input, $key, []);

            Setting::set(WhatsappSettings::templateSettingKey($key, 'enabled'), Arr::get($row, 'enabled') ? '1' : '0');
            Setting::set(WhatsappSettings::templateSettingKey($key, 'name'), trim((string) Arr::get($row, 'name', '')));
            Setting::set(WhatsappSettings::templateSettingKey($key, 'language'), trim((string) Arr::get($row, 'language', '')));
        }

        Setting::save();

        return $this
            ->httpResponse()
            ->setPreviousRoute('whatsapp.templates.index')
            ->withUpdatedSuccessMessage();
    }

    public function sync(WhatsappService $service): BaseHttpResponse
    {
        $response = $this->httpResponse()->setPreviousRoute('whatsapp.templates.index');

        try {
            $result = $service->syncTemplates();
        } catch (Throwable $exception) {
            BaseHelper::logError($exception);

            return $response
                ->setError()
                ->setMessage(trans('plugins/whatsapp::whatsapp.templates.sync_failed', ['error' => $exception->getMessage()]));
        }

        return $response->setMessage(trans('plugins/whatsapp::whatsapp.templates.synced', $result));
    }
}
