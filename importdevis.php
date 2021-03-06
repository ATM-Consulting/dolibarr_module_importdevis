<?php

	require('config.php');
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/core/lib/propal.lib.php');
	dol_include_once('/core/lib/order.lib.php');
	dol_include_once('/core/lib/function.lib.php');
	dol_include_once('/importdevis/lib/importdevis.lib.php');
	if (!empty($conf->subtotal->enabled)) dol_include_once('/subtotal/class/subtotal.class.php');
	if (!empty($conf->nomenclature->enabled)) dol_include_once('/nomenclature/class/nomenclature.class.php');
	set_time_limit(0);
	$PDOdb = new TPDOdb;
	//var_dump($_REQUEST);exit;
	
	$doliversion = (float) DOL_VERSION;
	$langs->Load('importdevis@importdevis');
	
	$id = GETPOST('id', 'int');
	$delete_lines_before_import = GETPOST('delete_lines_before_import');
	$origin = GETPOST('origin');
	$action = GETPOST('action', 'alpha');
	$error = false;
	
	$object= '';
	if ($origin=='propal'){
		$result = restrictedArea($user, 'propal', $id);
		//var_dump($result);exit;
		$object = new Propal($db);
	}else{
		$result = restrictedArea($user,'commande', $id);
		//var_dump($result, $origin);exit;
		$object = new Commande($db);
		
	}
	if ($id > 0) {
		$ret = $object->fetch($id);
		if ($ret > 0)
			$ret = $object->fetch_thirdparty();
		if ($ret < 0)
			dol_print_error('', $object->error);
		if ($object->statut != Propal::STATUS_DRAFT || $object->statut != Commande::STATUS_DRAFT)
		{
			//var_dump('toto');exit;
			$error = true;
			setEventMessages($langs->trans('importdevis'.$origin.'DraftWarning'), null, 'warnings');
		}
			
	}
	
	
	if ($action == 'send_file')
	{
		$TData = importFile($db, $conf, $langs);
		fiche_preview($object, $TData);
		
		exit;
	}
	else if($action == 'import_data') {
		
		if(!empty($delete_lines_before_import) && !empty($object->lines)) {
			foreach($object->lines as $l) {
				$l->delete();
			}
		}
		
		$default_tva = 0;
		if (!empty($conf->global->IMPORTPROPAL_FORCE_TVA)) $default_tva = $conf->global->IMPORTPROPAL_FORCE_TVA;
		$TLastLevelTitleAdded = array(); // Tableau pour empiler et dépiller les niveaux de titre pour ensuite ajouter les sous-totaux
		$TData = $_REQUEST['TData'];
		$last_line_id = null;
		$last_line_product = null;
		
		foreach($TData as $k=>$row) 
		{
			
			if (empty($row['to_import'])) continue;
			elseif(!empty($conf->subtotal->enabled) && $row['type'] == 'title') 
			{
				_addSousTotaux($langs, $object, $TLastLevelTitleAdded, $row['level']);
				
				// Add title ou sub-title
				TSubtotal::addSubTotalLine($object,$row['label'], 0+$row['level']);
				$TLastLevelTitleAdded[] = $row['level'];
			}
			else if (!empty($conf->nomenclature->enabled) && ($row['type'] == 'nomenclature' || $row['type'] == 'workstation'))
			{
				
				//var_dump($last_line_product,$last_line_id);exit;
				if ($last_line_id > 0)
				{
					
					$nomenclature = new TNomenclature;
					$workstation = new TWorkstation;
					
					if($last_line_product>0 && !empty($conf->global->CREATE_PRODUCT_FROM_IMPORT)) {
						$nomenclature->loadByObjectId($PDOdb, $last_line_product, 'product');
						$nomenclature->fk_object = $last_line_product;
						$nomenclature->fk_nomenclature_parent = 0;
						$nomenclature->is_default = 0;
						$nomenclature->object_type ='product';
						$nomenclature->save($PDOdb);
						
					} 
					else {
						$nomenclature->loadByObjectId($PDOdb, $last_line_id, $object->element);
						$nomenclature->fk_object = $last_line_id;
						$nomenclature->fk_nomenclature_parent = 0;
						$nomenclature->is_default = 0;
						$nomenclature->object_type = $object->element;
						$nomenclature->save($PDOdb);

					}
					
					if(!empty($row['fk_product'])) {
						$k = $nomenclature->addChild($PDOdb, 'TNomenclatureDet');
						$nomenclature->TNomenclatureDet[$k]->fk_product = $row['fk_product'];
						$nomenclature->TNomenclatureDet[$k]->title = $row['label'];
						$nomenclature->TNomenclatureDet[$k]->fk_nomenclature = $nomenclature->getId();
						$nomenclature->TNomenclatureDet[$k]->qty = $row['qty'];
						$nomenclature->TNomenclatureDet[$k]->price = $row['price'];
						$nomenclature->TNomenclatureDet[$k]->is_imported = $last_line_id;
					}
					
					if (!empty($row['fk_workstation']) && !empty($conf->workstation->enabled)){
						//var_dump('tata');exit;
						$workstation->loadBy($PDOdb, $row['ref'], 'code');
						
						$k = $nomenclature->addChild($PDOdb, 'TNomenclatureWorkstation');
						$det = &$nomenclature->TNomenclatureWorkstation[$k];
	       				$det->fk_workstation = $row['fk_workstation'];
						$det->qty = $row['qty'];
					}
					
					$nomenclature->save($PDOdb);

				}
			}
			else if ($row['type']='line'){
				$product=new Product($db);
				$fk_product = $row['fk_product'];
				$ref = $row['product_ref'];
			
				$res = $product->fetch($fk_product, $ref);
				//var_dump($product);exit;
				
				$product->ref        = $ref;
				$product->label      = $row['label'];
				$product->price      = $row['price'];
				$product->weight     = $row['weight'];
				$product->length     = $row['length'];
				$product->buyprice   = $row['buy_price'];
				$product->status = 1;
				$product->status_buy = 1;
				
				if(!empty($row['buy_price'])){
					$product->buyprice   = $row['buy_price'];
					
				}
					//echo (int)$product->id;
				if (empty($product->id)){
					if (!empty($conf->global->CREATE_PRODUCT_FROM_IMPORT)){				
						$product->create($user);
					}
				}else{
					if (!empty($conf->global->IMPORTDEVIS_UPDATE_PRODUCT)) $product->update($product->id, $user);
				}
				
				//var_dump($product->id);
				$last_line_product = $product->id;
				
				if ($product->id > 0 && !empty($conf->global->CREATE_PRODUCT_FROM_IMPORT)) // TODO on pourrais faire de l'update line ici
				{
					$nomenclature = new TNomenclature;
					$nomenclature->loadByObjectId($PDOdb, $product->id, 'product');
					$nomenclature->deleteChildrenNotImported($PDOdb);
					
				}
				
				
				if ($row['fk_propaldet'] > 0) // TODO on pourrais faire de l'update line ici
				{
					$last_line_id = $row['fk_propaldet'];
					$nomenclature = new TNomenclature;
					$nomenclature->loadByObjectId($PDOdb, $last_line_id, $object->element);
					$nomenclature->deleteChildrenNotImported($PDOdb);
					
				}
				
				else // Add line 
				{
						
				//		var_dump($product->id);
					if ($doliversion >= 3.8)
					{
						if ($row['fk_unit'] == 'none') $row['fk_unit'] = null;
						
						if($object->element=='facture') $last_line_id =  $object->addline($row['label'], $row['price'],$row['qty'],$default_tva,0,0,$product->id,0,'','',0,0,'','HT',0,Facture::TYPE_STANDARD,-1,0,'',0,0,null,0,'',0,100,'',$row['fk_unit']);
						else if($object->element=='propal')$last_line_id = $object->addline($row['label'], $row['price'],$row['qty'],$default_tva,0,0,$product->id,0,'HT',0,0,0,-1,0,0,0,0,'','','',0,$row['fk_unit']);
						else if($object->element=='commande') $last_line_id =  $object->addline($row['label'], $row['price'],$row['qty'],$default_tva,0,0,$product->id,0,0,0,'HT',0,'','',0,-1,0,0,null,0,'',0,$row['fk_unit']);
					}
					else 
					{
						if($object->element=='facture') $last_line_id =  $object->addline($row['label'], $row['price'],$row['qty'],$default_tva,0,0,$product->id,0,'','',0,0,'','HT');
						else if($object->element=='propal')$last_line_id = $object->addline($row['label'], $row['price'],$row['qty'],$default_tva,0,0,$product->id);
						else if($object->element=='commande') $last_line_id =  $object->addline($row['label'], $row['price'],$row['qty'],$default_tva,0,0,$product->id);	

					}
					
					if($res<0) {
						var_dump($row,$last_line_id, $object->db);
						exit;
					}
				}
			}
		}
//exit;
		if (!empty($conf->subtotal->enabled))
		{
			// Check pour ajouter les derniers sous-totaux
			_addSousTotaux($langs, $object, $TLastLevelTitleAdded, 0);	
		}

		setEventMessage("Lignes importées");
		
		if($object->element=='propal') header('location:'.dol_buildpath('/comm/propal.php?id='.$object->id,1));
		if($object->element=='commande') header('location:'.dol_buildpath('/commande/card.php?id='.$object->id, 1));
		
		exit;
	}
	else {
		fiche_import($object, $error);
	}
	
