<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2017, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerb.ai/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://cerb.ai	    http://webgroup.media
***********************************************************************/

class PageSection_ProfilesKbArticle extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$visit = CerberusApplication::getVisit();
		$request = DevblocksPlatform::getHttpRequest();
		$translate = DevblocksPlatform::getTranslationService();
		
		$active_worker = CerberusApplication::getActiveWorker();
		$context = CerberusContexts::CONTEXT_KB_ARTICLE;
		
		$stack = $request->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // article
		@$id = intval(array_shift($stack));
		
		if(null == ($article = DAO_KbArticle::get($id))) {
			return;
		}
		$tpl->assign('article', $article);	/* @var $article Model_KbArticle */
		
		// Dictionary
		$labels = array();
		$values = array();
		CerberusContexts::getContext($context, $article, $labels, $values, '', true, false);
		$dict = DevblocksDictionaryDelegate::instance($values);
		$tpl->assign('dict', $dict);
		
		$point = 'cerberusweb.profiles.kb';
		$tpl->assign('point', $point);
		
		// Categories
		
		$categories = DAO_KbCategory::getAll();
		$tpl->assign('categories', $categories);
		
		$breadcrumbs = $article->getCategories();
		$tpl->assign('breadcrumbs', $breadcrumbs);
			
		// Properties
			
		$properties = array();
			
		$properties['updated'] = array(
			'label' => DevblocksPlatform::translateCapitalized('common.updated'),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $article->updated,
		);
			
		$properties['views'] = array(
			'label' => mb_ucfirst($translate->_('kb_article.views')),
			'type' => Model_CustomField::TYPE_NUMBER,
			'value' => $article->views,
		);
			
		$properties['id'] = array(
			'label' => mb_ucfirst($translate->_('common.id')),
			'type' => Model_CustomField::TYPE_NUMBER,
			'value' => $article->id,
		);
			
		// Custom Fields

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds($context, $article->id)) or array();
		$tpl->assign('custom_field_values', $values);
		
		$properties_cfields = Page_Profiles::getProfilePropertiesCustomFields($context, $values);
		
		if(!empty($properties_cfields))
			$properties = array_merge($properties, $properties_cfields);
		
		// Custom Fieldsets

		$properties_custom_fieldsets = Page_Profiles::getProfilePropertiesCustomFieldsets($context, $article->id, $values);
		$tpl->assign('properties_custom_fieldsets', $properties_custom_fieldsets);
		
		// Link counts
		
		$properties_links = array(
			$context => array(
				$article->id => 
					DAO_ContextLink::getContextLinkCounts(
						$context,
						$article->id,
						array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
					),
			),
		);
		
		$tpl->assign('properties_links', $properties_links);
		
		// Properties
		
		$tpl->assign('properties', $properties);
			
		// Tabs
		$tab_manifests = Extension_ContextProfileTab::getExtensions(false, $context);
		$tpl->assign('tab_manifests', $tab_manifests);
		
		// Interactions
		$interactions = Event_GetInteractionsForWorker::getInteractionsByPointAndWorker('record:' . $context, $dict, $active_worker);
		$interactions_menu = Event_GetInteractionsForWorker::getInteractionMenu($interactions);
		$tpl->assign('interactions_menu', $interactions_menu);
		
		// Template
		$tpl->display('devblocks:cerberusweb.kb::kb/profile.tpl');
	}
	
	function showArticleTabAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['context_id'], 'integer', 0);
		
		if(!$id || false == ($article = DAO_KbArticle::get($id)))
			return;
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('article', $article);
		
		$tpl->display('devblocks:cerberusweb.kb::kb/ajax/tab_article.tpl');
	}
	
	function showBulkPopupAction() {
		@$id_csv = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);

		if(!empty($id_csv)) {
			$ids = DevblocksPlatform::parseCsvString($id_csv);
			$tpl->assign('ids', implode(',', $ids));
		}
		
		// Categories
		$categories = DAO_KbCategory::getAll();
		$tpl->assign('categories', $categories);
		
		$levels = DAO_KbCategory::getTree(0); //$root_id
		$tpl->assign('levels',$levels);
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_KB_ARTICLE, false);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->display('devblocks:cerberusweb.kb::kb/bulk.tpl');
	}
	
	function startBulkUpdateJsonAction() {
		// Filter: whole list or check
		@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
		$ids = array();
		
		// View
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);
		$view->setAutoPersist(false);
		
		// Scheduled behavior
		@$behavior_id = DevblocksPlatform::importGPC($_POST['behavior_id'],'string','');
		@$behavior_when = DevblocksPlatform::importGPC($_POST['behavior_when'],'string','');
		@$behavior_params = DevblocksPlatform::importGPC($_POST['behavior_params'],'array',array());
		
		$do = array();

		// Categories
		@$category_ids = DevblocksPlatform::importGPC($_REQUEST['category_ids'],'array',array());
		
		if(is_array($category_ids)) {
			$do['category_delta'] = array();
			
			foreach($category_ids as $cat_id) {
				@$cat_mode = DevblocksPlatform::importGPC($_REQUEST['category_ids_'.$cat_id],'string','');
				if(!empty($cat_mode))
					$do['category_delta'][] = $cat_mode . $cat_id;
			}
		}
		
		// Do: Custom fields
		$do = DAO_CustomFieldValue::handleBulkPost($do);

		// Do: Scheduled Behavior
		if(0 != strlen($behavior_id)) {
			$do['behavior'] = array(
				'id' => $behavior_id,
				'when' => $behavior_when,
				'params' => $behavior_params,
			);
		}
		
		switch($filter) {
			// Checked rows
			case 'checks':
				@$ids_str = DevblocksPlatform::importGPC($_REQUEST['ids'],'string');
				$ids = DevblocksPlatform::parseCsvString($ids_str);
				break;
				
			case 'sample':
				@$sample_size = min(DevblocksPlatform::importGPC($_REQUEST['filter_sample_size'],'integer',0),9999);
				$filter = 'checks';
				$ids = $view->getDataSample($sample_size);
				break;
			default:
				break;
		}
		
		// If we have specific IDs, add a filter for those too
		if(!empty($ids)) {
			$view->addParam(new DevblocksSearchCriteria(SearchFields_KbArticle::ID, 'in', $ids));
		}
		
		// Create batches
		$batch_key = DAO_ContextBulkUpdate::createFromView($view, $do);
		
		header('Content-Type: application/json; charset=utf-8');
		
		echo json_encode(array(
			'cursor' => $batch_key,
		));
		
		return;
	}
	
	function getEditorHtmlPreviewAction() {
		@$data = DevblocksPlatform::importGPC($_REQUEST['data'], 'string', '');

		$model = new Model_KbArticle();
		$model->content = $data;
		$model->format = Model_KbArticle::FORMAT_HTML;
		
		echo $model->getContent();
	}
	
	function getEditorParsedownPreviewAction() {
		@$data = DevblocksPlatform::importGPC($_REQUEST['data'], 'string', '');
		
		$model = new Model_KbArticle();
		$model->content = $data;
		$model->format = Model_KbArticle::FORMAT_MARKDOWN;
		
		echo $model->getContent();
	}
};