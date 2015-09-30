<?php

	require('config.php');
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/core/lib/propal.lib.php');
	dol_include_once('/core/lib/function.lib.php');
	dol_include_once('/importdevis/lib/importdevis.lib.php');
	if (!empty($conf->subtotal->enabled)) dol_include_once('/subtotal/class/subtotal.class.php');
	
	$doliversion = (float) DOL_VERSION;
	$langs->Load('importdevis@importdevis');
	
	$id = GETPOST('id', 'int');
	$action = GETPOST('action', 'alpha');
	
	$result = restrictedArea($user, 'propal', $id);
	$object = new Propal($db);
	
	if ($id > 0) {
		$ret = $object->fetch($id);
		if ($ret > 0)
			$ret = $object->fetch_thirdparty();
		if ($ret < 0)
			dol_print_error('', $object->error);
	}
	
	
	if ($action == 'send_dgpf')
	{
		$TData = importFile($db, $conf, $langs);
		fiche_preview($object, $TData);
		
		exit;
	}
	else if($action == 'import_data') {
		
		$TLastLevelTitleAdded = array(); // Tableau pour empiler et dépiller les niveaux de titre pour ensuite ajouter les sous-totaux
		$TData = $_REQUEST['TData'];

		foreach($TData as $row) 
		{
			if (empty($row['to_import'])) continue;
			elseif(!empty($conf->subtotal->enabled) && $row['type'] == 'title') 
			{
				_addSousTotaux($langs, $object, $TLastLevelTitleAdded, $row['level']);
				
				// Add title ou sub-title
				TSubtotal::addSubTotalLine($object,$row['label'], 0+$row['level']);
				$TLastLevelTitleAdded[] = $row['level'];
			}
			else 
			{
				 	
				if ($doliversion >= 3.8)
				{
					if ($row['fk_unit'] == 'none') $row['fk_unit'] = null;
					
					if($object->element=='facture') $res =  $object->addline($row['label'], $row['price'],$row['qty'],0,0,0,$row['fk_product'],0,'','',0,0,'','HT',0,Facture::TYPE_STANDARD,-1,0,'',0,0,null,0,'',0,100,'',$row['fk_unit']);
					else if($object->element=='propal') $res = $object->addline($row['label'], $row['price'],$row['qty'],0,0,0,$row['fk_product'],0,'HT',0,0,0,-1,0,0,0,0,'','','',0,$row['fk_unit']);
					else if($object->element=='commande') $res =  $object->addline($row['label'], $row['price'],$row['qty'],0,0,0,$row['fk_product'],0,0,0,'HT',0,'','',0,-1,0,0,null,0,'',0,$row['fk_unit']);
				}
				else 
				{
					if($object->element=='facture') $res =  $object->addline($row['label'], $row['price'],$row['qty'],0,0,0,$row['fk_product'],0,'','',0,0,'','HT');
					else if($object->element=='propal') $res = $object->addline($row['label'], $row['price'],$row['qty'],0,0,0,$row['fk_product']);
					else if($object->element=='commande') $res =  $object->addline($row['label'], $row['price'],$row['qty'],0,0,0,$row['fk_product']);	
				}
				
				if($res<0) {
					var_dump($row,$res, $object->db);
					exit;
				}
					
			}

		}

		if (!empty($conf->subtotal->enabled))
		{
			// Check pour ajouter les derniers sous-totaux
			_addSousTotaux($langs, $object, $TLastLevelTitleAdded, 0);	
		}

		setEventMessage("Lignes importées");
		
		if($object->element=='propal') header('location:'.dol_buildpath('/comm/propal.php?id='.$object->id,1));
		
		exit;
	}
	else {
		fiche_import($object);
	}
	