function fiche_preview(&$object, &$TData) {
	
	global $langs, $user, $db, $conf;

	//var_dump($_REQUEST);exit;
    $origin=GETPOST('origin');
	$head=null;
	
	if ($object->element=='propal'){
	    $head = propal_prepare_head($object);
	}else{
		$head = commande_prepare_head($object);
	}

	if (empty($user->rights->importdevis->myactions))
	{
		accessforbidden();
		exit;
	}

	$form=new Form($db);
	
		llxHeader();
		$title = $langs->trans('Import');
		if ($origin=='propal'){
	    	dol_fiche_head($head, 'importdevis', $title, 0, 'propal');
		}else{
			dol_fiche_head($head, 'importdevis', $title, 0, 'commande');
		}
		?>
		<style type="text/css">
			#table_before_import tr.title_line td.for_line > * {
				display:none;
			}
			
			#table_before_import tr.line_line td.for_title > * {
				display:none;
			}
			
			.for_line select{
				white-space:normal;
				width:300px;
			}
			.ui-dialog {
			    overflow: visible !important;  /* or 'visible' whatever */
			}
		</style>
		
		<script type="text/javascript">
			$(function() {
				var old_type;
				
				$('#to_parse .type select').unbind().click(function() { old_type = $(this).val(); }).change(function() {
					switchClass($(this));
				});
				
				$( "#pop-edit-product-link" ).dialog({
			      modal: true,
			      autoOpen: false,
			      title:"Lier un produit à cette ligne",
			      buttons: {
			        "Lier ce produit": function() {
			        	
			          var fk_product = $('#fk_product_to_link').val() ;
			          var k = $(this).attr('k');	
			          $input = $('tr[k='+k+'] input[rel=fk_product]');
			         //console.log($input);
			         
			          $.ajax({
			          	url:"<?php echo dol_buildpath('/product/ajax/products.php',1) ?>?action=fetch&id="+fk_product
			          	,dataType:'json'
			          }).done(function(product) {
			          	
			          	$('span[rel="ref-product"][k='+k+']').html(product.ref);
			          	$input.val(fk_product);
			          	console.log(product);
			          	
			          });
			        		
			          $( this ).dialog( "close" );
			        }
			      }
			    });
				
				function switchClass(element)
				{
					var type_value = $(element).val();
	
					if (type_value == 'title') 
					{
						$(element).parent().parent().addClass('liste_titre title_line');
						$(element).parent().parent().removeClass('line_line');
					}
					else 
					{
						$(element).parent().parent().addClass('line_line');
						$(element).parent().parent().removeClass('liste_titre title_line');
						
						if (old_type == 'title' && type_value == 'line')
						{
							while (element.length > 0)
							{
								element = $(element).parent().parent().next().find('td.type').children('select');
								
								if (element.val() == 'title') break;
								
								element.children('option[value=nomenclature]').attr('selected', true);
							}
							
						}
					}
				}
			});
			
			var imp_is_all_check = true;
			function checkAndUncheckAllImport()
			{
				if (imp_is_all_check)
				{
					imp_is_all_check = false;
					$("#to_parse tr .check_imp").attr('checked', false).prop('checked', false);
				}
				else
				{
					imp_is_all_check = true;
					$("#to_parse tr .check_imp").attr('checked', true).prop('checked', true);
				}
			}
			
			function edit_product_link(k) {
				$div = $('#pop-edit-product-link');
				$div.attr('k', k);
				
				$div.dialog('open');
			}
			
		</script>
		<div id="pop-edit-product-link" class="ui-dialog"  >
			<?php
			$form->select_produits('', 'fk_product_to_link');
			?>
		</div>
		<table id="table_before_import" width="100%" class="border">
			<tr>
				<td width="25%"><?php echo $langs->trans('Ref'); ?></td>
				<td colspan="3"><div style="vertical-align: middle"><div class="inline-block floatleft refid"><?php echo $object->ref; ?></div></div></td>
			</tr>
			<tr>
				<td><?php echo $langs->trans('Company'); ?></td>MO-1
				<td colspan="3"><?php echo $object->thirdparty->getNomUrl(1); ?></td>
			</tr>
			<tr>
				<td colspan="4">
					
						<?php
							$PDOdb = new TPDOdb; 
							$formCore=new TFormCore('auto','to_parse', 'post');
							echo $formCore->hidden('action', 'import_data');
							echo $formCore->hidden('id', $object->id);
							echo $formCore->hidden('origin', $origin);
							echo $formCore->hidden('token', $_SESSION['newtoken']);
							echo $formCore->hidden('data', base64_encode(serialize($TData)));
						
							?>
							<table class="border" width="100%">
								<tr class="liste_titre">
									<th onclick="javascript:checkAndUncheckAllImport();" style="cursor:pointer;" title="sélectionner/désélectionner tous">Imp.</th>
									<th>Type</th>
									<?php if ($conf->subtotal->enabled) { ?><th>Niveau</th><?php } ?>
									<th>Produit</th>
									<th>Label</th>
									<th>Qté</th>
									<?php if (!empty($conf->global->PRODUCT_USE_UNITS)) { ?><th>Unité</th><?php } ?>
									<th>Prix Achat</th>
									<th>Prix</th>
									<?php if (!empty($conf->global->IMPORTPROPAL_USE_MAJ_ON_NOMENCLATURE)) { ?>
									<th>Ligne d'origine</th>
									<?php } ?>
								</tr>
							<?php
							
							if (!empty($conf->global->IMPORTPROPAL_USE_MAJ_ON_NOMENCLATURE))
							{
								$TPropalDet = array();
								
								foreach ($object->lines as $line)
								{
									$label = !empty($line->label) ? $line->label : $line->desc;
									$label.= ' (qté : '.$line->qty.', total HT : '.$line->total_ht.')';
									$TPropalDet[$line->id] = $label;
								}
							}
							$class = '';
							//var_dump($TData);
							
							$TWorkstation = TWorkstation::getWorstations($PDOdb);
							
							foreach($TData as $k=>&$row) {
								//var_dump($row);MO-1
								$workstation = new TWorkstation;
								//var_dump($workstation->loadBy($PDOdb, $row['workstation'], 'code'));
								//var_dump($workstation);exit;

								if (!empty($row['ref']))
								{
									$res = $workstation->loadBy($PDOdb, $row['ref'], 'code');
	
									if ($res >0){
										$row['type']='workstation';
										$id_workstation = $workstation->getId();
										//var_dump($workstation);
	
									}	
								}
								
								
								$type=$row['type'];
								
								if($type == 'title') {
									$class = '';
									print '<tr class="'.$class.' liste_titre title_line">';
									print '<td>'.$formCore->checkbox1('', 'TData['.$k.'][to_import]', 1,true, '', 'check_imp').'</td>';
									print '<td class="type">'.$form->selectarray('TData['.$k.'][type]', getTypeLine(), $row['type']).'</td>';
									print '<td class="for_title">'.$form->selectarray('TData['.$k.'][level]', getLevelTitle(), $row['level']).'</td>';
									print '<td class="for_line">';
									//$form->select_produits(0, 'TData['.$k.'][fk_product]');
									print '</td>';
									print '<td>'.$formCore->texte('', 'TData['.$k.'][label]', $row['label'], 50,255) .'</td>';
									print '<td class="for_line">'.$formCore->texte('', 'TData['.$k.'][qty]', $row['qty'], 3,20) .'</td>';
									if (!empty($conf->global->PRODUCT_USE_UNITS)) print '<td class="for_line"></td>';
									print '<td class="for_line">'.$formCore->texte('', 'TData['.$k.'][price]', $row['price'], 10,20) .'</td>';
									print '<td class="for_line">'.$formCore->texte('', 'TData['.$k.'][price]', $row['price'], 10,20) .'</td>';										
								}
								elseif($type == 'workstation'){
									//var_dump($type);
									$class = '';
									print '<tr class="'.$class.' workstation_line">';
									print '<td>'.$formCore->checkbox1('', 'TData['.$k.'][to_import]', 1,true, '', 'check_imp').'</td>';
									print '<td class="type">'.$form->selectarray('TData['.$k.'][type]', getTypeLine(), $row['type']).'</td>';
									print '<td></td>';
									print '<td class="for_line">';
									
									echo $formCore->combo('', 'TData['.$k.'][fk_workstation]', $TWorkstation,$id_workstation);
									print '</td>';
									print '<td>'.$row['workstation'] .'</td>';
									print '<td class="for_line">'.$formCore->texte('', 'TData['.$k.'][qty]', $row['qty'], 3,20) .'</td>';
									if (!empty($conf->global->PRODUCT_USE_UNITS)) print '<td class="for_line"></td>';
									print '<td></td>';
									print '<td></td>';
								}
								else {
									$class = ($class == 'impair') ? 'pair' : 'impair';
									print '<tr class="line_line '.$class.'" k="'.$k.'">';
									print '<td>'.$formCore->checkbox1('', 'TData['.$k.'][to_import]', 1,true, '', 'check_imp').'</td>';
									print '<td class="type">'.$form->selectarray('TData['.$k.'][type]', getTypeLine(), $row['type']).'</td>';
									if ($conf->subtotal->enabled) print '<td class="for_title">'.$form->selectarray('TData['.$k.'][level]', getLevelTitle(), $row['level']).'</td>';
									print '<td class="for_line">';
									
									if(!empty($row['product_ref'])) {
										$p=new Product($db);
										$p->fetch(null, $row['product_ref']);
										
										$fk_product = $p->id;
									}
									else{
										$fk_product = 0;
										
									}
									
									print '<span rel="ref-product" k="'.$k.'">'.( $fk_product > 0 ? $p->getNomUrl(1) : 'N/A' ).'</span> <a href="javascript:edit_product_link('.$k.')">'.img_edit('Changer le produit de destination').'</a>';
									//$form->select_produits($fk_product, 'TData['.$k.'][fk_product]');
									
									echo $formCore->hidden('TData['.$k.'][fk_product]', $fk_product,' rel="fk_product" ');
									echo $formCore->hidden('TData['.$k.'][product_ref]', $row['product_ref'],' rel="product_ref" ');
									
									print '</td>';
									
									
									print '<td>'.$formCore->texte('', 'TData['.$k.'][label]', $row['label'], 80,255);
										print '<table>';
											print '<tr>';
											print '<td>Longueur : '.$formCore->texte('', 'TData['.$k.'][length]', $row['length'], 15,255).'</td>';
												print '<td>Largeur : '.$formCore->texte('', 'TData['.$k.'][width]', $row['width'], 15,255).'</td>';
												print '<td>Hauteur : '.$formCore->texte('', 'TData['.$k.'][height]', $row['height'], 15,255).'</td>';
												print '<td>Poids : '.$formCore->texte('', 'TData['.$k.'][weight]', $row['weight'], 15,255).'</td>';
											print '</tr>';
										print '</table>';

									print '</td>';
									
									
									print '<td class="for_line">'.$formCore->texte('', 'TData['.$k.'][qty]', $row['qty'], 3,20) .'</td>';
									if (!empty($conf->global->PRODUCT_USE_UNITS)) print '<td class="for_line">'.$form->selectUnits($row['fk_unit'],'TData['.$k.'][fk_unit]',1).'</td>';
									print '<td class="for_line">'.$formCore->texte('','TData['.$k.'][buy_price]', $row['buy_price'], 10, 20).'</td>';
									print '<td class="for_line">'.$formCore->texte('', 'TData['.$k.'][price]', $row['price'], 10,20) .'</td>';
								}

								if (!empty($conf->global->IMPORTPROPAL_USE_MAJ_ON_NOMENCLATURE))
								{
									print '<td class="for_line">'.$form->selectarray('TData['.$k.'][fk_propaldet]', $TPropalDet, '', 1).'</td>';
								}

								print '</tr>';
							}
						//exit;
							?>
							</table>
							<div class="tabsAction">
								<?php echo $langs->trans('DeleteLinesBeforeImport'); ?> <input id="delete_lines_before_import" name="delete_lines_before_import" type="checkbox" value="1" />
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
function fiche_import(&$object, $error) {
	global $langs, $user;
	
	$origin=GETPOST('origin');
	$head=null;
	
	if ($origin=='propal'){
	    $head = propal_prepare_head($object);
	}else{
		$head = commande_prepare_head($object);
	}
	
	if (empty($user->rights->importdevis->myactions))
	{
		accessforbidden();
	}
	else 
	{
		llxHeader();
		$title = $langs->trans('Import');
		if ($origin=='propal'){
	    	dol_fiche_head($head, 'importdevis', $title, 0, 'propal');
		}else {
			dol_fiche_head($head, 'importdevis', $title, 0, 'commande');
		}
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
			<?php if (!$error) { ?>
			<tr>
				<td><?php echo $langs->trans('FileToImport'); ?></td>
				<td>
					<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
						<input name="action" type="hidden" value="send_file" />
						<input name="id" type="hidden" value="<?php echo $object->id; ?>" />
						<input name="origin" type="hidden" value="<?php echo $origin;?>"/>
						<input name="token" type="hidden" value="<?php echo $_SESSION['newtoken']; ?>" />
						
						<input name="fileDGPF" type="file" />
						<?php echo $langs->trans('NbLineToAvoid'); ?> <input name="nb_line_to_avoid" type="number" value="<?php echo (int)$conf->global->IMPORTPROPAL_NB_LINE_TO_AVOID ?>" size="2" />
						
						<input class="button" type="submit" value="<?php echo $langs->trans('SendFile'); ?>" />
					</form>
				</td>
			</tr>
			<?php } ?>
		</table>
	    	
    	<?php
	    dol_fiche_end();
		llxFooter();
	}
}    
