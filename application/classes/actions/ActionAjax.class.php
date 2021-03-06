<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

/**
 * Экшен обработки ajax запросов
 * Ответ отдает в JSON фомате
 *
 * @package actions
 * @since 1.0
 */
class ActionAjax extends Action {
	/**
	 * Текущий пользователь
	 *
	 * @var ModuleUser_EntityUser|null
	 */
	protected $oUserCurrent=null;
	/**
	 * Инициализация
	 */
	public function Init() {
		/**
		 * Устанавливаем формат ответа
		 */
		$this->Viewer_SetResponseAjax('json');
		/**
		 * Получаем текущего пользователя
		 */
		$this->oUserCurrent=$this->User_GetUserCurrent();
	}
	/**
	 * Регистрация евентов
	 */
	protected function RegisterEvent() {
		$this->AddEventPreg('/^vote$/i','/^comment$/','EventVoteComment');
		$this->AddEventPreg('/^vote$/i','/^topic$/','EventVoteTopic');
		$this->AddEventPreg('/^vote$/i','/^blog$/','EventVoteBlog');
		$this->AddEventPreg('/^vote$/i','/^user$/','EventVoteUser');
		$this->AddEventPreg('/^vote$/i','/^question$/','EventVoteQuestion');
		$this->AddEventPreg('/^vote$/i','/^get$/','/^info$/','/^topic$/','EventVoteGetInfoTopic');

		$this->AddEventPreg('/^favourite$/i','/^save-tags/','EventFavouriteSaveTags');
		$this->AddEventPreg('/^favourite$/i','/^topic$/','EventFavouriteTopic');
		$this->AddEventPreg('/^favourite$/i','/^comment$/','EventFavouriteComment');
		$this->AddEventPreg('/^favourite$/i','/^talk$/','EventFavouriteTalk');

		$this->AddEventPreg('/^stream$/i','/^comment$/','EventStreamComment');
		$this->AddEventPreg('/^stream$/i','/^topic$/','EventStreamTopic');

		$this->AddEventPreg('/^blogs$/i','/^top$/','EventBlogsTop');
		$this->AddEventPreg('/^blogs$/i','/^self$/','EventBlogsSelf');
		$this->AddEventPreg('/^blogs$/i','/^join$/','EventBlogsJoin');
		$this->AddEventPreg('/^blogs$/i','/^get-by-category$/','EventBlogsGetByCategory');

		$this->AddEventPreg('/^preview$/i','/^text$/','EventPreviewText');

		$this->AddEventPreg('/^autocompleter$/i','/^tag$/','EventAutocompleterTag');
		$this->AddEventPreg('/^autocompleter$/i','/^user$/','EventAutocompleterUser');

		$this->AddEventPreg('/^comment$/i','/^delete$/','EventCommentDelete');

		$this->AddEventPreg('/^geo$/i','/^get/','/^regions$/','EventGeoGetRegions');
		$this->AddEventPreg('/^geo$/i','/^get/','/^cities$/','EventGeoGetCities');

		$this->AddEventPreg('/^infobox$/i','/^info$/','/^blog$/','EventInfoboxInfoBlog');

		$this->AddEventPreg('/^media$/i','/^upload$/','/^$/','EventMediaUpload');
		$this->AddEventPreg('/^media$/i','/^upload-link$/','/^$/','EventMediaUploadLink');
		$this->AddEventPreg('/^media$/i','/^generate-target-tmp$/','/^$/','EventMediaGenerateTargetTmp');
		$this->AddEventPreg('/^media$/i','/^submit-insert$/','/^$/','EventMediaSubmitInsert');
		$this->AddEventPreg('/^media$/i','/^submit-create-photoset$/','/^$/','EventMediaSubmitCreatePhotoset');
		$this->AddEventPreg('/^media$/i','/^load-gallery$/','/^$/','EventMediaLoadGallery');
		$this->AddEventPreg('/^media$/i','/^remove-file$/','/^$/','EventMediaRemoveFile');
		$this->AddEventPreg('/^media$/i','/^save-data-file$/','/^$/','EventMediaSaveDataFile');

		$this->AddEventPreg('/^property$/i','/^tags$/','/^autocompleter$/','/^$/','EventPropertyTagsAutocompleter');

		$this->AddEventPreg('/^validate$/i','/^captcha$/','/^$/','EventValidateCaptcha');
	}


	/**********************************************************************************
	 ************************ РЕАЛИЗАЦИЯ ЭКШЕНА ***************************************
	 **********************************************************************************
	 */

	/**
	 * Ajax валидация каптчи
	 */
	protected function EventValidateCaptcha() {
		/**
		 * Устанавливаем формат Ajax ответа
		 */
		$this->Viewer_SetResponseAjax('json');

		$sName=isset($_REQUEST['params']['name']) ? $_REQUEST['params']['name'] : '';
		$sValue=isset($_REQUEST['fields'][0]['value']) ? $_REQUEST['fields'][0]['value'] : '';
		$sField=isset($_REQUEST['fields'][0]['field']) ? $_REQUEST['fields'][0]['field'] : '';

		if (!$this->Validate_Validate('captcha',$sValue,array('name'=>$sName))) {
			$aErrors=$this->Validate_GetErrors();
			$this->Viewer_AssignAjax('aErrors',array(htmlspecialchars($sField)=>array(reset($aErrors))));
		}
	}