function fiche_preview(&$object, &$TData) {
	
	global $langs, $user, $db, $conf;

    $head = propal_prepare_head($object);

	if (empty($user->rights->importdevis->read))
	{
		accessforbidden();
		exit;
	}

	$form=new Form($db);
	
		llxHeader();
		$title = $langs->trans('Import');
	    dol_fiche_head($head, 'importdevis', $title, 0, 'propal');
		
		?><table width="100%" class="border">
			<tr>
				<td width="25%"><?php echo $langs->trans('Ref'); ?></td>
				<td colspan="3"><div style="vertical-align: middle"><div class="inline-block floatleft refid"><?php echo $object->ref; ?></div></div></td>
			</tr>
			<tr>
				<td><?php echo $langs->trans('Company'); ?></td>
				<td colspan="3"><?php echo $object->thirdparty->getNomUrl(1); ?></td>
			</tr>
			<tr>
				<td colspan="4">
					
						<?php
							$formCore=new TFormCore('auto','to_parse', 'post');
							echo $formCore->hidden('action', 'import_data');
							echo $formCore->hidden('id', $object->id);
							echo $formCore->hidden('token', $_SESSION['newtoken']);
							echo $formCore->hidden('data', base64_encode(serialize($TData)));
						
							?>
							<table class="border" width="100%">
								<tr class="liste_titre">
									<th>Imp.</th>
									<th>Type</th>
									<th>Produit</th>
									<th>Label</th>
									<th>Qté</th>
									<?php if (!empty($conf->global->PRODUCT_USE_UNITS)) { ?><th>Unité</th><?php } ?>
									<th>Prix</th>
								</tr>
							<?php
							$class = '';
							foreach($TData as $k=>&$row) {
									
								echo $formCore->hidden( 'TData['.$k.'][type]', $row['type']);
								echo $formCore->hidden( 'TData['.$k.'][level]', $row['level']);
								
									
								if($row['type'] == 'title') {
									$class = '';
									print '<tr class="liste_titre">';
									print '<td>'.$formCore->checkbox1('', 'TData['.$k.'][to_import]', 1,true).'</td>';
									print '<td>'.$row['level'].'</td>';
									print '<td></td>';
									print '<td colspan="3">'.$formCore->texte('', 'TData['.$k.'][label]', $row['label'], 50,255) .'</td>';
									if (!empty($conf->global->PRODUCT_USE_UNITS)) print '<td></td>';
									print '</tr>';	
								}	
								else {
									$class = ($class == 'impair') ? 'pair' : 'impair';
									print '<tr class="'.$class.'">';
									print '<td>'.$formCore->checkbox1('', 'TData['.$k.'][to_import]', 1,true).'</td>';
									print '<td>P</td>';
									print '<td>';
									
									if(!empty($row['product_ref'])) {
										$p=new Product($db);
										$p->fetch(null, $row['product_ref']);
										
										$fk_product = $p->id;
									}
									else{
										$fk_product = 0;
									}
									
									$form->select_produits($fk_product, 'TData['.$k.'][fk_product]');
									print '</td>';
									print '<td>'.$formCore->texte('', 'TData['.$k.'][label]', $row['label'], 80,255) .'</td>';
									print '<td>'.$formCore->texte('', 'TData['.$k.'][qty]', $row['qty'], 3,20) .'</td>';
									if (!empty($conf->global->PRODUCT_USE_UNITS)) print '<td>'.$form->selectUnits($row['fk_unit'],'TData['.$k.'][fk_unit]',1).'</td>';
									print '<td>'.$formCore->texte('', 'TData['.$k.'][price]', $row['price'], 10,20) .'</td>';
									print '</tr>';	
								}
								
							}
						
							?>
							</table>
							<div class="tabsAction">
								<input class="button" type="submit" value="<?php echo $langs->trans('Import'); ?>" />
							</div>
							<?php
							$formCore->end();
					?>
				</td>
			</tr>
		</table>
	<?php
	    
	    dol_fiche_end();
		llxFooter();	
		
	
}
function fiche_import(&$object) {
	global $langs, $user;

    $head = propal_prepare_head($object);

	if (empty($user->rights->importdevis->read))
	{
		accessforbidden();
	}
	else 
	{
		llxHeader();
		$title = $langs->trans('Import');
	    dol_fiche_head($head, 'importdevis', $title, 0, 'propal');
		
		?>
		
		<table width="100%" class="border">
			<tr>
				<td width="25%"><?php echo $langs->trans('Ref'); ?></td>
				<td colspan="3"><div style="vertical-align: middle"><div class="inline-block floatleft refid"><?php echo $object->ref; ?></div></div></td>
			</tr>
			<tr>
				<td><?php echo $langs->trans('Company'); ?></td>
				<td colspan="3"><?php echo $object->thirdparty->getNomUrl(1); ?></td>
			</tr>
			<tr>
				<td><?php echo $langs->trans('FileToImport'); ?></td>
				<td>
					<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
						<input name="action" type="hidden" value="send_dgpf" />
						<input name="id" type="hidden" value="<?php echo $object->id; ?>" />
						<input name="token" type="hidden" value="<?php echo $_SESSION['newtoken']; ?>" />
						
						<input name="fileDGPF" type="file" />
						<?php echo $langs->trans('NbLineToAvoid'); ?> <input name="nb_line_to_avoid" type="number" value="<?php echo (int)$conf->global->IMPORTPROPAL_NB_LINE_TO_AVOID ?>" size="2" />
						
						<input class="button" type="submit" value="<?php echo $langs->trans('SendFile'); ?>" />
					</form>
				</td>
			</tr>
		</table>
	    	
    	<?php
	    
	    dol_fiche_end();
		llxFooter();
	}
}    