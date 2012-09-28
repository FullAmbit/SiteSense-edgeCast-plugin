<?php

class plugin_edgeCast
{
	var 
	$accountNumber,
	$token,
	$ftp,
	$ftpDomain,
	$ftpUser,
	$ftpPass,
	$ftpBaseDir;
	
	public function __construct($accountNumber = '',$token = '',$ftpDomain = 'ftp.gdlcdn.com',$ftpUser = '',$ftpPass = '',$ftpBaseDir = '')
	{
		$this->accountNumber = $accountNumber;
		$this->token = $token;
		$this->ftpDomain = rtrim($ftpDomain,'/') . '/';
		$this->ftpUser = $ftpUser;
		$this->ftpPass = $ftpPass;
		$this->ftpBaseDir = rtrim($ftpBaseDir,'/') . '/';
		
		$this->ftp = ftp_connect(rtrim($this->ftpDomain,'/')) or die("FAIL");
		ftp_login($this->ftp,$this->ftpUser,$this->ftpPass);
	}
	
	public function __destruct()
	{
		ftp_close($this->ftp);
	}
	
	// Upload A New File And Update The Cache
	public function newFile($localPath,$remotePath,$cacheURL,$type = 3)
	{
		$result = $this->uploadFile($localPath,$remotePath,$cacheURL);
		
		if($result === FALSE || $result !== '')
		{
			return 'There was an error in the FTP.';
		}
		
		// Purge The Cache
		$uri = 'https://api.edgecast.com/v2/mcc/customers/'.$this->accountNumber.'/edge/purge';
		
		$body = array('MediaPath' => $cacheURL,'MediaType' => $type);
				
		$result = $this->makeRequest($uri,$body);
	}
	// Uploads To FTP
	private function uploadFile($local,$remote,$url)
	{
		// Check To See If The File Even Exists Locally
		if(!file_exists($local))
		{
			return false;
		}
		
		$dirList = pathinfo($remote);
		$dirList = explode('/',$dirList['dirname']);
		//var_dump($dirList);
		
		$dirPath = '/'.$this->ftpBaseDir;
		
		foreach($dirList as $index => $dir)
		{
			if($dir == '') continue;
			
			$dirPath .= $dir.'/';
			
			if(@ftp_chdir($this->ftp,$dirPath) === FALSE)
			{
				ftp_mkdir($this->ftp,$dirPath);
			}
		}
		
		//curl_setopt($cH,CURLOPT_QUOTE,array("CWF"));
		$cH = curl_init();
		$fH = fopen($local,'r');
		
		curl_setopt($cH,CURLOPT_URL,'ftp://'.str_replace('@','%40',$this->ftpUser).':'.$this->ftpPass.'@'.$this->ftpDomain.$this->ftpBaseDir.$remote);
		curl_setopt($cH, CURLOPT_UPLOAD, 1);
		curl_setopt($cH, CURLOPT_INFILE, $fH);
		curl_setopt($cH, CURLOPT_INFILESIZE, filesize($local));
		$output = curl_exec($cH);
		$errors = curl_error($cH);
		curl_close($cH);
				
		return $errors;
	}
	
	public function renameFolder($old,$new)
	{
		// Check If Directory Exists Already
		if(@ftp_chdir($this->ftp,$new))
		{
			return false;
		}
		ftp_rename($this->ftp,$old,$new);
	}
	
	public function delete($path,$dir = FALSE)
	{
		ftp_pasv($this->ftp,true);
		if($dir)
		{
			$this->deleteDirectory($path);
		} else {
			@ftp_delete($this->ftp,$path);
		}
	}
	
	private function deleteDirectory($path)
	{
		$path = rtrim($path,'/') . '/';
		$list = ftp_nlist($this->ftp,$path);

		// if invalid path, return
		if($list === FALSE)
		{
			return;
		}
		
		foreach($list as $index => $subPath)
		{
			// Is It A Directory
			if(@ftp_chdir($this->ftp,$subPath) === TRUE)
			{
				$this->deleteDirectory($subPath);
				continue;
			}
			// It's A File, Delete
			ftp_delete($this->ftp,$subPath);
		}
		ftp_rmdir($this->ftp,$path);
	}
	
	// Makes The Request to REST Server
	private function makeRequest($uri,$body)
	{
		$body = json_encode($body);
				
		$headers = array(
		'Authorization: TOK:'.$this->token,
		'Accept: application/json',
		'Content-Type: application/json',
		'Host: api.edgecast.com',
		'Content-Length: '.strlen($body)
		);
					
		// Save To Tempoary File
		$fh = fopen('php://temp/maxmemory:256000','w');
		fwrite($fh,$body);
		fseek($fh,0);
		
		$handle = curl_init();
		
		curl_setopt($handle,CURLOPT_HTTPHEADER,$headers);
		// Binary Transfer
		curl_setopt($handle, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_URL, $uri);
		// Set PUT Method
		curl_setopt($handle, CURLOPT_PUT, true);
		// Take Return Headers
		curl_setopt($handle,CURLOPT_HEADER,true);
		// Instead of POST fields use it from a file
		curl_setopt($handle, CURLOPT_INFILE, $fh);
		curl_setopt($handle, CURLOPT_INFILESIZE, strlen($body));
		// Accept Certificate
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		
		$output = curl_exec($handle);
		$error = curl_error($handle);
				
		// Close Everything
		fclose($fh);
		curl_close($handle);
				
		if(!$output)
		{
			return $error;
		} else {
			return true;
		}
	}
}
?>