	protected function EventPropertyTagsAutocompleter() {
		/**
		 * Первые буквы тега переданы?
		 */
		if (!($sValue=getRequest('value',null,'post')) or !is_string($sValue)) {
			return ;
		}
		$aItems=array();
		/**
		 * Формируем список тегов
		 */
		$aTags=$this->Property_GetPropertyTagsByLike($sValue,getRequestStr('property_id'),10);
		foreach ($aTags as $oTag) {
			$aItems[]=$oTag->getText();
		}
		/**
		 * Передаем результат в ajax ответ
		 */
		$this->Viewer_AssignAjax('aItems',$aItems);
	}

	protected function EventMediaUploadLink() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * URL передали?
		 */
		if (!($sUrl=getRequestStr('url'))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Проверяем корректность target'а
		 */
		$sTargetType=getRequestStr('target_type');
		$sTargetId=getRequestStr('target_id');

		$sTargetTmp=empty($_COOKIE['media_target_tmp_'.$sTargetType]) ? getRequestStr('target_tmp') : $_COOKIE['media_target_tmp_'.$sTargetType];
		if ($sTargetId) {
			$sTargetTmp=null;
			if (!$this->Media_CheckTarget($sTargetType,$sTargetId)) {
				$this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
				return false;
			}
		} else {
			$sTargetId=null;
			if (!$sTargetTmp or !$this->Media_IsAllowTargetType($sTargetType)) {
				$this->Message_AddError($this->Lang_Get('system_error'), $this->Lang_Get('error'));
				return false;
			}
		}

		/**
		 * Выполняем загрузку файла
		 */
		if ($mResult=$this->Media_UploadUrl($sUrl,$sTargetType,$sTargetId,$sTargetTmp) and is_object($mResult)) {
			$aParams=array(
				'align'=>getRequestStr('align'),
				'title'=>getRequestStr('title')
			);
			$this->Viewer_AssignAjax('sText',$this->Media_BuildCodeForEditor($mResult,$aParams));
		} else {
			$this->Message_AddError(is_string($mResult) ? $mResult : $this->Lang_Get('system_error'), $this->Lang_Get('error'));
		}
	}

