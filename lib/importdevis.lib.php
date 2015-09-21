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
 *	\file		lib/importdevis.lib.php
 *	\ingroup	importdevis
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function importdevisAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("importdevis@importdevis");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/importdevis/admin/importdevis_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/importdevis/admin/importdevis_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@importdevis:/importdevis/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@importdevis:/importdevis/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'importdevis');

    return $head;
}
function _importFileParseCSV(&$file)  {
	$handle = fopen($file['tmp_name'], 'r');
	
	$TData = array();
	while ($line = fgetcsv($handle, 4096, ';'))
	{
		
		$TData[] = $line;
	}
	
	fclose($handle);
	
	return $TData;
}
function _importFileParseXLS(&$file) {
	dol_include_once('/importdevis/lib/spreadsheet-reader/php');
	dol_include_once('/importdevis/lib/spreadsheet-reader/SpreadsheetReader.php');
	
	$Reader = new SpreadsheetReader($file['tmp_name'], $file['name']);
	//var_dump($Reader);
	$TData = array();
	foreach ($Reader as $k => $line) {
		if ($line) {
			$TData[] = $line;
		}
				
	}
	
	return $TData;
}

function importFile(&$db, &$conf, &$langs)
{
	$file = $_FILES['fileDGPF'];
	$info = pathinfo($file['name']);
	
	if (($file['type'] != 'text/csv' || strtolower($info['extension']) != 'csv') && $conf->global->IMPORTPROPAL_FORMAT == 'DGPF') 
	{
		setEventMessages($langs->trans('importDGPFErrorExtension'), null, 'errors');
		return;
	}
	else {
		
	}
	/*
	if($file['type'] == 'text/csv') {
		$TData = _importFileParseCSV($file);
	}
	else {*/
		$TData = _importFileParseXLS($file);
	//}
	/*
	 * [0] => ''
	 * [1] => Indice de grand titre
	 * [2] => Indice sous titre (ex : 3.2.1.2 = indice de niveau 4)
	 * [3] => ''
	 * [4] => Description
	 * [5] => ''
	 * [6] => Sous-total et sous-sous-total
	 * [7] => ''
	 * [8] => Unité
	 * [9] => Qté
	 * [10]=> ''
	 * [11]=> ''
	 * 
	 */
	 
	 var_dump($TData );exit;
	
	foreach($TData as $k=>&$line) {
		
		if($conf->global->IMPORTPROPAL_FORMAT == 'DGPF') {
			$line[1] = trim($line[1]);
			$line[2] = trim($line[2]);
			$line[4] = trim($line[4]);
			$line[6] = trim($line[6]);
			$line[8] = trim($line[8]);
			$line[9] = trim($line[9]);
			
			if (empty($line[1]) && empty($line[2]) && empty($line[4]) && empty($line[6]) && empty($line[8]) && empty($line[9])) unset($TData[$k]);
			
		}
		
		
	}
	
	
	
	
	
	
	/*
	 * Règles : 
	 *  - Si unité => ligne d’ouvrage, reliée à la 1ère ligne de titre du dessus
	 *  - Si description uniquement => description de l’ouvrage
	 *  - Si quantité vide => Mettre 999999 (voir affichage en rouge)
	 *  - Si indice et pas de quantité ni d’unité => ligne de chapitre
	 */
	foreach ($TData as $line)
	{		
		var_dump($line);
	}
	
	exit;
}
