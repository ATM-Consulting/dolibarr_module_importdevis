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
function _importFileParseCSV(&$file, $nb_line_to_avoid)  {
global $conf;

	$handle = fopen($file['tmp_name'], 'r');
	$method = 'lineMapper_'.$conf->global->IMPORTPROPAL_FORMAT;
	$method_after = 'dataParserAfter_'.$conf->global->IMPORTPROPAL_FORMAT;
	
	$k = 0;
	$TData = array();
	while ($line = fgetcsv($handle, 4096, ';'))
	{
		if($k<$nb_line_to_avoid) {
			null;
		}
		else{
			if(function_exists($method)) {
				$line = call_user_func($method, $line);
		    }

			if (!empty($line) ) {
				$TData[] = $line;
		  	}
			
		}
		
		 
		 $k++;
	}
	
	if(function_exists($method_after)) {
			$TData = call_user_func($method_after, $TData);
	 } 
	
	
	fclose($handle);
	
	return $TData;
}
function lineMapper_DGPF($line) {
	
	$line[0] = trim($line[0]);
	$line[1] = trim($line[1]);
	$line[2] = trim($line[2]);
	$line[3] = trim($line[3]);
	
	if (empty($line[0]) && empty($line[1])) return '';
	
	$niveau = trim($line[0]);
	$niveau = empty($niveau) ? null : explode('.', $niveau);
	$level = count($niveau);
	
	
	/**
	 *	$line
	 * 	[0] = numéro titre ou ligne
	 *  [1] = titre/label
	 *	[2] = unité
	 * 	[3] = qty
	 * 
	 * Processus pour détection des lignes :
	 *  - Si unité => ligne d’ouvrage, reliée à la 1ère ligne de titre du dessus
	 *  - Si désignation uniquement => description de l’ouvrage
	 *  - Si quantité vide => Mettre 999999 (voir affichage en rouge)
	 *  - Si indice et pas de quantité ni d’unité => ligne de chapitre
	 *     * Regarder l’indice pour connaître le niveau du chapitre
	 *     * Exemple : si indice A.6.d.12 => Niveau 4
	 *     * Exemple : si indice C-3-3 => Niveau 3
	 */
	$is_title = true;
	
	if (!empty($line[2]) && $line[2] != '.') $is_title = false;
	elseif (empty($line[0]) && empty($line[2]) && empty($line[3])) return ''; // vu comment le système fonctionne actuellement les descriptions ne peuvent pas être ajoutés
	
	if (!$is_title && empty($line[3])) $line[3] = 999999;
	
	$fk_unit = _getFkUnitByCode($line[2]);
	
	$Tab=array(
		'label'=>$line[0].' - '.$line[1] 
		,'qty'=>empty($line[3]) ? 1 : (float)$line[3]
		,'type'=>$is_title ? 'title' : 'line'
		,'product_ref'=>''
		,'title1'=>$line[1]
		,'title2'=>''
		,'title3'=>''
		,'level'=>$level
		,'price'=>0
		,'fk_unit'=>$fk_unit
	);
	
	return $Tab;
	
}

function _getFkUnitByCode($code)
{
	global $db,$conf;
	
	if (empty($conf->global->PRODUCT_USE_UNITS)) return null;
	
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'c_units WHERE code = "'.$db->escape($code).'"';
	$resql = $db->query($sql);
	
	if ($resql && $db->num_rows($resql) > 0)
	{
		$obj = $db->fetch_object($resql);
		return $obj->rowid;
	}
	
	return null;
}

function lineMapper_SMARTBOM($line) {
	//var_dump($line);
	
	if(empty($line[11])) return false;
	
	$Tab=array(
		'label'=>empty($line[4]) ? $line[11] : $line[4] 
		,'qty'=>empty($line[15]) ? 1 : (float)$line[15]
		,'type'=>'line'
		,'product_ref'=>$line[5]
		,'title1'=>$line[1]
		,'title2'=>$line[2]
		,'title3'=>$line[3]
		,'level'=>0
		,'price'=>0
	);
	
	return $Tab;
	
}

