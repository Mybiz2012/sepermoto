<?php

class AdverController extends Controller
{
	public function addaddressAction()
	{
		//$this->_helper->layout()->setLayout('empty');
		$this->_helper->layout()->disableLayout();
		if($this->param && $this->user) {
			if(!$this->posts->fetchAll("user_id = {$this->user->id} AND id=$this->param")->current()) {
				echo "<script>parent.jAlert('This is not your adver',''); parent.closeCurrDiv(); </script>";
				return;
			}
		}
	
		if($this->user) {
			$inappropriate = $this->posts->inappropriate($this->user->id);
			if($inappropriate>=2) {
				$this->view->inappropriate = true;
				echo "<script>$('#popupwindowContent h1').after('<div class=\"alert\">".$this->view->translate("You have 2 (or more) inappropriate posts. You cannot add any more posts before you correct them")."</div>')</script>";
				//F::alert("You have 2 (or more) inappropriate posts. You cannot add any more posts before you correct them");
			}
				
		} else {
			if($this->param) $_SESSION['edit'] = 1;
			else unset($_SESSION['edit']);
				
			$this->view->headScript()->appendFile('http://maps.google.com/maps/api/js?sensor=false&language=en');
			$this->view->headScript()->appendFile('/public/assets/js/ui/jquery-ui-1.8.7.min.js');
			$this->view->headScript()->appendFile('/public/assets/js/ui/jquery.ui.addresspicker.js');
		}
	
		if($this->param) {
			$this->view->adver = $this->posts->find($this->param)->current();
			$_POST['lat'] = $this->view->adver->lat;
			$_POST['lng'] = $this->view->adver->lng;
			$_POST['locality'] = $this->view->adver->locality;
			$_POST['country'] = $this->view->adver->country;
		}
	
	}
	
