<?php
function permission_catalog(): array
{
    return [
        'soci'=>['Soci',['view'=>'Consultare','write'=>'Creare e modificare']],
        'tesseramenti'=>['Tesseramenti e quote',['view'=>'Consultare','write'=>'Creare e modificare']],
        'pagamenti'=>['Pagamenti tesseramenti',['write'=>'Registrare','delete'=>'Eliminare']],
        'prima_nota'=>['Prima nota',['view'=>'Consultare','write'=>'Registrare e modificare','delete'=>'Eliminare','export'=>'Esportare CSV']],
        'whatsapp'=>['WhatsApp e template',['view'=>'Consultare','write'=>'Preparare e aggiornare','delete'=>'Eliminare']],
        'richieste'=>['Contatti dal sito',['view'=>'Consultare','write'=>'Gestire lo stato','delete'=>'Eliminare']],
        'importazioni'=>['Importazioni',['write'=>'Importare soci e tesseramenti, consultare esiti']],
        'configurazione'=>['Stagioni e listini',['view'=>'Consultare','write'=>'Modificare']],
        'sito'=>['Sito vetrina',['write'=>'Modificare e pubblicare']],
        'attivita'=>['Registro attività',['view'=>'Consultare il registro']],
    ];
}

function security_ready(): bool
{
    return get_db_connection()->securityReady();
}

function normalise_permissions(array $input): array
{
    $valid = [];
    foreach (permission_catalog() as $module=>[$label,$actions]) foreach ($actions as $action=>$name) $valid[] = "$module.$action";
    $result = array_values(array_intersect($valid,array_filter($input,'is_string')));
    foreach ($result as $permission) {
        [$module] = explode('.',$permission);
        if (in_array("$module.view",$valid,true)) $result[] = "$module.view";
        if ($module === 'pagamenti') $result[] = 'tesseramenti.view';
    }
    return array_values(array_unique($result));
}

function can(string $permission): bool
{
    $user = current_user();
    return $user && (($user['ruolo'] ?? '') === 'admin'
        || ($permission !== 'utenti.manage' && in_array($permission,$user['permessi'] ?? [],true)));
}

function permission_routes(): array
{
    $routes = [];
    foreach (['soci','tesseramenti','prima_nota'] as $module) {
        foreach (['list','view'] as $page) $routes["/$module/$page.php"] = "$module.view";
        foreach (['create','edit'] as $page) $routes["/$module/$page.php"] = "$module.write";
    }
    $routes += [
        '/tesseramenti/pagamento_add.php'=>'pagamenti.write','/tesseramenti/pagamento_delete.php'=>'pagamenti.delete',
        '/prima_nota/delete.php'=>'prima_nota.delete','/prima_nota/export.php'=>'prima_nota.export',
        '/richieste/list.php'=>'richieste.view','/richieste/view.php'=>'richieste.view',
        '/sito/edit.php'=>'sito.write','/utenti/list.php'=>'utenti.manage','/utenti/edit.php'=>'utenti.manage',
        '/attivita/list.php'=>'attivita.view',
    ];
    foreach (['stagioni','tipologie'] as $module) foreach (['list','create','edit'] as $p) $routes["/$module/$p.php"] = $p === 'list' ? 'configurazione.view' : 'configurazione.write';
    foreach (['index','list','template_list','direct','compose','edit','template_create','template_edit','template_delete'] as $p) {
        $routes["/messaggi/$p.php"] = in_array($p,['index','list','template_list'],true) ? 'whatsapp.view' : ($p === 'template_delete' ? 'whatsapp.delete' : 'whatsapp.write');
    }
    foreach (['upload','mapping','process','log'] as $p) $routes["/importazioni/$p.php"] = 'importazioni.write';
    return $routes;
}

function request_route(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (array_keys(permission_routes()) as $route) if (str_ends_with($script,$route)) return $route;
    return $script;
}

function enforce_route_permission(): void
{
    $route = request_route();
    $permission = permission_routes()[$route] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';
        if ($route === '/messaggi/list.php') $permission = $action === 'elimina' ? 'whatsapp.delete' : 'whatsapp.write';
        if ($route === '/richieste/view.php') $permission = $action === 'elimina' ? 'richieste.delete' : 'richieste.write';
    }
    if (!$permission && !str_ends_with($route,'/dashboard.php')) $permission = 'nessun_permesso_configurato';
    if ($permission) require_permission($permission);
}

function require_permission(string $permission): void
{
    if (!can($permission)) {
        audit_event('sicurezza','accesso_negato',null,null,['permesso'=>$permission]);
        render_error_page('Questa operazione non è abilitata', 'Il tuo account non dispone di questo permesso. Chiedi a un amministratore del club.',403);
    }
}
