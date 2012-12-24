<?php

function edgeCast_settings()
{
	return array(
		'isCDN' => 1
	);
}

function edgeCast_install($data,$db)
{
	// Required SQL Run Here
	
	$data->output['installSuccess'] = TRUE;
}

function edgeCast_postInstall($data,$db)
{
}

?>