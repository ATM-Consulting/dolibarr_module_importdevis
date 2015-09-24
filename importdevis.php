<?php

	require('config.php');
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/core/lib/propal.lib.php');
	dol_include_once('/core/lib/function.lib.php');
	dol_include_once('/importdevis/lib/importdevis.lib.php');
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
	else {
		fiche_import($object);
	}
	
function fiche_preview(&$object, &$TData) {
	
	global $langs, $user;

    $head = propal_prepare_head($object);

	if (empty($user->rights->importdevis->read))
	{
		accessforbidden();
		exit;
	}
	
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
					<form name="to_parse" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
						<input name="action" type="hidden" value="save_data" />
						<input name="token" type="hidden" value="<?php echo $_SESSION['newtoken']; ?>" />
						<input name="data" type="hidden" value="<?php echo base64_encode(serialize($TData)); ?>" />
						<table class="border" width="100%">
						<?php
						
							foreach($TData as &$row) {
									
								if($row['type'] == 'title') {
									print '<tr class="liste_titre">';	
								}	
								
								print '<td>'.$row['label'].'</td>';
								print '<td>'.$row['qty'].'</td>';
								
								print '</tr>';
								
							}
									
														
						
						?>
						</table>
						<input class="button" type="submit" value="<?php echo $langs->trans('Save'); ?>" />
					</form>
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