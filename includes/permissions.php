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
        'articoli'=>['Articoli ed eventi',['view'=>'Consultare','write'=>'Creare, modificare e pubblicare','delete'=>'Eliminare']],
        'attivita'=>['Registro attività',['view'=>'Consultare il registro']],
        'partite'=>['Partite e prenotazioni',['view'=>'Consultare presenze','write'=>'Creare partite e aprire/chiudere prenotazioni','assegna'=>'Gestire presenze e assegnare sedie','export'=>'Esportare elenco presenze']],
        'area_soci'=>['Accessi area soci',['write'=>'Inviare, rinnovare e revocare gli accessi dei soci']],
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
        if ($module === 'area_soci') $result[] = 'soci.view';
    }
    return array_values(array_unique($result));
}

/** Elenco comuni pulito: accetta testo (uno per riga, o separati da virgola/punto e virgola) oppure array. */
function normalise_comuni(mixed $input): array
{
    if (is_string($input)) $input = preg_split('/[\r\n,;]+/', $input) ?: [];
    if (!is_array($input)) return [];
    $out = [];
    foreach ($input as $comune) {
        if (!is_string($comune)) continue;
        $comune = trim((string)preg_replace('/\s+/u', ' ', $comune));
        if ($comune === '' || mb_strlen($comune) > 100) continue;
        $out[mb_strtolower($comune)] = $comune;
    }
    return array_slice(array_values($out), 0, 40);
}

/** True se la migrazione 016 (colonna comuni_json) è stata eseguita. */
function comuni_scope_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $ready = (bool)get_db_connection()->query("SHOW COLUMNS FROM utenti_accessi LIKE 'comuni_json'")->fetch();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/** Comuni consentiti all'utente corrente, oppure null se non ha limiti (amministratori inclusi). */
function user_comuni_scope(): ?array
{
    $user = current_user();
    if (!$user || ($user['ruolo'] ?? '') === 'admin') return null;
    $comuni = $user['comuni'] ?? [];
    return $comuni ? array_values($comuni) : null;
}

function comune_in_scope(?string $comune, array $consentiti): bool
{
    $comune = mb_strtolower(trim((string)$comune));
    foreach ($consentiti as $c) if (mb_strtolower($c) === $comune) return true;
    return false;
}

/** Condizione SQL (con parametri nominali) da aggiungere alle query sui soci. */
function socio_scope_sql(string $alias = 's'): array
{
    $comuni = user_comuni_scope();
    if ($comuni === null) return ['', []];
    $names = [];
    $params = [];
    foreach ($comuni as $i => $comune) {
        $names[] = ":sc$i";
        $params["sc$i"] = $comune;
    }
    return [" AND TRIM($alias.comune) IN (" . implode(',', $names) . ')', $params];
}

function can(string $permission): bool
{
    $user = current_user();
    if (!$user) return false;
    if (($user['ruolo'] ?? '') === 'admin') return true;
    if ($permission === 'utenti.manage') return false;
    // Chi ha un limite per comune può usare solo la sezione Soci.
    if (!empty($user['comuni']) && !in_array($permission, ['soci.view','soci.write'], true)) return false;
    return in_array($permission,$user['permessi'] ?? [],true);
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
        '/impostazioni/mail.php'=>'utenti.manage',
        '/articoli/list.php'=>'articoli.view','/articoli/edit.php'=>'articoli.write','/articoli/delete.php'=>'articoli.delete',
        '/attivita/list.php'=>'attivita.view',
        '/partite/list.php'=>'partite.view','/partite/view.php'=>'partite.view','/partite/sostenitori.php'=>'partite.view',
        '/partite/edit.php'=>'partite.write','/partite/stato.php'=>'partite.write','/partite/sale.php'=>'partite.write',
        '/partite/prenotazione.php'=>'partite.assegna','/partite/export.php'=>'partite.export',
        '/soci/accesso.php'=>'area_soci.write','/soci/famiglia.php'=>'area_soci.write',
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

/**
 * Limite per comune: chi lo ha vede solo i soci dei comuni assegnati e solo la sezione Soci.
 * Ogni altra pagina (dashboard compresa) è chiusa, perché mostrerebbe dati di tutti i soci.
 */
function enforce_comuni_scope(string $route): void
{
    $comuni = user_comuni_scope();
    if ($comuni === null) return;

    if (str_ends_with($route,'/dashboard.php')) {
        header('Location: ' . (function_exists('base_path') ? base_path('/soci/list.php') : '/soci/list.php'));
        exit;
    }

    $deny = function (string $title, string $text) use ($route): void {
        audit_event('sicurezza','accesso_negato',null,null,['ambito'=>'comuni','pagina'=>$route]);
        render_error_page($title, $text, 403);
    };

    if (!in_array($route, ['/soci/list.php','/soci/view.php','/soci/edit.php','/soci/create.php'], true)) {
        $deny('Sezione non disponibile', 'Il tuo account è limitato ai soci dei comuni assegnati: questa sezione non è abilitata.');
    }

    if (in_array($route, ['/soci/view.php','/soci/edit.php'], true)) {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = get_db_connection()->prepare('SELECT comune FROM soci WHERE id_socio = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row && !comune_in_scope($row['comune'] ?? null, $comuni)) {
                $deny('Socio non disponibile', 'Questo socio non rientra nei comuni assegnati al tuo account.');
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($route, ['/soci/edit.php','/soci/create.php'], true)) {
        $nuovo = is_string($_POST['comune'] ?? null) ? $_POST['comune'] : '';
        if (!comune_in_scope($nuovo, $comuni)) {
            $deny('Comune non consentito', 'Puoi indicare solo questi comuni: ' . implode(', ', $comuni) . '. Torna indietro e correggi il campo Comune.');
        }
    }
}

function enforce_route_permission(): void
{
    $route = request_route();
    enforce_comuni_scope($route);
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
