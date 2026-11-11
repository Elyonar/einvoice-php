<?php

// @recipe states-and-lgas States and LGAs
// @summary A Nigerian address uses the tax authority's own state and LGA codes. Read both lists, fill an LGA picker from the chosen state, save a buyer with the codes, and handle a value that is not on the lists.

declare(strict_types=1);

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ValidationException;

// @step client Create the client
$yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);

// @step lists Read the states and the LGAs
// @text Both lists are free to read and change rarely, so read them once and keep them. A state has code and name. An LGA has code, name and stateCode, where stateCode is its state's code.
// @op getInvoiceReferenceList
$states = $yona->reference->getResource('states')['items'];
$lgas = $yona->reference->getResource('lgas')['items'];
echo count($states), ' states, ', count($lgas), " LGAs\n";

// @step picker Fill the LGA picker from the chosen state
// @text Filter the LGAs by the chosen state's code. Keep the code, never the name alone: a few LGA names exist in two states, and a code cannot be worked out from a name.
$lagos = array_values(array_filter($states, fn (array $state): bool => $state['name'] === 'Lagos'))[0] ?? null;
if ($lagos === null) {
    throw new RuntimeException('Lagos is not on the list of states');
}
$lgasOfLagos = array_values(array_filter($lgas, fn (array $lga): bool => $lga['stateCode'] === $lagos['code']));
$ikeja = array_values(array_filter($lgasOfLagos, fn (array $lga): bool => $lga['name'] === 'Ikeja'))[0] ?? null;
if ($ikeja === null) {
    throw new RuntimeException('Ikeja is not on the list of LGAs for Lagos');
}
echo $lagos['code'], ' ', $ikeja['code'], ' (', count($lgasOfLagos), " LGAs in Lagos)\n";

// @step buyer Save a buyer with the codes
// @text Send the state and LGA codes in the buyer's address. The exact names are accepted too and are stored as the codes; the answer always carries the codes. Both fields are optional.
// @op createBuyer
$buyer = $yona->buyers->create([
    'name' => 'Acme Trading ' . bin2hex(random_bytes(4)),
    'partyType' => 'individual',
    'address' => ['line1' => '12 Allen Avenue', 'city' => 'Ikeja', 'state' => $lagos['code'], 'lga' => $ikeja['code'], 'country' => 'NG'],
]);
$saved = $buyer['address'] ?? [];
if (($saved['state'] ?? null) !== $lagos['code'] || ($saved['lga'] ?? null) !== $ikeja['code']) {
    throw new RuntimeException('the buyer did not keep the codes');
}
echo 'buyer ', $buyer['id'], ' ', $saved['state'], ' ', $saved['lga'], "\n";

// @step refused Handle a value that is not on the lists
// @text A state that is not on the list is refused with VAL202. An LGA that is not on the list, or that belongs to another state, is refused with VAL203. Nothing is saved, and errors names the field.
// @op updateBuyer
$elsewhere = array_values(array_filter($lgas, fn (array $lga): bool => $lga['stateCode'] !== $lagos['code']))[0] ?? null;
if ($elsewhere === null) {
    throw new RuntimeException('every LGA is in Lagos');
}
try {
    $yona->buyers->update($buyer['id'], [
        'address' => ['line1' => '12 Allen Avenue', 'city' => 'Ikeja', 'state' => $lagos['code'], 'lga' => $elsewhere['code'], 'country' => 'NG'],
    ]);
    throw new RuntimeException('an LGA of another state was accepted');
} catch (ValidationException $err) {
    if ($err->errorCode !== 'VAL203') {
        throw $err;
    }
    echo 'refused: ', $err->errorCode, "\n";
    foreach ($err->errors as $e) {
        echo "  {$e->field}: {$e->message}\n";
    }
}

// @step tidy Remove the example buyer
// @op deleteBuyer
$yona->buyers->delete($buyer['id']);
