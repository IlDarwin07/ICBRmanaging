<?php
declare(strict_types=1);

/** Pure payload builder: no network, database access or implicit sends. */
function icbr_wa_template_payload(string $phone, string $template, string $language, array $bodyParameters = []): array
{
    if (!preg_match('/^[1-9][0-9]{6,14}$/D', $phone)) {
        throw new InvalidArgumentException('Numero richiesto in formato internazionale, solo cifre.');
    }
    if (!preg_match('/^[a-z0-9_]{1,512}$/D', $template)) {
        throw new InvalidArgumentException('Nome template Meta non valido.');
    }
    if (!preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $language)) {
        throw new InvalidArgumentException('Codice lingua non valido.');
    }
    if (array_values($bodyParameters) !== $bodyParameters) {
        throw new InvalidArgumentException('I parametri devono essere una lista ordinata.');
    }
    $parameters = [];
    foreach ($bodyParameters as $value) {
        if (!is_string($value) || trim($value) === '' || preg_match('//u', $value) !== 1 || strlen($value) > 4096) {
            throw new InvalidArgumentException('Parametro testuale vuoto, troppo lungo o non UTF-8.');
        }
        $parameters[] = ['type' => 'text', 'text' => $value];
    }
    $definition = ['name' => $template, 'language' => ['code' => $language]];
    if ($parameters) {
        $definition['components'] = [['type' => 'body', 'parameters' => $parameters]];
    }
    return [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'template',
        'template' => $definition,
    ];
}
