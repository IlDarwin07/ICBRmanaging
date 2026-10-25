<?php
/**
 * Esporta una SOLA anteprima visiva statica da un server locale dimostrativo.
 * Non distribuire sessioni, password, token o database nel bundle.
 * Esempio: ICBR_PREVIEW_PASSWORD=... php bin/build_visual_preview.php /path/output
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$output = $argv[1] ?? '';
if ($output === '' || !getenv('ICBR_PREVIEW_PASSWORD')) exit("Specificare output e ICBR_PREVIEW_PASSWORD.\n");
$base = 'http://127.0.0.1:8082';
$cookie = '';
function request(string $path, ?array $post = null): string {
    global $base, $cookie;
    $headers = "Cookie: $cookie\r\n";
    if ($post !== null) $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $context = stream_context_create(['http'=>['method'=>$post === null ? 'GET' : 'POST',
        'header'=>$headers,'content'=>$post === null ? '' : http_build_query($post),
        'follow_location'=>0,'ignore_errors'=>true]]);
    $body = file_get_contents($base . $path, false, $context);
    $responseHeaders = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
    foreach ($responseHeaders ?? [] as $line) {
        if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $line, $m)) $cookie = $m[1];
    }
    return $body === false ? '' : $body;
}
$anonymousLogin = request('/login.php');
preg_match('/name="csrf_token" value="([^"]+)"/', $anonymousLogin, $match);
request('/login.php', ['csrf_token'=>$match[1] ?? '', 'username'=>'demo','password'=>getenv('ICBR_PREVIEW_PASSWORD')]);
$dashboard = request('/dashboard.php');
if (!str_contains($dashboard, 'Anteprima con soli dati sintetici')) exit("Esportazione vietata: il server non è in modalità dimostrativa.\n");
$routes = [
    '/index.php'=>'index.html','/privacy.php'=>'privacy.html','/login.php'=>'area-riservata.html',
    '/dashboard.php'=>'dashboard.html','/soci/list.php'=>'soci.html','/soci/view.php?id=1'=>'socio.html',
    '/soci/create.php'=>'nuovo-socio.html','/tesseramenti/list.php'=>'tesseramenti.html',
    '/tesseramenti/view.php?id=1'=>'tesseramento.html','/prima_nota/list.php'=>'prima-nota.html',
    '/prima_nota/create.php'=>'nuovo-movimento.html','/messaggi/index.php'=>'whatsapp.html',
    '/messaggi/template_list.php'=>'template.html','/messaggi/template_create.php'=>'nuovo-template.html',
    '/messaggi/compose.php'=>'componi.html','/messaggi/direct.php'=>'messaggio-singolo.html',
    '/messaggi/list.php'=>'messaggi.html','/richieste/list.php'=>'richieste.html',
    '/richieste/view.php?id=1'=>'richiesta.html','/importazioni/upload.php'=>'importazioni.html',
    '/stagioni/list.php'=>'stagioni.html','/tipologie/list.php'=>'tipologie.html',
    '/sito/edit.php'=>'sito-vetrina.html',
    '/utenti/list.php'=>'direttivo.html','/utenti/edit.php'=>'nuovo-account.html',
    '/utenti/edit.php?id=2'=>'account-direttivo.html','/utenti/edit.php?id=1'=>'account-admin.html',
    '/attivita/list.php'=>'attivita.html',
];
if (!is_dir($output)) mkdir($output, 0755, true);
foreach ($routes as $path=>$filename) {
    $html = $path === '/login.php' ? $anonymousLogin : request($path);
    if ($html === '' || !str_contains($html, '</html>')) throw new RuntimeException("Pagina incompleta: $path");
    $html = preg_replace('/<input[^>]*name="(?:csrf_token|nonce|revision|versione)"[^>]*>/', '', $html);
    $html = preg_replace('/<div class="(?:preview-notice|admin-demo-note)">.*?<\/div>/s', '', $html);
    if ($path === '/login.php') {
        $html = preg_replace('/<form\b.*?<\/form>/s', '<p>Questa è un’anteprima visiva in sola lettura, non un accesso al gestionale reale.</p><a class="btn preview-enter" href="./dashboard.html">Esplora il gestionale dimostrativo ↗</a>', $html);
    }
    $html = preg_replace_callback('/\b(href|src|action)="([^"]*)"/', function($m) use($routes) {
        $value = html_entity_decode($m[2], ENT_QUOTES);
        if (str_starts_with($value, '/assets/')) return $m[1].'="./'.substr($value,1).'"';
        if (!str_starts_with($value, '/')) return $m[0];
        $parts = parse_url($value);
        $path = $parts['path'] ?? '';
        $exact = $path . (isset($parts['query']) ? '?'.$parts['query'] : '');
        $dest = $routes[$exact] ?? $routes[$path] ?? null;
        if ($path === '/logout.php') $dest = 'index.html';
        if ($dest) return $m[1].'="./'.$dest.(isset($parts['fragment'])?'#'.$parts['fragment']:'').'"';
        return $m[1].'="#" data-preview-only="true"';
    }, $html);
    $html = str_replace('</head>', '<link rel="stylesheet" href="./preview.css"><script src="./preview.js" defer></script></head>', $html);
    $html = preg_replace('/(<body[^>]*>)/', '$1<div class="visual-preview-banner">ANTEPRIMA VISIVA · dati sintetici · salvataggi e invii disabilitati · <a href="./index.html">Vetrina</a> / <a href="./dashboard.html">Gestionale</a></div>', $html, 1);
    file_put_contents($output.'/'.$filename, $html);
}
echo "Esportate ".count($routes)." pagine in sola lettura. Copiare public/assets nell’output.\n";