	public function addAction()
	{
		$this->_helper->layout()->disableLayout();
		
		if(isset($_GET['block'])) {
			$this->posts->update(array("inappropriate"=>$this->_getParam('block')),"id=".$this->param);
			$this->_redirect("/adver/add/".$this->param);
		}
		
		if(!$this->param && $this->_getParam('country')) {
			$this->param = $this->posts->insert(array('lat'=>$this->_getParam('lat'),'lng'=>$this->_getParam('lng'),'locality'=>$this->_getParam('locality'),'country'=>$this->_getParam('country'), 'user_id'=>($this->user ? $this->user->id : null)));
			$this->_redirect("/adver/add/{$this->param}".($this->_getParam('hash') ? "?hash=".$this->_getParam('hash') : ""));		
		} else if($this->param && $this->_getParam('country')) {
			$this->posts->update(array('lat'=>$this->_getParam('lat'),'lng'=>$this->_getParam('lng'),'locality'=>$this->_getParam('locality'),'country'=>$this->_getParam('country')), "id=".$this->param);
		}
				
		if($this->param )
		{
			if($this->user)	{	
				if($this->user->role!="city" && $this->user->role!="admin" ) {
					if(!$this->posts->fetchAll("user_id = {$this->user->id} AND id=$this->param")->current()) {
						echo "<script>parent.jAlert('This is not your adver',''); parent.closeCurrDiv(); </script>";
						return;
						//F::alert("This is not your adver");
						//$this->_redirect('/account/');
				}}
			} else	if($this->_getParam('hash'))	{	
				if(!$post = $this->posts->fetchAll("id=$this->param AND (user_id = (SELECT id FROM members WHERE hash='{$this->_getParam('hash')}') OR active =0)")->current())
				{
					echo "<script>parent.jAlert('This is not your adver',''); parent.closeCurrDiv(); </script>";
					return;
				}
				F::login($post->user_id);
				echo "<script>parent.reloadUserPanel()</script>";
			}			
		}
		$fields = array(
				'subject'=>array('type'=>'text'),
				'category'=>array('type'=>'radio', 'checked'=>'Informations',  'options' => array('Informations'=>'Questions/informations','Offres' => 'Offres', 'Demandes' => 'Demandes', 'Communication' => 'City communication')),
				'details' => array('type' => 'textarea'),
				'user_id' => array('type'=>'hidden'),
				'price' => array('type'=>'text'),
				'currency' => array('type'=>'select', 'options' => array('USD'=>'USD','EUR'=>'EUR','GPB'=>'GPB')),
				'per' => array('type'=>'select', 'options' => array('total'=>'per total','month'=>'per month','day'=>'per day', 'hour' => 'per hour')),		
				'locality' => array(),
				'country' => array(),
				'end_date' => array("type" => "text"),
				'anonymous' => array()
		);
		$former = new Former($fields, null , 'posts','id');

		if($this->param){
			
			$former->load($this->param);
			if($former->values['subject']) $_SESSION['edit'] = 1;
			
			// check inappropriate
			$CurrPost = $this->posts->find($this->param)->current();
			$this->view->photos = $CurrPost->findDependentRowset('Photo');
			
			if( strpos($_SERVER['HTTP_REFERER'], "/admin/posts") === false) 
				if($CurrPost->inappropriate == 1){$this->view->inappropriate=true; F::alert ("This post is inappropriate");}
		}
		
		$post_images = new Zend_Session_Namespace('post_images');

		if(!$this->_request->isPost()) unset($post_images->post_images);
		
		$this->view->poster = $this->users->find($former->values['user_id'])->current();

		if($this->_request->isPost())
		{			
			$_POST['anonymous'] = $this->_getParam('name-notify') ? 0 : 1;
						
			if(!$this->param && $this->user) {
				$_POST['user_id'] = $this->user->id;
				$_POST['email'] = $this->user->email;
			}
			
			$id = $former->submit($_POST);
			
			$this->param = $id;
			$former->load($id);
			
			if($former->values['user_id']) {
				if($this->_getParam('phone')) {
					$updatearray["telefon"] = $this->_getParam('phone');
				} else {
					$updatearray["telefon"] = null;
				}
				if($this->_getParam('name-notify')==1)
					$updatearray["username"] = $this->_getParam('username');
				
				$this->users->update($updatearray,"id=".$former->values['user_id']);
			}
			
			if($this->_getParam('tags')) {
				$inserted = array();	
				foreach($this->_getParam('tags') as $tag) {
					if(!is_numeric($tag)) {
						$this->tags->insertCustomTag(trim($tag), $id);
					} else {
						if(!in_array($tag, $inserted)) {
							$inserted[] = $tag;
							
							if(!$this->tags2posts->fetchAll("postid=$id AND tagid=$tag")->current())
								$this->tags2posts->insert(array('postid'=>$id, 'tagid' => $tag));
						}
					}
				}
			}
				
			if($this->param && $this->_getParam('dtags')){
				foreach($this->_getParam('dtags') as $tag)
				$this->tags2posts->delete("tagid=".$tag." AND postid=".$this->param);
			}

			if(!empty($post_images->post_images)) {
				if($this->param && !$this->photo->hasPhoto($this->param)) $main = true;
				foreach($post_images->post_images as $item) {
					if($main) {
						$main=false;
						
						$this->photo->update(array("post_id"=>$id, "main"=>1), "photo_id=$item");
					} else {
						$this->photo->update(array("post_id"=>$id), "photo_id=$item");
					}
				}
			}
		}

		$this->view->tags = array();
		if($this->param)
		{
			$former->load($this->param);
			$currentAd = $this->posts->find($this->param)->current();
			$this->view->tags =  $currentAd->findManyToManyRowset('Tags','Tags2posts','Posts');
		}
		
		if($this->_request->isPost())
		{	
			if( strpos($_SERVER['HTTP_REFERER'], "/admin/posts") !== false) {	
				//F::alert("Adver was edit");
				echo "<script>$('#popupwindowContent h1').after('<div class=\"alert\">Adver was edit</div>'); document.location='/admin/posts'</script>";
				
			} else {
				
				if($this->_getParam('subject')) {
					if((!$this->user && !$this->_getParam('hash') && !$edit) || ($this->user && $this->user->aposts>0 && (!$this->user->password && !$this->user->openid ) )) {
						$this->_redirect("/adver/registration?adver_id={$id}&email={$this->_getParam('email')}");
					} else  {
						$this->_helper->viewRenderer->setNoRender(true);
						echo "<script>myAds(); showPost($id, false, '".(isset($_SESSION['edit']) ? $this->view->translate("Post was edited") : $this->view->translate("Your post has been created") )."', true)</script>";
						//$this->_redirect("/adver/show/$id");
					}
				}
			}			
		}
		
		if($former->values['end_date'] == null) $this->view->setdate = true;
		if($former->values['end_date'] == "0000-00-00 00:00:00" ) $former->setValues(array("end_date"=>date('Y-m-d', strtotime("+15 days")))); 
		else $former->setValues(array("end_date"=>date('Y-m-d', strtotime($former->values['end_date']))));
		$this->view->form = $former;
	}
	
	

