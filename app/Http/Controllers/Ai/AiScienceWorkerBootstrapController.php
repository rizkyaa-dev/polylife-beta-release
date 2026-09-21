<?php

namespace App\Http\Controllers\Ai;

use Illuminate\Http\Response;

/** Dev-only same-origin entry point; never proxies requests or accepts caller URLs. */
final class AiScienceWorkerBootstrapController
{
    public function __invoke(): Response
    {
        abort_unless(app()->environment('local') && is_file(public_path('hot')), 404);
        $origin = rtrim(trim(file_get_contents(public_path('hot'))), '/');
        $allowed = config('security.headers.csp.dev_hosts', []);
        $allowed = is_array($allowed) ? $allowed : explode(',', $allowed);
        abort_unless(in_array($origin, array_map('trim', $allowed), true)
            && in_array(parse_url($origin, PHP_URL_SCHEME), ['http', 'https'], true)
            && ! parse_url($origin, PHP_URL_USER) && ! parse_url($origin, PHP_URL_PASS)
            && ! parse_url($origin, PHP_URL_QUERY) && ! parse_url($origin, PHP_URL_FRAGMENT), 503);
        $module = json_encode($origin.'/resources/js/ai/science/client/worker.js', JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);

        // Buffer messages until the imported module installs its handler.
        $source = "const pending=[]; self.onmessage=e=>pending.push(e);\n"
            ."import($module).then(()=>{const handle=self.onmessage; for(const e of pending) handle(e); pending.length=0;})"
            .".catch(()=>self.postMessage({failure:'unavailable'}));\n";

        return response($source, 200, ['Content-Type' => 'text/javascript; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
