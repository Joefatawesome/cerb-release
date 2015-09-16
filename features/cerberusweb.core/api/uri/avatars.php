<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2015, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

class Controller_Avatars extends DevblocksControllerExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		$translate = DevblocksPlatform::getTranslationService();
		
		$stack = $request->path; // URLS like: /avatars/worker/1
		array_shift($stack); // avatars
		
		@$alias = array_shift($stack); // worker
		@$avatar_context_id = intval(array_shift($stack)); // 1
		
		// Security
		if(null == ($active_worker = CerberusApplication::getActiveWorker()))
			die($translate->_('common.access_denied'));

		switch($alias) {
			case '_fetch':
				@$url = DevblocksPlatform::importGPC($_REQUEST['url'], 'string', '');
				$this->_fetchImageFromUrl($url);
				break;
		}
		
		// Look up the context extension
		if(empty($alias) || false == ($avatar_context_mft = Extension_DevblocksContext::getByAlias($alias, false)))
			$this->_renderContextAvatar();
		
		// Look up the avatar record
		if(false == ($avatar = DAO_ContextAvatar::getByContext($avatar_context_mft->id, $avatar_context_id))) {
			// If we don't have an avatar record, check Gravatar
			// [TODO] If enabled
			if(false == ($this->_fetchGravatarImage($avatar_context_mft->id, $avatar_context_id)))
				$this->_renderContextAvatar($avatar_context_mft->id);
		}
		
		if(empty($avatar->content_type) 
				|| empty($avatar->storage_size) 
				|| empty($avatar->storage_key) 
				|| false == ($contents = Storage_ContextAvatar::get($avatar)))
			$this->_renderContextAvatar($avatar->context);
		
		// Set headers
		header('Pragma: cache');
		header('Cache-control: max-age=7200', true); // 2 hours // , must-revalidate
		header('Expires: ' . gmdate('D, d M Y H:i:s',time()+7200) . ' GMT'); // 2 hours
		header('Accept-Ranges: bytes');

		header("Content-Type: " . $avatar->content_type);
		header("Content-Length: " . $avatar->storage_size);
		
		echo $contents;
		exit;
	}
	
	private function _fetchImageFromUrl($url) {
		$response = array('status'=>true, 'imageData'=>null);
		
		try {
			if(empty($url))
				throw new DevblocksException("No URL provided");
		
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
			
			// Make sure this is only image content
			if(substr(strtolower($info['content_type']),0,6) != 'image/')
				throw new DevblocksException("Only image types may be fetched.");
			
			$response['status'] = true;
			// [TODO] Can we be more memory efficient re the double copy of $output?
			$response['imageData'] = sprintf("data:%s;base64,", $info['content_type']) . base64_encode($output);
			
		} catch(DevblocksException $e) {
			$response['status'] = false;
			$response['error'] = $e->getMessage();
			
		} catch(Exception $e) {
			$response['status'] = false;
			$response['error'] = 'An error occurred.';
		}
		
		header("Pragma: no-cache");
		header("Content-Type: application/json");
		
		echo json_encode($response);
		exit;
	}
	
	private function _renderContextAvatar($context=null) {
		switch($context) {
			case CerberusContexts::CONTEXT_APPLICATION:
				$contents = file_get_contents(APP_PATH . '/features/cerberusweb.core/resources/images/avatars/app.png');
				break;
				
			case CerberusContexts::CONTEXT_ADDRESS:
			case CerberusContexts::CONTEXT_CONTACT_PERSON:
			case CerberusContexts::CONTEXT_WORKER:
				$n = mt_rand(1, 4);
				$contents = file_get_contents(APP_PATH . sprintf('/features/cerberusweb.core/resources/images/avatars/person%d.png', $n));
				break;
				
			case CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT:
				$contents = file_get_contents(APP_PATH . '/features/cerberusweb.core/resources/images/avatars/va.png');
				break;
				
			default:
				$contents = file_get_contents(APP_PATH . '/features/cerberusweb.core/resources/images/avatars/convo.png');
				break;
		}
		
		// Set headers
		header('Pragma: cache');
		header('Cache-control: max-age=7200', true); // 2 hours // , must-revalidate
		header('Expires: ' . gmdate('D, d M Y H:i:s',time()+7200) . ' GMT'); // 2 hours
		header('Accept-Ranges: bytes');
		
		header("Content-Type: " . 'image/png');
		header("Content-Length: " . strlen($contents));
		
		echo $contents;
		exit;
	}
	
	private function _getEmailFromContext($context, $context_id) {
		switch($context) {
			case CerberusContexts::CONTEXT_ADDRESS:
				if(false == ($address = DAO_Address::get($context_id)))
					return false;
					
				return $address->email;
				break;
				
			case CerberusContexts::CONTEXT_WORKER:
				if(false == ($worker = DAO_Worker::get($context_id)))
					return false;
					
				return $worker->email;
				break;
		}
		
		return false;
	}
	
	private function _fetchGravatarImage($context, $context_id) {
		if(false == ($email = $this->_getEmailFromContext($context, $context_id)))
			return false;
		
		$hash = md5($email);
		$url = sprintf("https://www.gravatar.com/avatar/%s?s=100&r=pg&d=404", $hash);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 sec
		$imagedata = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		
		try {
			if(!is_array($info))
				throw new Exception();
			
			// Only '200 OK' responses
			if(!isset($info['http_code']) || 200 != $info['http_code'])
				throw new Exception();
			
			// Only image mime types
			if(!isset($info['content_type']) || substr(strtolower($info['content_type']),0,6) != 'image/')
				throw new Exception();
			
			// Max image size 75KB
			if(!isset($info['size_download']) || $info['size_download'] > 75000)
				throw new Exception();
			
			if(empty($imagedata))
				throw new Exception();
			
		} catch(Exception $e) {
			// Make an empty avatar to signify that we've checked Gravatar and found nothing, so we don't repeatedly check
			$fields = array(
				DAO_ContextAvatar::CONTEXT => $context,
				DAO_ContextAvatar::CONTEXT_ID => $context_id,
				DAO_ContextAvatar::CONTENT_TYPE => '',
				DAO_ContextAvatar::UPDATED_AT => time(),
				DAO_ContextAvatar::IS_APPROVED => 1,
			);
			DAO_ContextAvatar::create($fields);
			
			return false;
		}
		
		// If we found an image, save it locally for reuse
		if(DAO_ContextAvatar::upsertWithImage($context, $context_id, $imagedata, $info['content_type'])) {
			// Set headers
			header('Pragma: cache');
			header('Cache-control: max-age=7200', true); // 2 hours // , must-revalidate
			header('Expires: ' . gmdate('D, d M Y H:i:s',time()+7200) . ' GMT'); // 2 hours
			header('Accept-Ranges: bytes');
			
			header("Content-Type: " . $info['content_type']);
			header("Content-Length: " . strlen($imagedata));
			
			echo $imagedata;
			exit;
		}
		
		return false;
	}
};