function _dPA_SMARTBOM_add_title(&$Tab, $label, $level=1) {
	global $conf;
	
	if(empty($conf->subtotal->enabled)) return false;
	if(empty($label)) return false;
	
	$nb = count($Tab);
	
	$found = false;
	for($k = $nb-1;$k>=0;$k--) {
		
		if($Tab[$k]['type'] == 'title' && $Tab[$k]['label'] === $label && $Tab[$k]['level'] == $level) {
			$found = true;
			break;
		}
		else if($Tab[$k]['type'] == 'title' && $Tab[$k]['level'] == $level) {
			break;
		}
		
	}
	
	if(!$found) {
		
		/*$Tab[]=array(
			'label'=>'Sous-total'
			,'type'=>'subtotal'
			,'qty'=>0
			,'level'=>$level
			
		)*/
		
		$Tab[] = array(
			'label'=>$label
			,'qty'=>0
			,'level'=>$level
			,'type'=>'title'
		
		);
		
	}
	
	
	
	if($found === false) return false;
	else return $k;
}

function dataParserAfter_SMARTBOM($TData) {
	
	$Tab = array();
	
	foreach($TData as &$row) {
		
		_dPA_SMARTBOM_add_title($Tab, $row['title1']);
		_dPA_SMARTBOM_add_title($Tab, $row['title2'],2);
		_dPA_SMARTBOM_add_title($Tab, $row['title3'],3);
		
		$Tab[] = $row;
	}

	return $Tab;
}

function _importFileParseXLS2(&$file, $nb_line_to_avoid) {
	global $conf;
	
	set_time_limit(0);
	$TData=array();
	$method = 'lineMapper_'.$conf->global->IMPORTPROPAL_FORMAT;
	$method_after = 'dataParserAfter_'.$conf->global->IMPORTPROPAL_FORMAT;
	
	$filename = sys_get_temp_dir().'/'. $file['name'];
	copy($file['tmp_name'], $filename);
	require(__DIR__.'/PHPExcel/Classes/PHPExcel.php');
	
	$objReader = PHPExcel_IOFactory::createReader('Excel2007'); 
	/**  Advise the Reader of which WorkSheets we want to load  **/ 
	$objReader->setReadDataOnly(true);
	$objReader->setLoadSheetsOnly('Exemple'); 
	 
	
	$objPHPExcel = $objReader->load($filename);
	$objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
	$k=0;
	foreach ($objWorksheet->getRowIterator() as $row) {
	  $cellIterator = $row->getCellIterator();
	  $cellIterator->setIterateOnlyExistingCells(false); // This loops all cells,
	                                                     // even if it is not set.
	  $k++;
		                                                 // By default, only cells
	  if($k<=$nb_line_to_avoid) continue;                                             // that are set will be
	    
	  $line = array();
	  foreach ($cellIterator as $cell) {
	    $line[] =$cell->getValue();
	  }
	  
	  if(function_exists($method)) {
			$line = call_user_func($method, $line);
	  } 
		
	  if (!empty($line) ) {
			$TData[] = $line;
	  }
	  
	  
	}			if(function_exists($method)) {
				$line = call_user_func($method, $line);
		    } 
			
			if (!empty($line) ) {
				$TData[] = $line;
		  	}
	
	
	if(function_exists($method_after)) {
			$TData = call_user_func($method_after, $TData);
	  } 
	
	return $TData;
	
}

