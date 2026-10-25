<?php
/**
 * Audit delle scritture effettive. Mai valori SQL, password o testi dei messaggi.
 * Il record di audit partecipa alla stessa transazione della modifica.
 */
class ActivityPDO extends PDO
{
    public bool $auditBusy = false;
    private ?bool $ready = null;
    private ?string $domainInsertId = null;
    private bool $auditFailed = false;

    public function securityReady(): bool
    {
        if ($this->ready === null) {
            $stmt = parent::query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('utenti_accessi','registro_attivita')");
            $this->ready = (int)$stmt->fetchColumn() === 2;
        }
        return $this->ready;
    }

    public function record(string $module, string $action, ?string $entity = null, ?string $id = null, array $details = [], ?string $author = null): void
    {
        if (!$this->securityReady()) return;
        static $request;
        $request ??= bin2hex(random_bytes(16));
        $user = $_SESSION['user'] ?? null;
        $this->auditBusy = true;
        try {
            $stmt = $this->prepare('INSERT INTO registro_attivita (data_evento,id_utente,autore,modulo,azione,entita,id_entita,dettagli_json,richiesta) VALUES (UTC_TIMESTAMP(),?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $user['id_utente'] ?? null,
                mb_substr($author ?? ($user['username'] ?? (PHP_SAPI === 'cli' ? 'Sistema / CLI' : 'Visitatore sito')),0,180),
                $module,$action,$entity,$id,
                $details ? json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) : null,$request,
            ]);
        } catch (Throwable $e) {
            $this->auditFailed = true;
            throw $e;
        } finally { $this->auditBusy = false; }
    }

    public function mutation(string $sql, callable $execute, ?array $params = null, ?callable $rowCount = null): mixed
    {
        $match = [];
        if ($this->auditBusy || !preg_match('/^\s*(INSERT(?:\s+IGNORE)?\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-z_]+)`?/i',$sql,$match)
            || $match[2] === 'registro_attivita' || !$this->securityReady()
            || (preg_match('/^\s*UPDATE\s+utenti_admin\s+SET\s+ultimo_accesso\b/i',$sql))) return $execute();
        $table = strtolower($match[2]);
        $modules = ['utenti_admin'=>'utenti','utenti_accessi'=>'utenti','soci'=>'soci','tesseramenti'=>'tesseramenti',
            'pagamenti_tesseramenti'=>'pagamenti','prima_nota'=>'prima_nota','messaggi_whatsapp'=>'whatsapp',
            'template_whatsapp'=>'whatsapp','richieste_contatto'=>'richieste','importazioni'=>'importazioni','righe_importazione'=>'importazioni',
            'stagioni'=>'configurazione','tipologie_tesseramento'=>'configurazione'];
        if (!isset($modules[$table])) return $execute();
        $own = !$this->inTransaction();
        if ($own) $this->beginTransaction();
        try {
            $result = $execute();
            if ($result === false) throw new RuntimeException('Scrittura non riuscita.');
            $insert = str_starts_with(strtoupper($match[1]),'INSERT') || str_starts_with(strtoupper($match[1]),'REPLACE');
            $id = null;
            if ($insert) {
                $this->domainInsertId = parent::lastInsertId();
                if ($this->domainInsertId !== '0') $id = $this->domainInsertId;
                if ($table === 'utenti_accessi' && isset($params[0])) $id = (string)$params[0];
            } elseif ($params && preg_match('/\bWHERE\s+`?\w+`?\s*=\s*(?::(\w+)|(\?))/i',$sql,$where,PREG_OFFSET_CAPTURE)) {
                $key = isset($where[1]) && $where[1][0] !== '' ? $where[1][0] : substr_count(substr($sql,0,$where[2][1]),'?');
                $value = $params[$key] ?? $params[':'.$key] ?? null;
                if (is_scalar($value)) $id = mb_substr((string)$value,0,80);
            }
            $rows = $rowCount ? $rowCount() : (is_int($result) ? $result : null);
            $action = $insert ? (preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i',$sql) ? 'salvataggio' : 'creazione') : (str_starts_with(strtoupper($match[1]),'DELETE') ? 'eliminazione' : 'modifica');
            if ($action === 'salvataggio' && $rows === 2) $rows = 1;
            if ($rows !== 0) $this->record($modules[$table],$action,$table,$id,$rows === null ? [] : ['righe'=>$rows]);
            if ($own) $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($own && $this->inTransaction()) $this->rollBack();
            throw $e;
        }
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $name === null && $this->domainInsertId !== null ? $this->domainInsertId : parent::lastInsertId($name);
    }

    public function commit(): bool
    {
        if ($this->auditFailed) {
            if ($this->inTransaction()) parent::rollBack();
            $this->auditFailed = false;
            throw new RuntimeException('Registro attività non disponibile: modifica annullata.');
        }
        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->auditFailed = false;
        return parent::rollBack();
    }

    public function exec(string $statement): int|false
    {
        return $this->mutation($statement,fn()=>parent::exec($statement));
    }
}

class ActivityStatement extends PDOStatement
{
    protected function __construct(private ActivityPDO $connection) {}
    public function execute(?array $params = null): bool
    {
        return $this->connection->mutation($this->queryString,function() use($params) {
            $ok = parent::execute($params);
            return $ok;
        },$params,fn()=>$this->rowCount());
    }
}

function audit_event(string $module, string $action, ?string $entity = null, ?string $id = null, array $details = [], ?string $author = null): void
{
    $pdo = get_db_connection();
    if ($pdo instanceof ActivityPDO) $pdo->record($module,$action,$entity,$id,$details,$author);
}
