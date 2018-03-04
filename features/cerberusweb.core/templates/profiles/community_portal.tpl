{$page_context = CerberusContexts::CONTEXT_PORTAL}
{$page_context_id = $community_tool->id}
{$is_writeable = Context_CommunityTool::isWriteableByActor($community_tool, $active_worker)}

<h1>{$community_tool->name}</h1>

<div class="cerb-profile-toolbar">
	<form class="toolbar" action="{devblocks_url}{/devblocks_url}" onsubmit="return false;" style="margin-bottom:5px;">
		<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
		
		<span id="spanInteractions">
		{include file="devblocks:cerberusweb.core::events/interaction/interactions_menu.tpl"}
		</span>
		
		<!-- Card -->
		<button type="button" id="btnProfileCard" title="{'common.card'|devblocks_translate|capitalize}" data-context="{$page_context}" data-context-id="{$page_context_id}"><span class="glyphicons glyphicons-nameplate"></span></button>
		
		<!-- Edit -->
		{if $is_writeable && $active_worker->hasPriv("contexts.{$page_context}.update")}
		<button type="button" id="btnDisplayCommunityToolEdit" title="{'common.edit'|devblocks_translate|capitalize} (E)" class="cerb-peek-trigger" data-context="{$page_context}" data-context-id="{$page_context_id}" data-edit="true"><span class="glyphicons glyphicons-cogwheel"></span></button>
		{/if}
		
		{if $active_worker->hasPriv("contexts.{$page_context}.comment")}<button id="btnProfileComment" type="button" class="cerb-peek-comments-add" data-context="{CerberusContexts::CONTEXT_COMMENT}" data-context-id="0" data-edit="context:{$page_context} context.id:{$page_context_id}"><span class="glyphicons glyphicons-conversation" title="{'common.comment'|devblocks_translate|capitalize}"></span></button>{/if}
	</form>
	
	{if $pref_keyboard_shortcuts}
		<small>
		{$translate->_('common.keyboard')|lower}:
		(<b>e</b>) {'common.edit'|devblocks_translate|lower}
		(<b>1-9</b>) change tab
		</small>
	{/if}
</div>

<fieldset class="properties">
	<legend>{'Community Portal'|devblocks_translate|capitalize}</legend>

	<div style="margin-left:15px;">
	{foreach from=$properties item=v key=k name=props}
		<div class="property">
			{if $k == '...'}
				<b>{$translate->_('...')|capitalize}:</b>
				...
			{else}
				{include file="devblocks:cerberusweb.core::internal/custom_fields/profile_cell_renderer.tpl"}
			{/if}
		</div>
		{if $smarty.foreach.props.iteration % 3 == 0 && !$smarty.foreach.props.last}
			<br clear="all">
		{/if}
	{/foreach}
	<br clear="all">
	
	<div style="margin-top:5px;">
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_COMMENT}" data-query="on.community_portal:(id:{$page_context_id})"><div class="badge-count">{$profile_counts.comments|default:0}</div> {'common.comments'|devblocks_translate|capitalize}</button>
		<button type="button" class="cerb-search-trigger" data-context="{CerberusContexts::CONTEXT_ACTIVITY_LOG}" data-query="target.community_portal:(id:{$page_context_id})"><div class="badge-count">{$profile_counts.log|default:0}</div> {'common.activity_logs'|devblocks_translate|capitalize}</button>
	</div>
	
	</div>
</fieldset>

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/profile_fieldsets.tpl" properties=$properties_custom_fieldsets}

{include file="devblocks:cerberusweb.core::internal/profiles/profile_record_links.tpl" properties=$properties_links}

<div>
{include file="devblocks:cerberusweb.core::internal/notifications/context_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div>
{include file="devblocks:cerberusweb.core::internal/macros/behavior/scheduled_behavior_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div id="community_toolTabs">
	<ul>
		{$tabs = []}

		{foreach from=$extension->profileGetTabs($community_tool) item=tab_label key=tab_id}
		{$tabs[] = $tab_id}
		<li><a href="{devblocks_url}ajax.php?c=profiles&a=handleSectionAction&section=community_portal&action=showProfileTab&tab_id={$tab_id}&portal_id={$page_context_id}{/devblocks_url}">{$tab_label}</a></li>
		{/foreach}
		
		{foreach from=$tab_manifests item=tab_manifest}
			{$tabs[] = $tab_manifest->params.uri}
			<li><a href="{devblocks_url}ajax.php?c=profiles&a=showTab&ext_id={$tab_manifest->id}&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}"><i>{$tab_manifest->params.title|devblocks_translate}</i></a></li>
		{/foreach}
	</ul>
</div>
<br>

<script type="text/javascript">
$(function() {
	var tabOptions = Devblocks.getDefaultjQueryUiTabOptions();
	tabOptions.active = Devblocks.getjQueryUiTabSelected('community_toolTabs');

	var tabs = $("#community_toolTabs").tabs(tabOptions);

	$('#btnProfileCard').cerbPeekTrigger();
	
	// Edit
	{if $is_writeable && $active_worker->hasPriv("contexts.{$page_context}.update")}
	$('#btnDisplayCommunityToolEdit')
		.cerbPeekTrigger()
		.on('cerb-peek-opened', function(e) {
		})
		.on('cerb-peek-saved', function(e) {
			e.stopPropagation();
			document.location.reload();
		})
		.on('cerb-peek-deleted', function(e) {
			document.location.href = '{devblocks_url}{/devblocks_url}';
			
		})
		.on('cerb-peek-closed', function(e) {
		})
	;
	{/if}
	
	// Comments
	$('#btnProfileComment')
		.cerbPeekTrigger()
		.on('cerb-peek-saved', function() {
			//genericAjaxPopup($layer,'c=internal&a=showPeekPopup&context={$peek_context}&context_id={$dict->id}&view_id={$view_id}','reuse',false,'50%');
		})
		;

	// Interactions
	var $interaction_container = $('#spanInteractions');
	{include file="devblocks:cerberusweb.core::events/interaction/interactions_menu.js.tpl"}
});
</script>

{if $pref_keyboard_shortcuts}
<script type="text/javascript">
$(function() {
	$(document).keypress(function(event) {
		if(event.altKey || event.ctrlKey || event.shiftKey || event.metaKey)
			return;
		
		if($(event.target).is(':input'))
			return;
		
		hotkey_activated = true;
		
		switch(event.which) {
			case 49:  // (1) tab cycle
			case 50:  // (2) tab cycle
			case 51:  // (3) tab cycle
			case 52:  // (4) tab cycle
			case 53:  // (5) tab cycle
			case 54:  // (6) tab cycle
			case 55:  // (7) tab cycle
			case 56:  // (8) tab cycle
			case 57:  // (9) tab cycle
			case 58:  // (0) tab cycle
				try {
					idx = event.which-49;
					$tabs = $("#community_toolTabs").tabs();
					$tabs.tabs('option', 'active', idx);
				} catch(ex) { }
				break;
			case 101:  // (E) edit
				try {
					$('#btnDisplayCommunityToolEdit').click();
				} catch(ex) { }
				break;
			case 109:  // (M) macros
				try {
					$('#btnDisplayMacros').click();
				} catch(ex) { }
				break;
			default:
				// We didn't find any obvious keys, try other codes
				hotkey_activated = false;
				break;
		}
		
		if(hotkey_activated)
			event.preventDefault();
	});
});
</script>
{/if}

{include file="devblocks:cerberusweb.core::internal/profiles/profile_common_scripts.tpl"}