function _importFileParseXLS(&$file, $nb_line_to_avoid) {
global $conf;

	dol_include_once('/importdevis/lib/spreadsheet-reader/php-excel-reader/excel_reader2.php');
	dol_include_once('/importdevis/lib/spreadsheet-reader/SpreadsheetReader.php');

	$method = 'lineMapper_'.$conf->global->IMPORTPROPAL_FORMAT;
	$method_after = 'dataParserAfter_'.$conf->global->IMPORTPROPAL_FORMAT;
	
	$filename = sys_get_temp_dir().'/'. $file['name'];
	copy($file['tmp_name'], $filename);
	
	$Reader = new SpreadsheetReader($filename);
	
	
	$Reader->ChangeSheet(0);
	//var_dump($Reader);
	$TData = array();
	foreach ($Reader as $k => $line) {
		
		if($k<$nb_line_to_avoid) continue;
		
		if(function_exists($method)) {
			$line = call_user_func($method, array($line));
		} 
		
		if (!empty($line) ) {
			$TData[] = $line;
		}
				
	}
	
	if(function_exists($method_after)) {
			$TData = call_user_func($method_after, $TData);
	 } 
	
	
	return $TData;
}

function _generateArrayFromXML($file, $nb_line_to_avoid)
{
	$TData = array();
	$filename = sys_get_temp_dir().'/'. $file['name'];
	copy($file['tmp_name'], $filename);
	$TmpData = array();
	// On récupère le fichier XML et on le convertie en tableau grace au json encode
	$array=json_decode(json_encode(simplexml_load_file($filename)),true);
	$indexNewTable = 1;
	foreach($array as $key => $item)
	{
		/***************************
		 * @Def
		 * Colonne1 .. Colonne15
		 **************************/
		$subkey=preg_replace('/Colonne/', '', $key); // Catch only the number's column
		$i=0;
		foreach($item as $element => $text)
		{
			/***************************
			 * @Def
			 * Name
			 * element1 .. element40
			 **************************/
			$jump=false;
			$subelement=preg_replace('/element/', '', $element); // Catch only the number's row
			if(is_array($text))$text=null; // Eject array element because = empty value
			if(strtolower($subelement) === 'nom' || $i < $nb_line_to_avoid)$jump=true;
			if(!$jump)
			{
				$TmpData[$subelement][$indexNewTable] = $text;
			}
			$i++;
		}
		$indexNewTable++;
	}
	/* ************************************************************
	 * 
	 * Doit retourner un tableau avec cette structure (16 champs)
	 * 
	 * ************************************************************

	array (size=39)
	  1 => 
	    array (size=16)
	      1 => string '1' (length=1)
	      2 => string 'PRAGUE' (length=6)
	      3 => string 'V1' (length=2)
	      4 => string 'DOOR' (length=4)
	      5 => null
	      6 => null
	      7 => null
	      8 => string 'EDEN' (length=4)
	      9 => string '1297' (length=4)
	      10 => string '1008' (length=4)
	      11 => string '"D1@Boss.-Extru.1@@Défaut@Pièce28"' (length=36)
	      12 => string ' 6.1571 kg' (length=10)
	      13 => null
	      14 => string '1' (length=1)
	      15 => string '3194_PRAGUE_V1_DOOR_.SLDPRT' (length=27)
	      16 => string 'OUI' (length=3)
	 
	 
	 */
	return $TmpData;
}

function _importFileParseXML($file, $nb_line_to_avoid)
{
	global $conf;
	
	$method = 'lineMapper_XML_'.$conf->global->IMPORTPROPAL_FORMAT;
	$method_after = 'dataParserAfter_'.$conf->global->IMPORTPROPAL_FORMAT;
	
	$array = _generateArrayFromXML($file, $nb_line_to_avoid);
	$TData = array();
	foreach ($array as $item)
	{
		if(function_exists($method)) {
			$line = call_user_func($method, $item);
	    }

		if (!empty($line) ) {
			$TData[] = $line;
	  	}
	}
	
	if(function_exists($method_after)) {
			$TData = call_user_func($method_after, $TData);
	 } 
	return $TData;
}

