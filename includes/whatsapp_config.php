<?php
declare(strict_types=1);

function icbr_wa_validate_settings(array $input): array
{
    $out = [];
    foreach (['phone_number_id', 'business_account_id'] as $key) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || ($value !== '' && !preg_match('/^[1-9][0-9]{4,39}$/D', $value))) {
            throw new InvalidArgumentException('Identificativo Meta non valido. Usa le cifre dell’ID, non il numero telefonico.');
        }
        $out[$key] = $value;
    }
    $version = $input['graph_version'] ?? '';
    if (!is_string($version) || ($version !== '' && !preg_match('/^v[1-9][0-9]{0,2}\.[0-9]{1,2}$/D', $version))) {
        throw new InvalidArgumentException('Versione Graph non valida: usa il formato vNN.N indicato da Meta.');
    }
    $out['graph_version'] = $version;
    return $out;
}

function icbr_wa_settings_ready(PDO $pdo): bool
{
    return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'impostazioni'")->fetchColumn() === 1;
}

function icbr_wa_read_settings(PDO $pdo): array
{
    $values = ['phone_number_id' => '', 'business_account_id' => '', 'graph_version' => ''];
    if (!icbr_wa_settings_ready($pdo)) return $values;
    $stmt = $pdo->prepare('SELECT chiave, valore FROM impostazioni WHERE chiave IN (?, ?, ?)');
    $stmt->execute(['wa_phone_number_id', 'wa_business_account_id', 'wa_graph_version']);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = substr($row['chiave'], 3);
        if (array_key_exists($key, $values)) $values[$key] = $row['valore'];
    }
    return $values;
}

function icbr_wa_save_settings(PDO $pdo, array $input, int $actor): void
{
    $values = icbr_wa_validate_settings($input);
    if ($actor < 1) throw new InvalidArgumentException('Operatore non valido.');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO impostazioni (chiave, valore, aggiornata_da) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore), aggiornata_da = VALUES(aggiornata_da)');
        foreach ($values as $key => $value) $stmt->execute(['wa_' . $key, $value, $actor]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
