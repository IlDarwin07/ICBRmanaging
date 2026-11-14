<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/whatsapp_transport.php';

/** Internal service. HTTP callers must authenticate, verify CSRF and supply the session actor, never a posted actor ID. */
final class ICBRWhatsAppDispatch
{
    public function __construct(private PDO $db, private int $actor) {}
    private function transaction(callable $work): mixed {
        if ($this->db->inTransaction()) throw new LogicException('Transazione già aperta.');
        $this->db->beginTransaction();
        try { $result = $work(); $this->db->commit(); return $result; }
        catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function authorize(): void {
        $s = $this->db->prepare('SELECT u.ruolo,u.attivo,a.* FROM utenti_admin u LEFT JOIN utenti_accessi a ON a.id_utente=u.id_utente WHERE u.id_utente=? FOR UPDATE');
        $s->execute([$this->actor]); $u = $s->fetch(PDO::FETCH_ASSOC);
        if (!$u || !(int)$u['attivo']) throw new RuntimeException('Operatore non abilitato.');
        if ($u['ruolo'] === 'admin') return;
        $permissions = json_decode($u['permessi_json'] ?? '[]', true);
        $scope = json_decode($u['comuni_json'] ?? '[]', true);
        if (!is_array($permissions) || !in_array('whatsapp.send', $permissions, true) || !is_array($scope) || $scope) throw new RuntimeException('Permesso whatsapp.send richiesto, senza limitazioni per comune.');
    }
    private function audit(string $action, int $id): void {
        if ($this->db instanceof ActivityPDO) $this->db->record('whatsapp', $action, 'wa_lotti', (string)$id);
    }
    private function settings(): array {
        $c = icbr_wa_validate_settings(icbr_wa_read_settings($this->db));
        if (in_array('', $c, true)) throw new InvalidArgumentException('Completa gli identificativi Meta e la versione API.');
        return $c;
    }
    private function model(int $id): array {
        $s = $this->db->prepare('SELECT * FROM wa_modelli WHERE id_modello=? FOR UPDATE'); $s->execute([$id]); $m = $s->fetch(PDO::FETCH_ASSOC);
        if (!$m || !(int)$m['attivo'] || !$m['verificato_il'] || !$m['verificato_da']) throw new InvalidArgumentException('Modello inattivo o non verificato manualmente.');
        $mapping = json_decode($m['parametri_json'], true, 32, JSON_THROW_ON_ERROR);
        $allowed = ['nome','cognome','nome_completo','telefono','quota','pagato','residuo','stagione','data'];
        if (!is_array($mapping) || !array_is_list($mapping) || count($mapping)>20) throw new InvalidArgumentException('Parametri modello non validi.');
        foreach ($mapping as $key) if (!is_string($key) || !in_array($key,$allowed,true)) throw new InvalidArgumentException('Parametro non consentito.');
        preg_match_all('/\{\{([1-9][0-9]*)\}\}/', $m['testo_anteprima'], $found);
        $positions = array_values(array_unique(array_map('intval',$found[1]))); sort($positions);
        $rest = preg_replace('/\{\{([1-9][0-9]*)\}\}/','',$m['testo_anteprima']);
        if ($positions !== ($mapping ? range(1,count($mapping)) : []) || str_contains($rest,'{{') || str_contains($rest,'}}')) throw new InvalidArgumentException('Segnaposto modello incoerenti.');
        return ['name'=>$m['nome_meta'],'language'=>$m['lingua'],'body'=>$m['testo_anteprima'],'mapping'=>$mapping];
    }
    private function recipient(int $enrolment, int $season, string $filter, array $model, string $date): array {
        $s = $this->db->prepare("SELECT t.*,s.nome,s.cognome,s.telefono,s.attivo_record,st.codice_stagione,tip.quota_standard,
          (SELECT COALESCE(SUM(CASE WHEN p.tipo_pagamento='rimborso' THEN -p.importo ELSE p.importo END),0) FROM pagamenti_tesseramenti p WHERE p.id_tesseramento=t.id_tesseramento) AS versato
          FROM tesseramenti t JOIN soci s ON s.id_socio=t.id_socio JOIN stagioni st ON st.id_stagione=t.id_stagione
          LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia=t.id_tipologia WHERE t.id_tesseramento=? FOR UPDATE");
        $s->execute([$enrolment]); $r=$s->fetch(PDO::FETCH_ASSOC);
        if (!$r || !(int)$r['attivo_record'] || (int)$r['id_stagione']!==$season) throw new InvalidArgumentException('Tesseramento non disponibile nella stagione selezionata.');
        $phone=wa_phone($r['telefono']);
        $s=$this->db->prepare('SELECT telefono,stato FROM wa_consensi WHERE id_socio=? FOR UPDATE'); $s->execute([$r['id_socio']]); $c=$s->fetch(PDO::FETCH_ASSOC);
        if (!$phone || !$c || $c['stato']!=='concesso' || $c['telefono']!==$phone) throw new InvalidArgumentException('Consenso mancante, revocato o riferito a un altro numero.');
        $quota=tess_quota($r); $paid=(float)$r['versato']; $state=stato_quota($quota,$paid)['code'];
        if (($filter==='residuo' && !in_array($state,['da_pagare','acconto'],true)) || ($filter!=='' && $filter!=='residuo' && $filter!==$state)) throw new InvalidArgumentException('Lo stato pagamento non corrisponde più ai filtri.');
        $vars=['nome'=>$r['nome'],'cognome'=>$r['cognome'],'nome_completo'=>trim($r['nome'].' '.$r['cognome']),'telefono'=>$phone,'quota'=>$quota===null?'n/d':euro($quota),'pagato'=>euro($paid),'residuo'=>$quota===null?'n/d':euro(tess_residuo($quota,$paid)),'stagione'=>$r['codice_stagione'],'data'=>$date];
        $parameters=[]; $replace=[];
        foreach ($model['mapping'] as $i=>$key) { $parameters[]=$vars[$key]; $replace['{{'.($i+1).'}}']=$vars[$key]; }
        return ['socio'=>(int)$r['id_socio'],'telefono'=>$phone,'payload'=>icbr_wa_template_payload($phone,$model['name'],$model['language'],$parameters),'testo'=>strtr($model['body'],$replace)];
    }
    public function prepare(int $season, int $modelId, array $enrolments, string $filter=''): array {
        $valid=['','residuo','da_pagare','acconto','saldato','eccedenza','quota_sconosciuta','anomalia_rimborso'];
        if ($season<1 || $modelId<1 || !$enrolments || count($enrolments)>500 || !in_array($filter,$valid,true)) throw new InvalidArgumentException('Stagione, modello, filtro o selezione non validi (massimo 500).');
        foreach ($enrolments as $id) if (!is_int($id) || $id<1) throw new InvalidArgumentException('ID tesseramento non valido.');
        $enrolments=array_values(array_unique($enrolments)); sort($enrolments);
        return $this->transaction(function() use($season,$modelId,$enrolments,$filter) {
            $this->authorize(); $model=$this->model($modelId); $settings=$this->settings(); $date=date('d/m/Y'); $recipients=[]; $phones=[];
            foreach ($enrolments as $id) {
                $r=$this->recipient($id,$season,$filter,$model,$date);
                if (isset($phones[$r['telefono']])) throw new InvalidArgumentException('Numero condiviso fra soci: scegli esplicitamente un solo destinatario per numero.');
                $phones[$r['telefono']]=true; $recipients[$id]=$r;
            }
            $token=bin2hex(random_bytes(32));
            $snapshot=json_encode(['model'=>$model,'settings'=>$settings,'date'=>$date],JSON_THROW_ON_ERROR);
            $s=$this->db->prepare("INSERT INTO wa_lotti (token_hash,id_utente,id_stagione,id_modello,modello_json,filtri_json,scade_il) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))");
            $s->execute([hash('sha256',$token),$this->actor,$season,$modelId,$snapshot,json_encode(['stato'=>$filter],JSON_THROW_ON_ERROR)]);
            $batch=(int)$this->db->query('SELECT LAST_INSERT_ID()')->fetchColumn();
            $s=$this->db->prepare('INSERT INTO wa_inviati (id_lotto,id_socio,id_tesseramento,telefono,payload_json,testo_anteprima) VALUES (?,?,?,?,?,?)');
            foreach ($recipients as $id=>$r) $s->execute([$batch,$r['socio'],$id,$r['telefono'],json_encode($r['payload'],JSON_THROW_ON_ERROR),$r['testo']]);
            $this->audit('lotto_preparato',$batch);
            return ['id_lotto'=>$batch,'token'=>$token,'destinatari'=>array_values($recipients)];
        });
    }
    private function batch(int $id, string $token): array {
        $s=$this->db->prepare('SELECT *,scade_il>NOW() AS valido FROM wa_lotti WHERE id_lotto=? FOR UPDATE'); $s->execute([$id]); $b=$s->fetch(PDO::FETCH_ASSOC);
        if (!$b || (int)$b['id_utente']!==$this->actor || !preg_match('/^[a-f0-9]{64}$/D',$token) || !hash_equals($b['token_hash'],hash('sha256',$token))) throw new RuntimeException('Lotto o token non disponibile per questo operatore.');
        return $b;
    }
    private function revalidate(array $b, array $message): array {
        $snap=json_decode($b['modello_json'],true,32,JSON_THROW_ON_ERROR); $filter=json_decode($b['filtri_json'],true,32,JSON_THROW_ON_ERROR);
        if ($snap['settings']!==$this->settings() || $snap['model']!==$this->model((int)$b['id_modello'])) throw new InvalidArgumentException('Configurazione o modello cambiati: rigenera l’anteprima.');
        $r=$this->recipient((int)$message['id_tesseramento'],(int)$b['id_stagione'],$filter['stato'],$snap['model'],$snap['date']);
        if ($r['socio']!==(int)$message['id_socio'] || $r['telefono']!==$message['telefono'] || $r['testo']!==$message['testo_anteprima'] || $r['payload']!==json_decode($message['payload_json'],true,32,JSON_THROW_ON_ERROR)) throw new InvalidArgumentException('Dati del destinatario cambiati: rigenera l’anteprima.');
        return ['settings'=>$snap['settings'],'payload'=>$r['payload']];
    }
    public function confirm(int $id, string $token): bool {
        return $this->transaction(function() use($id,$token) {
            $this->authorize(); $b=$this->batch($id,$token);
            if ($b['stato']==='confermato') return false;
            if ($b['stato']!=='anteprima' || !(int)$b['valido']) throw new InvalidArgumentException('Anteprima scaduta o annullata.');
            $s=$this->db->prepare('SELECT * FROM wa_inviati WHERE id_lotto=? ORDER BY id_invio FOR UPDATE'); $s->execute([$id]); $rows=$s->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) throw new InvalidArgumentException('Lotto senza destinatari.');
            foreach ($rows as $r) $this->revalidate($b,$r);
            $this->db->prepare("UPDATE wa_lotti SET stato='confermato',confermato_il=NOW() WHERE id_lotto=? AND stato='anteprima'")->execute([$id]);
            $this->audit('lotto_confermato',$id); return true;
        });
    }
    /** One explicit dispatch step, no scheduler and no automatic retries. Mock transport is CLI-test-only. */
    public function sendOne(int $id, string $token, ?callable $testSender=null): ?array {
        if ($testSender!==null && (PHP_SAPI!=='cli' || getenv('ICBR_WA_DB_TEST')!=='1')) throw new LogicException('Trasporto simulato riservato ai test CLI.');
        if ($testSender===null && (getenv('ICBR_WA_SEND_ENABLED')!=='1' || !getenv('ICBR_WA_ACCESS_TOKEN') || !function_exists('curl_init'))) throw new RuntimeException('Invio disabilitato o trasporto non configurato.');
        $job=$this->transaction(function() use($id,$token) {
            $this->authorize(); $b=$this->batch($id,$token);
            if ($b['stato']!=='confermato' || !(int)$b['valido']) throw new InvalidArgumentException('Lotto non confermato o finestra di invio scaduta.');
            $s=$this->db->prepare("SELECT * FROM wa_inviati WHERE id_lotto=? AND stato='preparato' ORDER BY id_invio LIMIT 1 FOR UPDATE"); $s->execute([$id]); $m=$s->fetch(PDO::FETCH_ASSOC);
            if (!$m) return null;
            try { $data=$this->revalidate($b,$m); }
            catch (InvalidArgumentException $e) {
                $this->db->prepare("UPDATE wa_inviati SET stato='escluso',motivo=? WHERE id_invio=? AND stato='preparato'")->execute(['Idoneità o dati modificati dopo l’anteprima.', $m['id_invio']]);
                $this->audit('destinatario_escluso',$id); return ['id'=>(int)$m['id_invio'],'excluded'=>true];
            }
            $s=$this->db->prepare("UPDATE wa_inviati SET stato='in_corso',tentato_il=NOW() WHERE id_invio=? AND stato='preparato'"); $s->execute([$m['id_invio']]);
            if ($s->rowCount()!==1) throw new RuntimeException('Messaggio già acquisito.');
            $this->audit('invio_acquisito',$id); return ['id'=>(int)$m['id_invio']]+$data;
        });
        if ($job===null) return null;
        if (!empty($job['excluded'])) return ['id_invio'=>$job['id'],'stato'=>'escluso'];
        $result=['state'=>'uncertain','message_id'=>null,'http_status'=>0,'error_code'=>null];
        try {
            $p=$job['payload']; $parameters=array_column($p['template']['components'][0]['parameters'] ?? [],'text');
            $result=$testSender!==null ? $testSender($job['settings'],$p) : icbr_wa_send_template($job['settings'],$p['to'],$p['template']['name'],$p['template']['language']['code'],$parameters);
        } catch (Throwable $e) { /* Outcome may be ambiguous: never requeue automatically. */ }
        $messageId=$result['message_id'] ?? null;
        $accepted=($result['state'] ?? '')==='accepted' && is_string($messageId) && preg_match('/^[\x21-\x7E]{1,255}$/D',$messageId);
        $state=$accepted ? 'accettato' : (($result['state'] ?? '')==='rejected' ? 'fallito' : 'incerto');
        $http=$result['http_status'] ?? 0; $http=is_int($http) && $http>=0 && $http<=599 ? $http : 0;
        $code=$result['error_code'] ?? null; $code=is_int($code) && $code>=0 && $code<=2147483647 ? $code : null;
        $this->transaction(function() use($job,$state,$accepted,$messageId,$http,$code,$id) {
            $s=$this->db->prepare("UPDATE wa_inviati SET stato=?,meta_message_id=?,http_status=?,errore_codice=? WHERE id_invio=? AND stato='in_corso'");
            $s->execute([$state,$accepted?$messageId:null,$http,$code,$job['id']]);
            if ($s->rowCount()!==1) throw new RuntimeException('Esito non salvato: verificare il messaggio senza ripetere l’invio.');
            $this->audit('invio_esito_registrato',$id);
        });
        return ['id_invio'=>$job['id'],'stato'=>$state];
    }
}