	public function deleteadverAction()
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();
		$this->posts->delete("id={$this->_getParam('post_id')}");
		//if($this->user) $this->posts->delete("id={$this->_getParam('post_id')} AND user_id = ".$this->user->id);
		//else $this->posts->delete("id={$this->_getParam('post_id')} AND user_id = (SELECT id FROM members WHERE hash='{$this->_getParam('hash')}')");
		echo $this->view->translate('Adver was deleted');
	}

	public function deletephotoAction()
	{	
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();
		//$referer = explode("/", $_SERVER['HTTP_REFERER']);
		//$this->photo->delete("photo_id=".$this->param. " AND post_id=".$referer[count($referer)-1]);
		$this->photo->delete("photo_id=".$this->param. " AND post_id=".$this->param2);
		$post_images = new Zend_Session_Namespace('post_images');
		foreach($post_images->post_images as $key=>$value)
			if($value==$this->param)
				unset($post_images->post_images[$key]);
	}
	
	public function inappropriateAction()
	{
		if(!isset($_POST['email']) && $this->user) $_POST['email'] = $this->user->email;
		
		//$this->_helper->layout()->setLayout('empty');
		$this->_helper->layout()->disableLayout();


		$array = array();
		if(($cookie = Zend_Controller_Front::getInstance()->getRequest()->getCookie('inappropriate', 'default')) != 'default') {
			$array = json_decode($cookie);
			if(count($array) == 2){
				if(time()-$array[0] < 24*3600)
				{	
					$this->view->more2inap = true;
				}
			}	
		}
		
		if($this->_request->isPost())
		{
			if(!$this->user && $_SESSION['captcha_keystring'] != $_POST['keystring']) {
				echo "<script>$('#popupwindowContent .popup_H').after('<div class=\"alert\">".$this->view->translate("Invalid code")."</div>'); </script>";
				return;
			}
			if(count($array) == 2)	{
				if(time()-$array[0] > 24*3600) $array = array();
				else $this->view->more2inap = true;
				if(time()-$array[1] > 24*3600) $array = array();
				else	{
					$array = array(0=>$array[1]);
				}
			}
			
			array_push($array, time());
			setcookie('inappropriate', json_encode ($array), time()+3600*48, '/');
							
			$this->feedback->insert(array(
					'from_email'=>$this->_getParam('email'),
					'topic' => 'inappropriate',
					'option' => $this->_getParam('reason'),
					'message' => $this->_getParam('textl'),
					'post_id' => $this->param
			));
			$this->posts->update(array('inappropriate'=>1),"id=".$this->param);
			
			$post = $this->posts->find($this->param)->current();
			Mail::sendTemplateLeter($this->_getParam('email'), 1,  array("show_link"=>"http://".$_SERVER['HTTP_HOST']."?popup=showpost&post_id=".$this->param, "edit_link" => "http://".$_SERVER['HTTP_HOST']."?popup=editpost&post_id=$this->param&hash=".$this->users->find($post->user_id)->current()->hash, "block_reason" => $this->_getParam('reason'),  "block_text" =>  $this->_getParam('textl')));
			
			if($promoter = $this->users->fetchAll("role='city' AND locality='{$post->locality}'")->current()) 
				Mail::sendTemplateLeter($promoter->email, 257,  array("post_id"=>$post->id , "show_link"=>"http://".$_SERVER['HTTP_HOST']."?popup=showpost&post_id=".$this->param, "edit_link" => "http://".$_SERVER['HTTP_HOST']."?popup=editpost&post_id=$this->param&hash=".$this->users->find($post->user_id)->current()->hash, "block_reason" => $this->_getParam('reason'),  "block_text" =>  $this->_getParam('textl')));
			else
				Mail::sendTemplateLeter(Zend_Registry::getInstance()->get('appconfig')->developemnt->admin_email, 257,  array("post_id"=>$post->id, "show_link"=>"http://".$_SERVER['HTTP_HOST']."?popup=showpost&post_id=".$this->param, "edit_link" => "http://".$_SERVER['HTTP_HOST']."?popup=editpost&post_id=$this->param&hash=".$this->users->find($post->user_id)->current()->hash, "block_reason" => $this->_getParam('reason'),  "block_text" =>  $this->_getParam('textl')));
				
			echo "<script>showPost({$this->param}, false, 'inappropriate was send');</script>";
			//echo "<script>parent.jAlert('inappropriate was send', '');</script>";
			//F::alert('inappropriate was send');
			//echo "<script>$('#popupwindowContent .popup_H').after('<div class=\"info\">".$this->view->translate("inappropriate was send")."</div>'); </script>";
			//echo '<script>restorePost('.json_encode(array_merge($_POST,$_GET)).')</script>';
		}
	}

	public function registrationAction()
	{
		if(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'hash')!==false) $this->_redirect('/adver/show/'.$this->_getParam('adver_id'));
		
		
		if($this->_request->isPost())	echo "<script>restorePost(".json_encode($_POST).");</script>";
		

		//F::ketchup($this->view);
		//$this->_helper->layout()->setLayout('empty');
		$this->_helper->layout()->disableLayout();

		$this->view->adver_id = $this->_getParam('adver_id');
		echo '<input type="hidden" name="adver_id" />';

		$fields = array(
				'username' => array('type'=>'text'),
				'email' => array('type' =>'text'),
				'password' => array('type' => 'password'),
				'ip' => array('type' => 'hidden'),
				'telefon' => array('type' => 'text'),
				'telefon2' => array('type' => 'text'),
				'hash' => array('type'=>'hidden'),
				'anonymous' => array(),
				'lang' => array('type' => 'select', 'checked' => 'en', 'options' => array('en'=>'English', 'fr'=>'France','de'=>'German', 'it' => 'Italian', 'es' => 'Spanish', 'id' => 'Indonesian', 'pt' => 'Portuguese','pl' => 'Polish')),
				'avatar' => array('type'=>'hidden')
		);
		$former = new Former($fields, null , 'members','id');
		
		$user = $this->user ? $this->user : $this->users->fetchAll("email='".$this->_getParam('email')."'")->current();
		if(!$user) {
			$this->view->firstTime = true;
			$this->view->header = "Profile Activation";
		} else {			
			$former->load($user->id);
		}
		$this->view->form = $former;
		
		// esli podal 2 anonimno, prjachem knopky NEXT
		if($user && $user->anonymous) {
				$this->view->header = "Profile activation";
				$anonymous = $user;
				//TODO reload userdata from DB
				if($anonymous->aposts>=2) {
					$this->view->noanonymous = true;
					$this->view->header = "User registration";
				}
		}	
		// --->
		
		// check captcha
		if($this->_request->isPost())	{
			if(!$this->_getParam("anonymous") && isset($_POST['keystring']) && $_SESSION['captcha_keystring'] != $_POST['keystring']) 	{
				echo "<script>$('#popupwindowContent .adver_steps').after('<div class=\"alert\">".$this->view->translate('Invalid kaptcha')."</div>'); </script>";
				//F::alert('Invalid kaptcha');				
				return;
			}
		}
		// --->
		
		
		//----------------------------------------------------------------------VARIATOR------------------------------------------------------
		
		// submit post as anonymous
		if($this->_getParam("anonymous")) {
			if(!$anonymous) {
				$user_id = $this->users->insert(array("email"=>$this->_getParam('email'), 'ip' => ip2long($_SERVER['REMOTE_ADDR']), "anonymous" => 1, "aposts"=>1));
				$this->posts->update(array("user_id"=>$user_id, 'anonymous'=>1), "id=".$this->_getParam('adver_id'));
			} else {
				$this->posts->update(array("user_id"=>$anonymous->id, 'anonymous'=>1), "id=".$this->_getParam('adver_id'));
				if(!$this->user) $this->users->incPosts($anonymous->id);
			} 
			//$this->_redirect("/adver/show/".$this->_getParam('adver_id'));
			//$this->_redirect("/adver/contacts/".$this->_getParam('adver_id'));
			//$this->_redirect("/adver/show/".$this->_getParam('adver_id'));
			$this->_helper->viewRenderer->setNoRender(true);
			echo "<script>myAds();  reloadUserPanel(); showPost(".$this->_getParam('adver_id').", false, '".(isset($_SESSION['edit']) ? $this->view->translate("Post was edited") : $this->view->translate("Your post has been created") )."', true)</script>";
			return;
		}
		// --->
		
		
		// user exists
		if($this->_request->isGet() && $user && !$user->anonymous) {
			$this->view->provider = $user->provider;
			if(!$user->password && $user->provider) $this->view->onlyOpenID = true;
			$this->render('login');
			return;
		}
		// --->
		
		// user try to login
		if(!$this->_getParam('confirm') && $this->_getParam('password')){
			if($user = $this->users->fetchAll("email='{$this->_getParam('email')}' AND password='".md5($this->_getParam('password'))."'")->current())	{
				F::login($user->id);
				$this->posts->update(array("user_id" => $user->id), "id =". $this->_getParam('adver_id'));
				//$this->_redirect("/adver/show/".$this->_getParam('adver_id'));
				//$this->_redirect("/adver/contacts/".$this->_getParam('adver_id'));
				//$this->_redirect("/adver/show/".$this->_getParam('adver_id'));
				$this->_helper->viewRenderer->setNoRender(true);
				$this->posts->update(array("active"=>1), "id=".$this->_getParam('adver_id'));
				if(!$user->password && $user->provider) $this->users->incPosts($post->user_id);
				echo "<script>myAds();  reloadUserPanel(); showPost(".$this->_getParam('adver_id').", false, '".(isset($_SESSION['edit']) ? $this->view->translate("Post was edited") : $this->view->translate("Your post has been created") )."', true)</script>";
				return;
				
			}	else 	{
				echo "<script>$('#popupwindowContent .adver_steps').after('<div class=\"alert\">".$this->view->translate('Incorect credentials')."</div>'); </script>";
				//F::alert("Incorect credentials");
				unset($_SESSION['captcha_keystring']);
				$this->render('login');
				return;
			}
		}
		// --->
		
		// user register
		if($this->_request->isPost()) {
			$hash = md5(uniqid(rand(), true));
			if(!$this->_getParam('password')) $_POST["anonymous"] = 1;
			$_POST['hash'] = $hash;
			if(isset($anonymous)){
				$former->load($anonymous->id);
				$user_id = $anonymous->id;
				$_POST['anonymous'] = 0;
				$former->submit($_POST);
			} else
				$user_id = $former->submit($_POST);
			
			$this->posts->update(array("user_id"=>$user_id), "id=".$this->_getParam('adver_id'));
			if($this->_getParam('password')) F::login($user_id);
			
			// TODO reload userPanel
			
			if(!$this->_getParam("anonymous"))
				Mail::sendTemplateLeter($_POST['email'] , 40, array("name"=>$this->_getParam('username'), "pass" => $_POST['password'], "confirm" => "http://".$_SERVER['HTTP_HOST']."/account/confirm?hash=$hash"));
	
			//$this->_redirect('/adver/show/'.$this->_getParam('adver_id'));
			//$this->_redirect('/adver/contacts/'.$this->_getParam('adver_id'));
			//$this->_redirect('/adver/show/'.$this->_getParam('adver_id'));
			$this->_helper->viewRenderer->setNoRender(true);
			echo "<script>myAds(); reloadUserPanel(); showPost(".$this->_getParam('adver_id').", false, '".(isset($_SESSION['edit']) ? $this->view->translate("Post was edited") : $this->view->translate("Your post has been created") )."', true)</script>";
			return;
		}
		// --->
		
		// if user come back to edit on first adding
		//if($this->user && $this->user->aposts == 0) $this->_redirect('/adver/contacts/'.$this->_getParam('adver_id'));
		if($this->user && $this->user->aposts == 0) {
			//$this->_redirect('/adver/show/'.$this->_getParam('adver_id'));
			$this->_helper->viewRenderer->setNoRender(true);
			echo "<script>myAds();  reloadUserPanel(); showPost($this->_getParam('adver_id'), false, '".(isset($_SESSION['edit']) ? $this->view->translate("Post was edited") : $this->view->translate("Your post has been created") )."', true)</script>";
			return;
		}
	}
	
	public function finishAction()
	{
		$this->_helper->layout()->disableLayout();
		$this->view->post = $post = $this->view->post = $this->posts->find($this->param)->current();
		$this->posts->update(array("active"=>1), "id=".$post->id);
		$this->users->incPosts($post->user_id);
		F::login($post->user_id);
		echo "<script>reloadUserPanel()</script>";
	}
	
	/*
	public function finishAction()
	{
		//$this->_helper->layout()->setLayout('empty');
		//F::ketchup($this->view);
		$this->_helper->layout()->disableLayout();
		
		$fields = array(
				'username' => array('type'=>'text'),
				'email' => array('type'=>'text'),
				'password' => array('type' => 'password'),
				'ip' => array('type' => 'hidden'),
				'telefon' => array('type' => 'text'),
				'hash' => array('type'=>'hidden'),
				'lang' => array('type' => 'select', 'checked' => 'en', 'options' => array('en'=>'English', 'fr'=>'France','de'=>'German', 'it' => 'Italian', 'es' => 'Spanish', 'id' => 'Indonesian', 'pt' => 'Portuguese','pl' => 'Polish')),
				'anonymous' => array(),
				'avatar' => array('type'=>'hidden')
		);
		
		$this->view->post = $post = $this->view->post = $this->posts->find($this->param)->current();
		$this->view->aposts = $this->users->find($post->user_id)->current()->aposts;
		
		$former = new Former($fields, null , 'members','id');
		$this->view->form = $former;
		$former->load($post->user_id);
		
		// first post by OpenID
		if($this->user && $this->user->provider && $this->posts->countUserAds($this->user->id) == 0) 
			$this->view->openIDmess = true;
			
		// activate if < 2 anonymous posts 
		if((!$post->active && $this->view->aposts<=2) || ($this->user && !$this->user->anonymous)) $this->posts->update(array("active"=>1, "anonymous"=>0), "id=".$post->id);
		
		$user = $post->findDependentRowset('Users')->current();;
		
		// register user
		if($this->_request->isPost()){
		
			$alert = '';
			if($this->_getParam('anonymous')) {
					$this->view->nomyAds = 1;
					if(!$this->user) $this->users->incPosts($post->user_id);
					$alert = "parent.alertMsg('".$this->view->translate('fast-login')."');";
					Mail::sendTemplateLeter($user->email, 92, array("name"=>"", "password"=>"", "link" => "http://".$_SERVER['HTTP_HOST']."/account/confirm?hash={$user->hash}"));
			} else { 
				$_POST['ip'] = ip2long($_SERVER['REMOTE_ADDR']);;
				$_POST['anonymous'] = 0;
				
				$former->submit($_POST);
				if(!isset($_SESSION['edit'])) Mail::sendTemplateLeter($user->email, 40, array("name"=> $this->_getParam(' username'), "pass" => $this->_getParam(' password'), "confirm" => "http://".$_SERVER['HTTP_HOST']."/account/confirm?hash=$user->hash"));
				Mail::sendTemplateLeter($user->email, 92, array("name"=>$this->user->username,"password" =>"Your password for Maaple is ".$this->_getParam('password'),  "link" => "http://".$_SERVER['HTTP_HOST']."/account/confirm?hash={$this->user->hash}"));
				//$this->posts->update(array("user_id"=>$user_id), "id=".$post->id);
			}
			F::login($post->user_id);
			echo "<script>reloadUserPanel()</script>";
			return;	
			//$this->view->headScript()->appendScript("parent.reloadUserPanel(); $alert parent.myAds();parent.closeCurrDiv()");
				
		}	else if(!isset($_SESSION['edit'])) Mail::sendTemplateLeter($user->email, 6, array("title" => $post->subject, "text"=> $post->details, "show_link"=>"http://".$_SERVER['HTTP_HOST']."?popup=showpost&post_id=".$post->id, "edit_link" => "http://".$_SERVER['HTTP_HOST']."?popup=editpost&post_id=".$post->id."&hash=".$user->hash, "delete_link" => "http://".$_SERVER['HTTP_HOST']."?popup=deletepost&post_id=$post->id&hash=".$user->hash, "login_link" => "http://".$_SERVER['HTTP_HOST']."/account/login?login_link=".$this->users->find($post->user_id)->current()->hash));
		// --->
		
		
		if($this->user && !$this->user->confirm && !isset($_SESSION['edit'])) {
			$pcount = $this->posts->countUserAds($this->user->id);
			if($pcount >=1) {
				$this->view->noconfirmed = true;
			}			
		}   
		
		if ($user->anonymous && $user->aposts==0) $this->view->registration = true;
		
		if(@$_GET['username']=="hide") unset($_GET['username']);
		if(@$_GET['telefon']=="hide") unset($_GET['telefon']);
	} */
	
	
	
	public function forgotAction() 
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();
		if($user = $this->users->fetchAll("email='{$this->_getParam('email')}'")->current())
			{
				$hash = uniqid();
				Mail::sendTemplateLeter($this->_getParam('email') ,44,array("link"=>"http://{$_SERVER['HTTP_HOST']}?popup=forgot&hash=$hash"));
				$user->hash = $hash;
				$user->save();
				$result = "Plese, check your email";
			}
			else $result = "User not found";
		$this->_response->setHeader('Content-Type', 'text/json;')
		->setBody(json_encode($result));
	}


	public function hideAction()
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();

		if($post = $this->posts->fetch(array("user_id" => $this->user->id,  'id' => $this->param))->current())
		{
			$post->hide = $post->hide ? 0 : 1;
			$post->save();
		}
	}

	public function showAction()
	{
		$this->_helper->layout()->disableLayout();
		//$this->_helper->layout()->setLayout('empty');
		
		//if($this->action=='show' && !(@strpos($_SERVER['HTTP_REFERER'], 'registration')!==false  || @strpos($_SERVER['HTTP_REFERER'], 'adver/add')!==false || @strpos($_SERVER['HTTP_REFERER'], 'adver/addAddress')!==false || @strpos($_SERVER['HTTP_REFERER'], 'adver/login')!==false || @strpos($_SERVER['HTTP_REFERER'], 'loginza')!==false))  
		//	echo "<script>parent.loadPost({$this->param}); $('.next_post').show(); $('.preview_post').show();</script>";
		
		//$this->_helper->layout()->setLayout('empty');

		$this->view->adver = $post = $this->posts->find($this->param)->current();
		
		$days = floor( (time() - strtotime($this->view->adver->create_date)) / (24 * 3600) );
		if($days<0) $this->view->adver->create_date = "today";
		else if($days<30) {
			switch($days) {
				case 0: $this->view->adver->create_date = "today"; break;
				case 1: $this->view->adver->create_date = "tomorrow"; break;
				case 7: $this->view->adver->create_date = "1 week ago"; break;
				case 14: $this->view->adver->create_date = "2 weeks ago"; break;
				case 21: $this->view->adver->create_date = "3 weeks ago"; break;
				case 28: $this->view->adver->create_date = "4 weeks ago"; break;
				default: $this->view->adver->create_date = $days." days ago"; break;
			}
		} else {
			$this->view->adver->create_date= floor($days/30). " month and ".($days - floor($days/30)*30 - floor($days/30))." days ago" ;
		}
		$this->view->poster = $this->view->adver->findDependentRowset('Users')->current();
		$this->view->countUserAds = $this->posts->countUserAds($this->view->poster->id);
		if($this->view->countUserAds > 1) {
			F::login($this->view->poster->id);
			$this->posts->update(array("active"=>1),"id=".$this->param);
		}
		
		$cookie = Zend_Controller_Front::getInstance()->getRequest()->getCookie('locality', 'default');
		if($cookie != 'default')	{
			$array = explode("::",$cookie);
			$lat = $array[2];
			$lon = $array[3];
			$this->view->distance = F::distance($lat, $lon, $post->lat, $post->lng);
			if($this->view->distance>1) $this->view->distance = round($this->view->distance,1) . " km";
			else $this->view->distance = ceil($this->view->distance*1000) . " m";
		}
		if($this->_getParam('showpost') || $this->_getParam('popup')=='showpost') 
			echo "<script>searchMap.setCenter(new google.maps.LatLng($post->lat, $post->lng)); fitBounds(); </script>";
	}

	public function uploadAction()
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();


		if(isset($_FILES) && !empty($_FILES))
		{
			foreach($_FILES as $key=>$file)
			{	
				if(!in_array($file['type'], array('image/png', 'image/jpeg', 'image/gif', 'image/pjpeg', 'image/x-png'))) {
					echo "<script>parent.jAlert('error type of file', ''); parent.document.getElementById('userfile').value = ''; this.parent.$('.upload-progress').hide(); </script>";
					return;
				}
				if($file['size'] > 524288){
					echo "<script>parent.jAlert('error max filesize 512kb', ''); parent.document.getElementById('userfile').value = ''; this.parent.$('.upload-progress').hide(); </script>";
					return;
				}
				$path = 'uploadedPics/';
				if(empty($file['name'])) continue;
				$file['name'] = Helper::newName($file['name']);
				move_uploaded_file($file['tmp_name'], $path.$file['name']);					}
				$id = $this->photo->insert(array('file'=>$file['name']));
				$session = new Zend_Session_Namespace('post_images');
				$checked = "";
				if(!isset($session->post_images)) {
					$session->post_images = array();
					if(!$this->param) $checked = "checked='checked'";
				} 
				array_push($session->post_images, $id);
				$img=Graphics::img($file['name'], null, 100);
				
				echo <<<EOT
				<script>this.parent.$('.upload-progress').hide(); this.parent.$('.post_images').append("<li id='photo_$id'><div>$img</div><div><button class='del_btn' onclick='$.get(\"/adver/deletePhoto/$id/$this->param\",  function() { $(\"#photo_$id\").remove(); ResizeContent(); } ); return false' ></button><div class='make_main'><input class='ch_mainphoto' onchange='$(\".ch_mainphoto\").removeAttr(\"checked\"); $(this).attr(\"checked\",\"checked\"); $.get(\"/adver/setMainPhoto/$id\");' type='checkbox' $checked /><label>main image</label></div></div></li>"); this.parent.$('#userfile').val(''); setTimeout(parent.ResizeContent,2000);</script>
EOT;
		}
	}

	public function testAction()
	{

		if($this->_request->isPost()) {
			$this->_helper->viewRenderer->setNoRender(true);
			$this->_helper->layout()->disableLayout();
			echo "<script>window.parent.handleResponse('<strong>saddasdas</strong>');</script>";
		}

	}
	
	public function  setmainphotoAction() 
	{	
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();
		
		$this->photo->setMainPhoto($this->param);
	}
	
	public function changeratingAction()
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();
		echo $this->posts->changeRating($this->param,$this->param2);
	} 
	
	public function messagesAction()
	{	
		$this->_helper->layout()->disableLayout();
		$messages = $this->messages->getMessages($this->user->id);
		
		foreach($messages as $message) {
			$contacter = $this->user->id == $message->from ? $message->to : $message->from;
			if(isset($resultMessages[$message->post_id] [$contacter])) {
				if($resultMessages[$message->post_id] [$contacter]->date < $message->date)
					$resultMessages[$message->post_id] [$contacter] = $message;
			}
			else $resultMessages[$message->post_id][$contacter] = $message;			
		}
		/*
		foreach($resultMessages as $key => $group) {
			$flag = false;
			foreach($group as $message) {
				if($message->from==$this->user->id) {
					$flag = true;
				}   
				break;
			}
			if($flag) unset($resultMessages[$key]);
		}*/
				 
			
		foreach($resultMessages as $group)
			foreach($group as $message)
			 	$array[] = $message;
			 	
			 	
		if(!$array) $this->_helper->redirector("outbox");
			 	
		$this->view->inboxcount = $this->messages->inbox($this->user->id);
		$this->view->inboxMessages = $array;
	}
	
	public function outboxAction()
	{
		$this->_helper->layout()->disableLayout();

		$this->view->outboxMessages = $this->messages->outboxMessages($this->user->id);
	}
	
	public function trunkmessagesAction()
	{
		F::ketchup($this->view);
		$this->_helper->layout()->disableLayout();
		
		
		/*if($this->_getParam('message_id')) {
			$this->view->messages = $this->messages->find($this->_getParam('message_id'));
			if($this->_request->isPost()){
				Mail::sendTemplateLeter($this->view->messages->current()->email, 72,  array("text" => $this->_getParam('text'), "email" => $this->user->email, 'show_link' => "http://".$_SERVER['HTTP_HOST']."/?popup=showpost&post_id=".$this->param,  'login_link' => "", "copy" => ""));
				F::alert("message was send");
				$_POST = array();
			} else {
				$this->messages->update(array('read'=>1), "post_id={$this->_getParam('message_id')}");
			}
		} else { */
		
			if($this->_request->isPost()){
				$this->messages->insert(array("from"=>$this->user->id, "to"=>$this->_getParam('to'), 'parent'=>$this->_getParam('parent'), 'post_id'=>$this->param, "text"=>$this->_getParam('text')));
				$reciver = $this->users->find($this->_getParam('to'))->current();
				Mail::sendTemplateLeter($reciver->email, 72,  array("text" => $this->_getParam('text'), "email" => $this->user->email, 'show_link' => "http://".$_SERVER['HTTP_HOST']."/?popup=showpost&post_id=".$this->param,  'login_link' => "Please login to answer it  http://".$_SERVER['HTTP_HOST']."/account/login?login_link=".$reciver->hash, "copy" => ""));
				//F::alert("message was send");
				echo "<script> $('#popupwindowContent h1').after('<div class=\"alert\">".$this->view->translate("Message was send")."</div>')</script>";
				$_POST = array();
			}
		
		
			$this->messages->update(array('read'=>1), "post_id={$this->param} AND `to`={$this->user->id}");
			$this->view->messages = $this->messages->fetchAll("(`to`=$this->param2 OR `from`=$this->param2) AND post_id=".$this->param, "date DESC");
			
			$contactor_id = $this->view->messages->current()->to != $this->user->id  ? $this->view->messages->current()->to : $this->view->messages->current()->from;
			$this->view->contactor = $this->users->find($this->param2)->current();
		/*} */
	}
	
	public function postmessagesAction()
	{
		//F::ketchup($this->view,'',false);
		$this->_helper->layout()->disableLayout();
		
		if($this->_request->isPost()){
			if(!$this->_getParam('email')) {
				$this->messages->insert(array("from"=>$this->user->id, "to"=>$this->_getParam('to'), 'parent'=>$this->_getParam('parent'), 'post_id'=>$this->param, "text"=>$this->_getParam('text')));
				$reciver = $this->users->find($this->_getParam('to'))->current();
				Mail::sendTemplateLeter($reciver->email, 72,  array("text" => $this->_getParam('text'), "email" => $this->user->email, 'show_link' => "http://".$_SERVER['HTTP_HOST']."/?popup=showpost&post_id=".$this->param,  'login_link' => "Please login to answer it  http://".$_SERVER['HTTP_HOST']."/account/login?login_link=".$reciver->hash, "copy" => ""));
			} else {
				Mail::sendTemplateLeter($this->_getParam('email'), 72,  array("text" => $this->_getParam('text'), "email" => $this->user->email, 'show_link' => "http://".$_SERVER['HTTP_HOST']."/?popup=showpost&post_id=".$this->param, "copy" => ""));
			}
			echo "<script> $('#popupwindowContent h1').after('<div class=\"alert\">".$this->view->translate("Message was send")."</div>')</script>";
			//F::alert("message was send");
			$_POST = array();
		}
		
		$messages = $this->messages->postMessages($this->param);
		
		$resultMessages = array();
		
		foreach($messages as $message) {
			$contacter = $this->user->id == $message->from ? $message->to : $message->from;
			if(isset($resultMessages[$contacter])) {
				if($resultMessages[$contacter]->date < $message->date)
					$resultMessages[$contacter] = $message;
			}
			else $resultMessages[$contacter] = $message;
		}
		
		$this->view->messages = $resultMessages;
		//$this->view->post = $this->posts->find($this->param)->current();
		
		/*if($this->_getParam('xml')) {
			$this->_helper->viewRenderer->setNoRender(true);
			$this->_helper->layout()->disableLayout();
			$this->_response->setHeader('Content-Type', 'text/xml; charset=utf-8')
			->setBody($this->messages->postMessages($this->param));
		}*/
	}
	
	
	public function xmlmessagesAction()
	{
		$this->_helper->viewRenderer->setNoRender(true);
		$this->_helper->layout()->disableLayout();
		$this->_response->setHeader('Content-Type', 'text/xml; charset=utf-8')
		->setBody($this->messages->postMessages($this->param));
	}
	
	public function reactivationAction()
	{	
		$this->_helper->layout()->disableLayout();
		
		if($this->_request->isPost()){
			$this->posts->update(array("end_date"=>$this->_getParam("end_date")), "id=".$this->param);
			echo "<script>
				   $('#popupwindowContent h1').after('<div class=\"alert\">".$this->view->translate("Post was reactivated")."</div>');
				   myAds();
				   setTimeout(closeWindow, 800);
				  </script>";
			
			//F::alert("Post was reactivated");
			$this->view->goPosts=true; 
		} else {
			if(!$this->user) {
				$user = $this->users->fetchAll("hash='".$this->_getParam("hash")."'")->current();
				F::login($user->id);
				$this->user = $user;
			}
				
				$this->view->post = $this->posts->find($this->param)->current();
				if($this->view->post->user_id == $this->user->id) {
					$_POST["end_date"] = date("Y-m-d", strtotime($this->view->post->end_date)); 
					$this->view->appendDays = strtotime($this->view->post->end_date)>time() ? floor((strtotime($this->view->post->end_date)-time())/(3600*24)) : 0;
					F::alert((!$this->view->appendDays ? "Your post has been expired" : "Your post will expired to ". $this->view->appendDays ." days"). "You can reactivate fow 2 weeks");
				} else  unset($this->view->post);
		}
	}
	
}