	protected function EventMediaSaveDataFile() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		$aAllowData=array('title');
		$sName=getRequestStr('name');
		$sValue=getRequestStr('value');
		if (!in_array($sName,$aAllowData)) {
			return $this->EventErrorDebug();
		}
		$sId=getRequestStr('id');
		if ($oMedia=$this->Media_GetMediaByIdAndUserId($sId,$this->oUserCurrent->getId())) {
			$oMedia->setDataOne($sName,$sValue);
			$oMedia->Update();
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'));
		}
	}

	protected function EventMediaRemoveFile() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		$sId=getRequestStr('id');
		if ($oMedia=$this->Media_GetMediaByIdAndUserId($sId,$this->oUserCurrent->getId())) {
			$this->Media_DeleteFile($oMedia);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'));
		}
	}

	protected function EventMediaLoadGallery() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}

		$sType=getRequestStr('target_type');
		$sId=getRequestStr('target_id');
		$sTmp=getRequestStr('target_tmp');

		$aMediaItems=array();
		if ($sId) {
			$aMediaItems=$this->Media_GetMediaByTarget($sType,$sId,$this->oUserCurrent->getId());
		} elseif($sTmp) {
			$aMediaItems=$this->Media_GetMediaByTargetTmp($sTmp,$this->oUserCurrent->getId());
		}

		$oViewer=$this->Viewer_GetLocalViewer();
		$sTemplate='';
		foreach($aMediaItems as $oMediaItem) {
			$oViewer->Assign('oMediaItem',$oMediaItem);
			$sTemplate.=$oViewer->Fetch('modals/modal.upload_image.gallery.item.tpl');
		}

		$this->Viewer_AssignAjax('sTemplate',$sTemplate);
	}

	protected function EventMediaSubmitInsert() {
		$aIds=array(0);
		foreach((array)getRequest('ids') as $iId) {
			$aIds[]=(int)$iId;
		}

		$iUserId=$this->oUserCurrent ? $this->oUserCurrent->getId() : null;

		$aMediaItems=$this->Media_GetMediaItemsByFilter(array(
													   '#where'=>array('id in (?a) AND ( user_id is null OR user_id = ?d )'=>array($aIds,$iUserId))
												   )
		);
		if (!$aMediaItems) {
			$this->Message_AddError('Необходимо выбрать элементы');
			return false;
		}

		$aParams=array(
			'align'=>getRequestStr('align'),
			'size'=>getRequestStr('size')
		);

		$sTextResult='';
		foreach($aMediaItems as $oMedia) {
			$sTextResult.=$this->Media_BuildCodeForEditor($oMedia,$aParams)."\r\n";
		}
		$this->Viewer_AssignAjax('sTextResult',$sTextResult);
	}

	protected function EventMediaSubmitCreatePhotoset() {
		$aMediaItems=$this->Media_GetAllowMediaItemsById(getRequest('ids'));
		if (!$aMediaItems) {
			$this->Message_AddError('Необходимо выбрать элементы');
			return false;
		}

		$aItems=array();
		foreach($aMediaItems as $oMedia) {
			$aItems[]=$oMedia->getId();
		}

		$sTextResult='<gallery items="'.join(',',$aItems).'"';
		if (getRequest('use_thumbs')) {
			$sTextResult.=' nav="thumbs" ';
		}
		if (getRequest('show_caption')) {
			$sTextResult.=' caption="1" ';
		}
		$sTextResult.=' />';

		$this->Viewer_AssignAjax('sTextResult',$sTextResult);
	}

	protected function EventMediaGenerateTargetTmp() {
		$sType=getRequestStr('type');
		if ($this->Media_IsAllowTargetType($sType)) {
			$sTmp=func_generator();
			setcookie('media_target_tmp_'.$sType,$sTmp, time()+24*3600,Config::Get('sys.cookie.path'),Config::Get('sys.cookie.host'));
			$this->Viewer_AssignAjax('sTmpKey',$sTmp);
		}
	}

	protected function EventMediaUpload() {
		if (getRequest('is_iframe')) {
			$this->Viewer_SetResponseAjax('jsonIframe', false);
		} else {
			$this->Viewer_SetResponseAjax('json');
		}
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Файл был загружен?
		 */
		if (!isset($_FILES['filedata']['tmp_name'])) {
			return $this->EventErrorDebug();
		}
		/**
		 * Проверяем корректность target'а
		 */
		$sTargetType=getRequestStr('target_type');
		$sTargetId=getRequestStr('target_id');

		$sTargetTmp=empty($_COOKIE['media_target_tmp_'.$sTargetType]) ? getRequestStr('target_tmp') : $_COOKIE['media_target_tmp_'.$sTargetType];
		if ($sTargetId) {
			$sTargetTmp=null;
			if (!$this->Media_CheckTarget($sTargetType,$sTargetId)) {
				return $this->EventErrorDebug();
			}
		} else {
			$sTargetId=null;
			if (!$sTargetTmp or !$this->Media_IsAllowTargetType($sTargetType)) {
				return $this->EventErrorDebug();
			}
		}

		/**
		 * TODO: необходима проверка на максимальное общее количество файлов, на максимальный размер файла
		 * Эти настройки можно хранить в конфиге: module.media.type.topic.max_file_count=30 и т.п.
		 */

		/**
		 * Выполняем загрузку файла
		 */
		if ($mResult=$this->Media_Upload($_FILES['filedata'],$sTargetType,$sTargetId,$sTargetTmp) and is_object($mResult)) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('oMediaItem',$mResult);

			$sTemplateFile=$oViewer->Fetch('modals/modal.upload_image.gallery.item.tpl');

			$this->Viewer_AssignAjax('sTemplateFile',$sTemplateFile);
		} else {
			$this->Message_AddError(is_string($mResult) ? $mResult : $this->Lang_Get('system_error'), $this->Lang_Get('error'));
		}
	}

	/**
	 * Вывод информации о блоге
	 */
	protected function EventInfoboxInfoBlog() {
		/**
		 * Если блог существует и он не персональный
		 */
		if (!is_string(getRequest('iBlogId'))) {
			return $this->EventErrorDebug();
		}
		if (!($oBlog=$this->Blog_GetBlogById(getRequest('iBlogId'))) or $oBlog->getType()=='personal') {
			return $this->EventErrorDebug();
		}
		/**
		 * Получаем локальный вьюер для рендеринга шаблона
		 */
		$oViewer=$this->Viewer_GetLocalViewer();

		$oViewer->Assign('oBlog',$oBlog);
		if ($oBlog->getType()!='close' or $oBlog->getUserIsJoin()) {
			/**
			 * Получаем последний топик
			 */
			$aResult = $this->Topic_GetTopicsByFilter(array('blog_id'=>$oBlog->getId(),'topic_publish'=>1),1,1);
			$oViewer->Assign('oTopicLast',reset($aResult['collection']));
		}
		$oViewer->Assign('oUserCurrent',$this->oUserCurrent);
		/**
		 * Устанавливаем переменные для ajax ответа
		 */
		$this->Viewer_AssignAjax('sText',$oViewer->Fetch("actions/ActionBlogs/popover.blog.info.tpl"));
	}

	/**
	 * Получение информации о голосовании за топик
	 */
	protected function EventVoteGetInfoTopic() {
		if (!($oTopic = $this->Topic_GetTopicById(getRequestStr('id', null, 'post'))) ) {
			return $this->EventErrorDebug();
		}

		if (!$oTopic->getVote() && ($this->oUserCurrent && $oTopic->getUserId() != $this->oUserCurrent->getId()) && (strtotime($oTopic->getDateAdd()) + Config::Get('acl.vote.topic.limit_time') > time())) {
			return $this->EventErrorDebug();
		}

		$oViewer = $this->Viewer_GetLocalViewer();

		$oViewer->Assign('oTopic', $oTopic);
		$oViewer->Assign('oUserCurrent', $this->oUserCurrent);

		$this->Viewer_AssignAjax('sText', $oViewer->Fetch("vote.info.tpl"));
	}

	/**
	 * Получение списка регионов по стране
	 */
	protected function EventGeoGetRegions() {
		$iCountryId=getRequestStr('country');
		$iLimit=200;
		if (is_numeric(getRequest('limit')) and getRequest('limit')>0) {
			$iLimit=getRequest('limit');
		}
		/**
		 * Находим страну
		 */
		if (!($oCountry=$this->Geo_GetGeoObject('country',$iCountryId))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Получаем список регионов
		 */
		$aResult=$this->Geo_GetRegions(array('country_id'=>$oCountry->getId()),array('sort'=>'asc'),1,$iLimit);
		$aRegions=array();
		foreach($aResult['collection'] as $oObject) {
			$aRegions[]=array(
				'id' => $oObject->getId(),
				'name' => $oObject->getName(),
			);
		}
		/**
		 * Устанавливаем переменные для ajax ответа
		 */
		$this->Viewer_AssignAjax('aRegions',$aRegions);
	}
	/**
	 * Получение списка городов по региону
	 */
	protected function EventGeoGetCities() {
		$iRegionId=getRequestStr('region');
		$iLimit=500;
		if (is_numeric(getRequest('limit')) and getRequest('limit')>0) {
			$iLimit=getRequest('limit');
		}
		/**
		 * Находим регион
		 */
		if (!($oRegion=$this->Geo_GetGeoObject('region',$iRegionId))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Получаем города
		 */
		$aResult=$this->Geo_GetCities(array('region_id'=>$oRegion->getId()),array('sort'=>'asc'),1,$iLimit);
		$aCities=array();
		foreach($aResult['collection'] as $oObject) {
			$aCities[]=array(
				'id' => $oObject->getId(),
				'name' => $oObject->getName(),
			);
		}
		/**
		 * Устанавливаем переменные для ajax ответа
		 */
		$this->Viewer_AssignAjax('aCities',$aCities);
	}
	/**
	 * Голосование за комментарий
	 *
	 */
	protected function EventVoteComment() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Комментарий существует?
		 */
		if (!($oComment=$this->Comment_GetCommentById(getRequestStr('idComment',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Голосует автор комментария?
		 */
		if ($oComment->getUserId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Пользователь уже голосовал?
		 */
		if ($oTopicCommentVote=$this->Vote_GetVote($oComment->getId(),'comment',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Время голосования истекло?
		 */
		if (strtotime($oComment->getDate())<=time()-Config::Get('acl.vote.comment.limit_time')) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_time'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Пользователь имеет право голоса?
		 */
		if (!$this->ACL_CanVoteComment($this->oUserCurrent,$oComment)) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error_acl'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Как именно голосует пользователь
		 */
		$iValue=getRequestStr('value',null,'post');
		if (!in_array($iValue,array('1','-1'))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Голосуем
		 */
		$oTopicCommentVote=Engine::GetEntity('Vote');
		$oTopicCommentVote->setTargetId($oComment->getId());
		$oTopicCommentVote->setTargetType('comment');
		$oTopicCommentVote->setVoterId($this->oUserCurrent->getId());
		$oTopicCommentVote->setDirection($iValue);
		$oTopicCommentVote->setDate(date("Y-m-d H:i:s"));
		$iVal=(float)$this->Rating_VoteComment($this->oUserCurrent,$oComment,$iValue);
		$oTopicCommentVote->setValue($iVal);

		$oComment->setCountVote($oComment->getCountVote()+1);
		if ($this->Vote_AddVote($oTopicCommentVote) and $this->Comment_UpdateComment($oComment)) {
			$this->Message_AddNoticeSingle($this->Lang_Get('comment_vote_ok'),$this->Lang_Get('attention'));
			$this->Viewer_AssignAjax('iRating',$oComment->getRating());
			/**
			 * Добавляем событие в ленту
			 */
			$this->Stream_write($oTopicCommentVote->getVoterId(), 'vote_comment', $oComment->getId());
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_vote_error'),$this->Lang_Get('error'));
			return;
		}
	}
	/**
	 * Голосование за топик
	 *
	 */
	protected function EventVoteTopic() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Топик существует?
		 */
		if (!($oTopic=$this->Topic_GetTopicById(getRequestStr('idTopic',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Голосует автор топика?
		 */
		if ($oTopic->getUserId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Пользователь уже голосовал?
		 */
		if ($oTopicVote=$this->Vote_GetVote($oTopic->getId(),'topic',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Время голосования истекло?
		 */
		if (strtotime($oTopic->getDateAdd())<=time()-Config::Get('acl.vote.topic.limit_time')) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_time'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Как проголосовал пользователь
		 */
		$iValue=getRequestStr('value',null,'post');
		if (!in_array($iValue,array('1','-1','0'))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Права на голосование
		 */
		if (!$this->ACL_CanVoteTopic($this->oUserCurrent,$oTopic) and $iValue) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_vote_error_acl'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Голосуем
		 */
		$oTopicVote=Engine::GetEntity('Vote');
		$oTopicVote->setTargetId($oTopic->getId());
		$oTopicVote->setTargetType('topic');
		$oTopicVote->setVoterId($this->oUserCurrent->getId());
		$oTopicVote->setDirection($iValue);
		$oTopicVote->setDate(date("Y-m-d H:i:s"));
		$iVal=0;
		if ($iValue!=0) {
			$iVal=(float)$this->Rating_VoteTopic($this->oUserCurrent,$oTopic,$iValue);
		}
		$oTopicVote->setValue($iVal);
		$oTopic->setCountVote($oTopic->getCountVote()+1);
		if ($iValue==1) {
			$oTopic->setCountVoteUp($oTopic->getCountVoteUp()+1);
		} elseif ($iValue==-1) {
			$oTopic->setCountVoteDown($oTopic->getCountVoteDown()+1);
		} elseif ($iValue==0) {
			$oTopic->setCountVoteAbstain($oTopic->getCountVoteAbstain()+1);
		}
		if ($this->Vote_AddVote($oTopicVote) and $this->Topic_UpdateTopic($oTopic)) {
			if ($iValue) {
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_vote_ok'),$this->Lang_Get('attention'));
			} else {
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_vote_ok_abstain'),$this->Lang_Get('attention'));
			}
			$this->Viewer_AssignAjax('iRating',$oTopic->getRating());
			/**
			 * Добавляем событие в ленту
			 */
			$this->Stream_write($oTopicVote->getVoterId(), 'vote_topic', $oTopic->getId());
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
	}
	/**
	 * Голосование за блог
	 *
	 */
	protected function EventVoteBlog() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Блог существует?
		 */
		if (!($oBlog=$this->Blog_GetBlogById(getRequestStr('idBlog',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Голосует за свой блог?
		 */
		if ($oBlog->getOwnerId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('blog.vote.notices.error_self'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Уже голосовал?
		 */
		if ($oBlogVote=$this->Vote_GetVote($oBlog->getId(),'blog',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('blog.vote.notices.error_already'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Имеет право на голосование?
		 */
		switch($this->ACL_CanVoteBlog($this->oUserCurrent,$oBlog)) {
			case ModuleACL::CAN_VOTE_BLOG_TRUE:
				$iValue=getRequestStr('value',null,'post');
				if (in_array($iValue,array('1','-1'))) {
					$oBlogVote=Engine::GetEntity('Vote');
					$oBlogVote->setTargetId($oBlog->getId());
					$oBlogVote->setTargetType('blog');
					$oBlogVote->setVoterId($this->oUserCurrent->getId());
					$oBlogVote->setDirection($iValue);
					$oBlogVote->setDate(date("Y-m-d H:i:s"));
					$iVal=(float)$this->Rating_VoteBlog($this->oUserCurrent,$oBlog,$iValue);
					$oBlogVote->setValue($iVal);
					$oBlog->setCountVote($oBlog->getCountVote()+1);
					if ($this->Vote_AddVote($oBlogVote) and $this->Blog_UpdateBlog($oBlog)) {
						$this->Viewer_AssignAjax('iCountVote',$oBlog->getCountVote());
						$this->Viewer_AssignAjax('iRating',$oBlog->getRating());
						$this->Message_AddNoticeSingle($this->Lang_Get('vote.notices.success'),$this->Lang_Get('attention'));
						/**
						 * Добавляем событие в ленту
						 */
						$this->Stream_write($oBlogVote->getVoterId(), 'vote_blog', $oBlog->getId());
					} else {
						$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('attention'));
						return;
					}
				} else {
					return $this->EventErrorDebug();
				}
				break;
			case ModuleACL::CAN_VOTE_BLOG_ERROR_CLOSE:
				$this->Message_AddErrorSingle($this->Lang_Get('blog.vote.notices.error_close'),$this->Lang_Get('attention'));
				return;
				break;

			default:
			case ModuleACL::CAN_VOTE_BLOG_FALSE:
				$this->Message_AddErrorSingle($this->Lang_Get('blog.vote.notices.error_acl'),$this->Lang_Get('attention'));
				return;
				break;
		}
	}
	/**
	 * Голосование за пользователя
	 *
	 */
	protected function EventVoteUser() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Пользователь существует?
		 */
		if (!($oUser=$this->User_GetUserById(getRequestStr('idUser',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Голосует за себя?
		 */
		if ($oUser->getId()==$this->oUserCurrent->getId()) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_self'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Уже голосовал?
		 */
		if ($oUserVote=$this->Vote_GetVote($oUser->getId(),'user',$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_already'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Имеет право на голосование?
		 */
		if (!$this->ACL_CanVoteUser($this->oUserCurrent,$oUser)) {
			$this->Message_AddErrorSingle($this->Lang_Get('user_vote_error_acl'),$this->Lang_Get('attention'));
			return;
		}
		/**
		 * Как проголосовал
		 */
		$iValue=getRequestStr('value',null,'post');
		if (!in_array($iValue,array('1','-1'))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Голосуем
		 */
		$oUserVote=Engine::GetEntity('Vote');
		$oUserVote->setTargetId($oUser->getId());
		$oUserVote->setTargetType('user');
		$oUserVote->setVoterId($this->oUserCurrent->getId());
		$oUserVote->setDirection($iValue);
		$oUserVote->setDate(date("Y-m-d H:i:s"));
		$iVal=(float)$this->Rating_VoteUser($this->oUserCurrent,$oUser,$iValue);
		$oUserVote->setValue($iVal);
		//$oUser->setRating($oUser->getRating()+$iValue);
		$oUser->setCountVote($oUser->getCountVote()+1);
		if ($this->Vote_AddVote($oUserVote) and $this->User_Update($oUser)) {
			$this->Message_AddNoticeSingle($this->Lang_Get('user_vote_ok'),$this->Lang_Get('attention'));
			$this->Viewer_AssignAjax('iRating',$oUser->getRating());
			$this->Viewer_AssignAjax('iSkill',$oUser->getSkill());
			$this->Viewer_AssignAjax('iCountVote',$oUser->getCountVote());
			/**
			 * Добавляем событие в ленту
			 */
			$this->Stream_write($oUserVote->getVoterId(), 'vote_user', $oUser->getId());
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
	}
	/**
	 * Голосование за вариант ответа в опросе
	 *
	 */
	protected function EventVoteQuestion() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Параметры голосования
		 */
		$idAnswer=getRequestStr('idAnswer',null,'post');
		$idTopic=getRequestStr('idTopic',null,'post');
		/**
		 * Топик существует?
		 */
		if (!($oTopic=$this->Topic_GetTopicById($idTopic))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Тип топика - опрос?
		 */
		if ($oTopic->getType()!='question') {
			return $this->EventErrorDebug();
		}
		/**
		 * Уже голосовал?
		 */
		if ($oTopicQuestionVote=$this->Topic_GetTopicQuestionVote($oTopic->getId(),$this->oUserCurrent->getId())) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_question_vote_already'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Вариант ответа
		 */
		$aAnswer=$oTopic->getQuestionAnswers();
		if (!isset($aAnswer[$idAnswer]) and $idAnswer!=-1) {
			return $this->EventErrorDebug();
		}

		if ($idAnswer==-1) {
			$oTopic->setQuestionCountVoteAbstain($oTopic->getQuestionCountVoteAbstain()+1);
		} else {
			$oTopic->increaseQuestionAnswerVote($idAnswer);
		}
		$oTopic->setQuestionCountVote($oTopic->getQuestionCountVote()+1);
		/**
		 * Голосуем(отвечаем на опрос)
		 */
		$oTopicQuestionVote=Engine::GetEntity('Topic_TopicQuestionVote');
		$oTopicQuestionVote->setTopicId($oTopic->getId());
		$oTopicQuestionVote->setVoterId($this->oUserCurrent->getId());
		$oTopicQuestionVote->setAnswer($idAnswer);

		if ($this->Topic_AddTopicQuestionVote($oTopicQuestionVote) and $this->Topic_updateTopic($oTopic)) {
			$this->Message_AddNoticeSingle($this->Lang_Get('topic_question_vote_ok'),$this->Lang_Get('attention'));
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('oTopic',$oTopic);
			$this->Viewer_AssignAjax('sText',$oViewer->Fetch("topics/poll_result.tpl"));
		} else {
			return $this->EventErrorDebug();
		}
	}
	/**
	 * Сохраняет теги для избранного
	 *
	 */
	protected function EventFavouriteSaveTags() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Объект уже должен быть в избранном
		 */
		if ($oFavourite=$this->Favourite_GetFavourite(getRequestStr('target_id'),getRequestStr('target_type'),$this->oUserCurrent->getId())) {
			/**
			 * Обрабатываем теги
			 */
			$aTags=explode(',',trim(getRequestStr('tags'),"\r\n\t\0\x0B ."));
			$aTagsNew=array();
			$aTagsNewLow=array();
			$aTagsReturn=array();
			foreach ($aTags as $sTag) {
				$sTag=trim($sTag);
				if (func_check($sTag,'text',2,50) and !in_array(mb_strtolower($sTag,'UTF-8'),$aTagsNewLow)) {
					$sTagEsc=htmlspecialchars($sTag);
					$aTagsNew[]=$sTagEsc;
					$aTagsReturn[]=array(
						'tag' => $sTagEsc,
						'url' => $this->oUserCurrent->getUserWebPath().'favourites/'.$oFavourite->getTargetType().'s/tag/'.$sTagEsc.'/', // костыль для URL с множественным числом
					);
					$aTagsNewLow[]=mb_strtolower($sTag,'UTF-8');
				}
			}
			if (!count($aTagsNew)) {
				$oFavourite->setTags('');
			} else {
				$oFavourite->setTags(join(',',$aTagsNew));
			}
			$this->Viewer_AssignAjax('aTags',$aTagsReturn);
			$this->Favourite_UpdateFavourite($oFavourite);
			return;
		}
		return $this->EventErrorDebug();
	}
	/**
	 * Обработка избранного - топик
	 *
	 */
	protected function EventFavouriteTopic() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Можно только добавить или удалить из избранного
		 */
		$iType=getRequestStr('type',null,'post');
		if (!in_array($iType,array('1','0'))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Топик существует?
		 */
		if (!($oTopic=$this->Topic_GetTopicById(getRequestStr('id',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Пропускаем топик из черновиков
		 */
		if (!$oTopic->getPublish()) {
			$this->Message_AddErrorSingle($this->Lang_Get('error_favorite_topic_is_draft'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Топик уже в избранном?
		 */
		$oFavouriteTopic=$this->Topic_GetFavouriteTopic($oTopic->getId(),$this->oUserCurrent->getId());
		if (!$oFavouriteTopic and $iType) {
			$oFavouriteTopicNew=Engine::GetEntity('Favourite',
												  array(
													  'target_id'      => $oTopic->getId(),
													  'user_id'        => $this->oUserCurrent->getId(),
													  'target_type'    => 'topic',
													  'target_publish' => $oTopic->getPublish()
												  )
			);
			$oTopic->setCountFavourite($oTopic->getCountFavourite()+1);
			if ($this->Topic_AddFavouriteTopic($oFavouriteTopicNew) and $this->Topic_UpdateTopic($oTopic)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_favourite_add_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',true);
				$this->Viewer_AssignAjax('iCount', $oTopic->getCountFavourite());
			} else {
				return $this->EventErrorDebug();
			}
		}
		if (!$oFavouriteTopic and !$iType) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_favourite_add_no'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTopic and $iType) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_favourite_add_already'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTopic and !$iType) {
			$oTopic->setCountFavourite($oTopic->getCountFavourite()-1);
			if ($this->Topic_DeleteFavouriteTopic($oFavouriteTopic) and $this->Topic_UpdateTopic($oTopic)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('topic_favourite_del_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',false);
				$this->Viewer_AssignAjax('iCount', $oTopic->getCountFavourite());
			} else {
				return $this->EventErrorDebug();
			}
		}
	}
	/**
	 * Обработка избранного - комментарий
	 *
	 */
	protected function EventFavouriteComment() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Можно только добавить или удалить из избранного
		 */
		$iType=getRequestStr('type',null,'post');
		if (!in_array($iType,array('1','0'))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Комментарий существует?
		 */
		if (!($oComment=$this->Comment_GetCommentById(getRequestStr('id',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Комментарий уже в избранном?
		 */
		$oFavouriteComment=$this->Comment_GetFavouriteComment($oComment->getId(),$this->oUserCurrent->getId());
		if (!$oFavouriteComment and $iType) {
			$oFavouriteCommentNew=Engine::GetEntity('Favourite',
													array(
														'target_id'      => $oComment->getId(),
														'target_type'    => 'comment',
														'user_id'        => $this->oUserCurrent->getId(),
														'target_publish' => $oComment->getPublish()
													)
			);
			$oComment->setCountFavourite($oComment->getCountFavourite()+1);
			if ($this->Comment_AddFavouriteComment($oFavouriteCommentNew) and $this->Comment_UpdateComment($oComment)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('comment_favourite_add_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',true);
				$this->Viewer_AssignAjax('iCount', $oComment->getCountFavourite());
			} else {
				return $this->EventErrorDebug();
			}
		}
		if (!$oFavouriteComment and !$iType) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_favourite_add_no'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteComment and $iType) {
			$this->Message_AddErrorSingle($this->Lang_Get('comment_favourite_add_already'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteComment and !$iType) {
			$oComment->setCountFavourite($oComment->getCountFavourite()-1);
			if ($this->Comment_DeleteFavouriteComment($oFavouriteComment) and $this->Comment_UpdateComment($oComment)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('comment_favourite_del_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',false);
				$this->Viewer_AssignAjax('iCount', $oComment->getCountFavourite());
			} else {
				return $this->EventErrorDebug();
			}
		}
	}
	/**
	 * Обработка избранного - личное сообщение
	 *
	 */
	protected function EventFavouriteTalk() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Можно только добавить или удалить из избранного
		 */
		$iType=getRequestStr('type',null,'post');
		if (!in_array($iType,array('1','0'))) {
			return $this->EventErrorDebug();
		}
		/**
		 *	Сообщение существует?
		 */
		if (!($oTalk=$this->Talk_GetTalkById(getRequestStr('id',null,'post')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Сообщение уже в избранном?
		 */
		$oFavouriteTalk=$this->Talk_GetFavouriteTalk($oTalk->getId(),$this->oUserCurrent->getId());
		if (!$oFavouriteTalk and $iType) {
			$oFavouriteTalkNew=Engine::GetEntity('Favourite',
												 array(
													 'target_id'      => $oTalk->getId(),
													 'target_type'    => 'talk',
													 'user_id'        => $this->oUserCurrent->getId(),
													 'target_publish' => '1'
												 )
			);
			if ($this->Talk_AddFavouriteTalk($oFavouriteTalkNew)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('talk_favourite_add_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',true);
			} else {
				return $this->EventErrorDebug();
			}
		}
		if (!$oFavouriteTalk and !$iType) {
			$this->Message_AddErrorSingle($this->Lang_Get('talk_favourite_add_no'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTalk and $iType) {
			$this->Message_AddErrorSingle($this->Lang_Get('talk_favourite_add_already'),$this->Lang_Get('error'));
			return;
		}
		if ($oFavouriteTalk and !$iType) {
			if ($this->Talk_DeleteFavouriteTalk($oFavouriteTalk)) {
				$this->Message_AddNoticeSingle($this->Lang_Get('talk_favourite_del_ok'),$this->Lang_Get('attention'));
				$this->Viewer_AssignAjax('bState',false);
			} else {
				return $this->EventErrorDebug();
			}
		}

	}
	/**
	 * Обработка получения последних комментов
	 * Используется в блоке "Прямой эфир"
	 *
	 */
	protected function EventStreamComment() {
		if ($aComments=$this->Comment_GetCommentsOnline('topic',Config::Get('block.stream.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aComments',$aComments);
			$sTextResult=$oViewer->Fetch("blocks/block.stream_comment.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('block_stream_comments_no'),$this->Lang_Get('attention'));
			return;
		}
	}
	/**
	 * Обработка получения последних топиков
	 * Используется в блоке "Прямой эфир"
	 *
	 */
	protected function EventStreamTopic() {
		if ($oTopics=$this->Topic_GetTopicsLast(Config::Get('block.stream.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('oTopics',$oTopics);
			$sTextResult=$oViewer->Fetch("blocks/block.stream_topic.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('block_stream_topics_no'),$this->Lang_Get('attention'));
			return;
		}
	}
	/**
	 * Обработка получения TOP блогов
	 * Используется в блоке "TOP блогов"
	 *
	 */
	protected function EventBlogsTop() {
		/**
		 * Получаем список блогов и формируем ответ
		 */
		if ($aResult=$this->Blog_GetBlogsRating(1,Config::Get('block.blogs.row'))) {
			$aBlogs=$aResult['collection'];
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aBlogs',$aBlogs);
			$sTextResult=$oViewer->Fetch("blocks/block.blogs_top.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
	}
	/**
	 * Обработка получения своих блогов
	 * Используется в блоке "TOP блогов"
	 *
	 */
	protected function EventBlogsSelf() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Получаем список блогов и формируем ответ
		 */
		if ($aBlogs=$this->Blog_GetBlogsRatingSelf($this->oUserCurrent->getId(),Config::Get('block.blogs.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aBlogs',$aBlogs);
			$sTextResult=$oViewer->Fetch("blocks/block.blogs_top.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('block_blogs_self_error'),$this->Lang_Get('attention'));
			return;
		}
	}
	/**
	 * Обработка получения подключенных блогов
	 * Используется в блоке "TOP блогов"
	 *
	 */
	protected function EventBlogsJoin() {
		/**
		 * Пользователь авторизован?
		 */
		if (!$this->oUserCurrent) {
			$this->Message_AddErrorSingle($this->Lang_Get('need_authorization'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Получаем список блогов и формируем ответ
		 */
		if ($aBlogs=$this->Blog_GetBlogsRatingJoin($this->oUserCurrent->getId(),Config::Get('block.blogs.row'))) {
			$oViewer=$this->Viewer_GetLocalViewer();
			$oViewer->Assign('aBlogs',$aBlogs);
			$sTextResult=$oViewer->Fetch("blocks/block.blogs_top.tpl");
			$this->Viewer_AssignAjax('sText',$sTextResult);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('block_blogs_join_error'),$this->Lang_Get('attention'));
			return;
		}
	}

	/**
	 * Загружает список блогов конкретной категории
	 */
	protected function EventBlogsGetByCategory() {
		if (!($oCategory=$this->Blog_GetCategoryById(getRequestStr('id')))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Получаем все дочерние категории
		 */
		$aCategoriesId=$this->Blog_GetChildrenCategoriesById($oCategory->getId(),true);
		$aCategoriesId[]=$oCategory->getId();
		/**
		 * Формируем фильтр для получения списка блогов
		 */
		$aFilter=array(
			'exclude_type' => 'personal',
			'category_id'  => $aCategoriesId
		);
		/**
		 * Получаем список блогов(все по фильтру)
		 */
		$aResult=$this->Blog_GetBlogsByFilter($aFilter,array('blog_title'=>'asc'),1,PHP_INT_MAX);
		$aBlogs=$aResult['collection'];
		/**
		 * Получаем список блогов и формируем ответ
		 */
		if ($aBlogs) {
			$aResult=array();
			foreach($aBlogs as $oBlog) {
				$aResult[]=array(
					'id' => $oBlog->getId(),
					'title' => htmlspecialchars($oBlog->getTitle()),
					'category_id' => $oBlog->getCategoryId(),
					'type' => $oBlog->getType(),
					'rating' => $oBlog->getRating(),
					'url' => $oBlog->getUrl(),
					'url_full' => $oBlog->getUrlFull(),
				);
			}
			$this->Viewer_AssignAjax('aBlogs',$aResult);
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('blog.categories.empty'),$this->Lang_Get('attention'));
			return;
		}
	}
	/**
	 * Предпросмотр текста
	 *
	 */
	protected function EventPreviewText() {
		$sText=getRequestStr('text',null,'post');
		$bSave=getRequest('save',null,'post');
		/**
		 * Экранировать или нет HTML теги
		 */
		if ($bSave) {
			$sTextResult=htmlspecialchars($sText);
		} else {
			$sTextResult=$this->Text_Parser($sText);
		}
		/**
		 * Передаем результат в ajax ответ
		 */
		$this->Viewer_AssignAjax('sText',$sTextResult);
	}
	/**
	 * Автоподставновка тегов
	 *
	 */
	protected function EventAutocompleterTag() {
		/**
		 * Первые буквы тега переданы?
		 */
		if (!($sValue=getRequest('value',null,'post')) or !is_string($sValue)) {
			return ;
		}
		$aItems=array();
		/**
		 * Формируем список тегов
		 */
		$aTags=$this->Topic_GetTopicTagsByLike($sValue,10);
		foreach ($aTags as $oTag) {
			$aItems[]=$oTag->getText();
		}
		/**
		 * Передаем результат в ajax ответ
		 */
		$this->Viewer_AssignAjax('aItems',$aItems);
	}
	/**
	 * Автоподставновка пользователей
	 *
	 */
	protected function EventAutocompleterUser() {
		/**
		 * Первые буквы логина переданы?
		 */
		if (!($sValue=getRequest('value',null,'post')) or !is_string($sValue)) {
			return ;
		}
		$aItems=array();
		/**
		 * Формируем список пользователей
		 */
		$aUsers=$this->User_GetUsersByLoginLike($sValue,10);
		foreach ($aUsers as $oUser) {
			$aItems[]=$oUser->getLogin();
		}
		/**
		 * Передаем результат в ajax ответ
		 */
		$this->Viewer_AssignAjax('aItems',$aItems);
	}
	/**
	 * Удаление/восстановление комментария
	 *
	 */
	protected function EventCommentDelete() {
		/**
		 * Есть права на удаление комментария?
		 */
		if (!$this->ACL_CanDeleteComment($this->oUserCurrent)) {
			$this->Message_AddErrorSingle($this->Lang_Get('not_access'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Комментарий существует?
		 */
		$idComment=getRequestStr('idComment',null,'post');
		if (!($oComment=$this->Comment_GetCommentById($idComment))) {
			return $this->EventErrorDebug();
		}
		/**
		 * Устанавливаем пометку о том, что комментарий удален
		 */
		$oComment->setDelete(($oComment->getDelete()+1)%2);
		$this->Hook_Run('comment_delete_before', array('oComment'=>$oComment));
		if (!$this->Comment_UpdateCommentStatus($oComment)) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		$this->Hook_Run('comment_delete_after', array('oComment'=>$oComment));
		/**
		 * Формируем текст ответа
		 */
		if ($bState=(bool)$oComment->getDelete()) {
			$sMsg=$this->Lang_Get('comment_delete_ok');
			$sTextToggle=$this->Lang_Get('comment_repair');
		} else {
			$sMsg=$this->Lang_Get('comment_repair_ok');
			$sTextToggle=$this->Lang_Get('comment_delete');
		}
		/**
		 * Обновление события в ленте активности
		 */
		$this->Stream_write($oComment->getUserId(), 'add_comment', $oComment->getId(), !$oComment->getDelete());
		/**
		 * Показываем сообщение и передаем переменные в ajax ответ
		 */
		$this->Message_AddNoticeSingle($sMsg,$this->Lang_Get('attention'));
		$this->Viewer_AssignAjax('bState',$bState);
		$this->Viewer_AssignAjax('sTextToggle',$sTextToggle);
	}
}