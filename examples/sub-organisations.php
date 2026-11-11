<?php

// @recipe sub-organisations Sub-organisations
// @summary For an organisation that runs several businesses: list its sub-organisations with an API key, see which are ready for live and connected to the tax authority, and create one with a live key.

declare(strict_types=1);

require 'vendor/autoload.php';

use Useyona\EInvoice\EInvoice;
use Useyona\EInvoice\Exception\ConflictException;
use Useyona\EInvoice\Exception\NotFoundException;
use Useyona\EInvoice\Exception\PermissionException;

// @step client Create the client
// @text Use a key of the parent organisation. No key preset includes the two sub-organisation permissions: an Owner or Admin ticks them under Sub-organisations when creating the key in the dashboard. Listing works in sandbox and live; creating needs a live key.
$yona = new EInvoice(['api_key' => (string) getenv('YONA_API_KEY')]);

// @step list List the sub-organisations
// @text Newest first, 100 a page at most, with the total in pagination. search keeps the ones whose name holds the text. The permission is organization.children.read.
// @op listChildOrganizations
try {
    $firstPage = $yona->organization->listSubOrganizations(['limit' => 100]);
} catch (PermissionException|NotFoundException|ConflictException $err) {
    // 403 AUTH019: the key does not hold organization.children.read. 404 RES001: sub-organisations are
    // not switched on here. 409 BIZ014: this organisation is itself a sub-organisation.
    $nothingToList =
        ($err instanceof PermissionException && $err->errorCode === 'AUTH019')
        || ($err instanceof NotFoundException && $err->errorCode === 'RES001')
        || ($err instanceof ConflictException && $err->errorCode === 'BIZ014');
    if (!$nothingToList) {
        throw $err;
    }
    echo 'this key cannot list sub-organisations: ', $err->errorCode, "\n";
    exit(0);
}
echo $firstPage->pagination['total'], " sub-organisations\n";

// @step paginate Walk every page
// @text An organisation can hold many sub-organisations. EInvoice::paginate fetches page after page for you.
// @op listChildOrganizations
$all = [];
foreach (EInvoice::paginate(fn (array $query) => $yona->organization->listSubOrganizations($query), ['limit' => 100]) as $sub) {
    $all[] = $sub;
    if (count($all) >= 300) {
        break;
    }
}
echo 'read ', count($all), "\n";

// @step readiness See which are ready for live
// @text The same page, with each sub-organisation's checklist, live status and next step. ids keeps only the ones you name (100 at most), given as a list.
// @op listChildOrganizationsReadiness
$ids = array_map(fn (array $sub): string => $sub['id'], $firstPage->data);
$readiness = $yona->organization->listSubOrganizationsReadiness($ids !== [] ? ['ids' => $ids] : ['limit' => 100]);
foreach ($readiness->data as $sub) {
    echo $sub['name'], ' ', $sub['liveStatus'], ' ', $sub['nextStep'], "\n";
}

// @step tax-connection Check their tax connections
// @text One item for each sub-organisation named: the state of its connection to the tax authority in the key's mode, and whether a submission would pass. The permission is tax_connection.read. An id that is not one of your sub-organisations is left out.
// @op listChildTaxConnections
if ($ids !== []) {
    $connections = $yona->taxConnection->listForSubOrganizations(['ids' => $ids]);
    foreach ($connections as $c) {
        echo $c['organizationId'], ' ', $c['state'], ' ', var_export($c['submissionsAllowed'], true), "\n";
    }
}

// @step create Create a sub-organisation
// @text With a live key that holds organization.children.create, this answers the new organisation; its status is pending_verification until it completes its own onboarding. A name is unique under its parent, so a repeat answers 409 RES002 and nothing is created twice. A sandbox key is refused, which is what this step shows in sandbox; with a live key it creates nothing here.
// @op createOrganization
if ($yona->mode() === 'sandbox') {
    try {
        $created = $yona->organization->createSubOrganization(['businessName' => 'Acme Retail Ltd', 'email' => 'accounts@acme.example']);
        throw new RuntimeException("a sandbox key created {$created['id']}");
    } catch (PermissionException $err) {
        echo 'refused in sandbox: ', $err->errorCode, "\n";
    }
}
