<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/importdevis.php
 * 	\ingroup	importdevis
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
/*$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}*/

require '../config.php';
// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/importdevis.lib.php';

// Translations
$langs->load("importdevis@importdevis");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}
	
if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "importdevisSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = importdevisAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104740Name"),
    0,
    "importdevis@importdevis"
);

// Setup page goes here
$form=new Form($db);
$formabricot=new TFormCore;

$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";

$TFormat = array('SMARTBOM' => 'SMARTBOM');
$TOtherFormat = explode(',', $conf->global->IMPORTPROPAL_OTHERFORMAT);
if (count($TOtherFormat) > 0)
{
	foreach ($TOtherFormat as $format)
	{
		if (empty($format)) continue;
		
		$TFormat[$format] = $format;
	}
}

// Example with a yes / no select
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("IMPORTPROPAL_FORMAT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_IMPORTPROPAL_FORMAT">';
print $form->textwithpicto($formabricot->combo('', 'IMPORTPROPAL_FORMAT', $TFormat, $conf->global->IMPORTPROPAL_FORMAT), $langs->transnoentitiesnoconv('IMPORTPROPAL_FORMAT_INFO'), -1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("IMPORTPROPAL_USE_MAJ_ON_NOMENCLATURE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('IMPORTPROPAL_USE_MAJ_ON_NOMENCLATURE');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("CREATE_PRODUCT_FROM_IMPORT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('CREATE_PRODUCT_FROM_IMPORT');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("IMPORTPROPAL_FORCE_TVA").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_IMPORTPROPAL_FORCE_TVA">';
print '<input type="text" name="IMPORTPROPAL_FORCE_TVA" value="'.$conf->global->IMPORTPROPAL_FORCE_TVA.'" size="5" /> % ';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("IMPORTDEVIS_UPDATE_PRODUCT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('IMPORTDEVIS_UPDATE_PRODUCT');
print '</td></tr>';

print '</table>';

llxFooter();

$db->close();