function lineMapper_XML_SMARTBOM($line) {
	//var_dump($line);
	if(empty($line[2]) && empty($line[3]) && empty($line[4])) return false;
	
	$label=$line[2].'_'.$line[3].'_'.$line[4];
	if (!empty($line[5])){
		$label.='_'.$line[5];
	}
	if (!empty($line[13]) && $line[13]!="Matériau <non spécifié>"){
		$label.='_'.$line[13];
	}
	if (!empty($line[9])){
		$label.='_'.$line[9];
	}
	if (!empty($line[10])){
		$label.='_'.$line[10];
	}
	
	$Tab=array(
		'label'       => $label
		,'qty'        => (float)$line[14]
		,'type'       => 'line'
		,'product_ref'=> $line[1]
		,'title1'     => $line[2]
		,'title2'     => $line[3]
		,'title3'     => $line[4]
		,'level'      => 0
		,'price'      => 0
		,'width'      => $line[9]
		,'height'     => $line[10]
		,'weight'     => $line[12]
	);
	
	return $Tab;
	
}

function importFile(&$db, &$conf, &$langs)
{
	$file = $_FILES['fileDGPF'];
	$info = pathinfo($file['name']);
	$TData = array();
	
	if(strtolower($info['extension']) == 'csv') {
		$TData = _importFileParseCSV($file, GETPOST('nb_line_to_avoid'));
	}
	else if(strtolower($info['extension'] == 'xls')) {
		$TData = _importFileParseXLS2($file, GETPOST('nb_line_to_avoid'));	
	}
	else if(strtolower($info['extension'] == 'xml')) {
		$TData = _importFileParseXML($file, GETPOST('nb_line_to_avoid'));
	}
	return $TData;
}

/**
 * Si $level == 0 alors on ajoute tous les sous-totaux restants
 */
function _addSousTotaux(&$langs, &$object, &$TLastLevelTitleAdded, $level=0)
{
	$lastIndex = count($TLastLevelTitleAdded)-1;
	if ($lastIndex < 0) $lastIndex = 0;
	
	if ($level <= $TLastLevelTitleAdded[$lastIndex]) 
	{
		for ($i = $lastIndex; $i >= 0; $i--)
		{
			if (is_null($TLastLevelTitleAdded[$i])) continue;
			if ($level > $TLastLevelTitleAdded[$i]) break;
			// Add sous-total
			TSubtotal::addSubTotalLine($object,$langs->trans('SubTotal'), 100-$TLastLevelTitleAdded[$i]);
			$TLastLevelTitleAdded[$i] = null; // Nettoyage du tableau
		}
	}
}

function getTypeLine()
{
	global $conf,$langs;
	
	$Tab = array();
	
	if (!empty($conf->subtotal->enabled)) $Tab['title'] = $langs->trans('title');
	$Tab['line'] = $langs->trans('product');
	if (!empty($conf->nomenclature->enabled)) $Tab['nomenclature'] = $langs->trans('nomenclatureComponent');
	
	return $Tab;
}


function getLevelTitle()
{
	global $conf,$langs;
	
	$Tab = array();
	
	if (!empty($conf->global->SUBTOTAL_USE_NEW_FORMAT))
	{
		for ($i=1; $i <= 10; $i++) 
		{ 
			$Tab[$i] = $langs->trans('Level'.$i);
		}
	}
	else
	{
		$Tab[1] = $langs->trans('Level1');
		if ($conf->global->SUBTOTAL_MANAGE_SUBSUBTOTAL) $Tab[2] = $langs->trans('Level2');
	}
	
	return $Tab;
}


function lineMapper_IMMECA($line)
{
	if (empty($line[2])) return false; // Si label vide
	
	$Tab=array(
		'label'=>$line[2]
		,'qty'=>$line[3]
		,'type'=>'line'
		,'product_ref'=>$line[1]
		,'level'=>0
		,'price'=>$line[4]
	);
	
	return $Tab;
}
