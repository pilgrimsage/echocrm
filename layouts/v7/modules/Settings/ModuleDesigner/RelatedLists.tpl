<table id="md-related-lists-table">
<tr>
<td>
<div id="md-related-list-toolbar">
	<h2>{vtranslate('LBL_MODULES', $QUALIFIED_MODULE)}</h2>
	
	<ul id="md-modules-list">
	{foreach item=module from=$LIST_MODULES}
	<li>{$module.tablabel}</li>
	{/foreach}
	</ul>	
	<div id="md-related-list-other-module">
		<button id="md_related_list_other_module">{vtranslate('LBL_RELATED_LIST_OTHER_MODULE', $QUALIFIED_MODULE)}</button>
	</div>
</div>
</td>
<td>
<div>
<ul id="md-related-lists-ul" class="md-related-lists-ul">
<!-- Related lists added with JS -->
</ul>
</div>
</td>
</table>