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
function lineMapper_DGPF(&$line) {
	
	$line[1] = trim($line[1]);
	$line[2] = trim($line[2]);
	$line[4] = trim($line[4]);
	$line[6] = trim($line[6]);
	$line[8] = trim($line[8]);
	$line[9] = trim($line[9]);
	
	if (empty($line[1]) && empty($line[2]) && empty($line[4]) && empty($line[6]) && empty($line[8]) && empty($line[9])) return false;
	
	return $line;			if(function_exists($method)) {
				$line = call_user_func($method, $line);
		    } 
			
			if (!empty($line) ) {
				$TData[] = $line;
		  	}
	
}

function lineMapper_SMARTBOM($line) {
	//var_dump($line);
	
	if(empty($line[11])) return false;
	
	$Tab=array(
		'label'=>empty($line[4]) ? $line[11] : $line[4] 
		,'qty'=>empty($line[14]) ? 1 : (float)$line[14]
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
	if(strtolower($info['extension']) == 'csv') {
		$TData = _importFileParseCSV($file, GETPOST('nb_line_to_avoid'));
	}
	else {
		$TData = _importFileParseXLS2($file, GETPOST('nb_line_to_avoid'));	
	}
	
	
	return $TData;
	
}
