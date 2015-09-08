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
		importFile($db, $conf, $langs);
		header('Location: '.dol_buildpath('/importdevis/importdevis.php?id='.$id, 2));
		exit;
	}
	
	
    $head = propal_prepare_head($object);

	if (empty($user->rights->importdevis->read))
	{
		accessforbidden();
	}
	else 
	{
		llxHeader();
		$title = $langs->trans('importDGPF');
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
						<input name="id" type="hidden" value="<?php echo $id; ?>" />
						<input name="token" type="hidden" value="<?php echo $_SESSION['newtoken']; ?>" />
						
						<input name="fileDGPF" type="file" />
						
						<input class="button" type="submit" value="<?php echo $langs->trans('SendFile'); ?>" />
					</form>
				</td>
			</tr>
		</table>
	    	
    	<?php
	    
	    dol_fiche_end();
		llxFooter();
	}
    